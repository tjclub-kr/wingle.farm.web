<?php

// --------------------------------------------------
// 공통 DB 접속
// --------------------------------------------------
require_once __DIR__ . '/db_connect.php';

// --------------------------------------------------
// 공통 작업 매핑 정의 (한 곳에서 선언 후 참조)
// --------------------------------------------------
$taskMap = [
    '물'     => 10,
    '수확'   => 20,
    '옆순'   => 30,
    '가군1'  => 40,
    '가군3'  => 41,
    '나군3'  => 42,
    '가군5'  => 43,
    '나군5'  => 44,
    '가군7'  => 45,
    '나군7'  => 46,
    '병해'   => 70,
    '충해'   => 71
];

$reverseTaskMap = [
    10 => '물',
    20 => '수확',
    30 => '옆순',
    40 => '가군1',
    41 => '가군3',
    42 => '나군3',
    43 => '가군5',
    44 => '나군5',
    45 => '가군7',
    46 => '나군7',
    70 => '병해',
    71 => '충해'
];

// --------------------------------------------------
// 첨부파일 설정
// --------------------------------------------------
$max_file_count = 5;
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'hwp'];
$attachment_table = 'attachment';

// post_max_size 초과 시 POST/FILES 데이터가 유실되는 현상 예외 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST)
    && empty($_FILES)
    && isset($_SERVER['CONTENT_LENGTH'])
    && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(400);
    echo '업로드 허용 용량(post_max_size)을 초과하였습니다.';
    exit;
}

// --------------------------------------------------
// 공통 헬퍼 함수
// --------------------------------------------------
function getDefaultFarmIdByName($name) {
    $map = ['상밭' => 1, '중밭' => 2, '겨울밭' => 3];
    return isset($map[$name]) ? $map[$name] : 1;
}

function getFarmInfo($pdo, $identifier) {
    try {
        if (is_numeric($identifier) && intval($identifier) > 0) {
            $stmt = $pdo->prepare("SELECT farm_id, farm_name, row_count, col_count FROM farms WHERE farm_id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$identifier]);
        } else {
            $stmt = $pdo->prepare("SELECT farm_id, farm_name, row_count, col_count FROM farms WHERE farm_name = :name LIMIT 1");
            $stmt->execute([':name' => $identifier]);
        }
        
        $res = $stmt->fetch();
        if ($res) {
            return [
                'farm_id' => (int)$res['farm_id'],
                'farm_name' => $res['farm_name'],
                'row_count' => (int)$res['row_count'], 
                'col_count' => (int)$res['col_count']  
            ];
        }
    } catch (Exception $e) {}
    
    $fallbackId = is_numeric($identifier) ? (int)$identifier : getDefaultFarmIdByName($identifier);
    $fallbackName = is_numeric($identifier) ? '밭 ' . $identifier : $identifier;

    return [
        'farm_id' => $fallbackId, 
        'farm_name' => $fallbackName, 
        'row_count' => 4, 
        'col_count' => 10
    ];
}

// ==========================================
// AJAX API 영역 (HTML 출력 이전에 완전히 처리 및 종료)
// ==========================================

// 1. AJAX: 메모 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_memo') {
    header('Content-Type: application/json; charset=utf-8');

    $memoId = (int)($_POST['id'] ?? 0);
    if ($memoId <= 0) {
        echo json_encode(['success' => false, 'message' => '잘못된 ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $fileStmt = $pdo->prepare("SELECT att_id, storage_type, storage_path FROM {$attachment_table} WHERE att_grp_id = :memo_id AND status = 'ACTIVE'");
        $fileStmt->execute([':memo_id' => $memoId]);
        $files = $fileStmt->fetchAll();

        foreach ($files as $file) {
            if (($file['storage_type'] ?? '') === 'LOCAL' && !empty($file['storage_path'])) {
                $fullPath = __DIR__ . '/' . $file['storage_path'];
                if (is_file($fullPath) && !@unlink($fullPath)) {
                    throw new Exception('첨부파일 실제 파일 삭제에 실패했습니다.');
                }
            }
        }

        $delFileStmt = $pdo->prepare("DELETE FROM {$attachment_table} WHERE att_grp_id = :memo_id");
        $delFileStmt->execute([':memo_id' => $memoId]);

        $stmt = $pdo->prepare("DELETE FROM memoT WHERE id = :id");
        $stmt->execute([':id' => $memoId]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. AJAX: 이랑 데이터 DB 저장 (날짜 파라미터 적용)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_ridge_records') {
    header('Content-Type: application/json; charset=utf-8');
    
    $postFarmId  = !empty($_POST['farm_id']) ? intval($_POST['farm_id']) : 0;
    $workType    = !empty($_POST['work_type']) ? trim($_POST['work_type']) : '';
    $workDate    = !empty($_POST['work_date']) ? trim($_POST['work_date']) : date('Y-m-d');
    $content     = !empty($_POST['content']) ? trim($_POST['content']) : '';
    $ridges      = !empty($_POST['ridges']) && is_array($_POST['ridges']) ? $_POST['ridges'] : [];

    if ($postFarmId <= 0 || empty($workType)) {
        echo json_encode(['success' => false, 'message' => '필수 파라미터가 누락되었습니다.']);
        exit;
    }

    $taskId = $taskMap[$workType] ?? 99;

    try {
        $pdo->beginTransaction();
        
        $farmStmt = $pdo->prepare("SELECT row_count, col_count FROM farms WHERE farm_id = :farm_id LIMIT 1");
        $farmStmt->execute([':farm_id' => $postFarmId]);
        $farmRes = $farmStmt->fetch();
        
        $fRow = $farmRes ? (int)$farmRes['row_count'] : 4;
        $fCol = $farmRes ? (int)$farmRes['col_count'] : 10;

        $fCols = [];
        for ($i = 0; $i < $fRow; $i++) {
            $fCols[] = chr(65 + $i);
        }

        $stmt = $pdo->prepare("INSERT INTO ridge_records (farm_id, row_num, col_num, task_id, work_date, created_at, check_status, content) VALUES (:farm_id, :row_num, :col_num, :task_id, :work_date, CONCAT(:work_date_c, ' ', DATE_FORMAT(NOW(), '%H:%i:%s')), :check_status, :content)");
        
        $totalSavedCount = 0;
        for ($r = 1; $r <= $fCol; $r++) {
            foreach ($fCols as $c) {
                $ridgeName = "{$r}-{$c}";
                $checkStatus = in_array($ridgeName, $ridges) ? 'Y' : 'N';
                
                $colNum = ord(strtoupper($c)) - 64;
                if ($colNum < 1) $colNum = 1;

                $stmt->execute([
                    ':farm_id' => $postFarmId,
                    ':row_num' => $r,
                    ':col_num' => $colNum,
                    ':task_id' => $taskId,
                    ':work_date' => $workDate,
                    ':work_date_c' => $workDate,
                    ':check_status' => $checkStatus,
                    ':content' => $content
                ]);
                $totalSavedCount++;
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'count' => $totalSavedCount]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '저장 중 오류 발생: ' . $e->getMessage()]);
    }
    exit;
}

// 3. AJAX: 이랑 클릭 시 최근 정보 페이징 조회
if (isset($_GET['action']) && $_GET['action'] === 'get_ridge_info') {
    header('Content-Type: application/json; charset=utf-8');
    
    $ajaxFarmId = !empty($_GET['farm_id']) ? intval($_GET['farm_id']) : 0;
    $ridgeName  = !empty($_GET['ridge_name']) ? trim($_GET['ridge_name']) : '';
    $rowNum     = !empty($_GET['row_num']) ? intval($_GET['row_num']) : 0;
    $colNum     = !empty($_GET['col_num']) ? intval($_GET['col_num']) : 0;
    $page       = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit      = 10;
    $offset     = ($page - 1) * $limit;
    
    if ($rowNum <= 0 || $colNum <= 0) {
        $parts  = explode('-', $ridgeName);
        if (count($parts) === 2) {
            $p1 = trim($parts[0]);
            $p2 = trim($parts[1]);

            if (is_numeric($p1) && !is_numeric($p2)) {
                $rowNum = intval($p1);
                $colNum = ord(strtoupper($p2)) - 64;
            } elseif (!is_numeric($p1) && is_numeric($p2)) {
                $colNum = ord(strtoupper($p1)) - 64;
                $rowNum = intval($p2);
            } elseif (is_numeric($p1) && is_numeric($p2)) {
                $rowNum = intval($p1);
                $colNum = intval($p2);
            }
        }
    }

    if ($ajaxFarmId <= 0 || $rowNum <= 0 || $colNum <= 0) {
        echo json_encode(['success' => false, 'message' => '유효하지 않은 밭 또는 이랑 파라미터입니다.']);
        exit;
    }

    try {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ridge_records WHERE farm_id = :farm_id AND row_num = :row_num AND col_num = :col_num AND (check_status != 'N' OR check_status IS NULL)");
        $countStmt->execute([
            ':farm_id' => $ajaxFarmId,
            ':row_num' => $rowNum,
            ':col_num' => $colNum
        ]);
        $totalCount = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($totalCount / $limit);

        $stmt = $pdo->prepare("SELECT ridge_id, work_date, task_id, check_status, content, created_at, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS created_minute FROM ridge_records WHERE farm_id = :farm_id AND row_num = :row_num AND col_num = :col_num AND (check_status != 'N' OR check_status IS NULL) ORDER BY ridge_id DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute([
            ':farm_id' => $ajaxFarmId,
            ':row_num' => $rowNum,
            ':col_num' => $colNum
        ]);
        $rawRows = $stmt->fetchAll();

        $pesticideTasks = ['가군1', '가군3', '나군3', '가군5', '나군5', '가군7', '나군7'];
        $pestTasks      = ['병해', '충해'];

        $rows = [];
        foreach ($rawRows as $r) {
            $wType = $reverseTaskMap[$r['task_id']] ?? '작업';
            $dbContent = !empty($r['content']) ? trim($r['content']) : '';

            if (in_array($wType, $pesticideTasks)) {
                $displayContent = '[농약 - ' . $wType . ']' . ($dbContent !== '' ? ' ' . $dbContent : '');
            } elseif (in_array($wType, $pestTasks)) {
                $displayContent = '[병충해 - ' . $wType . ']' . ($dbContent !== '' ? ' ' . $dbContent : '');
            } else {
                $displayContent = '[' . $wType . '] ' . ($dbContent !== '' ? $dbContent : '작업완료');
            }

            $rows[] = [
                'work_date' => $r['work_date'],
                'work_type' => $wType,
                'task_id' => $r['task_id'],
                'created_minute' => !empty($r['created_minute']) ? $r['created_minute'] : date('Y-m-d H:i', strtotime($r['work_date'])),
                'content' => $displayContent
            ];
        }

        echo json_encode([
            'success' => true, 
            'data' => $rows,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => '데이터 조회 실패']);
    }
    exit;
}

// 4. AJAX: 동일 년월일시분 이랑 상태 정보 조회
if (isset($_GET['action']) && $_GET['action'] === 'get_batch_ridge_info') {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxFarmId = !empty($_GET['farm_id']) ? intval($_GET['farm_id']) : 0;
    $taskId = !empty($_GET['task_id']) ? intval($_GET['task_id']) : 0;
    $createdMinute = !empty($_GET['created_minute']) ? trim($_GET['created_minute']) : '';

    if ($ajaxFarmId <= 0 || $taskId <= 0 || empty($createdMinute)) {
        echo json_encode(['success' => false, 'message' => '파라미터가 부족합니다.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT row_num, col_num, check_status, content 
            FROM ridge_records 
            WHERE farm_id = :farm_id 
              AND task_id = :task_id 
              AND (
                  DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') = :cm1 
                  OR (created_at IS NULL AND DATE_FORMAT(work_date, '%Y-%m-%d %H:%i') = :cm2)
                  OR (created_at IS NULL AND work_date = :cm3)
              )
        ");
        $stmt->execute([
            ':farm_id' => $ajaxFarmId,
            ':task_id' => $taskId,
            ':cm1' => $createdMinute,
            ':cm2' => $createdMinute,
            ':cm3' => $createdMinute
        ]);
        $records = $stmt->fetchAll();

        $gridData = [];
        $batchContent = '';
        foreach ($records as $idx => $rec) {
            if ($idx === 0) {
                $batchContent = $rec['content'];
            }
            $rNum = $rec['row_num'];
            $cNum = $rec['col_num'];
            $cChar = chr(64 + $cNum);
            $key = "{$rNum}-{$cChar}";
            $gridData[$key] = $rec['check_status'];
        }

        echo json_encode(['success' => true, 'data' => $gridData, 'content' => $batchContent]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => '조회 실패']);
    }
    exit;
}

// 5. AJAX: 동일 년월일시분 이랑 상태 및 작업일자, 내용 일괄 업데이트 (task_id 변경 지원)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_batch_ridge_status') {
    header('Content-Type: application/json; charset=utf-8');
    
    $postFarmId    = !empty($_POST['farm_id']) ? intval($_POST['farm_id']) : 0;
    $taskId        = !empty($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    $newTaskId     = !empty($_POST['new_task_id']) ? intval($_POST['new_task_id']) : $taskId;
    $createdMinute = !empty($_POST['created_minute']) ? trim($_POST['created_minute']) : '';
    $newWorkDate   = !empty($_POST['new_work_date']) ? trim($_POST['new_work_date']) : '';
    $content       = !empty($_POST['content']) ? trim($_POST['content']) : '';
    $ridges        = !empty($_POST['ridges']) && is_array($_POST['ridges']) ? $_POST['ridges'] : [];

    if ($postFarmId <= 0 || $taskId <= 0 || empty($createdMinute)) {
        echo json_encode(['success' => false, 'message' => '필수 파라미터가 누락되었습니다.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $farmStmt = $pdo->prepare("SELECT row_count, col_count FROM farms WHERE farm_id = :farm_id LIMIT 1");
        $farmStmt->execute([':farm_id' => $postFarmId]);
        $farmRes = $farmStmt->fetch();
        
        $fRow = $farmRes ? (int)$farmRes['row_count'] : 4;
        $fCol = $farmRes ? (int)$farmRes['col_count'] : 10;

        $fCols = [];
        for ($i = 0; $i < $fRow; $i++) {
            $fCols[] = chr(65 + $i);
        }

        $setParts = ["check_status = :check_status", "content = :content"];
        if (!empty($newWorkDate)) {
            $setParts[] = "work_date = :new_work_date";
        }
        if (!empty($newTaskId) && $newTaskId !== $taskId) {
            $setParts[] = "task_id = :new_task_id";
        }
        $setClause = implode(', ', $setParts);

        $updateStmt = $pdo->prepare("
            UPDATE ridge_records 
            SET {$setClause}
            WHERE farm_id = :farm_id 
              AND task_id = :task_id 
              AND (
                  DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') = :cm1 
                  OR (created_at IS NULL AND DATE_FORMAT(work_date, '%Y-%m-%d %H:%i') = :cm2)
                  OR (created_at IS NULL AND work_date = :cm3)
              )
              AND row_num = :row_num 
              AND col_num = :col_num
        ");

        for ($r = 1; $r <= $fCol; $r++) {
            foreach ($fCols as $c) {
                $ridgeName = "{$r}-{$c}";
                $checkStatus = in_array($ridgeName, $ridges) ? 'Y' : 'N';
                
                $colNum = ord(strtoupper($c)) - 64;
                if ($colNum < 1) $colNum = 1;

                $params = [
                    ':check_status' => $checkStatus,
                    ':content' => $content,
                    ':farm_id' => $postFarmId,
                    ':task_id' => $taskId,
                    ':cm1' => $createdMinute,
                    ':cm2' => $createdMinute,
                    ':cm3' => $createdMinute,
                    ':row_num' => $r,
                    ':col_num' => $colNum
                ];

                if (!empty($newWorkDate)) {
                    $params[':new_work_date'] = $newWorkDate;
                }
                if (!empty($newTaskId) && $newTaskId !== $taskId) {
                    $params[':new_task_id'] = $newTaskId;
                }

                $updateStmt->execute($params);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => '수정 중 오류 발생: ' . $e->getMessage()]);
    }
    exit;
}

// 6. AJAX: 그리드 박스의 최근 작업 내역 요약 조회
if (isset($_GET['action']) && $_GET['action'] === 'get_all_ridges_latest') {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxFarmId = !empty($_GET['farm_id']) ? intval($_GET['farm_id']) : 0;
    try {
        $stmt = $pdo->prepare("
            SELECT row_num, col_num, task_id, work_date, created_at, check_status 
            FROM ridge_records 
            WHERE farm_id = :farm_id 
            ORDER BY ridge_id DESC
        ");
        $stmt->execute([':farm_id' => $ajaxFarmId]);
        $records = $stmt->fetchAll();
        
        $gridData = [];
        foreach ($records as $rec) {
            if (isset($rec['check_status']) && $rec['check_status'] === 'N') continue;

            $rNum = $rec['row_num'];
            $cNum = $rec['col_num'];
            
            $cCharKey = chr(64 + $cNum);
            $key = "{$rNum}-{$cCharKey}";
            
            $taskName = $reverseTaskMap[$rec['task_id']] ?? '';
            $category = '';
            if ($taskName === '물') $category = '물';
            elseif ($taskName === '수확') $category = '수확';
            elseif ($taskName === '옆순') $category = '옆순';
            elseif (strpos($taskName, '농약') !== false || strpos($taskName, '가군') !== false || strpos($taskName, '나군') !== false) $category = '농약';
            elseif (in_array($taskName, ['병해', '충해'])) $category = '병충해';

            if ($category && !isset($gridData[$key][$category])) {
                $gridData[$key][$category] = $rec['work_date'];
            }
        }

        echo json_encode(['success' => true, 'data' => $gridData]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'data' => []]);
    }
    exit;
}

// 7. AJAX: 메모 페이징 조회 (첨부파일 포함)
if (isset($_GET['action']) && $_GET['action'] === 'get_memos') {
    header('Content-Type: application/json; charset=utf-8');
    $reqAjaxGroupId = !empty($_GET['group_id']) ? trim($_GET['group_id']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    try {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM memoT WHERE group_id = :group_id");
        $countStmt->execute([':group_id' => $reqAjaxGroupId]);
        $totalCount = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($totalCount / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit;
        }

        $stmt = $pdo->prepare("SELECT
                                   m.id,
                                   m.reg_date,
                                   m.content,
                                   (SELECT COUNT(*) FROM {$attachment_table} a WHERE a.att_grp_id = m.id AND a.status = 'ACTIVE') AS file_count
                               FROM memoT m
                               WHERE m.group_id = :group_id
                               ORDER BY m.id DESC
                               LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute([':group_id' => $reqAjaxGroupId]);
        $memosData = $stmt->fetchAll();

        if (!empty($memosData)) {
            $memoIds = array_column($memosData, 'id');
            $inQuery = implode(',', array_fill(0, count($memoIds), '?'));
            $attachSql = "SELECT att_id AS id, att_grp_id, original_name, stored_name, storage_path, mime_type, file_size, extension
                          FROM {$attachment_table}
                          WHERE att_grp_id IN ({$inQuery})
                            AND status = 'ACTIVE'
                          ORDER BY att_id ASC";
            $attachStmt = $pdo->prepare($attachSql);
            $attachStmt->execute($memoIds);
            $attachments = $attachStmt->fetchAll();

            $groupedAttachments = [];
            foreach ($attachments as $att) {
                $groupedAttachments[$att['att_grp_id']][] = $att;
            }

            foreach ($memosData as &$memo) {
                $memo['attachments'] = $groupedAttachments[$memo['id']] ?? [];
            }
            unset($memo);
        }

        echo json_encode([
            'success' => true,
            'data' => $memosData,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => '메모 조회 실패']);
    }
    exit;
}

// ==========================================
// 일반 웹 페이지 렌더링 영역
// ==========================================

$memo_write_max_file_count = $max_file_count;
$memo_write_allowed_extensions = $allowed_extensions;
$memo_write_attachment_table = $attachment_table;
$memo_write_upload_base_dir = __DIR__ . '/uploads';
$memo_write_submit_url = 'farmmngdtl.php';
include_once __DIR__ . '/memo_write_component_popup.php';

$memo_update_max_file_count = $max_file_count;
$memo_update_allowed_extensions = $allowed_extensions;
$memo_update_attachment_table = $attachment_table;
$memo_update_upload_base_dir = __DIR__ . '/uploads';
$memo_update_action_url = 'farmmngdtl.php';
include_once __DIR__ . '/memo_update_component_popup.php';

$reqFarmId  = !empty($_REQUEST['farm_id']) ? intval($_REQUEST['farm_id']) : 0;
$reqGroupId = !empty($_REQUEST['group_id']) ? trim($_REQUEST['group_id']) : '';

if ($reqFarmId > 0) {
    $identifier = $reqFarmId;
} elseif (!empty($reqGroupId)) {
    $identifier = $reqGroupId;
} else {
    $identifier = '상밭';
}

$farmInfo  = getFarmInfo($pdo, $identifier);

$farmId    = ($reqFarmId > 0) ? $reqFarmId : $farmInfo['farm_id']; 
$farmName  = !empty($farmInfo['farm_name']) ? $farmInfo['farm_name'] : (!empty($reqGroupId) ? $reqGroupId : '알 수 없는 밭');
$rowCount  = $farmInfo['row_count'];
$colCount  = $farmInfo['col_count'];
$groupId   = !empty($reqGroupId) ? $reqGroupId : (string)$farmId;

$cols = [];
for ($i = 0; $i < $rowCount; $i++) {
    $cols[] = chr(65 + $i);
}

$initialGridData = [];
try {
    $stmt = $pdo->prepare("
        SELECT row_num, col_num, task_id, work_date, created_at, check_status 
        FROM ridge_records 
        WHERE farm_id = :farm_id 
        ORDER BY ridge_id DESC
    ");
    $stmt->execute([':farm_id' => $farmId]);
    $allRecords = $stmt->fetchAll();

    foreach ($allRecords as $rec) {
        if (isset($rec['check_status']) && $rec['check_status'] === 'N') continue;

        $rNum = $rec['row_num'];
        $cNum = $rec['col_num'];
        
        $cCharKey = chr(64 + $cNum);
        $key = "{$rNum}-{$cCharKey}";
        
        $taskName = $reverseTaskMap[$rec['task_id']] ?? '';
        $category = '';
        if ($taskName === '물') $category = '물';
        elseif ($taskName === '수확') $category = '수확';
        elseif ($taskName === '옆순') $category = '옆순';
        elseif (strpos($taskName, '농약') !== false || strpos($taskName, '가군') !== false || strpos($taskName, '나군') !== false) $category = '농약';
        elseif (in_array($taskName, ['병해', '충해'])) $category = '병충해';

        if ($category && !isset($initialGridData[$key][$category])) {
            $initialGridData[$key][$category] = $rec['work_date'];
        }
    }
} catch (PDOException $e) {}

function getTaskStyleAndText($taskName, $workDate) {
    if (empty($workDate)) {
        return ['text' => '-', 'style' => 'color: #999; background: transparent;'];
    }
    
    $workDateOnly = substr($workDate, 0, 10);
    $targetDate = new DateTime($workDateOnly);
    $today = new DateTime(date('Y-m-d'));
    
    if ($targetDate > $today) {
        $days = 0;
    } else {
        $diff = $today->diff($targetDate);
        $days = $diff->days;
    }

    $text = ($days == 0) ? '오늘' : $days . '일';

    $isActive = false;
    $bgColor = '';
    $textColor = '#333';

    if ($taskName === '물') {
        if ($days <= 3) {
            $bgColor = '#e3f2fd';
            $textColor = '#0d47a1';
            $isActive = true;
        }
    } elseif ($taskName === '수확') {
        if ($days <= 3) {
            $bgColor = '#fffde7';
            $textColor = '#f57f17';
            $isActive = true;
        }
    } elseif ($taskName === '옆순') {
        if ($days > 3) {
            $bgColor = '#e8f5e9';
            $textColor = '#2e7d32';
            $isActive = true;
        }
    } elseif ($taskName === '농약') {
        if ($days <= 3) {
            $bgColor = '#ffe0b2';
            $textColor = '#e65100';
            $isActive = true;
        }
    } elseif ($taskName === '병충해') {
        if ($days <= 3) {
            $bgColor = '#ffebee';
            $textColor = '#c62828';
            $isActive = true;
        }
    }

    if ($isActive) {
        $style = "background-color: {$bgColor}; color: {$textColor}; font-weight: bold;";
    } else {
        $style = "color: #999; background: transparent;";
    }

    return ['text' => $text, 'style' => $style];
}

function getFarmMemoCount($pdo, $groupId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM memoT WHERE group_id = :group_id");
        $stmt->execute([':group_id' => $groupId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

function getFarmMemosPage($pdo, $groupId, $page = 1, $limit = 10) {
    global $attachment_table;
    try {
        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare("SELECT
                                  m.id,
                                  m.reg_date,
                                  m.content,
                                  (SELECT COUNT(*) FROM {$attachment_table} a WHERE a.att_grp_id = m.id AND a.status = 'ACTIVE') AS file_count
                              FROM memoT m
                              WHERE m.group_id = :group_id
                              ORDER BY m.id DESC
                              LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute([':group_id' => $groupId]);
        $memos = $stmt->fetchAll();

        if (!empty($memos)) {
            $memoIds = array_column($memos, 'id');
            $inQuery = implode(',', array_fill(0, count($memoIds), '?'));
            $attachSql = "SELECT att_id AS id, att_grp_id, original_name, stored_name, storage_path, mime_type, file_size, extension
                          FROM {$attachment_table}
                          WHERE att_grp_id IN ({$inQuery})
                            AND status = 'ACTIVE'
                          ORDER BY att_id ASC";
            $attachStmt = $pdo->prepare($attachSql);
            $attachStmt->execute($memoIds);
            $attachments = $attachStmt->fetchAll();

            $groupedAttachments = [];
            foreach ($attachments as $att) {
                $groupedAttachments[$att['att_grp_id']][] = $att;
            }

            foreach ($memos as &$memo) {
                $memo['attachments'] = $groupedAttachments[$memo['id']] ?? [];
            }
            unset($memo);
        }

        return $memos;
    } catch (PDOException $e) {
        return [];
    }
}

function getFarmDetailData($pdo, $category) {
    try {
        $stmt = $pdo->prepare("SELECT summary, content FROM farmT WHERE category = :category ORDER BY id DESC LIMIT 1");
        $stmt->execute([':category' => $category]);
        return $stmt->fetch();
    } catch (PDOException $e) { return null; }
}

$memoTotalCount = getFarmMemoCount($pdo, $groupId);
$memoTotalPages = ceil($memoTotalCount / 10);
$memos = getFarmMemosPage($pdo, $groupId, 1, 10);

$farmData = getFarmDetailData($pdo, $category ?? ''); 

$icons = ['상밭' => '🌱', '중밭' => '🌿', '겨울밭' => '❄️'];
$icon = $icons[$farmName] ?? '🌾';
$bgColor = !empty($farmData['summary']) ? htmlspecialchars($farmData['summary']) : '#f9f9f9';
$todayDate = date('Y-m-d');
?>

<style>
.dtl-container { display: flex; flex-direction: column; gap: 12px; padding: 12px; box-sizing: border-box; min-height: 100%; max-width: 600px; margin: 0 auto; padding-bottom: 30px; }
.dtl-header { position: sticky; top: 0; z-index: 1; display: flex; flex-direction: column; background-color: <?php echo $bgColor; ?>; padding: 12px 16px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); gap: 10px; }
.dtl-header-top { display: flex; align-items: center; justify-content: space-between; width: 100%; }
.dtl-title { font-size: 1.1rem; font-weight: bold; color: #333; white-space: nowrap; }

.filter-btn-group { display: flex; gap: 8px; align-items: center; justify-content: center; width: 100%; }
.filter-btn {
    background: #ffffff;
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    line-height: 1;
    opacity: 0.5;
    outline: none;
    user-select: none;
}
.filter-btn:hover { opacity: 0.8; }
.filter-btn.active {
    background-color: #e8f5e9;
    border-color: #4CAF50;
    box-shadow: 0 0 5px rgba(76, 175, 80, 0.6);
    opacity: 1;
    transform: scale(1.08);
}

.btn-back { text-decoration: none; color: #555; font-size: 0.9rem; font-weight: bold; background: rgba(255, 255, 255, 0.8); padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; white-space: nowrap; }
.dtl-info-section { background-color: #ffffff; padding: 14px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #eee; }
.status-btn-group { display: flex; justify-content: space-around; gap: 8px; margin-bottom: 14px; }
.status-btn { flex: 1; padding: 10px 0; font-size: 0.95rem; font-weight: bold; color: #333; background-color: #f5f5f5; border: 1px solid #ddd; border-radius: 6px; cursor: pointer; text-align: center; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 4px; }
.status-btn:hover { background-color: #e8f5e9; border-color: #4CAF50; color: #2e7d32; }
.status-btn:active { transform: scale(0.97); }

.main-grid-container { display: grid; grid-template-columns: repeat(<?php echo $rowCount > 0 ? $rowCount : 4; ?>, 1fr); gap: 6px; background-color: #fafafa; padding: 10px; border-radius: 6px; border: 1px solid #e0e0e0; align-items: start; }
.main-grid-box { min-height: auto; height: fit-content; background-color: #ffffff; border: 1px solid #cccccc; border-radius: 4px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding: 4px; cursor: pointer; transition: background-color 0.2s ease, border-color 0.2s ease; box-shadow: inset 0 1px 2px rgba(0,0,0,0.03); }

.grid-box-title { font-weight: bold; font-size: 0.8rem; color: #333; margin-bottom: 2px; border-bottom: 1px solid #eee; width: 100%; text-align: center; }
.grid-sub-lines { display: flex; flex-direction: column; width: 100%; font-size: 0.68rem; gap: 1px; }
.sub-line-item { display: flex; justify-content: space-between; padding: 0 2px; border-radius: 2px; }

.dtl-memo-section { background-color: #ffffff; border-radius: 8px; padding: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); border: 1px solid #eee; height: 380px; display: flex; flex-direction: column; box-sizing: border-box; flex-shrink: 0; }
.dtl-memo-title { font-size: 0.95rem; font-weight: bold; color: #444; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 6px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.btn-memo-icon { cursor: pointer; font-size: 1.1rem; padding: 2px 4px; border-radius: 4px; transition: background-color 0.2s; user-select: none; }
.btn-memo-icon:hover { background-color: rgba(0, 0, 0, 0.08); }
.memo-pagination { display: flex; align-items: center; gap: 4px; }
.memo-pagination button { padding: 3px 6px; font-size: 0.75rem; border: 1px solid #ddd; background-color: #fff; color: #333; border-radius: 4px; cursor: pointer; }
.memo-pagination button.active { background-color: #4CAF50; color: white; border-color: #4CAF50; font-weight: bold; }
.memo-pagination button:disabled { color: #ccc; border-color: #eee; cursor: default; }
.memo-list-dtl { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; flex: 1; overflow-y: auto; }
.memo-item-dtl { display: flex; align-items: center; gap: 8px; height: 32px; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; box-sizing: border-box; cursor: pointer; transition: background-color 0.2s; padding: 0 4px; border-radius: 4px; }
.memo-item-dtl:hover { background-color: #f1f8e9; }
.memo-item-dtl:last-child { border-bottom: none; }
.memo-date-dtl { font-size: 0.75rem; font-weight: bold; color: #777; white-space: nowrap; flex-shrink: 0; width: 80px; }
.memo-text-dtl { color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
.icon-clip { font-size: 0.9rem; line-height: 1; user-select: none; flex-shrink: 0; }
.file-input-wrapper { display: flex; flex-direction: column; gap: 4px; font-size: 0.82rem; color: #666; margin-top: 8px; }
.file-input-wrapper input[type="file"] { border: 1px solid #ccc; padding: 6px; border-radius: 4px; background-color: #f9f9f9; font-size: 0.85rem; }
.selected-files-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.selected-file-badge { display: inline-flex; align-items: center; background: #f1f8e9; padding: 4px 8px; border-radius: 4px; border: 1px solid #c5e1a5; color: #2e7d32; font-size: 0.82rem; }
.selected-file-badge .remove-badge { margin-left: 6px; cursor: pointer; font-weight: bold; color: #d32f2f; font-size: 0.95rem; line-height: 1; padding: 2px 4px; user-select: none; }
.modal-file-list { padding-top: 4px; font-size: 0.85rem; }
.file-item { display: inline-flex; align-items: center; background: #f1f8e9; padding: 4px 8px; margin: 2px 4px 2px 0; border-radius: 4px; border: 1px solid #c5e1a5; color: #2e7d32; word-break: break-all; }
.file-item-name { cursor: pointer; }
.file-item-name:hover { text-decoration: underline; }
.image-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); justify-content: center; align-items: center; z-index: 2000; }
.image-modal-content { position: relative; display: flex; flex-direction: column; align-items: center; max-width: 90vw; max-height: 90vh; }
.image-modal-content img { max-width: 100%; max-height: 90vh; object-fit: contain; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
.image-modal-close { position: absolute; top: -40px; right: 0; color: #fff; font-size: 32px; font-weight: bold; cursor: pointer; line-height: 1; padding: 5px; }
.btn-memo-delete { cursor: pointer; font-size: 0.85rem; padding: 2px 4px; border-radius: 4px; margin-left: 4px; flex-shrink: 0; transition: background-color 0.2s; }
.btn-memo-delete:hover { background-color: rgba(255, 0, 0, 0.15); }
.no-memo { color: #888; font-size: 0.85rem; text-align: center; margin-top: 20px; }
.grid-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }

#historyBatchModal, #pesticideHistoryBatchModal, #pestHistoryBatchModal, #editMemoModal { z-index: 1100; }

.grid-modal-content { background-color: white; padding: 16px; border-radius: 8px; width: 90%; max-width: 420px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); display: flex; flex-direction: column; gap: 12px; box-sizing: border-box; }
.grid-modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 8px; }
.grid-modal-header h3 { margin: 0; font-size: 1rem; color: #333; display: flex; align-items: center; gap: 6px; }
.pesticide-select { padding: 4px 8px; font-size: 0.88rem; font-weight: bold; border-radius: 4px; border: 1px solid #4CAF50; color: #2e7d32; background-color: #f1f8e9; outline: none; cursor: pointer; }
.date-input-row { display: flex; align-items: center; gap: 8px; font-size: 0.88rem; font-weight: bold; color: #333; background: #f9f9f9; padding: 6px 10px; border-radius: 4px; border: 1px solid #e0e0e0; }
.date-input-row input[type="date"] { padding: 4px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.88rem; outline: none; }

.content-input-row { display: flex; flex-direction: column; gap: 4px; font-size: 0.88rem; font-weight: bold; color: #333; background: #f9f9f9; padding: 6px 10px; border-radius: 4px; border: 1px solid #e0e0e0; margin-bottom: 8px; }
.content-input-row textarea { padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.88rem; outline: none; resize: vertical; min-height: 60px; font-family: inherit; }

.status-grid-table { width: 100%; border-collapse: collapse; text-align: center; }
.status-grid-table th, .status-grid-table td { border: 1px solid #ddd; padding: 8px 4px; font-size: 0.88rem; }
.status-grid-table th { background-color: #e8f5e9; font-weight: bold; color: #2e7d32; cursor: pointer; user-select: none; }
.status-grid-table th:hover, .status-grid-table td.row-label:hover { background-color: #c8e6c9; }
.status-grid-table td.row-label { background-color: #f1f8e9; font-weight: bold; color: #33691e; width: 18%; cursor: pointer; user-select: none; }
.status-grid-table input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
.grid-btn-group { display: flex; justify-content: space-between; align-items: center; margin-top: 4px; }
.btn-grid-save { padding: 8px 16px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
.btn-grid-close { padding: 8px 14px; background-color: #666; color: white; border: none; border-radius: 4px; cursor: pointer; }
.ridge-info-list { list-style: none; padding: 0; margin: 0; height: 280px; overflow-y: auto; }
.ridge-info-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 4px; border-bottom: 1px solid #f0f0f0; font-size: 0.85rem; cursor: pointer; transition: background-color 0.2s; }
.ridge-info-item:hover { background-color: #f1f8e9; }
.ridge-info-item:last-child { border-bottom: none; }
.ridge-info-date { font-weight: bold; color: #4CAF50; font-size: 0.8rem; white-space: nowrap; }
.ridge-info-text { color: #333; text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; margin-left: 10px; }
.ridge-pagination { display: flex; justify-content: center; align-items: center; gap: 4px; padding-top: 6px; border-top: 1px solid #eee; }
.ridge-pagination button { padding: 4px 8px; font-size: 0.8rem; border: 1px solid #ddd; background-color: #fff; color: #333; border-radius: 4px; cursor: pointer; }
.ridge-pagination button.active { background-color: #4CAF50; color: white; border-color: #4CAF50; font-weight: bold; }
.ridge-pagination button:disabled { color: #ccc; border-color: #eee; cursor: default; }
</style>

<div class="dtl-container">
    <div class="dtl-header">
        <div class="dtl-header-top">
            <span class="dtl-title"><?php echo $icon . ' ' . htmlspecialchars($farmName); ?>밭 상세정보</span>
			<button type="button" 
				class="btn-back" 
				onclick="if (typeof loadPage === 'function') { loadPage('farmmng.php', '밭 관리'); } else { location.href = 'farmmng.php'; }" 
				title="돌아가기">
				↩
			</button>
		</div>
        
        <div class="filter-btn-group">
            <button type="button" class="filter-btn" data-filter="물" onclick="toggleFilter(this)" title="물">💧</button>
            <button type="button" class="filter-btn" data-filter="수확" onclick="toggleFilter(this)" title="수확">🌾</button>
            <button type="button" class="filter-btn" data-filter="옆순" onclick="toggleFilter(this)" title="옆순">✂️</button>
            <button type="button" class="filter-btn" data-filter="농약" onclick="toggleFilter(this)" title="농약">💊</button>
            <button type="button" class="filter-btn" data-filter="병충해" onclick="toggleFilter(this)" title="병충해">🐛</button>
        </div>
    </div>

    <div class="dtl-info-section">
        <div class="status-btn-group">
            <button type="button" class="status-btn" onclick="openStatusGridModal('물')">💧</button>
            <button type="button" class="status-btn" onclick="openStatusGridModal('수확')">🌾</button>
            <button type="button" class="status-btn" onclick="openStatusGridModal('옆순')">✂️</button>
            <button type="button" class="status-btn" onclick="openPesticideGridModal()">💊</button>
            <button type="button" class="status-btn" onclick="openPestGridModal()">🐛</button>
        </div>

        <div class="main-grid-container" id="mainGridContainer">
            <?php 
            for ($r = 1; $r <= $colCount; $r++) {
                foreach ($cols as $c) {
                    $ridgeTitle = "{$r} - {$c}";
                    $ridgeKey = "{$r}-{$c}"; 
                    $colNum = ord(strtoupper($c)) - 64;
                    
                    $wWater   = isset($initialGridData[$ridgeKey]['물']) ? getTaskStyleAndText('물', $initialGridData[$ridgeKey]['물']) : ['text' => '-', 'style' => 'color: #999; background: transparent;'];
                    $wHarvest = isset($initialGridData[$ridgeKey]['수확']) ? getTaskStyleAndText('수확', $initialGridData[$ridgeKey]['수확']) : ['text' => '-', 'style' => 'color: #999; background: transparent;'];
                    $wSide    = isset($initialGridData[$ridgeKey]['옆순']) ? getTaskStyleAndText('옆순', $initialGridData[$ridgeKey]['옆순']) : ['text' => '-', 'style' => 'color: #999; background: transparent;'];
                    $wPest    = isset($initialGridData[$ridgeKey]['농약']) ? getTaskStyleAndText('농약', $initialGridData[$ridgeKey]['농약']) : ['text' => '-', 'style' => 'color: #999; background: transparent;'];
                    $wPestDisease = isset($initialGridData[$ridgeKey]['병충해']) ? getTaskStyleAndText('병충해', $initialGridData[$ridgeKey]['병충해']) : ['text' => '-', 'style' => 'color: #999; background: transparent;'];

                    echo "
                    <div class='main-grid-box' onclick=\"openRidgeInfoModal('{$ridgeTitle}', 1, {$r}, {$colNum})\">
                        <div class='grid-box-title'>{$ridgeTitle}</div>
                        <div class='grid-sub-lines' id='box-lines-{$r}-{$c}'>
                            <div class='sub-line-item' data-task='물' style='{$wWater['style']}'><span>물</span><span>{$wWater['text']}</span></div>
                            <div class='sub-line-item' data-task='수확' style='{$wHarvest['style']}'><span>수확</span><span>{$wHarvest['text']}</span></div>
                            <div class='sub-line-item' data-task='옆순' style='{$wSide['style']}'><span>옆순</span><span>{$wSide['text']}</span></div>
                            <div class='sub-line-item' data-task='농약' style='{$wPest['style']}'><span>농약</span><span>{$wPest['text']}</span></div>
                            <div class='sub-line-item' data-task='병충해' style='{$wPestDisease['style']}'><span>병충해</span><span>{$wPestDisease['text']}</span></div>
                        </div>
                    </div>";
                }
            }
            ?>
        </div>
    </div>

    <div class="dtl-memo-section">
        <div class="dtl-memo-title">
            <div style="display: flex; align-items: center; gap: 4px;">
                <span>메모</span>
                <span class="btn-memo-icon" title="새 메모 작성" onclick="openMemoWriteModal('<?php echo htmlspecialchars($groupId, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($farmName, ENT_QUOTES, 'UTF-8'); ?>')">📝</span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; justify-content: flex-end;">
                <div class="memo-pagination" id="memoPagination"></div>
                <span style="font-size: 0.75rem; color: #888; font-weight: normal; white-space: nowrap;" id="memoTotalCountSpan">총 <?php echo $memoTotalCount; ?>건</span>
            </div>
        </div>
        
        <ul class="memo-list-dtl" id="memoListContainer">
            <?php if (empty($memos)): ?>
                <li class="no-memo">기록된 메모가 없습니다.</li>
            <?php else: ?>
                <?php foreach ($memos as $m): ?>
                    <?php $formattedDate = date('m-d H:i', strtotime($m['reg_date'])); ?>
                    <li class="memo-item-dtl"
                        data-memo-item="true"
                        data-id="<?php echo (int)$m['id']; ?>"
                        data-raw-group-id="<?php echo htmlspecialchars($groupId, ENT_QUOTES, 'UTF-8'); ?>"
                        data-group-id="<?php echo htmlspecialchars($groupId, ENT_QUOTES, 'UTF-8'); ?>"
                        data-display-group-id="<?php echo htmlspecialchars($farmName, ENT_QUOTES, 'UTF-8'); ?>"
                        data-date="<?php echo htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8'); ?>"
                        data-content="<?php echo htmlspecialchars($m['content'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-files="<?php echo htmlspecialchars(json_encode($m['attachments'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                        onclick="openModalFromElem(this)">

                        <span class="memo-date-dtl">[<?php echo htmlspecialchars($formattedDate); ?>]</span>
                        <span class="memo-text-dtl" title="<?php echo htmlspecialchars($m['content'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($m['content']); ?>
                        </span>
                        <?php if ((int)($m['file_count'] ?? 0) > 0): ?>
                            <span class="icon-clip" title="첨부파일 <?php echo (int)$m['file_count']; ?>개">📎</span>
                        <?php endif; ?>
                        <span class="btn-memo-delete" title="삭제" onclick="event.stopPropagation(); deleteDtlMemo(<?php echo (int)$m['id']; ?>)">🗑️</span>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<div id="statusGridModal" class="grid-modal-overlay">
    <div class="grid-modal-content">
        <div class="grid-modal-header">
            <h3 id="gridModalTitle">상태 입력</h3>
            <span style="cursor:pointer; font-size: 1.2rem;" onclick="closeStatusGridModal()">✖</span>
        </div>
        <div class="date-input-row">
            <label for="statusWorkDate">📅 작업일자:</label>
            <input type="date" id="statusWorkDate" value="<?php echo $todayDate; ?>" max="<?php echo $todayDate; ?>">
        </div>
        <div class="content-input-row">
            <label for="statusContent">📝 내용 (선택):</label>
            <textarea id="statusContent" placeholder="작업 내용을 입력하세요"></textarea>
        </div>
        <table class="status-grid-table">
            <thead>
                <tr>
                    <th onclick="toggleAllGridCheckboxes('statusGridModal')">전체</th>
                    <?php foreach ($cols as $c): ?>
                        <th onclick="toggleColCheckboxes('statusGridModal', '<?php echo $c; ?>')"><?php echo $c; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($r = 1; $r <= $colCount; $r++): ?>
                    <tr>
                        <td class="row-label" onclick="toggleRowCheckboxes('statusGridModal', <?php echo $r; ?>)"><?php echo $r; ?></td>
                        <?php foreach ($cols as $c): ?>
                            <td><input type="checkbox" class="grid-chk row-<?php echo $r; ?> col-<?php echo $c; ?>" name="grid_chk[]" value="<?php echo $r . '-' . $c; ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <div class="grid-btn-group" style="justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn-grid-save" onclick="saveStatusGrid('normal')">저장</button>
            <button type="button" class="btn-grid-close" onclick="closeStatusGridModal()">닫기</button>
        </div>
    </div>
</div>

<div id="pesticideGridModal" class="grid-modal-overlay">
    <div class="grid-modal-content">
        <div class="grid-modal-header">
            <h3>
                <span id="pesticideModalTitle">상태 입력</span>
                <select id="pesticideSelect" class="pesticide-select">
                    <option value="가군1">가군1</option>
                    <option value="가군3">가군3</option>
                    <option value="나군3">나군3</option>
                    <option value="가군5">가군5</option>
                    <option value="나군5">나군5</option>
                    <option value="가군7">가군7</option>
                    <option value="나군7">나군7</option>
                </select>
            </h3>
            <span style="cursor:pointer; font-size: 1.2rem;" onclick="closePesticideGridModal()">✖</span>
        </div>
        <div class="date-input-row">
            <label for="pesticideWorkDate">📅 작업일자:</label>
            <input type="date" id="pesticideWorkDate" value="<?php echo $todayDate; ?>" max="<?php echo $todayDate; ?>">
        </div>
        <div class="content-input-row">
            <label for="pesticideContent">📝 내용 (선택):</label>
            <textarea id="pesticideContent" placeholder="농약 관련 내용을 입력하세요"></textarea>
        </div>
        <table class="status-grid-table">
            <thead>
                <tr>
                    <th onclick="toggleAllGridCheckboxes('pesticideGridModal')">전체</th>
                    <?php foreach ($cols as $c): ?>
                        <th onclick="toggleColCheckboxes('pesticideGridModal', '<?php echo $c; ?>')"><?php echo $c; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($r = 1; $r <= $colCount; $r++): ?>
                    <tr>
                        <td class="row-label" onclick="toggleRowCheckboxes('pesticideGridModal', <?php echo $r; ?>)"><?php echo $r; ?></td>
                        <?php foreach ($cols as $c): ?>
                            <td><input type="checkbox" class="grid-chk row-<?php echo $r; ?> col-<?php echo $c; ?>" name="grid_chk[]" value="<?php echo $r . '-' . $c; ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <div class="grid-btn-group" style="justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn-grid-save" onclick="saveStatusGrid('pesticide')">저장</button>
            <button type="button" class="btn-grid-close" onclick="closePesticideGridModal()">닫기</button>
        </div>
    </div>
</div>

<div id="pestGridModal" class="grid-modal-overlay">
    <div class="grid-modal-content">
        <div class="grid-modal-header">
            <h3>
                <span id="pestModalTitle">상태 입력</span>
                <select id="pestSelect" class="pesticide-select">
                    <option value="병해">병해</option>
                    <option value="충해">충해</option>
                </select>
            </h3>
            <span style="cursor:pointer; font-size: 1.2rem;" onclick="closePestGridModal()">✖</span>
        </div>
        <div class="date-input-row">
            <label for="pestWorkDate">📅 작업일자:</label>
            <input type="date" id="pestWorkDate" value="<?php echo $todayDate; ?>" max="<?php echo $todayDate; ?>">
        </div>
        <div class="content-input-row">
            <label for="pestContent">📝 내용 (선택):</label>
            <textarea id="pestContent" placeholder="병충해 관련 내용을 입력하세요"></textarea>
        </div>
        <table class="status-grid-table">
            <thead>
                <tr>
                    <th onclick="toggleAllGridCheckboxes('pestGridModal')">전체</th>
                    <?php foreach ($cols as $c): ?>
                        <th onclick="toggleColCheckboxes('pestGridModal', '<?php echo $c; ?>')"><?php echo $c; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($r = 1; $r <= $colCount; $r++): ?>
                    <tr>
                        <td class="row-label" onclick="toggleRowCheckboxes('pestGridModal', <?php echo $r; ?>)"><?php echo $r; ?></td>
                        <?php foreach ($cols as $c): ?>
                            <td><input type="checkbox" class="grid-chk row-<?php echo $r; ?> col-<?php echo $c; ?>" name="grid_chk[]" value="<?php echo $r . '-' . $c; ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <div class="grid-btn-group" style="justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn-grid-save" onclick="saveStatusGrid('pest')">저장</button>
            <button type="button" class="btn-grid-close" onclick="closePestGridModal()">닫기</button>
        </div>
    </div>
</div>

<div id="historyBatchModal" class="grid-modal-overlay">
    <div class="grid-modal-content">
        <div class="grid-modal-header">
            <h3 id="historyBatchTitle">동일 일시 이랑 상태 수정</h3>
            <span style="cursor:pointer; font-size: 1.2rem;" onclick="closeHistoryBatchModal()">✖</span>
        </div>
        <div class="date-input-row">
            <label for="historyBatchDate">📅 작업일자 수정:</label>
            <input type="date" id="historyBatchDate" value="<?php echo $todayDate; ?>" max="<?php echo $todayDate; ?>">
        </div>
        <div class="content-input-row">
            <label for="historyBatchContent">📝 내용 수정:</label>
            <textarea id="historyBatchContent" placeholder="수정할 내용을 입력하세요"></textarea>
        </div>
        <table class="status-grid-table">
            <thead>
                <tr>
                    <th onclick="toggleAllGridCheckboxes('historyBatchModal')">전체</th>
                    <?php foreach ($cols as $c): ?>
                        <th onclick="toggleColCheckboxes('historyBatchModal', '<?php echo $c; ?>')"><?php echo $c; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($r = 1; $r <= $colCount; $r++): ?>
                    <tr>
                        <td class="row-label" onclick="toggleRowCheckboxes('historyBatchModal', <?php echo $r; ?>)"><?php echo $r; ?></td>
                        <?php foreach ($cols as $c): ?>
                            <td><input type="checkbox" class="grid-chk row-<?php echo $r; ?> col-<?php echo $c; ?>" name="grid_chk[]" value="<?php echo $r . '-' . $c; ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <div class="grid-btn-group" style="justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn-grid-save" onclick="saveHistoryBatchGrid()">저장</button>
            <button type="button" class="btn-grid-close" onclick="closeHistoryBatchModal()">닫기</button>
        </div>
    </div>
</div>

<div id="pesticideHistoryBatchModal" class="grid-modal-overlay">
    <div class="grid-modal-content">
        <div class="grid-modal-header">
            <h3>
                <span id="pesticideHistoryBatchTitle">농약 이랑 상태 수정</span>
                <select id="pesticideHistorySelect" class="pesticide-select">
                    <option value="가군1">가군1</option>
                    <option value="가군3">가군3</option>
                    <option value="나군3">나군3</option>
                    <option value="가군5">가군5</option>
                    <option value="나군5">나군5</option>
                    <option value="가군7">가군7</option>
                    <option value="나군7">나군7</option>
                </select>
            </h3>
            <span style="cursor:pointer; font-size: 1.2rem;" onclick="closePesticideHistoryBatchModal()">✖</span>
        </div>
        <div class="date-input-row">
            <label for="pesticideHistoryBatchDate">📅 작업일자 수정:</label>
            <input type="date" id="pesticideHistoryBatchDate" value="<?php echo $todayDate; ?>" max="<?php echo $todayDate; ?>">
        </div>
        <div class="content-input-row">
            <label for="pesticideHistoryBatchContent">📝 내용 수정:</label>
            <textarea id="pesticideHistoryBatchContent" placeholder="수정할 내용을 입력하세요"></textarea>
        </div>
        <table class="status-grid-table">
            <thead>
                <tr>
                    <th onclick="toggleAllGridCheckboxes('pesticideHistoryBatchModal')">전체</th>
                    <?php foreach ($cols as $c): ?>
                        <th onclick="toggleColCheckboxes('pesticideHistoryBatchModal', '<?php echo $c; ?>')"><?php echo $c; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($r = 1; $r <= $colCount; $r++): ?>
                    <tr>
                        <td class="row-label" onclick="toggleRowCheckboxes('pesticideHistoryBatchModal', <?php echo $r; ?>)"><?php echo $r; ?></td>
                        <?php foreach ($cols as $c): ?>
                            <td><input type="checkbox" class="grid-chk row-<?php echo $r; ?> col-<?php echo $c; ?>" name="grid_chk[]" value="<?php echo $r . '-' . $c; ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <div class="grid-btn-group" style="justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn-grid-save" onclick="savePesticideHistoryBatchGrid()">저장</button>
            <button type="button" class="btn-grid-close" onclick="closePesticideHistoryBatchModal()">닫기</button>
        </div>
    </div>
</div>

<div id="pestHistoryBatchModal" class="grid-modal-overlay">
    <div class="grid-modal-content">
        <div class="grid-modal-header">
            <h3>
                <span id="pestHistoryBatchTitle">병충해 이랑 상태 수정</span>
                <select id="pestHistorySelect" class="pesticide-select">
                    <option value="병해">병해</option>
                    <option value="충해">충해</option>
                </select>
            </h3>
            <span style="cursor:pointer; font-size: 1.2rem;" onclick="closePestHistoryBatchModal()">✖</span>
        </div>
        <div class="date-input-row">
            <label for="pestHistoryBatchDate">📅 작업일자 수정:</label>
            <input type="date" id="pestHistoryBatchDate" value="<?php echo $todayDate; ?>" max="<?php echo $todayDate; ?>">
        </div>
        <div class="content-input-row">
            <label for="pestHistoryBatchContent">📝 내용 수정:</label>
            <textarea id="pestHistoryBatchContent" placeholder="수정할 내용을 입력하세요"></textarea>
        </div>
        <table class="status-grid-table">
            <thead>
                <tr>
                    <th onclick="toggleAllGridCheckboxes('pestHistoryBatchModal')">전체</th>
                    <?php foreach ($cols as $c): ?>
                        <th onclick="toggleColCheckboxes('pestHistoryBatchModal', '<?php echo $c; ?>')"><?php echo $c; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($r = 1; $r <= $colCount; $r++): ?>
                    <tr>
                        <td class="row-label" onclick="toggleRowCheckboxes('pestHistoryBatchModal', <?php echo $r; ?>)"><?php echo $r; ?></td>
                        <?php foreach ($cols as $c): ?>
                            <td><input type="checkbox" class="grid-chk row-<?php echo $r; ?> col-<?php echo $c; ?>" name="grid_chk[]" value="<?php echo $r . '-' . $c; ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        <div class="grid-btn-group" style="justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn-grid-save" onclick="savePestHistoryBatchGrid()">저장</button>
            <button type="button" class="btn-grid-close" onclick="closePestHistoryBatchModal()">닫기</button>
        </div>
    </div>
</div>

<div id="ridgeInfoModal" class="grid-modal-overlay">
    <div class="grid-modal-content">
        <div class="grid-modal-header">
            <h3 id="ridgeInfoTitle">이랑 정보 조회</h3>
            <span style="cursor:pointer; font-size: 1.2rem;" onclick="closeRidgeInfoModal()">✖</span>
        </div>
        <ul class="ridge-info-list" id="ridgeInfoList">
            <li class="no-memo">조회 중...</li>
        </ul>
        <div class="ridge-pagination" id="ridgePagination"></div>
        <div class="grid-btn-group">
            <span></span>
            <button type="button" class="btn-grid-close" onclick="closeRidgeInfoModal()">닫기</button>
        </div>
    </div>
</div>

<script>
    // 동적 페이지 재로드(SPA) 환경 시 재선언 오류(SyntaxError)를 방지하기 위해 var로 변경 및 전역 스코프 갱신
    var currentStatusType = '';
    var currentRidgeTitle = '';
    var currentRidgeRowNum = 0;
    var currentRidgeColNum = 0;
    var currentMemoPage = 1;

    var currentBatchTaskId = 0;
    var currentBatchCreatedMinute = '';

    var currentPesticideBatchTaskId = 0;
    var currentPesticideBatchCreatedMinute = '';

    var currentPestBatchTaskId = 0;
    var currentPestBatchCreatedMinute = '';

    var farmId = <?php echo (int)$farmId; ?>;
    var groupId = <?php echo json_encode($groupId, JSON_UNESCAPED_UNICODE); ?>;
    var farmName = <?php echo json_encode($farmName, JSON_UNESCAPED_UNICODE); ?>;
    var memoAjaxUrl = <?php echo json_encode($_SERVER['SCRIPT_NAME'], JSON_UNESCAPED_UNICODE); ?>;

    window.memoWriteSuccessCallback = function(result) {
        currentMemoPage = 1;
        loadMemos(1);
    };

    window.memoUpdateSuccessCallback = function(result) {
        const page = result && result.page ? parseInt(result.page, 10) : currentMemoPage;
        currentMemoPage = page > 0 ? page : currentMemoPage;
        loadMemos(currentMemoPage);
    };

    function getTodayString() {
        const today = new Date();
        const yr = today.getFullYear();
        const mo = ('0' + (today.getMonth() + 1)).slice(-2);
        const da = ('0' + today.getDate()).slice(-2);
        return `${yr}-${mo}-${da}`;
    }

    function toggleFilter(btn) {
        btn.classList.toggle('active');
        applyFilters();
    }
	
    function applyFilters() {
        const activeBtns = document.querySelectorAll('.filter-btn.active');
        const activeTasks = Array.from(activeBtns).map(b => b.getAttribute('data-filter'));

        const allSubLines = document.querySelectorAll('.main-grid-box .sub-line-item');
        allSubLines.forEach(item => {
            const task = item.getAttribute('data-task');
            if (activeTasks.length === 0) {
                item.style.display = 'flex';
            } else {
                item.style.display = activeTasks.includes(task) ? 'flex' : 'none';
            }
        });
    }

    function loadMemos(page) {
        fetch(`${memoAjaxUrl}?action=get_memos&farm_id=${farmId}&group_id=${encodeURIComponent(groupId)}&page=${page}`)
        .then(res => res.json())
        .then(res => {
            if(res.success) {
                currentMemoPage = res.page;
                renderMemos(res.data);
                renderMemoPagination(res.page, res.totalPages, res.totalCount);
            } else {
                console.error(res.message || '메모 조회 실패');
            }
        })
        .catch(err => console.error(err));
    }

    function renderMemos(memos) {
        const list = document.getElementById('memoListContainer');
        list.innerHTML = '';

        if(memos.length === 0) {
            list.innerHTML = '<li class="no-memo">기록된 메모가 없습니다.</li>';
            return;
        }

        memos.forEach(m => {
            let d = new Date(m.reg_date.replace(/-/g, '/'));
            let formattedDate = ('0' + (d.getMonth() + 1)).slice(-2) + '-' +
                                ('0' + d.getDate()).slice(-2) + ' ' +
                                ('0' + d.getHours()).slice(-2) + ':' +
                                ('0' + d.getMinutes()).slice(-2);

            let li = document.createElement('li');
            li.className = 'memo-item-dtl';
            li.setAttribute('data-memo-item', 'true');
            li.setAttribute('data-id', m.id);
            li.setAttribute('data-raw-group-id', groupId);
            li.setAttribute('data-group-id', groupId);
            li.setAttribute('data-display-group-id', farmName);
            li.setAttribute('data-date', formattedDate);
            li.setAttribute('data-content', m.content || '');
            li.setAttribute('data-files', JSON.stringify(m.attachments || []));
            li.onclick = function() {
                if (typeof window.openModalFromElem === 'function') {
                    window.openModalFromElem(this);
                }
            };

            const clipHtml = Number(m.file_count || 0) > 0
                ? `<span class="icon-clip" title="첨부파일 ${Number(m.file_count)}개">📎</span>`
                : '';

            li.innerHTML = `
                <span class="memo-date-dtl">[${formattedDate}]</span>
                <span class="memo-text-dtl" title="${escapeHtml(m.content)}">${escapeHtml(m.content)}</span>
                ${clipHtml}
                <span class="btn-memo-delete" title="삭제" onclick="event.stopPropagation(); deleteDtlMemo(${m.id})">🗑️</span>
            `;
            list.appendChild(li);
        });
    }

    function renderMemoPagination(currentPage, totalPages, totalCount) {
        document.getElementById('memoTotalCountSpan').innerText = `총 ${totalCount}건`;
        const pagination = document.getElementById('memoPagination');
        pagination.innerHTML = '';
        if (totalPages <= 1) return;

        let maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        let prevBtn = document.createElement('button');
        prevBtn.innerText = '<';
        prevBtn.disabled = (currentPage === 1);
        prevBtn.onclick = () => loadMemos(currentPage - 1);
        pagination.appendChild(prevBtn);

        for (let i = startPage; i <= endPage; i++) {
            let pBtn = document.createElement('button');
            pBtn.innerText = i;
            if (i === currentPage) pBtn.className = 'active';
            pBtn.onclick = () => loadMemos(i);
            pagination.appendChild(pBtn);
        }

        let nextBtn = document.createElement('button');
        nextBtn.innerText = '>';
        nextBtn.disabled = (currentPage === totalPages);
        nextBtn.onclick = () => loadMemos(currentPage + 1);
        pagination.appendChild(nextBtn);
    }

    function deleteDtlMemo(id) {
        if (!confirm('해당 메모와 첨부파일을 모두 삭제하시겠습니까?')) return;

        fetch(memoAjaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete_memo&id=${id}&farm_id=${farmId}&group_id=${encodeURIComponent(groupId)}`
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                loadMemos(currentMemoPage);
            } else {
                alert(res.message || '삭제 실패');
            }
        })
        .catch(err => alert('오류 발생'));
    }

    function escapeHtml(text) {
        if (!text) return '';
        let map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function uncheckAllGrids(modalId) {
        document.querySelectorAll(`#${modalId} .grid-chk`).forEach(el => el.checked = false);
    }
    
    function toggleAllGridCheckboxes(modalId) {
        let checkboxes = document.querySelectorAll(`#${modalId} .grid-chk`);
        let allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }

    function toggleRowCheckboxes(modalId, rowNum) {
        let checkboxes = document.querySelectorAll(`#${modalId} .grid-chk.row-${rowNum}`);
        let allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }

    function toggleColCheckboxes(modalId, colChar) {
        let checkboxes = document.querySelectorAll(`#${modalId} .grid-chk.col-${colChar}`);
        let allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }

    function openStatusGridModal(type) {
        currentStatusType = type;
        uncheckAllGrids('statusGridModal');
        document.getElementById('statusWorkDate').value = getTodayString();
        document.getElementById('statusContent').value = '';
        document.getElementById('gridModalTitle').innerText = `${type} 상태 입력`;
        document.getElementById('statusGridModal').style.display = 'flex';
    }
    function closeStatusGridModal() { document.getElementById('statusGridModal').style.display = 'none'; }

    function openPesticideGridModal() {
        uncheckAllGrids('pesticideGridModal');
        document.getElementById('pesticideWorkDate').value = getTodayString();
        document.getElementById('pesticideSelect').selectedIndex = 0;
        document.getElementById('pesticideContent').value = '';
        document.getElementById('pesticideGridModal').style.display = 'flex';
    }
    function closePesticideGridModal() { document.getElementById('pesticideGridModal').style.display = 'none'; }

    function openPestGridModal() {
        uncheckAllGrids('pestGridModal');
        document.getElementById('pestWorkDate').value = getTodayString();
        document.getElementById('pestSelect').selectedIndex = 0;
        document.getElementById('pestContent').value = '';
        document.getElementById('pestGridModal').style.display = 'flex';
    }
    function closePestGridModal() { document.getElementById('pestGridModal').style.display = 'none'; }

    function saveStatusGrid(gridType) {
        let modalId, workType, dateInputId, contentInputId;
        if (gridType === 'normal') {
            modalId = 'statusGridModal';
            workType = currentStatusType;
            dateInputId = 'statusWorkDate';
            contentInputId = 'statusContent';
        } else if (gridType === 'pesticide') {
            modalId = 'pesticideGridModal';
            workType = document.getElementById('pesticideSelect').value;
            dateInputId = 'pesticideWorkDate';
            contentInputId = 'pesticideContent';
        } else if (gridType === 'pest') {
            modalId = 'pestGridModal';
            workType = document.getElementById('pestSelect').value;
            dateInputId = 'pestWorkDate';
            contentInputId = 'pestContent';
        }

        const workDate = document.getElementById(dateInputId).value;
        const contentVal = document.getElementById(contentInputId).value;
        
        if (!workDate) {
            alert('작업일자를 입력해주세요.');
            return;
        }
        if (workDate > getTodayString()) {
            alert('작업일자는 오늘 이전 날짜만 선택할 수 있습니다.');
            return;
        }

        const allBoxes = document.querySelectorAll(`#${modalId} .grid-chk`);
        if (allBoxes.length === 0) {
            alert('저장할 데이터가 없습니다.');
            return;
        }

        const checkedBoxes = document.querySelectorAll(`#${modalId} .grid-chk:checked`);
        const ridgeList = Array.from(checkedBoxes).map(cb => cb.value);

        if (!confirm(`${workType} 작업(${workDate})을 저장하시겠습니까? (체크된 곳은 Y, 체크 안 된 곳은 N으로 전체 반영됩니다)`)) return;

        const formData = new URLSearchParams();
        formData.append('action', 'save_ridge_records');
        formData.append('farm_id', farmId);
        formData.append('work_type', workType);
        formData.append('work_date', workDate);
        formData.append('content', contentVal);
        ridgeList.forEach(r => formData.append('ridges[]', r));

        fetch('farmmngdtl.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert('저장되었습니다.');
                if (gridType === 'normal') closeStatusGridModal();
                else if (gridType === 'pesticide') closePesticideGridModal();
                else if (gridType === 'pest') closePestGridModal();
                
                refreshAllRidgesLatest();
            } else {
                alert(res.message || '저장 실패');
            }
        })
        .catch(err => {
            alert('통신 오류가 발생했습니다.');
        });
    }

    function getTaskStyleAndTextJS(taskName, dateStr) {
        if (!dateStr) {
            return { text: '-', style: 'color: #999; background: transparent;' };
        }
        
        let dateOnly = dateStr.substring(0, 10);
        let target = new Date(dateOnly.replace(/-/g, '/'));
        target.setHours(0, 0, 0, 0);
        let now = new Date();
        now.setHours(0, 0, 0, 0);
        
        let diffMs = now - target;
        let days = 0;
        
        if (diffMs > 0) {
            days = Math.floor(diffMs / 86400000);
        }
        
        let text = (days === 0) ? '오늘' : days + '일';
        
        let isActive = false;
        let bgColor = '';
        let textColor = '#333';
        
        if (taskName === '물') {
            if (days <= 3) { bgColor = '#e3f2fd'; textColor = '#0d47a1'; isActive = true; }
        } else if (taskName === '수확') {
            if (days <= 3) { bgColor = '#fffde7'; textColor = '#f57f17'; isActive = true; }
        } else if (taskName === '옆순') {
            if (days > 3) { bgColor = '#e8f5e9'; textColor = '#2e7d32'; isActive = true; }
        } else if (taskName === '농약') {
            if (days <= 3) { bgColor = '#ffe0b2'; textColor = '#e65100'; isActive = true; }
        } else if (taskName === '병충해') {
            if (days <= 3) { bgColor = '#ffebee'; textColor = '#c62828'; isActive = true; }
        }

        let style = isActive ? `background-color: ${bgColor}; color: ${textColor}; font-weight: bold;` : 'color: #999; background: transparent;';
        return { text: text, style: style };
    }

    function refreshAllRidgesLatest() {
        fetch(`${memoAjaxUrl}?action=get_all_ridges_latest&farm_id=${farmId}`)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                const gridData = res.data;
                const tasks = ['물', '수확', '옆순', '농약', '병충해'];
                for (let r = 1; r <= <?php echo $colCount; ?>; r++) {
                    const cols = <?php echo json_encode($cols); ?>;
                    cols.forEach(c => {
                        const key = `${r}-${c}`;
                        const boxLines = document.getElementById(`box-lines-${r}-${c}`);
                        if (!boxLines) return;
                        
                        tasks.forEach(task => {
                            const lineItem = boxLines.querySelector(`[data-task="${task}"]`);
                            if (lineItem) {
                                const dateStr = (gridData[key] && gridData[key][task]) ? gridData[key][task] : '';
                                const st = getTaskStyleAndTextJS(task, dateStr);
                                lineItem.setAttribute('style', st.style);
                                lineItem.innerHTML = `<span>${task}</span><span>${st.text}</span>`;
                            }
                        });
                    });
                }
                applyFilters();
            }
        })
        .catch(err => console.error(err));
    }

    function openRidgeInfoModal(title, page, rowNum, colNum) {
        currentRidgeTitle = title;
        currentRidgeRowNum = rowNum;
        currentRidgeColNum = colNum;
        
        document.getElementById('ridgeInfoTitle').innerText = `${title} 작업 이력`;
        document.getElementById('ridgeInfoModal').style.display = 'flex';
        
        loadRidgeInfoPage(page);
    }

    function closeRidgeInfoModal() {
        document.getElementById('ridgeInfoModal').style.display = 'none';
    }

    function loadRidgeInfoPage(page) {
        fetch(`${memoAjaxUrl}?action=get_ridge_info&farm_id=${farmId}&row_num=${currentRidgeRowNum}&col_num=${currentRidgeColNum}&page=${page}`)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                renderRidgeInfoList(res.data);
                renderRidgePagination(res.page, res.totalPages);
            } else {
                document.getElementById('ridgeInfoList').innerHTML = `<li class="no-memo">${escapeHtml(res.message || '조회 실패')}</li>`;
                document.getElementById('ridgePagination').innerHTML = '';
            }
        })
        .catch(err => {
            document.getElementById('ridgeInfoList').innerHTML = '<li class="no-memo">조회 중 오류가 발생했습니다.</li>';
        });
    }

    function renderRidgeInfoList(data) {
        const list = document.getElementById('ridgeInfoList');
        list.innerHTML = '';
        if (!data || data.length === 0) {
            list.innerHTML = '<li class="no-memo">작업 이력이 없습니다.</li>';
            return;
        }

        data.forEach(item => {
            let li = document.createElement('li');
            li.className = 'ridge-info-item';
            
            li.onclick = function() {
                openBatchModalByTask(item.task_id, item.created_minute, item.work_type);
            };

            li.innerHTML = `
                <span class="ridge-info-date">[${escapeHtml(item.created_minute)}]</span>
                <span class="ridge-info-text" title="${escapeHtml(item.content)}">${escapeHtml(item.content)}</span>
            `;
            list.appendChild(li);
        });
    }

    function renderRidgePagination(currentPage, totalPages) {
        const pagination = document.getElementById('ridgePagination');
        pagination.innerHTML = '';
        if (totalPages <= 1) return;

        let prevBtn = document.createElement('button');
        prevBtn.innerText = '<';
        prevBtn.disabled = (currentPage === 1);
        prevBtn.onclick = () => loadRidgeInfoPage(currentPage - 1);
        pagination.appendChild(prevBtn);

        for (let i = 1; i <= totalPages; i++) {
            let pBtn = document.createElement('button');
            pBtn.innerText = i;
            if (i === currentPage) pBtn.className = 'active';
            pBtn.onclick = () => loadRidgeInfoPage(i);
            pagination.appendChild(pBtn);
        }

        let nextBtn = document.createElement('button');
        nextBtn.innerText = '>';
        nextBtn.disabled = (currentPage === totalPages);
        nextBtn.onclick = () => loadRidgeInfoPage(currentPage + 1);
        pagination.appendChild(nextBtn);
    }

    function openBatchModalByTask(taskId, createdMinute, workType) {
        const pesticideTasks = [40, 41, 42, 43, 44, 45, 46];
        const pestTasks = [70, 71];

        if (pesticideTasks.includes(Number(taskId))) {
            openPesticideHistoryBatchModal(taskId, createdMinute, workType);
        } else if (pestTasks.includes(Number(taskId))) {
            openPestHistoryBatchModal(taskId, createdMinute, workType);
        } else {
            openNormalHistoryBatchModal(taskId, createdMinute, workType);
        }
    }

    function openNormalHistoryBatchModal(taskId, createdMinute, workType) {
        currentBatchTaskId = taskId;
        currentBatchCreatedMinute = createdMinute;

        uncheckAllGrids('historyBatchModal');
        document.getElementById('historyBatchTitle').innerText = `${workType} (${createdMinute}) 이랑 상태 수정`;
        document.getElementById('historyBatchDate').value = createdMinute.substring(0, 10);
        
        fetch(`${memoAjaxUrl}?action=get_batch_ridge_info&farm_id=${farmId}&task_id=${taskId}&created_minute=${encodeURIComponent(createdMinute)}`)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                document.getElementById('historyBatchContent').value = res.content || '';
                for (let key in res.data) {
                    if (res.data[key] === 'Y') {
                        let chk = document.querySelector(`#historyBatchModal .grid-chk[value="${key}"]`);
                        if (chk) chk.checked = true;
                    }
                }
            }
            document.getElementById('historyBatchModal').style.display = 'flex';
        });
    }

    function closeHistoryBatchModal() {
        document.getElementById('historyBatchModal').style.display = 'none';
    }

    function saveHistoryBatchGrid() {
        saveBatchGridCommon('historyBatchModal', currentBatchTaskId, currentBatchCreatedMinute, 'historyBatchDate', 'historyBatchContent', closeHistoryBatchModal);
    }

    function openPesticideHistoryBatchModal(taskId, createdMinute, workType) {
        currentPesticideBatchTaskId = taskId;
        currentPesticideBatchCreatedMinute = createdMinute;

        uncheckAllGrids('pesticideHistoryBatchModal');
        document.getElementById('pesticideHistoryBatchTitle').innerText = `농약 이랑 상태 수정`;
        document.getElementById('pesticideHistorySelect').value = workType;
        document.getElementById('pesticideHistoryBatchDate').value = createdMinute.substring(0, 10);

        fetch(`${memoAjaxUrl}?action=get_batch_ridge_info&farm_id=${farmId}&task_id=${taskId}&created_minute=${encodeURIComponent(createdMinute)}`)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                document.getElementById('pesticideHistoryBatchContent').value = res.content || '';
                for (let key in res.data) {
                    if (res.data[key] === 'Y') {
                        let chk = document.querySelector(`#pesticideHistoryBatchModal .grid-chk[value="${key}"]`);
                        if (chk) chk.checked = true;
                    }
                }
            }
            document.getElementById('pesticideHistoryBatchModal').style.display = 'flex';
        });
    }

    function closePesticideHistoryBatchModal() {
        document.getElementById('pesticideHistoryBatchModal').style.display = 'none';
    }

    function savePesticideHistoryBatchGrid() {
        const selectedWorkType = document.getElementById('pesticideHistorySelect').value;
        const pesticideTaskMap = {
            '가군1': 40, '가군3': 41, '나군3': 42, '가군5': 43, '나군5': 44, '가군7': 45, '나군7': 46
        };
        const newTaskId = pesticideTaskMap[selectedWorkType] || currentPesticideBatchTaskId;
        saveBatchGridCommon('pesticideHistoryBatchModal', currentPesticideBatchTaskId, currentPesticideBatchCreatedMinute, 'pesticideHistoryBatchDate', 'pesticideHistoryBatchContent', closePesticideHistoryBatchModal, newTaskId);
    }

    function openPestHistoryBatchModal(taskId, createdMinute, workType) {
        currentPestBatchTaskId = taskId;
        currentPestBatchCreatedMinute = createdMinute;

        uncheckAllGrids('pestHistoryBatchModal');
        document.getElementById('pestHistoryBatchTitle').innerText = `병충해 이랑 상태 수정`;
        document.getElementById('pestHistorySelect').value = workType;
        document.getElementById('pestHistoryBatchDate').value = createdMinute.substring(0, 10);

        fetch(`${memoAjaxUrl}?action=get_batch_ridge_info&farm_id=${farmId}&task_id=${taskId}&created_minute=${encodeURIComponent(createdMinute)}`)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                document.getElementById('pestHistoryBatchContent').value = res.content || '';
                for (let key in res.data) {
                    if (res.data[key] === 'Y') {
                        let chk = document.querySelector(`#pestHistoryBatchModal .grid-chk[value="${key}"]`);
                        if (chk) chk.checked = true;
                    }
                }
            }
            document.getElementById('pestHistoryBatchModal').style.display = 'flex';
        });
    }

    function closePestHistoryBatchModal() {
        document.getElementById('pestHistoryBatchModal').style.display = 'none';
    }

    function savePestHistoryBatchGrid() {
        const selectedWorkType = document.getElementById('pestHistorySelect').value;
        const pestTaskMap = { '병해': 70, '충해': 71 };
        const newTaskId = pestTaskMap[selectedWorkType] || currentPestBatchTaskId;
        saveBatchGridCommon('pestHistoryBatchModal', currentPestBatchTaskId, currentPestBatchCreatedMinute, 'pestHistoryBatchDate', 'pestHistoryBatchContent', closePestHistoryBatchModal, newTaskId);
    }

    function saveBatchGridCommon(modalId, taskId, createdMinute, dateInputId, contentInputId, closeCallback, newTaskId) {
        const newWorkDate = document.getElementById(dateInputId).value;
        const contentVal = document.getElementById(contentInputId).value;

        if (!newWorkDate) {
            alert('작업일자를 입력해주세요.');
            return;
        }

        const checkedBoxes = document.querySelectorAll(`#${modalId} .grid-chk:checked`);
        const ridgeList = Array.from(checkedBoxes).map(cb => cb.value);

        if (!confirm('수정 내용을 저장하시겠습니까?')) return;

        const formData = new URLSearchParams();
        formData.append('action', 'update_batch_ridge_status');
        formData.append('farm_id', farmId);
        formData.append('task_id', taskId);
        if (newTaskId && newTaskId !== taskId) {
            formData.append('new_task_id', newTaskId);
        }
        formData.append('created_minute', createdMinute);
        formData.append('new_work_date', newWorkDate);
        formData.append('content', contentVal);
        ridgeList.forEach(r => formData.append('ridges[]', r));

        fetch('farmmngdtl.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert('수정되었습니다.');
                if (typeof closeCallback === 'function') closeCallback();
                if (document.getElementById('ridgeInfoModal').style.display === 'flex') {
                    loadRidgeInfoPage(1);
                }
                refreshAllRidgesLatest();
            } else {
                alert(res.message || '수정 실패');
            }
        })
        .catch(err => alert('통신 오류가 발생했습니다.'));
    }
</script>