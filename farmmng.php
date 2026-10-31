<?php
require_once __DIR__ . '/db_connect.php';

// 첨부파일 테이블명 및 기본값 미선언 시 오류 방지 처리
$attachment_table = $attachment_table ?? 'attachment';
$max_file_count = $max_file_count ?? 5;
$allowed_extensions = $allowed_extensions ?? ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'hwp', 'hwpx', 'zip'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $memoId = intval($_POST['id'] ?? 0);
    if ($memoId > 0) {
        $pdo->beginTransaction();
        try {
            $fileStmt = $pdo->prepare("SELECT att_id, storage_type, storage_path FROM {$attachment_table} WHERE att_grp_id = :att_grp_id AND status = 'ACTIVE'");
            $fileStmt->execute([':att_grp_id' => $memoId]);
            $files = $fileStmt->fetchAll();

            foreach ($files as $file) {
                if (($file['storage_type'] ?? 'LOCAL') === 'LOCAL' && !empty($file['storage_path'])) {
                    $fullPath = __DIR__ . '/' . ltrim($file['storage_path'], '/');
                    if (is_file($fullPath)) { @unlink($fullPath); }
                }
            }
            $delFileStmt = $pdo->prepare("DELETE FROM {$attachment_table} WHERE att_grp_id = :att_grp_id");
            $delFileStmt->execute([':att_grp_id' => $memoId]);

            $stmt = $pdo->prepare("DELETE FROM memoT WHERE id = :id");
            $stmt->execute([':id' => $memoId]);

            $pdo->commit();
            echo "DELETED";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            http_response_code(500);
            echo "FAIL: 삭제 중 오류가 발생했습니다.";
        }
    } else {
        http_response_code(400);
        echo "INVALID ID";
    }
    exit;
}

$memo_write_max_file_count = $max_file_count;
$memo_write_allowed_extensions = $allowed_extensions;
$memo_write_attachment_table = $attachment_table;
$memo_write_upload_base_dir = __DIR__ . '/uploads';
$memo_write_submit_url = 'farmmng.php';
include_once __DIR__ . '/memo_write_component_popup.php';

$memo_update_max_file_count = $max_file_count;
$memo_update_allowed_extensions = $allowed_extensions;
$memo_update_attachment_table = $attachment_table;
$memo_update_upload_base_dir = __DIR__ . '/uploads';
$memo_update_action_url = 'farmmng.php';
include_once __DIR__ . '/memo_update_component_popup.php';

function getFarmsList($pdo) {
    try {
        $stmt = $pdo->query("SELECT farm_id, farm_name, row_count, col_count, farm_type FROM farms WHERE use_yn = 'Y' ORDER BY sort_order ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { return []; }
}

// 밭별 최근/오래전 작업 경과일 계산 함수
// (farmmngdtl.php 는 저장 시 선택하지 않은 이랑도 check_status='N' 으로 함께 INSERT 하므로
//  N 행은 "작업 안 함" 채움 행임. 따라서 Y 행만 대상으로 한다.)
// 1) check_status='Y' 기록 중 (farm_id,row_num,col_num,작업종류) 별 가장 최근 1건만 선택
//    (Y 기록이 하나도 없는 이랑은 자연히 제외됨)
// 2) 작업종류별로 최근일자(MAX)/가장 오래된 일자(MIN)의 오늘까지 경과일 산출
// 작업 코드: 10=물, 20=수확, 30=옆순, 40~46=농약(가군/나군 → 40으로 통합), 70~72=영양제(1군/2군/3군 → 70으로 통합)
function getFarmTaskStats($pdo, $farmId) {

    $sql = "
        SELECT
            task_id,
            last_work_date,
            last_elapsed_days,
            first_work_date,
            CASE
                WHEN first_elapsed_days <= last_elapsed_days THEN '-'
                ELSE first_elapsed_days
            END AS first_elapsed_days
        FROM (
            SELECT
                grouped_task_id AS task_id,
                MAX(work_date) AS last_work_date,
                DATEDIFF(CURDATE(), MAX(work_date)) AS last_elapsed_days,
                MIN(work_date) AS first_work_date,
                DATEDIFF(CURDATE(), MIN(work_date)) AS first_elapsed_days
            FROM (
                SELECT
                    work_date,
                    grouped_task_id,
                    ROW_NUMBER() OVER (
                        PARTITION BY farm_id, row_num, col_num, grouped_task_id
                        ORDER BY created_at DESC, ridge_id DESC
                    ) AS rn
                FROM (
                    SELECT
                        ridge_id,
                        farm_id,
                        row_num,
                        col_num,
                        work_date,
                        check_status,
                        created_at,
                        CASE
                            WHEN CAST(task_id AS UNSIGNED) BETWEEN 40 AND 46 THEN 40
                            WHEN CAST(task_id AS UNSIGNED) BETWEEN 70 AND 72 THEN 70
                            ELSE CAST(task_id AS UNSIGNED)
                        END AS grouped_task_id
                    FROM ridge_records
                    WHERE farm_id = :farm_id
                      AND check_status = 'Y'
                      AND work_date IS NOT NULL
                ) base
            ) r
            WHERE r.rn = 1
            GROUP BY grouped_task_id
        ) t
        ORDER BY task_id
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':farm_id' => $farmId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('[farmmng] getFarmTaskStats error: ' . $e->getMessage());
        return [];
    }
}

function getFarmMemosPaged($pdo, $groupId, $page = 1, $limit = 5) {
    global $attachment_table;
    if (empty($attachment_table)) {
        $attachment_table = 'attachment';
    }
    try {
        $groupIdStr = (string)$groupId;

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM memoT WHERE group_id = :group_id");
        $stmtCount->bindValue(':group_id', $groupIdStr, PDO::PARAM_STR);
        $stmtCount->execute();
        $totalCount = (int)$stmtCount->fetchColumn();
        $totalPages = max(1, (int)ceil($totalCount / $limit));
        if ($page > $totalPages) { $page = $totalPages; }
        if ($page < 1) { $page = 1; }
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare("
            SELECT m.id, m.reg_date, m.content,
                (SELECT COUNT(*) FROM {$attachment_table} a WHERE a.att_grp_id = m.id AND a.status = 'ACTIVE') AS file_count
            FROM memoT m 
            WHERE m.group_id = :group_id 
            ORDER BY m.id DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':group_id', $groupIdStr, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $memos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($memos)) {
            $memoIds = array_column($memos, 'id');
            $inQuery = implode(',', array_fill(0, count($memoIds), '?'));
            $attachStmt = $pdo->prepare("SELECT att_id AS id, att_grp_id, original_name, storage_path, extension FROM {$attachment_table} WHERE att_grp_id IN ({$inQuery}) AND status = 'ACTIVE'");
            $attachStmt->execute($memoIds);
            $attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
            $groupedAttachments = [];
            foreach ($attachments as $att) { $groupedAttachments[$att['att_grp_id']][] = $att; }
            foreach ($memos as &$memo) { $memo['attachments'] = $groupedAttachments[$memo['id']] ?? []; }
            unset($memo);
        }
        return ['memos' => $memos, 'page' => $page, 'totalPages' => $totalPages, 'totalCount' => $totalCount];
    } catch (PDOException $e) {
        return ['memos' => [], 'page' => 1, 'totalPages' => 1, 'totalCount' => 0];
    }
}

$farms = getFarmsList($pdo);
$bgPalette = ['#e8f5e9', '#fff3e0', '#e1f5fe', '#f3e5f5', '#fffde7', '#fbe9e7'];

function renderPaginationHTML($farmParam, $page, $totalPages) {
    $blockSize = 5;
    $currentBlock = (int)ceil($page / $blockSize);
    $startPage = ($currentBlock - 1) * $blockSize + 1;
    $endPage = min($startPage + $blockSize - 1, $totalPages);
    $prevPage = max(1, $page - 1);
    $nextPage = min($totalPages, $page + 1);

    $disabledFirst = ($page <= 1) ? 'disabled' : '';
    $disabledPrev = ($page <= 1) ? 'disabled' : '';
    $disabledNext = ($page >= $totalPages) ? 'disabled' : '';
    $disabledLast = ($page >= $totalPages) ? 'disabled' : '';

    $html = '<div class="farm-pagination">';
    $html .= '<button onclick="changeFarmPage(\''.$farmParam.'\', 1)" '.$disabledFirst.'>&lt;&lt;</button>';
    $html .= '<button onclick="changeFarmPage(\''.$farmParam.'\', '.$prevPage.')" '.$disabledPrev.'>&lt;</button>';
    for ($i = $startPage; $i <= $endPage; $i++) {
        $activeClass = ($i == $page) ? 'active' : '';
        $html .= '<button onclick="changeFarmPage(\''.$farmParam.'\', '.$i.')" class="'.$activeClass.'">'.$i.'</button>';
    }
    $html .= '<button onclick="changeFarmPage(\''.$farmParam.'\', '.$nextPage.')" '.$disabledNext.'>&gt;</button>';
    $html .= '<button onclick="changeFarmPage(\''.$farmParam.'\', '.$totalPages.')" '.$disabledLast.'>&gt;&gt;</button>';
    $html .= '</div>';
    return $html;
}

function renderFarmBox($title, $icon, $groupId, $bgColor, $memoData, $farmParam, $taskStats) {
    $memos = $memoData['memos'];
    $page = $memoData['page'];
    $totalPages = $memoData['totalPages'];
    
    // 작업 ID 매핑 및 초기화 (farmmngdtl.php $taskMap 기준: 10=물, 20=수확, 30=옆순, 40~46=농약→40)
    $tasks = [
        '10' => ['last' => '-', 'first' => '-'], // 💧 물
        '20' => ['last' => '-', 'first' => '-'], // 🌾 수확
        '30' => ['last' => '-', 'first' => '-'], // ✂️ 옆순
        '40' => ['last' => '-', 'first' => '-']  // ☠️ 농약(40~46)
    ];

    if (!empty($taskStats)) {
        foreach ($taskStats as $t) {
            $tId = (string)$t['task_id']; // 키 매칭을 위해 문자열로 처리
            if (isset($tasks[$tId])) {
                $tasks[$tId]['last'] = $t['last_elapsed_days'] !== null ? $t['last_elapsed_days'] : '-';
                $tasks[$tId]['first'] = $t['first_elapsed_days'] !== null ? $t['first_elapsed_days'] : '-';
            }
        }
    }
    ?>
    <div class="farm-box" style="background-color: <?php echo $bgColor; ?>;">
        <div class="farm-title">
            <span><?php echo $icon . ' ' . htmlspecialchars($title); ?></span>
            <div class="farm-title-right">
                <?php echo renderPaginationHTML($farmParam, $page, $totalPages); ?>
                <span class="btn-memo-icon" title="새 메모 작성" onclick="openMemoWriteModal('<?php echo htmlspecialchars($groupId, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>')">📝</span>
            </div>
        </div>
        <div class="top-farm-wrapper">
            <div class="status-dashboard" onclick="goToFarmDetail('<?php echo htmlspecialchars($groupId); ?>')">
                <div class="task-summary-line" title="최근 경과일 / 최대 경과일">
                    💧 <?php echo $tasks['10']['last']; ?> 
                    🌾 <?php echo $tasks['20']['last']; ?> 
                    ✂️ <?php echo $tasks['30']['last']; ?> 
                    ☠️ <?php echo $tasks['40']['last']; ?> 
                    <span class="stat-divider">/</span> 
                    💧 <?php echo $tasks['10']['first']; ?> 
                    🌾 <?php echo $tasks['20']['first']; ?> 
                    ✂️ <?php echo $tasks['30']['first']; ?> 
                    ☠️ <?php echo $tasks['40']['first']; ?>
                </div>
            </div>
            <div class="memo-section">
                <?php if (empty($memos)): ?>
                    <p style="color: #888; font-size: 0.8rem; margin: 4px 0;">기록된 내용이 없습니다</p>
                <?php else: ?>
                    <ul class="memo-list-mini">
                        <?php foreach ($memos as $m): ?>
                            <?php $formattedDate = date('m-d H:i', strtotime($m['reg_date'])); ?>
                            <li class="memo-item-mini"
                                data-memo-item="true"
                                data-id="<?php echo (int)$m['id']; ?>"
                                data-group-id="<?php echo htmlspecialchars($groupId, ENT_QUOTES, 'UTF-8'); ?>"
                                data-display-group-id="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
                                data-date="<?php echo htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8'); ?>"
                                data-content="<?php echo htmlspecialchars($m['content'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-files="<?php echo htmlspecialchars(json_encode($m['attachments'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                onclick="openModalFromElem(this)">
                                <span class="memo-date-mini">[<?php echo htmlspecialchars($formattedDate); ?>]</span>
                                <span class="memo-text-mini"><?php echo htmlspecialchars($m['content']); ?></span>
                                <?php if ((int)($m['file_count'] ?? 0) > 0): ?>
                                    <span class="icon-clip" title="첨부파일">📎</span>
                                <?php endif; ?>
                                <span class="btn-memo-delete" title="삭제" onclick="event.stopPropagation(); deleteFarmMemo(<?php echo $m['id']; ?>)">🗑</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>
<style>
    /* flex: 1 과 height: 100% 를 제거하여 내용만큼 높이가 늘어나도록 수정 */
    .farm-container { display: flex; flex-direction: column; gap: 12px; box-sizing: border-box; padding-bottom: 20px; }
    .farm-box { padding: 12px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; }
    .farm-title { font-size: 1.05rem; font-weight: bold; margin-bottom: 8px; color: #333; border-bottom: 1px solid rgba(0, 0, 0, 0.1); padding-bottom: 4px; display: flex; justify-content: space-between; align-items: center; }
    .farm-title-right { display: flex; align-items: center; gap: 6px; }
    .farm-pagination { display: flex; gap: 2px; align-items: center; }
    .farm-pagination button { padding: 1px 5px; font-size: 0.75rem; border: 1px solid #ccc; background-color: #fff; color: #333; border-radius: 3px; cursor: pointer; }
    .farm-pagination button.active { background-color: #4CAF50; color: white; border-color: #4CAF50; font-weight: bold; }
    .btn-memo-icon { cursor: pointer; font-size: 1.1rem; }
    .btn-memo-delete { cursor: pointer; font-size: 0.85rem; color: #d32f2f; }
    .top-farm-wrapper { display: flex; flex-direction: column; gap: 10px; }
    
    /* 한 줄 모니터링 대시보드 디자인 */
    .status-dashboard { display: flex; flex-direction: column; justify-content: center; align-items: center; background-color: rgba(255, 255, 255, 0.8); padding: 8px 10px; border-radius: 6px; cursor: pointer; border: 1px solid rgba(0, 0, 0, 0.08); }
    .task-summary-line { display: flex; justify-content: center; align-items: center; gap: 8px; width: 100%; background: rgba(255, 255, 255, 0.5); padding: 6px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; color: #333; white-space: nowrap; overflow-x: auto; }
    .stat-divider { font-weight: bold; color: #aaa; margin: 0 4px; }
    
    .memo-section { background-color: rgba(255, 255, 255, 0.7); border-radius: 6px; padding: 6px 8px; }
    .memo-list-mini { list-style: none; padding: 0; margin: 0; }
    .memo-item-mini { display: flex; align-items: center; gap: 6px; padding: 3px 0; border-bottom: 1px solid #eee; font-size: 0.85rem; cursor: pointer; }
    .memo-date-mini { font-size: 0.75rem; font-weight: bold; color: #666; white-space: nowrap; }
    .memo-text-mini { color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
</style>
<div class="farm-container">
    <?php if (empty($farms)): ?>
        <div style="text-align: center; padding: 20px; color: #666;">등록된 밭 정보가 없습니다.</div>
    <?php else: ?>
        <?php foreach ($farms as $index => $farm): ?>
            <?php
                $farmId = $farm['farm_id'];
                $farmName = $farm['farm_name'];
                $farmType = $farm['farm_type'] ?? '노지';
                $icon = ($farmType === '하우스') ? '🏠' : '🌱';
                $bgColor = $bgPalette[$index % count($bgPalette)];
                $farmParam = 'p_' . $farmId;
                $currentPage = max(1, (int)($_GET[$farmParam] ?? 1));

                $taskStats = getFarmTaskStats($pdo, $farmId);
                $memoData = getFarmMemosPaged($pdo, $farmId, $currentPage, 5);
                
                renderFarmBox($farmName, $icon, $farmId, $bgColor, $memoData, $farmParam, $taskStats);
            ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<script>
    window.memoWriteSuccessCallback = function() {
        const targetUrl = 'farmmng.php' + window.location.search;
        if (typeof window.loadPage === 'function') { window.loadPage(targetUrl, '밭 관리'); } else { window.location.reload(); }
    };
    window.memoUpdateSuccessCallback = function() {
        const targetUrl = 'farmmng.php' + window.location.search;
        if (typeof window.loadPage === 'function') { window.loadPage(targetUrl, '밭 관리'); } else { window.location.reload(); }
    };
    function changeFarmPage(farmParam, page) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set(farmParam, page);
        const targetUrl = 'farmmng.php?' + urlParams.toString();
        if (typeof window.loadPage === 'function') { window.loadPage(targetUrl, '밭 관리'); } else { window.location.href = targetUrl; }
    }
    function goToFarmDetail(groupId) {
        const url = 'farmmngdtl.php?farm_id=' + encodeURIComponent(groupId) + '&group_id=' + encodeURIComponent(groupId);
        if (typeof window.loadPage === 'function') { window.loadPage(url); } else { window.location.href = url; }
    }
    function deleteFarmMemo(memoId) {
        if (confirm('이 메모를 정말 삭제하시겠습니까?')) {
            const formData = new URLSearchParams();
            formData.append('action', 'delete');
            formData.append('id', memoId);
            fetch('farmmng.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData })
            .then(res => {
                if (!res.ok) throw new Error('삭제 실패');
                const targetUrl = 'farmmng.php' + window.location.search;
                if (typeof window.loadPage === 'function') { window.loadPage(targetUrl, '밭 관리'); } else { window.location.reload(); }
            })
            .catch(err => { alert('삭제 중 오류가 발생했습니다.'); });
        }
    }
</script>