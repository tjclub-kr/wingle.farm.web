<?php
// DB 접속 정보 설정
require_once __DIR__ . '/db_connect.php';

// --------------------------------------------------
// 첨부파일 설정 변수
// --------------------------------------------------
$max_file_count = 5; // 최대 업로드 가능 파일 개수
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'hwp']; // 허용 확장자
$attachment_table = 'attachment'; // 실제 DB 테이블명 적용 (attachment)

// post_max_size 초과 시 POST/FILES 데이터가 유실되는 현상 예외 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code(400);
    echo "업로드 허용 용량(post_max_size)을 초과하였습니다.";
    exit;
}

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user,$db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    echo "<div style='color:red; text-align:center;'>데이터베이스 연결 실패: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit;
}

// --------------------------------------------------
// 공용 신규 메모 작성 컴포넌트 연결
// --------------------------------------------------
$memo_write_max_file_count = $max_file_count;
$memo_write_allowed_extensions = $allowed_extensions;
$memo_write_attachment_table = $attachment_table;
$memo_write_upload_base_dir = __DIR__ . '/uploads';
$memo_write_submit_url = 'memo.php';
$memo_write_render_mode = 'inline';
$memo_write_inline_group_id = 'memo';
$memo_write_inline_title = '새 메모 작성';

include_once __DIR__ . '/memo_write_component.php';

// --------------------------------------------------
// 공용 메모 수정 팝업 컴포넌트 설정
// --------------------------------------------------
$memo_update_max_file_count = $max_file_count;
$memo_update_allowed_extensions = $allowed_extensions;
$memo_update_attachment_table = $attachment_table;
$memo_update_upload_base_dir = __DIR__ . '/uploads';
$memo_update_action_url = 'memo.php';

include_once __DIR__ . '/memo_update_component_popup.php';

if (!function_exists('renderMemoWriteButton')) {
    http_response_code(500);
    echo 'memo_write_component.php를 불러오지 못했습니다.';
    exit;
}

// 4. 메모 삭제 처리 (첨부파일 상태 DELETED로 변경)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/plain; charset=utf-8');

    $deleteId = (int)$_GET['id'];
    $pdo->beginTransaction();
    try {
        $stmtFile = $pdo->prepare("UPDATE {$attachment_table} SET status = 'DELETED' WHERE att_grp_id = :id");
        $stmtFile->execute([':id' => $deleteId]);

        $stmt = $pdo->prepare("DELETE FROM memoT WHERE id = :id");
        $stmt->execute([':id' => $deleteId]);

        $pdo->commit();
        echo "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo "삭제 처리 실패";
    }
    exit;
}

// 5. 페이징 설정 및 목록 조회
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$memos = [];
$totalCount = 0;
$totalPages = 1;

try {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM memoT");
    $totalCount = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalCount / $limit));

    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $sql = "SELECT m.id, 
                   m.group_id AS raw_group_id,
                   CASE WHEN m.group_id = 'memo' THEN 'memo' ELSE COALESCE(f.farm_name, m.group_id) END AS display_group_id, 
                   m.reg_date, 
                   m.content,
                   (SELECT COUNT(*) FROM {$attachment_table} a WHERE a.att_grp_id = m.id AND a.status = 'ACTIVE') AS file_count
            FROM memoT m 
            LEFT JOIN farms f ON m.group_id = f.farm_id 
            ORDER BY m.id DESC 
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $memos = $stmt->fetchAll();

    // 가져온 메모들에 해당하는 첨부파일 목록 일괄 조회
    if (!empty($memos)) {
        $memoIds = array_column($memos, 'id');
        $inQuery = implode(',', array_fill(0, count($memoIds), '?'));
        $attachSql = "SELECT att_id AS id, att_grp_id, original_name, storage_path, extension 
                      FROM {$attachment_table} 
                      WHERE att_grp_id IN ({$inQuery}) AND status = 'ACTIVE'";
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

} catch (PDOException $e) {
    // 쿼리 실패 처리
}

$blockSize = 5;
$currentBlock = ceil($page / $blockSize);
$startPage = ($currentBlock - 1) * $blockSize + 1;
$endPage = min($startPage + $blockSize - 1, $totalPages);
?>

<style>
    /* 하단 여백(padding-bottom: 20px) 추가 및 높이 제한 제거 */
    .memo-container { display: flex; flex-direction: column; width: 100%; gap: 15px; box-sizing: border-box; padding-bottom: 20px; }
    .memo-top-section { background-color: #ffffff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 10px; }
    .memo-top-section h3 { font-size: 1rem; color: #333; margin: 0; }
    .memo-form-row { display: flex; flex-direction: column; gap: 4px; }
    .memo-form-row input[type="date"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem; box-sizing: border-box; }
    .memo-form-row textarea { width: 100%; height: 120px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; resize: vertical; font-size: 0.9rem; line-height: 1.4; box-sizing: border-box; }
    .file-input-wrapper { display: flex; flex-direction: column; gap: 4px; font-size: 0.82rem; color: #666; margin-top: 5px; }
    .file-input-wrapper input[type="file"] { border: 1px solid #ccc; padding: 6px; border-radius: 4px; background-color: #f9f9f9; font-size: 0.85rem; }
    
    .selected-files-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .selected-file-badge { display: inline-flex; align-items: center; background: #f1f8e9; padding: 4px 8px; border-radius: 4px; border: 1px solid #c5e1a5; color: #2e7d32; font-size: 0.82rem; }
    .selected-file-badge .remove-badge { margin-left: 6px; cursor: pointer; font-weight: bold; color: #d32f2f; font-size: 0.95rem; line-height: 1; padding: 2px 4px; display: inline-block; user-select: none; }
    .selected-file-badge .remove-badge:hover { color: #b71c1c; background-color: rgba(211, 47, 47, 0.1); border-radius: 3px; }

    .btn-save { padding: 8px 16px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; align-self: flex-end; }
    .btn-save:disabled { background-color: #9e9e9e; cursor: not-allowed; }
    
    .memo-bottom-section { width: 100%; box-sizing: border-box; }
    .memo-bottom-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .memo-bottom-header h3 { font-size: 1rem; color: #333; margin: 0; }
    .memo-pagination { display: flex; gap: 4px; align-items: center; }
    .memo-pagination button { padding: 4px 8px; font-size: 0.85rem; border: 1px solid #ddd; background-color: #fff; color: #333; border-radius: 4px; cursor: pointer; }
    .memo-pagination button:hover:not(:disabled) { background-color: #f1f8e9; }
    .memo-pagination button.active { background-color: #4CAF50; color: white; border-color: #4CAF50; font-weight: bold; }
    .memo-pagination button:disabled { color: #ccc; border-color: #eee; cursor: default; }
    
    /* max-height 제한 및 overflow-y 속성 제거 */
    .memo-list-wrapper { border-top: 1px solid #e0e0e0; border-bottom: 1px solid #e0e0e0; background-color: #fff; }
    .memo-list { display: flex; flex-direction: column; width: 100%; padding: 0; margin: 0; list-style: none; }
    .memo-item { background-color: transparent; padding: 8px 4px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; cursor: pointer; width: 100%; box-sizing: border-box; }
    .memo-item:last-child { border-bottom: none; }
    .memo-item:hover { background-color: #f8f9fa; }
    
    .memo-info { display: flex; gap: 4px; align-items: center; flex: 1; min-width: 0; }
    .memo-group { font-size: 0.82rem; font-weight: bold; color: #2e7d32; white-space: nowrap; flex-shrink: 0; width: 52px; overflow: hidden; text-overflow: ellipsis; display: inline-block; }
    .memo-date { font-size: 0.82rem; font-weight: bold; color: #777; white-space: nowrap; flex-shrink: 0; }
    .memo-preview { font-size: 0.88rem; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; margin-left: 2px; }
    
    .memo-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; padding-right: 5px; }
    .icon-clip { font-size: 0.95rem; line-height: 1; cursor: default; user-select: none; }
    .btn-delete { background: none; border: none; font-size: 1rem; cursor: pointer; padding: 1px 3px; flex-shrink: 0; line-height: 1; opacity: 0.7; }
    .btn-delete:hover { opacity: 1; transform: scale(1.1); }

    /* 상세 팝업 스타일은 memo_update_component_popup.php에서 관리 */
</style>

<div class="memo-container">
    <!-- 신규 메모 작성 영역은 memo_write_component.php의 inline 모드로 표시 -->
    <!-- 메모 목록 영역 -->
    <div class="memo-bottom-section">
        <div class="memo-bottom-header">
            <h3>메모 목록 (최근순)</h3>
            <div class="memo-pagination">
                <button onclick="goToPage(1)" <?php echo ($page <= 1) ? 'disabled' : ''; ?>> &lt;&lt; </button>
                <button onclick="goToPage(<?php echo max(1, $page - 1); ?>)" <?php echo ($page <= 1) ? 'disabled' : ''; ?>> &lt; </button>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <button onclick="goToPage(<?php echo $i; ?>)" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></button>
                <?php endfor; ?>
                
                <button onclick="goToPage(<?php echo min($totalPages, $page + 1); ?>)" <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>> &gt; </button>
                <button onclick="goToPage(<?php echo $totalPages; ?>)" <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>> &gt;&gt; </button>
            </div>
        </div>

        <div class="memo-list-wrapper">
            <?php if (empty($memos)): ?>
                <p style="color: #888; font-size: 0.9rem; padding: 15px;">등록된 메모가 없습니다.</p>
            <?php else: ?>
                <ul class="memo-list">
                    <?php foreach ($memos as $memo): ?>
                        <?php 
                            $formattedDate = date('Y-m-d H:i', strtotime($memo['reg_date']));
                            $hasFile = ($memo['file_count'] > 0);
                        ?>
                        <li class="memo-item"
                            data-id="<?php echo $memo['id']; ?>"
                            data-raw-group-id="<?php echo htmlspecialchars($memo['raw_group_id'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-display-group-id="<?php echo htmlspecialchars($memo['display_group_id'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-date="<?php echo htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8'); ?>"
                            data-content="<?php echo htmlspecialchars($memo['content'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-files="<?php echo htmlspecialchars(json_encode($memo['attachments'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                            onclick="openModalFromElem(this)">
                            <div class="memo-info">
                                <span class="memo-group" title="<?php echo htmlspecialchars($memo['display_group_id']); ?>">
                                    [<?php echo htmlspecialchars($memo['display_group_id']); ?>]
                                </span>
                                <span class="memo-date">[<?php echo htmlspecialchars($formattedDate); ?>]</span>
                                <span class="memo-preview"><?php echo htmlspecialchars($memo['content']); ?></span>
                            </div>
                            <div class="memo-actions">
                                <?php if ($hasFile): ?>
                                    <span class="icon-clip" title="첨부파일 <?php echo $memo['file_count']; ?>개">📎</span>
                                <?php endif; ?>
                                <button class="btn-delete" title="삭제" onclick="event.stopPropagation(); deleteMemo(<?php echo $memo['id']; ?>);">🗑️</button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 메모 수정 팝업 공용 컴포넌트 -->
<?php include_once __DIR__ . '/memo_update_component_popup.php'; ?>

<script>
(function() {
    const currentPage = <?php echo $page; ?>;
    
    const currentFileUrl = 'memo.php';

    // memo_write_component.php 저장 성공 후 memo2 목록 갱신
    window.memoWriteSuccessCallback = function() {
        window.goToPage(1);
    };

    // 메모 수정 팝업 저장 완료 후 현재 보고 있던 메모관리 화면만 갱신
    // 페이지 이동 없이 동일 페이지 번호를 유지하면서 최신 내용을 다시 조회합니다.
    window.memoUpdateSuccessCallback = function(result) {
        const page = result && result.page ? result.page : currentPage;
        window.goToPage(page);
    };

    window.goToPage = function(page) {
        const targetUrl = currentFileUrl + '?page=' + page;
        if (typeof window.loadPage === 'function') {
            window.loadPage(targetUrl, '메모장');
        } else {
            window.location.href = targetUrl;
        }
    };

    window.deleteMemo = function(id) {
        if (confirm('정말로 이 메모를 삭제할까?')) {
            const deleteUrl = currentFileUrl + '?action=delete&id=' + id;
            fetch(deleteUrl)
                .then(async res => {
                    const rawText = await res.text();
                    const resText = rawText.trim();
                    if (res.ok && (resText === 'success' || resText.endsWith('success'))) {
                        window.goToPage(currentPage);
                    } else {
                        const cleanMsg = rawText.replace(/<[^>]*>?/gm, '').trim();
                        throw new Error(cleanMsg || '삭제 중 오류가 발생했어.');
                    }
                })
                .catch(err => alert(err.message));
        }
    };

})();
</script>