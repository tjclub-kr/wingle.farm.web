<?php
require_once __DIR__ . '/db_connect.php';

// 오늘의 할일 첨부파일 설정 (백엔드 기본값)
$TODO_ATTACHMENT_MAX_FILES = 5;
$TODO_ATTACHMENT_EXTENSIONS = ['jpg','jpeg','png','gif','webp','pdf','txt','doc','docx','xls','xlsx','hwp','hwpx','zip'];
$TODO_ATTACHMENT_MAX_SIZE = 20 * 1024 * 1024;

function todoAttachmentGroupId(int $taskId): int { return -abs($taskId); }
function todoUploadFiles(array $files, int $taskId, PDO $pdo, int $maxFiles, array $allowedExt, int $maxSize): array {
    $groupId = todoAttachmentGroupId($taskId);
    $uploadDir = __DIR__ . '/uploads/attachments/' . date('Y') . '/' . date('m');
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) throw new RuntimeException('첨부파일 저장 폴더를 만들 수 없습니다.');
    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];
    if (!is_array($names)) { $names=[$names]; $tmpNames=[$tmpNames]; $errors=[$errors]; $sizes=[$sizes]; }
    $validIndexes=[];
    foreach ($names as $i=>$name) { if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $validIndexes[]=$i; }
    if (count($validIndexes) > $maxFiles) throw new RuntimeException('첨부파일은 최대 '.$maxFiles.'개까지 등록할 수 있습니다.');
    $saved=[];
    foreach ($validIndexes as $i) {
        if (($errors[$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('파일 업로드 중 오류가 발생했습니다.');
        $original=basename((string)$names[$i]); $size=(int)$sizes[$i];
        if ($size <= 0 || $size > $maxSize) throw new RuntimeException($original.' 파일의 크기가 허용 범위를 초과했습니다.');
        $ext=strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext,$allowedExt,true)) throw new RuntimeException($original.' 파일은 허용되지 않는 확장자입니다.');
        $stored=bin2hex(random_bytes(16)).($ext ? '.'.$ext : '');
        $target=$uploadDir.'/'.$stored;
        if (!move_uploaded_file($tmpNames[$i], $target)) throw new RuntimeException($original.' 파일 저장에 실패했습니다.');
        $mime=function_exists('mime_content_type') ? (mime_content_type($target) ?: null) : null;
        $checksum=hash_file('sha256',$target) ?: null;
        $path='uploads/attachments/'.date('Y').'/'.date('m').'/'.$stored;
        $st=$pdo->prepare("INSERT INTO attachment (att_grp_id, original_name, stored_name, storage_type, storage_path, mime_type, file_size, extension, checksum, status, created_at, updated_at) VALUES (?, ?, ?, 'LOCAL', ?, ?, ?, ?, ?, 'ACTIVE', NOW(), NOW())");
        $st->execute([$groupId,$original,$stored,$path,$mime,$size,$ext,$checksum]);
        $saved[]=(int)$pdo->lastInsertId();
    }
    return $saved;
}

function todoDeleteAttachment(int $attId, PDO $pdo): void {
    $st=$pdo->prepare("SELECT storage_path FROM attachment WHERE att_id=? AND status='ACTIVE'"); $st->execute([$attId]); $row=$st->fetch(PDO::FETCH_ASSOC);
    if ($row) { 
        $path = $row['storage_path'];
        // 이관된 다른 할일에서 동일한 실제 파일을 참조하고 있는지 확인 후 실체 파일 삭제
        $chk = $pdo->prepare("SELECT COUNT(*) FROM attachment WHERE storage_path=? AND status='ACTIVE' AND att_id != ?");
        $chk->execute([$path, $attId]);
        if ((int)$chk->fetchColumn() === 0) {
            $file=__DIR__.'/'.ltrim($path,'/'); if (is_file($file)) @unlink($file); 
        }
    }
    $pdo->prepare("UPDATE attachment SET status='DELETED', updated_at=NOW() WHERE att_id=?")->execute([$attId]);
}

$today = date('Y-m-d');
$viewDate = isset($_GET['d']) ? $_GET['d'] : $today;

$dateStatus = 0;
if ($viewDate < $today) $dateStatus = -1;
if ($viewDate > $today) $dateStatus = 1;

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action) {
    if ($action === 'add_todo') {
        try {
            $task_date = trim($_POST['task_date'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if (!$task_date || !$content) throw new RuntimeException('날짜와 할일 내용을 입력해 주세요.');
            $stmt = $pdo->prepare("INSERT INTO dailytasksT (task_date, content, completion_status) VALUES (?, ?, '')");
            $stmt->execute([$task_date, $content]);
            $taskId=(int)$pdo->lastInsertId();
            if (isset($_FILES['attachments'])) todoUploadFiles($_FILES['attachments'],$taskId,$pdo,$TODO_ATTACHMENT_MAX_FILES,$TODO_ATTACHMENT_EXTENSIONS,$TODO_ATTACHMENT_MAX_SIZE);
            echo "success";
        } catch (Throwable $e) { http_response_code(400); echo $e->getMessage(); }
        exit;
    }
    if ($action === 'delete_attachment') { 
        try { 
            $attId=(int)($_POST['att_id']??0); 
            if(!$attId) throw new RuntimeException('첨부파일 ID가 없습니다.'); 
            todoDeleteAttachment($attId,$pdo); 
            echo 'success'; 
        } catch(Throwable $e){ http_response_code(400); echo $e->getMessage(); } 
        exit; 
    }
    if ($action === 'attachments') {
        $taskId=(int)($_GET['task_id']??0); 
        $st=$pdo->prepare("SELECT att_id, original_name, storage_path, file_size, extension FROM attachment WHERE att_grp_id=? AND status='ACTIVE' ORDER BY att_id ASC"); 
        $st->execute([todoAttachmentGroupId($taskId)]); 
        header('Content-Type: application/json; charset=utf-8'); 
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE); 
        exit;
    }
    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM dailytasksT WHERE id = ?");
            $stmt->execute([$id]);
            echo "success";
        }
        exit;
    }
    if ($action === 'update_content') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            $content = trim($_POST['content'] ?? '');
            if (!$id || $content === '') throw new RuntimeException('수정 내용을 입력해 주세요.');

            $stmt = $pdo->prepare("UPDATE dailytasksT SET content = ? WHERE id = ?");
            $stmt->execute([$content, $id]);

            // 새 첨부파일 업로드 처리
            if (isset($_FILES['attachments'])) {
                $st = $pdo->prepare("SELECT COUNT(*) FROM attachment WHERE att_grp_id = ? AND status = 'ACTIVE'");
                $st->execute([todoAttachmentGroupId($id)]);
                $currentCount = (int)$st->fetchColumn();

                $files = $_FILES['attachments'];
                $newCount = is_array($files['name']) ? count(array_filter($files['name'], fn($v) => $v !== '')) : 1;

                if (($currentCount + $newCount) > $TODO_ATTACHMENT_MAX_FILES) {
                    throw new RuntimeException('기존 파일을 포함하여 최대 ' . $TODO_ATTACHMENT_MAX_FILES . '개까지만 유지할 수 있습니다.');
                }
                todoUploadFiles($files, $id, $pdo, $TODO_ATTACHMENT_MAX_FILES, $TODO_ATTACHMENT_EXTENSIONS, $TODO_ATTACHMENT_MAX_SIZE);
            }
            echo "success";
        } catch (Throwable $e) {
            http_response_code(400);
            echo $e->getMessage();
        }
        exit;
    }
    if ($action === 'complete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE dailytasksT SET completion_status = '완료' WHERE id = ?");
            $stmt->execute([$id]);
            echo "success";
        }
        exit;
    }
    if ($action === 'undo_complete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE dailytasksT SET completion_status = '' WHERE id = ?");
            $stmt->execute([$id]);
            echo "success";
        }
        exit;
    }
    if ($action === 'change_date') {
        $id = $_POST['id'] ?? 0;
        $new_date = $_POST['new_date'] ?? '';
        $content = $_POST['content'] ?? '';
        if ($id && $new_date && $content) {
            $stmt = $pdo->prepare("UPDATE dailytasksT SET completion_status = ? WHERE id = ?");
            $stmt->execute([$new_date, $id]);
            
            $stmt2 = $pdo->prepare("INSERT INTO dailytasksT (task_date, content, completion_status) VALUES (?, ?, '')");
            $stmt2->execute([$new_date, $content]);
            $newTaskId = (int)$pdo->lastInsertId();

            // 기존 할일의 활성화(ACTIVE) 상태 첨부파일을 새 할일(newTaskId) 그룹으로 이관 복사
            $oldGrpId = todoAttachmentGroupId((int)$id);
            $newGrpId = todoAttachmentGroupId($newTaskId);

            $stAtt = $pdo->prepare("SELECT original_name, stored_name, storage_type, storage_path, mime_type, file_size, extension, checksum FROM attachment WHERE att_grp_id = ? AND status = 'ACTIVE'");
            $stAtt->execute([$oldGrpId]);
            $oldAttachments = $stAtt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($oldAttachments)) {
                $insAtt = $pdo->prepare("INSERT INTO attachment (att_grp_id, original_name, stored_name, storage_type, storage_path, mime_type, file_size, extension, checksum, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', NOW(), NOW())");
                foreach ($oldAttachments as $att) {
                    $insAtt->execute([
                        $newGrpId,
                        $att['original_name'],
                        $att['stored_name'],
                        $att['storage_type'],
                        $att['storage_path'],
                        $att['mime_type'],
                        $att['file_size'],
                        $att['extension'],
                        $att['checksum']
                    ]);
                }
            }

            echo "success";
        }
        exit;
    }
}

// 현재 조회 중인 날짜($viewDate) 기준으로 요약 통계 재계산
$stmt = $pdo->prepare("SELECT COUNT(*) FROM dailytasksT WHERE task_date < ? AND completion_status = ''");
$stmt->execute([$viewDate]);
$prevIncompleteCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM dailytasksT WHERE task_date = ?");
$stmt->execute([$viewDate]);
$todayTotalCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM dailytasksT WHERE task_date = ? AND completion_status = ''");
$stmt->execute([$viewDate]);
$todayIncompleteCount = $stmt->fetchColumn();

// 향후 미완료 할일 건수 재계산 (completion_status가 NULL 또는 빈 값인 경우)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM dailytasksT WHERE task_date > ? AND (completion_status IS NULL OR completion_status = '')");
$stmt->execute([$viewDate]);
$futureTotalCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT t.*, (SELECT COUNT(*) FROM attachment a WHERE a.att_grp_id = -t.id AND a.status='ACTIVE') AS attachment_count FROM dailytasksT t WHERE t.task_date = ? ORDER BY t.id DESC");
$stmt->execute([$viewDate]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 향후 미완료 할일 목록 재계산 (completion_status가 NULL 또는 빈 값인 경우)
$stmt = $pdo->prepare("SELECT * FROM dailytasksT WHERE task_date > ? AND (completion_status IS NULL OR completion_status = '') ORDER BY task_date ASC, id DESC");
$stmt->execute([$viewDate]);
$futureTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
    .date-nav-container { display: flex; justify-content: center; align-items: center; position: relative; padding: 15px 0; margin-bottom: 10px; background-color: #f8f9fa; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .date-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 20px; color: #2c3e50; cursor: pointer; padding: 10px 15px; font-weight: bold; }
    .date-nav-btn.prev { left: 10px; }
    .date-nav-btn.next { right: 10px; }
    .date-title-wrap { position: relative; font-size: 18px; font-weight: bold; color: #2c3e50; display: flex; align-items: center; }
    .today-badge { position: absolute; left: 100%; margin-left: 8px; font-size: 14px; color: #e74c3c; white-space: nowrap; }
    .stats-container { display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; background-color: #ffffff; padding: 12px 10px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #eee; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
    .stat-item { display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 20%; }
    .stat-label { font-size: 11px; color: #7f8c8d; margin-bottom: 4px; text-align: center; }
    .stat-value { font-size: 16px; font-weight: bold; color: #2c3e50; }
    .stat-value.alert { color: #e74c3c; }
    .task-list { list-style: none; padding: 0; margin: 0; }
    .task-item { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px; margin-bottom: 10px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); border: 1px solid #eee; }
    .task-content-line { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 15px; cursor: pointer; color: #333; }
    .task-content-line.completed { color: #95a5a6; }
    .task-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
    .task-attachment-icon { font-size: 17px; cursor: pointer; color: #7f8c8d; transition: color 0.2s; }
    .task-attachment-icon:hover { color: #8e44ad; }
    .attachment-box { margin-bottom: 12px; border-top: 1px solid #eee; padding-top: 10px; }
    .attachment-title { font-size: 13px; font-weight: bold; color: #555; margin-bottom: 7px; }
    .attachment-list { display:flex; flex-direction:column; gap:5px; margin-bottom:8px; max-height:120px; overflow-y:auto; }
    .attachment-row { display:flex; align-items:center; gap:6px; font-size:12px; }
    .attachment-name { flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-decoration:none; color:#2c3e50; }
    .attachment-delete { border:0; background:none; cursor:pointer; padding:2px 4px; }
    .selected-file-badge { display:inline-flex; align-items:center; background:#f1f8e9; padding:4px 8px; margin:2px 4px 2px 0; border-radius:4px; border:1px solid #c5e1a5; color:#2e7d32; font-size:11px; }
    .selected-file-badge-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px; }
    .selected-file-badge-remove { margin-left:6px; cursor:pointer; color:#d32f2f; font-weight:bold; }
    .attachment-help { font-size:10px; color:#888; margin-top:5px; line-height:1.4; }
    .task-delete-btn { background: none; border: none; color: #e74c3c; font-size: 18px; cursor: pointer; padding: 5px; }
    .task-status-tag { font-weight: bold; font-size: 14px; white-space: nowrap; }
    .status-completed { color: #2ecc71; }
    .status-transfer { color: #f39c12; }
    .status-incomplete { color: #e74c3c; }
    .detail-modal-overlay, .date-picker-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9998; justify-content: center; align-items: center; }
    .date-picker-overlay { z-index: 9999; } 
    .detail-modal-content { background: #fff; width: 90%; max-width: 360px; padding: 20px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    .detail-modal-content textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; resize: none; margin-bottom: 15px; background-color: #fff; color: #333; box-sizing: border-box; }
    .modal-btn-group { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
    .modal-btn { padding: 10px 14px; border: none; border-radius: 6px; font-size: 14px; font-weight: bold; cursor: pointer; color: #fff; }
    .btn-edit { background-color: #3498db; }
    .btn-complete { background-color: #2ecc71; }
    .btn-changedate { background-color: #f39c12; }
    .btn-undo { background-color: #e67e22; }
    .btn-close { background-color: #95a5a6; }
    .btn-save-date { background-color: #2c3e50; }
    .date-picker-content input[type="date"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; font-size: 15px; box-sizing: border-box;}
    .future-section { position: fixed; bottom: 60px; left: 0; right: 0; background-color: #fff; border-radius: 16px 16px 0 0; box-shadow: 0 -4px 15px rgba(0,0,0,0.1); z-index: 90; margin: 0; }
    .future-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; background-color: #f8f9fa; border-radius: 16px 16px 0 0; cursor: pointer; border-top: 1px solid #eee; }
    .future-title { font-size: 16px; font-weight: bold; color: #2c3e50; }
    .future-toggle-icon { font-size: 16px; color: #7f8c8d; user-select: none; }
    .future-content { display: none; max-height: 50vh; overflow-y: auto; padding: 10px 20px 20px 20px; background-color: #fff; }
    .future-item { display: flex; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px dashed #eee; font-size: 14px; cursor: pointer; transition: background-color 0.2s; }
    .future-item:hover { background-color: #f8f9fa; }
    .future-date-badge { background-color: #ecf0f1; color: #7f8c8d; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; white-space: nowrap; }
    .future-text { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #333; }
    .bottom-spacer { height: 130px; }
</style>

<input type="hidden" id="current-view-date" value="<?= htmlspecialchars($viewDate, ENT_QUOTES) ?>">
<input type="hidden" id="current-date-status" value="<?= $dateStatus ?>">

<div class="date-nav-container">
    <button class="date-nav-btn prev" onclick="moveDate(-1, '<?= htmlspecialchars($viewDate, ENT_QUOTES) ?>')">&#10094;</button>
    <div class="date-title-wrap">
        <?= htmlspecialchars($viewDate) ?>
        <?php if ($dateStatus === 0): ?><span class="today-badge">(오늘)</span><?php endif; ?>
    </div>
    <button class="date-nav-btn next" onclick="moveDate(1, '<?= htmlspecialchars($viewDate, ENT_QUOTES) ?>')">&#10095;</button>
</div>

<div class="stats-container">
    <div class="stat-item"><span class="stat-label">이전 미완료</span><span class="stat-value <?= $prevIncompleteCount > 0 ? 'alert' : '' ?>"><?= $prevIncompleteCount ?></span></div>
    <div class="stat-item"><span class="stat-label">오늘 총 할일</span><span class="stat-value"><?= $todayTotalCount ?></span></div>
    <div class="stat-item"><span class="stat-label">오늘 미완료</span><span class="stat-value <?= $todayIncompleteCount > 0 ? 'alert' : '' ?>"><?= $todayIncompleteCount ?></span></div>
    <div class="stat-item"><span class="stat-label">오늘이후 할일</span><span class="stat-value"><?= $futureTotalCount ?></span></div>
</div>

<ul class="task-list">
    <?php if (count($tasks) === 0): ?>
        <li style="text-align: center; color: #999; padding: 20px;">할일이 없어! 새 할일을 추가해 볼까?</li>
    <?php else: ?>
        <?php foreach ($tasks as $task): 
            $status = $task['completion_status'];
            $isCompleted = ($status === '완료');
            $isChanged = ($status !== '' && $status !== null &&$status !== '완료');
            $isDoneAny = ($isCompleted || $isChanged);$canDelete = ($dateStatus >= 0 && !$isDoneAny);
        ?>
            <li class="task-item">
                <div class="task-content-line <?= $isDoneAny ? 'completed' : '' ?>" 
                     data-id="<?= $task['id'] ?>" 
                     data-content="<?= htmlspecialchars($task['content'], ENT_QUOTES) ?>" 
                     data-status="<?= htmlspecialchars($status ?? '', ENT_QUOTES) ?>"
                     onclick="openTaskDetail(this)">
                    <?= htmlspecialchars($task['content']) ?>
                </div>
                <div class="task-actions">
                    <?php if ($task['attachment_count'] > 0): ?>
                        <span class="task-attachment-icon" title="첨부파일 <?= (int)$task['attachment_count'] ?>개" onclick="openTaskDetail(this.closest('.task-item').querySelector('.task-content-line'))">📎</span>
                    <?php endif; ?>

                    <?php if ($isCompleted): ?>
                        <span class="task-status-tag status-completed">[완료]</span>
                    <?php elseif ($isChanged): ?>
                        <span class="task-status-tag status-transfer">[이관 : <?= htmlspecialchars($status) ?>]</span>
                    <?php elseif ($dateStatus <= 0): ?>
                        <span class="task-status-tag status-incomplete">[미완료]</span>
                    <?php endif; ?>

                    <?php if ($canDelete): ?>
                        <button class="task-delete-btn" onclick="deleteDailyTask(<?= $task['id'] ?>)">🗑️</button>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
</ul>

<div class="bottom-spacer"></div>

<div class="future-section">
    <div class="future-header" onclick="toggleFutureTasks()">
        <div class="future-title">향후 할일 <span style="font-size:13px; color:#7f8c8d; font-weight:normal;">(총 <?= $futureTotalCount ?>건)</span></div>
        <div class="future-toggle-icon" id="future-toggle-icon">▲</div>
    </div>
    <div class="future-content" id="future-tasks-content">
        <?php if (count($futureTasks) === 0): ?>
            <div style="padding: 20px; text-align: center; color: #999; font-size: 14px;">등록된 향후 할일이 없습니다.</div>
        <?php else: ?>
            <?php foreach ($futureTasks as$ftask): ?>
                <div class="future-item" onclick="refreshCurrentView('<?= htmlspecialchars($ftask['task_date'], ENT_QUOTES) ?>')">
                    <span class="future-date-badge"><?= htmlspecialchars($ftask['task_date']) ?></span>
                    <span class="future-text"><?= htmlspecialchars($ftask['content']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="detail-modal-overlay" id="taskDetailModal">
    <div class="detail-modal-content">
        <input type="hidden" id="modal-task-id">
        <textarea id="modal-task-content" rows="6"></textarea>
        
        <div class="attachment-box">
            <div class="attachment-title">기존 첨부파일 <span id="modal-attachment-count"></span></div>
            <div id="modal-attachment-list" class="attachment-list"></div>
            
            <div style="margin-top: 10px; border-top: 1px dashed #eee; padding-top: 8px;">
                <label for="modal-attachments" style="font-size: 12px; font-weight: bold; color: #555;">새 첨부파일 추가:</label>
                <input type="file" id="modal-attachments" multiple style="margin-top: 4px; width: 100%; font-size: 12px;">
                <div id="modal-selected-files-container" style="display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;"></div>
                <div id="modal-attachment-help" class="attachment-help"></div>
            </div>
        </div>

        <div class="modal-btn-group">
            <button class="modal-btn btn-edit" id="btnEdit" onclick="updateTaskContent()">수정</button>
            <button class="modal-btn btn-complete" id="btnComplete" onclick="setTaskComplete()">완료</button>
            <button class="modal-btn btn-changedate" id="btnChangeDate" onclick="openDatePicker()">날짜변경</button>
            <button class="modal-btn btn-undo" id="btnUndo" onclick="undoTaskComplete()">완료취소</button>
            <button class="modal-btn btn-close" onclick="closeTaskDetail()">닫기</button>
        </div>
    </div>
</div>

<div class="date-picker-overlay" id="datePickerModal">
    <div class="detail-modal-content date-picker-content">
        <h4 style="margin-bottom:15px; color:#2c3e50; text-align:center;">이동할 날짜를 선택해 줘</h4>
        <input type="date" id="new-task-date">
        <div class="modal-btn-group">
            <button class="modal-btn btn-close" onclick="closeDatePicker()">취소</button>
            <button class="modal-btn btn-save-date" onclick="saveTaskDateChange()">저장</button>
        </div>
    </div>
</div>

<script>
    let existingFileCountForDetail = 0;
    let editSelectedFilesForDetail = [];

    function toggleFutureTasks() {
        const content = document.getElementById('future-tasks-content');
        const icon = document.getElementById('future-toggle-icon');
        if (content.style.display === 'block') {
            content.style.display = 'none';
            icon.innerText = '▲';
        } else {
            content.style.display = 'block';
            icon.innerText = '▼';
        }
    }

    function refreshCurrentView(targetDate) {
        if (typeof loadPage === 'function') {
            loadPage('dailytasks.php?d=' + targetDate, '오늘의 할일');
        } else {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('d', targetDate);
            window.location.href = currentUrl.toString();
        }
    }

    function moveDate(offset, baseDate) {
        const parts = baseDate.split('-');
        const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
        dateObj.setDate(dateObj.getDate() + offset);
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
        const dd = String(dateObj.getDate()).padStart(2, '0');
        refreshCurrentView(`${yyyy}-${mm}-${dd}`);
    }

    function getCurrentViewDate() { return document.getElementById('current-view-date').value; }
    function getCurrentDateStatus() { return parseInt(document.getElementById('current-date-status').value, 10); }

    function deleteDailyTask(id) {
        if (confirm('이 할일을 삭제할까?')) {
            const formData = new FormData();
            formData.append('id', id);
            fetch('dailytasks.php?action=delete', { method: 'POST', body: formData })
            .then(res => res.text()).then(data => {
                if (data.trim() === 'success') { refreshCurrentView(getCurrentViewDate()); }
            });
        }
    }

    function openTaskDetail(element) {
        const id = element.getAttribute('data-id');
        const content = element.getAttribute('data-content');
        const status = element.getAttribute('data-status');
        const dateStatus = getCurrentDateStatus();
        
        document.getElementById('modal-task-id').value = id;
        document.getElementById('modal-task-content').value = content;
        
        const btnEdit = document.getElementById('btnEdit');
        const btnComplete = document.getElementById('btnComplete');
        const btnChangeDate = document.getElementById('btnChangeDate');
        const btnUndo = document.getElementById('btnUndo');
        const textarea = document.getElementById('modal-task-content');
        
        btnEdit.style.display = 'none';
        btnComplete.style.display = 'none';
        btnChangeDate.style.display = 'none';
        btnUndo.style.display = 'none';
        textarea.removeAttribute('readonly');

        if (status === '완료') {
            textarea.setAttribute('readonly', true);
            if (dateStatus !== -1) { btnUndo.style.display = 'block'; }
        } else if (status !== '') {
            textarea.setAttribute('readonly', true);
        } else {
            if (dateStatus === -1) {
                textarea.setAttribute('readonly', true);
                btnComplete.style.display = 'block';
                btnChangeDate.style.display = 'block';
            } else {
                btnEdit.style.display = 'block';
                btnComplete.style.display = 'block';
                btnChangeDate.style.display = 'block';
            }
        }

        // 새 첨부파일 선택 목록 초기화
        editSelectedFilesForDetail = [];
        const fileInput = document.getElementById('modal-attachments');
        if (fileInput) {
            fileInput.value = '';
            if (window.TODO_ATTACHMENT_EXTENSIONS) {
                fileInput.accept = window.TODO_ATTACHMENT_EXTENSIONS.map(e => '.' + e).join(',');
            }
        }
        syncDetailSelectedFilesAndRender();
        
        const helpText = document.getElementById('modal-attachment-help');
        if (helpText) {
            const max = window.TODO_ATTACHMENT_MAX_FILES || 5;
            const ext = (window.TODO_ATTACHMENT_EXTENSIONS || []).join(', ').toUpperCase();
            helpText.textContent = `최대 ${max}개 / ${ext} / 파일당 20MB`;
        }

        document.getElementById('taskDetailModal').style.display = 'flex';
        loadTaskAttachments(id);
    }

    function loadTaskAttachments(taskId) {
        const list = document.getElementById('modal-attachment-list');
        const count = document.getElementById('modal-attachment-count');
        list.innerHTML = '<span style="color:#999;font-size:11px;">불러오는 중...</span>';
        
        fetch('dailytasks.php?action=attachments&task_id=' + encodeURIComponent(taskId))
            .then(r => r.json())
            .then(files => {
                existingFileCountForDetail = files.length;
                count.textContent = '(' + files.length + '개)';
                if (!files.length) {
                    list.innerHTML = '<span style="color:#999;font-size:11px;">첨부파일이 없습니다.</span>';
                    return;
                }
                list.innerHTML = files.map(f => `
                    <div class="attachment-row" id="att-row-${f.att_id}">
                        <a class="attachment-name" href="${encodeURI(f.storage_path)}" target="_blank">📎 ${escapeHtml(f.original_name)}</a>
                        <button type="button" class="attachment-delete" onclick="deleteTaskAttachment(${f.att_id})">🗑️</button>
                    </div>
                `).join('');
            })
            .catch(() => {
                existingFileCountForDetail = 0;
                list.innerHTML = '<span style="color:#e74c3c;font-size:11px;">첨부파일 조회 실패</span>';
            });
    }

    // 파일 개별 삭제: 팝업을 닫지 않고 해당 아이템만 제거
    function deleteTaskAttachment(attId) {
        if (!confirm('이 첨부파일을 삭제할까?')) return;
        
        const fd = new FormData();
        fd.append('att_id', attId);
        
        fetch('dailytasks.php?action=delete_attachment', { method: 'POST', body: fd })
            .then(async r => {
                const t = await r.text();
                if (!r.ok) throw new Error(t);
                return t;
            })
            .then(() => {
                const row = document.getElementById('att-row-' + attId);
                if (row) row.remove();
                
                existingFileCountForDetail = Math.max(0, existingFileCountForDetail - 1);
                
                const count = document.getElementById('modal-attachment-count');
                if (count) count.textContent = '(' + existingFileCountForDetail + '개)';
                
                const list = document.getElementById('modal-attachment-list');
                if (list && list.children.length === 0) {
                    list.innerHTML = '<span style="color:#999;font-size:11px;">첨부파일이 없습니다.</span>';
                }

                // 메인 목록 아이콘 표시 실시간 동기화
                const taskId = document.getElementById('modal-task-id').value;
                const taskItem = document.querySelector(`.task-content-line[data-id="${taskId}"]`);
                if (taskItem) {
                    const taskActions = taskItem.closest('.task-item').querySelector('.task-actions');
                    const clipIcon = taskActions?.querySelector('.task-attachment-icon');
                    if (clipIcon) {
                        if (existingFileCountForDetail === 0) {
                            clipIcon.remove();
                        } else {
                            clipIcon.title = `첨부파일 ${existingFileCountForDetail}개`;
                        }
                    }
                }
            })
            .catch(e => alert(e.message || '파일 삭제 중 오류가 발생했어.'));
    }

    // 파일 선택 시 이벤트
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'modal-attachments') {
            const maxFiles = window.TODO_ATTACHMENT_MAX_FILES || 5;
            const allowedExt = window.TODO_ATTACHMENT_EXTENSIONS || [];
            const newFiles = Array.from(e.target.files || []);

            for (const file of newFiles) {
                if (existingFileCountForDetail + editSelectedFilesForDetail.length >= maxFiles) {
                    alert(`기존 파일을 포함해 최대 ${maxFiles}개까지만 선택할 수 있어!`);
                    break;
                }

                const extParts = file.name.split('.');
                const ext = extParts.length > 1 ? extParts.pop().toLowerCase() : '';

                if (allowedExt.length > 0 && !allowedExt.includes(ext)) {
                    alert(`허용되지 않는 파일 형식이야: ${file.name}`);
                    continue;
                }

                const isDuplicate = editSelectedFilesForDetail.some(item => 
                    item.name === file.name && item.size === file.size && item.lastModified === file.lastModified
                );

                if (!isDuplicate) {
                    editSelectedFilesForDetail.push(file);
                }
            }
            syncDetailSelectedFilesAndRender();
        }
    });

    function removeDetailSelectedFile(index) {
        if (index >= 0 && index < editSelectedFilesForDetail.length) {
            editSelectedFilesForDetail.splice(index, 1);
            syncDetailSelectedFilesAndRender();
        }
    }

    function syncDetailSelectedFilesAndRender() {
        const fileInput = document.getElementById('modal-attachments');
        const container = document.getElementById('modal-selected-files-container');
        
        if (fileInput) {
            const dt = new DataTransfer();
            editSelectedFilesForDetail.forEach(f => dt.items.add(f));
            fileInput.files = dt.files;
        }

        if (!container) return;
        container.innerHTML = '';

        editSelectedFilesForDetail.forEach((file, index) => {
            const badge = document.createElement('div');
            badge.className = 'selected-file-badge';

            const nameSpan = document.createElement('span');
            nameSpan.className = 'selected-file-badge-name';
            nameSpan.textContent = '📎 ' + file.name;

            const removeBtn = document.createElement('span');
            removeBtn.className = 'selected-file-badge-remove';
            removeBtn.innerHTML = '&times;';
            removeBtn.title = '제외';
            removeBtn.onclick = function(evt) {
                evt.stopPropagation();
                removeDetailSelectedFile(index);
            };

            badge.appendChild(nameSpan);
            badge.appendChild(removeBtn);
            container.appendChild(badge);
        });
    }

    function escapeHtml(v) { const d=document.createElement('div'); d.textContent=v??''; return d.innerHTML; }

    function closeTaskDetail() { document.getElementById('taskDetailModal').style.display = 'none'; }

    // [수정] 버튼 클릭 시 할일 내용 수정과 새 첨부파일 업로드를 동시 처리
    function updateTaskContent() {
        const id = document.getElementById('modal-task-id').value;
        const content = document.getElementById('modal-task-content').value;
        if (!content.trim()) return alert('내용을 입력해 줘!');

        const maxFiles = window.TODO_ATTACHMENT_MAX_FILES || 5;
        if (existingFileCountForDetail + editSelectedFilesForDetail.length > maxFiles) {
            return alert(`기존 파일을 포함해 최대 ${maxFiles}개까지만 유지할 수 있습니다!`);
        }

        const btnEdit = document.getElementById('btnEdit');
        if (btnEdit) btnEdit.disabled = true;

        const formData = new FormData();
        formData.append('id', id);
        formData.append('content', content);

        editSelectedFilesForDetail.forEach(file => {
            formData.append('attachments[]', file);
        });

        fetch('dailytasks.php?action=update_content', { method: 'POST', body: formData })
            .then(async res => {
                const text = await res.text();
                if (!res.ok) throw new Error(text);
                return text;
            })
            .then(data => {
                if (data.trim() === 'success') {
                    closeTaskDetail();
                    refreshCurrentView(getCurrentViewDate());
                } else {
                    alert(data);
                }
            })
            .catch(err => {
                alert(err.message || '수정 중 오류가 발생했어.');
            })
            .finally(() => {
                if (btnEdit) btnEdit.disabled = false;
            });
    }

    function setTaskComplete() {
        const id = document.getElementById('modal-task-id').value;
        const formData = new FormData();
        formData.append('id', id);
        fetch('dailytasks.php?action=complete', { method: 'POST', body: formData })
        .then(res => res.text()).then(data => {
            if (data.trim() === 'success') { closeTaskDetail(); refreshCurrentView(getCurrentViewDate()); }
        });
    }

    function undoTaskComplete() {
        const id = document.getElementById('modal-task-id').value;
        const formData = new FormData();
        formData.append('id', id);
        fetch('dailytasks.php?action=undo_complete', { method: 'POST', body: formData })
        .then(res => res.text()).then(data => {
            if (data.trim() === 'success') { closeTaskDetail(); refreshCurrentView(getCurrentViewDate()); }
        });
    }

    function openDatePicker() {
        const today = new Date();
        const tomorrow = new Date(today.getTime());
        tomorrow.setDate(today.getDate() + 1);
        const localTomorrow = new Date(tomorrow.getTime() - (tomorrow.getTimezoneOffset() * 60000)).toISOString().split('T')[0];
        
        const dateInput = document.getElementById('new-task-date');
        dateInput.min = localTomorrow;
        dateInput.value = localTomorrow;
        document.getElementById('datePickerModal').style.display = 'flex';
    }

    function closeDatePicker() { document.getElementById('datePickerModal').style.display = 'none'; }

    function saveTaskDateChange() {
        const id = document.getElementById('modal-task-id').value;
        const content = document.getElementById('modal-task-content').value;
        const newDate = document.getElementById('new-task-date').value;
        const currentView = getCurrentViewDate();

        if (!newDate) return alert('변경할 날짜를 선택해 줘!');
        if (newDate === currentView) return alert('이미 같은 날짜야!');

        const formData = new FormData();
        formData.append('id', id);
        formData.append('content', content);
        formData.append('new_date', newDate);

        fetch('dailytasks.php?action=change_date', { method: 'POST', body: formData })
        .then(res => res.text()).then(data => {
            if (data.trim() === 'success') {
                closeDatePicker();
                closeTaskDetail();
                refreshCurrentView(currentView);
            }
        });
    }
</script>