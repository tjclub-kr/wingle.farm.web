<?php
/**
 * 공용 메모 수정 팝업 컴포넌트
 *
 * 역할:
 * - 메모 상세/수정 팝업 UI
 * - 기존 첨부파일 표시 및 삭제
 * - 수정 시 새 첨부파일 추가 선택
 * - 이미지 미리보기
 * - action=edit 서버 처리
 * - action=delete_file 서버 처리
 *
 * 사용:
 *   $memo_update_max_file_count = 5;
 *   $memo_update_allowed_extensions = [...];
 *   $memo_update_action_url = 'memo2.php';
 *   include_once __DIR__ . '/memo_update_component_popup.php';
 */

$memo_update_max_file_count = $memo_update_max_file_count ?? ($max_file_count ?? 5);
$memo_update_allowed_extensions = $memo_update_allowed_extensions ?? ($allowed_extensions ?? [
    'jpg', 'jpeg', 'png', 'gif',
    'pdf', 'doc', 'docx',
    'xls', 'xlsx', 'zip', 'txt', 'hwp'
]);
$memo_update_action_url = $memo_update_action_url ?? 'memo2.php';
$memo_update_attachment_table = $memo_update_attachment_table ?? ($attachment_table ?? 'attachment');
$memo_update_upload_base_dir = $memo_update_upload_base_dir ?? (__DIR__ . '/uploads');
$memo_update_current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// --------------------------------------------------
// 메모 수정 서버 처리
// 이 컴포넌트가 action=edit 요청을 직접 처리합니다.
// --------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'edit'
) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/plain; charset=utf-8');

    $editId = (int)($_POST['id'] ?? 0);
    $editGroupId = trim((string)($_POST['group_id'] ?? ''));
    $editContent = trim((string)($_POST['content'] ?? ''));

    if ($editId <= 0 || $editGroupId === '' || $editContent === '') {
        http_response_code(400);
        echo '수정 데이터가 부정확합니다.';
        exit;
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        echo 'DB 연결 객체($pdo)가 준비되지 않았습니다.';
        exit;
    }

    $movedFiles = [];

    try {
        $pdo->beginTransaction();

        // 1. 메모 내용 수정
        $stmt = $pdo->prepare(
            "UPDATE memoT
             SET content = :content
             WHERE id = :id
               AND group_id = :group_id"
        );

        $stmt->execute([
            ':content'  => $editContent,
            ':id'       => $editId,
            ':group_id' => $editGroupId
        ]);

        // 2. 신규 첨부파일 추가
        if (
            isset($_FILES['files']['name']) &&
            is_array($_FILES['files']['name'])
        ) {
            $fileCount = count($_FILES['files']['name']);
            $validFileCount = 0;

            foreach ($_FILES['files']['name'] as $i => $name) {
                $error = (int)($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($error !== UPLOAD_ERR_NO_FILE) {
                    $validFileCount++;
                }
            }

            if ($validFileCount > 0) {
                // 기존 활성 첨부파일 수
                $cntStmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM {$memo_update_attachment_table}
                     WHERE att_grp_id = :id
                       AND status = 'ACTIVE'"
                );
                $cntStmt->execute([':id' => $editId]);
                $currentFileCount = (int)$cntStmt->fetchColumn();

                if (($currentFileCount + $validFileCount) > $memo_update_max_file_count) {
                    throw new RuntimeException(
                        "파일은 최대 {$memo_update_max_file_count}개까지만 유지할 수 있습니다."
                    );
                }

                $dateFolder = date('Ymd');
                $uploadSubDir = 'uploads/' . $dateFolder;
                $targetDir = rtrim($memo_update_upload_base_dir, '/\\') . DIRECTORY_SEPARATOR . $dateFolder;

                if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                    throw new RuntimeException('업로드 디렉토리 생성에 실패했습니다.');
                }

                $attachStmt = $pdo->prepare(
                    "INSERT INTO {$memo_update_attachment_table}
                    (
                        att_grp_id,
                        original_name,
                        stored_name,
                        storage_type,
                        storage_path,
                        mime_type,
                        file_size,
                        extension,
                        checksum,
                        status
                    ) VALUES (
                        :att_grp_id,
                        :original_name,
                        :stored_name,
                        'LOCAL',
                        :storage_path,
                        :mime_type,
                        :file_size,
                        :extension,
                        :checksum,
                        'ACTIVE'
                    )"
                );

                for ($i = 0; $i < $fileCount; $i++) {
                    $error = (int)($_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);

                    if ($error === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($error !== UPLOAD_ERR_OK) {
                        throw new RuntimeException(
                            '파일 업로드 오류가 발생했습니다. 오류 코드: ' . $error
                        );
                    }

                    $tmpName = (string)($_FILES['files']['tmp_name'][$i] ?? '');
                    $origName = (string)($_FILES['files']['name'][$i] ?? '');
                    $fileSize = (int)($_FILES['files']['size'][$i] ?? 0);

                    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                        throw new RuntimeException(
                            '정상적인 업로드 파일이 아닙니다: ' . $origName
                        );
                    }

                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                    if (
                        $ext === '' ||
                        !in_array($ext, $memo_update_allowed_extensions, true)
                    ) {
                        throw new RuntimeException(
                            "허용되지 않는 파일 확장자입니다: .{$ext}"
                        );
                    }

                    $mimeType = (string)($_FILES['files']['type'][$i] ?? '');
                    if ($mimeType === '' && function_exists('mime_content_type')) {
                        $mimeType = (string)mime_content_type($tmpName);
                    }
                    if ($mimeType === '') {
                        $mimeType = 'application/octet-stream';
                    }

                    $storedName =
                        date('YmdHis') . '_' .
                        bin2hex(random_bytes(4)) . '.' .
                        $ext;

                    $storagePath = $uploadSubDir . '/' . $storedName;
                    $fullPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $storedName;

                    if (!move_uploaded_file($tmpName, $fullPath)) {
                        throw new RuntimeException(
                            '파일 저장 중 오류가 발생했습니다: ' . $origName
                        );
                    }

                    $movedFiles[] = $fullPath;

                    $attachStmt->execute([
                        ':att_grp_id'    => $editId,
                        ':original_name' => $origName,
                        ':stored_name'   => $storedName,
                        ':storage_path'  => $storagePath,
                        ':mime_type'     => $mimeType,
                        ':file_size'     => $fileSize,
                        ':extension'     => $ext,
                        ':checksum'     => hash_file('sha256', $fullPath)
                    ]);
                }
            }
        }

        $pdo->commit();
        echo 'success';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        foreach ($movedFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        http_response_code(400);
        echo $e->getMessage();
    }

    exit;
}

// --------------------------------------------------
// 단일 첨부파일 삭제 처리
// 수정 팝업 컴포넌트에서 직접 처리합니다.
// --------------------------------------------------
if (
    isset($_GET['action']) &&
    $_GET['action'] === 'delete_file' &&
    isset($_GET['file_id'])
) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/plain; charset=utf-8');

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        echo 'DB 연결 객체($pdo)가 준비되지 않았습니다.';
        exit;
    }

    $fileId = (int)$_GET['file_id'];

    if ($fileId <= 0) {
        http_response_code(400);
        echo '잘못된 파일 ID입니다.';
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT storage_path
             FROM {$memo_update_attachment_table}
             WHERE att_id = :id"
        );
        $stmt->execute([':id' => $fileId]);
        $fileInfo = $stmt->fetch();

        if (!$fileInfo) {
            http_response_code(400);
            echo '존재하지 않는 파일입니다.';
            exit;
        }

        $storagePath = (string)$fileInfo['storage_path'];
        $fullPath = __DIR__ . '/' . ltrim($storagePath, '/\\');

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }

        $delStmt = $pdo->prepare(
            "DELETE FROM {$memo_update_attachment_table}
             WHERE att_id = :id"
        );
        $delStmt->execute([':id' => $fileId]);

        echo 'success';
    } catch (Throwable $e) {
        http_response_code(400);
        echo '파일 삭제 실패: ' . $e->getMessage();
    }

    exit;
}
?>

<style>
    /* 메모 수정 팝업 */
    .memo-update-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .memo-update-modal-content {
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        width: 85%;
        max-width: 380px;
        max-height: 85vh;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        display: flex;
        flex-direction: column;
        gap: 12px;
        box-sizing: border-box;
        overflow-y: auto;
    }

    .memo-update-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.95rem;
        font-weight: bold;
        border-bottom: 1px solid #ddd;
        padding-bottom: 8px;
        flex-shrink: 0;
        color: #555;
    }

    .memo-update-modal-body {
        display: flex;
        flex-direction: column;
        flex: 1;
        overflow-y: auto;
        gap: 10px;
    }

    .memo-update-textarea {
        width: 100%;
        height: 140px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        resize: vertical;
        font-size: 0.95rem;
        line-height: 1.5;
        box-sizing: border-box;
        color: #333;
    }

    .memo-update-file-list {
        padding-top: 5px;
        font-size: 0.9rem;
    }

    .memo-update-file-item {
        display: inline-flex;
        align-items: center;
        background: #f1f8e9;
        padding: 4px 8px;
        margin: 2px 4px 2px 0;
        border-radius: 4px;
        border: 1px solid #c5e1a5;
        color: #2e7d32;
        word-break: break-all;
    }

    .memo-update-file-item-name {
        cursor: pointer;
    }

    .memo-update-file-item-name:hover {
        text-decoration: underline;
    }

    .memo-update-file-area {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 0.82rem;
        color: #666;
        border-top: 1px dashed #ccc;
        padding-top: 10px;
    }

    .memo-update-file-area input[type="file"] {
        width: 100%;
        border: 1px solid #ccc;
        padding: 6px;
        box-sizing: border-box;
        border-radius: 4px;
        background-color: #f9f9f9;
        font-size: 0.85rem;
    }

    .memo-update-selected-files {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px;
    }

    .memo-update-selected-file {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        background: #f1f8e9;
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid #c5e1a5;
        color: #2e7d32;
        font-size: 0.82rem;
        box-sizing: border-box;
    }

    .memo-update-selected-file-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .memo-update-remove-badge {
        margin-left: 8px;
        cursor: pointer;
        color: #d32f2f;
        font-weight: bold;
        user-select: none;
    }

    .memo-update-remove-badge:hover {
        color: #b71c1c;
    }

    .memo-update-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 10px;
        flex-shrink: 0;
        border-top: 1px solid #eee;
        padding-top: 10px;
    }

    .memo-update-save {
        padding: 6px 14px;
        background-color: #4CAF50;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
    }

    .memo-update-save:disabled {
        background-color: #9e9e9e;
        cursor: not-allowed;
    }

    .memo-update-close {
        padding: 6px 14px;
        background-color: #666;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    /* 이미지 프리뷰 팝업 */
    .memo-update-image-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.85);
        justify-content: center;
        align-items: center;
        z-index: 2000;
    }

    .memo-update-image-content {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        max-width: 90vw;
        max-height: 90vh;
    }

    .memo-update-image-content img {
        max-width: 100%;
        max-height: 90vh;
        object-fit: contain;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    }

    .memo-update-image-close {
        position: absolute;
        top: -40px;
        right: 0;
        color: #fff;
        font-size: 32px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
        padding: 5px;
        text-shadow: 0 1px 3px rgba(0,0,0,0.5);
    }

    .memo-update-image-close:hover {
        color: #ddd;
    }
</style>

<!-- 메모 상세/수정 팝업 -->
<div id="detailModal" class="memo-update-modal-overlay" aria-hidden="true">
    <div class="memo-update-modal-content">
        <div class="memo-update-modal-header">
            <span>
                그룹ID:
                <span id="modalGroupIdView" style="color:#2e7d32;"></span>
            </span>
            <span id="modalDate"></span>
        </div>

        <div class="memo-update-modal-body">
            <input type="hidden" id="editModalId">
            <input type="hidden" id="editModalGroupId">

            <textarea
                id="editModalContent"
                class="memo-update-textarea"
                placeholder="수정할 내용을 입력하세요..."
            ></textarea>

            <!-- 기존 첨부파일 목록 -->
            <div
                id="modalFileList"
                class="memo-update-file-list"
                style="display:none;"
            ></div>

            <!-- 새 첨부파일 추가 -->
            <div class="memo-update-file-area">
                <label for="editMemoFiles">
                    <strong>새 첨부파일 추가:</strong>
                </label>

                <input
                    type="file"
                    id="editMemoFiles"
                    multiple
                    accept="<?php
                        echo htmlspecialchars(
                            '.' . implode(',.', $memo_update_allowed_extensions),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>"
                >

                <div
                    id="editSelectedFilesContainer"
                    class="memo-update-selected-files"
                ></div>

                <span>
                    * 기존 파일 포함 최대
                    <?php echo (int)$memo_update_max_file_count; ?>개
                    / 허용 확장자:
                    <?php echo htmlspecialchars(
                        implode(', ', $memo_update_allowed_extensions),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </span>
            </div>
        </div>

        <div class="memo-update-modal-footer">
            <button
                type="button"
                class="memo-update-save"
                id="memoUpdateSaveButton"
                onclick="updateMemo()"
            >수정 저장</button>

            <button
                type="button"
                class="memo-update-close"
                onclick="closeModal()"
            >닫기</button>
        </div>
    </div>
</div>

<!-- 이미지 미리보기 -->
<div id="imagePreviewModal" class="memo-update-image-overlay" aria-hidden="true">
    <div class="memo-update-image-content">
        <span
            class="memo-update-image-close"
            onclick="closeImageModal()"
        >&times;</span>
        <img id="previewImage" src="" alt="원본 이미지 미리보기">
    </div>
</div>

<script>
(function() {
    const currentPage = <?php echo (int)$memo_update_current_page; ?>;
    const maxFileCount = <?php echo (int)$memo_update_max_file_count; ?>;
    const allowedExtensions = <?php echo json_encode(
        array_values($memo_update_allowed_extensions),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ); ?>;
    const currentFileUrl = <?php echo json_encode(
        $memo_update_action_url,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ); ?>;

    let isSaving = false;
    let existingFileCountForEdit = 0;
    let editSelectedFiles = [];

    const editMemoFilesInput = document.getElementById('editMemoFiles');
    const editSelectedFilesContainer = document.getElementById('editSelectedFilesContainer');

    window.openModalFromElem = function(elem) {
        const id = elem.getAttribute('data-id');
        const rawGroupId = elem.getAttribute('data-raw-group-id');
        const displayGroupId = elem.getAttribute('data-display-group-id');
        const date = elem.getAttribute('data-date');
        const content = elem.getAttribute('data-content');
        const filesStr = elem.getAttribute('data-files');

        let files = [];

        try {
            files = filesStr ? JSON.parse(filesStr) : [];
        } catch (e) {
            console.error('JSON parsing error:', e);
            files = [];
        }

        existingFileCountForEdit = files.length;
        editSelectedFiles = [];

        document.getElementById('editModalId').value = id;
        document.getElementById('editModalGroupId').value = rawGroupId;
        document.getElementById('modalGroupIdView').innerText = displayGroupId;
        document.getElementById('modalDate').innerText = date;
        document.getElementById('editModalContent').value = content;

        if (editMemoFilesInput) {
            editMemoFilesInput.value = '';
        }

        syncEditFilesAndRender();
        renderExistingFiles(files);

        const modal = document.getElementById('detailModal');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
    };

    function renderExistingFiles(files) {
        const fileListContainer = document.getElementById('modalFileList');
        fileListContainer.innerHTML = '';

        if (!Array.isArray(files) || files.length === 0) {
            fileListContainer.style.display = 'none';
            return;
        }

        fileListContainer.style.display = 'block';

        const titleDiv = document.createElement('div');
        titleDiv.style.fontWeight = 'bold';
        titleDiv.style.marginBottom = '5px';
        titleDiv.style.color = '#555';
        titleDiv.innerText = '기존 첨부파일:';
        fileListContainer.appendChild(titleDiv);

        files.forEach(function(file) {
            const ext = (file.extension || '').toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(ext);

            const fileDiv = document.createElement('div');
            fileDiv.className = 'memo-update-file-item';

            const fileSpan = document.createElement('span');
            fileSpan.className = 'memo-update-file-item-name';
            fileSpan.innerText = '📎 ' + (file.original_name || '첨부파일');

            fileSpan.onclick = function() {
                handleFileClick(file.storage_path, isImage);
            };

            const delBtn = document.createElement('span');
            delBtn.className = 'memo-update-remove-badge';
            delBtn.innerHTML = '&times;';
            delBtn.title = '완전 삭제';

            delBtn.onclick = function(evt) {
                evt.stopPropagation();
                deleteAttachedFile(file.id, fileDiv);
            };

            fileDiv.appendChild(fileSpan);
            fileDiv.appendChild(delBtn);
            fileListContainer.appendChild(fileDiv);
        });
    }

    window.closeModal = function() {
        const modal = document.getElementById('detailModal');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    };

    window.handleFileClick = function(path, isImage) {
        if (!path) {
            return;
        }

        if (isImage) {
            document.getElementById('previewImage').src = path;

            const modal = document.getElementById('imagePreviewModal');
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
        } else {
            window.open(path, '_blank');
        }
    };

    window.closeImageModal = function() {
        const modal = document.getElementById('imagePreviewModal');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.getElementById('previewImage').src = '';
    };

    window.deleteAttachedFile = function(fileId, fileDiv) {
        if (!fileId) {
            return;
        }

        if (!confirm('이 첨부파일을 완전히 삭제할까? (서버에서도 바로 지워져!)')) {
            return;
        }

        fetch(currentFileUrl + '?action=delete_file&file_id=' + encodeURIComponent(fileId))
            .then(async function(res) {
                const rawText = await res.text();
                const resText = rawText.trim();

                if (
                    res.ok &&
                    (resText === 'success' || resText.endsWith('success'))
                ) {
                    alert('첨부파일이 안전하게 삭제됐어!');

                    if (fileDiv) {
                        fileDiv.remove();
                    }

                    existingFileCountForEdit = Math.max(
                        0,
                        existingFileCountForEdit - 1
                    );

                    const fileListContainer = document.getElementById('modalFileList');

                    if (
                        fileListContainer.querySelectorAll(
                            '.memo-update-file-item'
                        ).length === 0
                    ) {
                        fileListContainer.style.display = 'none';
                    }

                    const memoId = document.getElementById('editModalId').value;
                    const memoItem = document.querySelector(
                        '.memo-item[data-id="' + memoId + '"]'
                    );

                    if (memoItem) {
                        const oldFilesStr = memoItem.getAttribute('data-files');

                        if (oldFilesStr) {
                            try {
                                let oldFiles = JSON.parse(oldFilesStr);
                                oldFiles = oldFiles.filter(function(file) {
                                    return String(file.id) !== String(fileId);
                                });
                                memoItem.setAttribute(
                                    'data-files',
                                    JSON.stringify(oldFiles)
                                );
                            } catch (e) {
                                console.error('첨부파일 목록 갱신 실패:', e);
                            }
                        }

                        if (existingFileCountForEdit === 0) {
                            const clipIcon = memoItem.querySelector('.icon-clip');
                            if (clipIcon) {
                                clipIcon.remove();
                            }
                        }
                    }
                } else {
                    const cleanMsg = rawText
                        .replace(/<[^>]*>?/gm, '')
                        .trim();

                    throw new Error(
                        cleanMsg || '파일 삭제 중 오류가 발생했어.'
                    );
                }
            })
            .catch(function(err) {
                alert(err.message || '파일 삭제 중 오류가 발생했어.');
            });
    };

    window.updateMemo = function() {
        if (isSaving) {
            return;
        }

        const id = document.getElementById('editModalId').value;
        const groupId = document.getElementById('editModalGroupId').value;
        const content = document
            .getElementById('editModalContent')
            .value
            .trim();

        if (!content) {
            alert('내용을 입력해줘!');
            return;
        }

        if (
            existingFileCountForEdit + editSelectedFiles.length >
            maxFileCount
        ) {
            alert(
                '기존 파일을 포함해 최대 ' +
                maxFileCount +
                '개까지만 첨부할 수 있어.'
            );
            return;
        }

        isSaving = true;

        const saveButton = document.getElementById('memoUpdateSaveButton');
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = '저장 중...';
        }

        const formData = new FormData();

        formData.append('action', 'edit');
        formData.append('id', id);
        formData.append('group_id', groupId);
        formData.append('content', content);

        editSelectedFiles.forEach(function(file) {
            formData.append('files[]', file);
        });

        fetch(currentFileUrl, {
            method: 'POST',
            body: formData,
            cache: 'no-store'
        })
            .then(async function(res) {
                const rawText = await res.text();
                const resText = rawText.trim();

                if (
                    res.ok &&
                    (resText === 'success' || resText.endsWith('success'))
                ) {
                    window.closeModal();

                    // 수정 완료 후 호출 페이지에서 자체 갱신하도록 콜백 우선 처리
                    // 밭 관리 / 밭 상세정보 / 메모관리 화면이 각각
                    // 현재 보고 있던 페이지와 상태를 유지하면서 최신 데이터만 다시 조회할 수 있습니다.
                    if (typeof window.memoUpdateSuccessCallback === 'function') {
                        window.memoUpdateSuccessCallback({
                            id: id,
                            groupId: groupId,
                            content: content,
                            page: currentPage
                        });
                    } else if (typeof window.goToPage === 'function') {
                        window.goToPage(currentPage);
                    } else {
                        // 콜백이 없는 단독 사용 환경에서만 기존 동작 유지
                        window.location.href =
                            currentFileUrl + '?page=' + currentPage;
                    }
                } else {
                    const cleanMsg = rawText
                        .replace(/<[^>]*>?/gm, '')
                        .trim();

                    throw new Error(
                        cleanMsg || '수정 중 오류가 발생했어.'
                    );
                }
            })
            .catch(function(err) {
                alert(err.message || '수정 중 오류가 발생했어.');
            })
            .finally(function() {
                isSaving = false;

                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.textContent = '수정 저장';
                }
            });
    };

    if (editMemoFilesInput) {
        editMemoFilesInput.addEventListener('change', function() {
            const newFiles = Array.from(this.files || []);

            for (const file of newFiles) {
                if (
                    existingFileCountForEdit +
                    editSelectedFiles.length >=
                    maxFileCount
                ) {
                    alert(
                        '기존 파일을 포함해 최대 ' +
                        maxFileCount +
                        '개까지만 선택할 수 있어!'
                    );
                    break;
                }

                const extParts = file.name.split('.');
                const ext =
                    extParts.length > 1
                        ? extParts.pop().toLowerCase()
                        : '';

                if (!allowedExtensions.includes(ext)) {
                    alert(
                        '허용되지 않는 파일 형식이야: ' +
                        file.name
                    );
                    continue;
                }

                const isDuplicate = editSelectedFiles.some(function(item) {
                    return (
                        item.name === file.name &&
                        item.size === file.size &&
                        item.lastModified === file.lastModified
                    );
                });

                if (!isDuplicate) {
                    editSelectedFiles.push(file);
                }
            }

            syncEditFilesAndRender();
        });
    }

    window.removeEditSelectedFile = function(index) {
        if (index < 0 || index >= editSelectedFiles.length) {
            return;
        }

        editSelectedFiles.splice(index, 1);
        syncEditFilesAndRender();
    };

    function syncEditFilesAndRender() {
        const dt = new DataTransfer();

        editSelectedFiles.forEach(function(file) {
            dt.items.add(file);
        });

        if (editMemoFilesInput) {
            editMemoFilesInput.files = dt.files;
        }

        if (!editSelectedFilesContainer) {
            return;
        }

        editSelectedFilesContainer.innerHTML = '';

        editSelectedFiles.forEach(function(file, index) {
            const badge = document.createElement('div');
            badge.className = 'memo-update-selected-file';

            const fileNameSpan = document.createElement('span');
            fileNameSpan.className = 'memo-update-selected-file-name';
            fileNameSpan.innerText = '📎 ' + file.name + ' (새로 추가)';

            const removeBtn = document.createElement('span');
            removeBtn.className = 'memo-update-remove-badge';
            removeBtn.innerHTML = '&times;';
            removeBtn.title = '제외';

            removeBtn.onclick = function(evt) {
                evt.stopPropagation();
                window.removeEditSelectedFile(index);
            };

            badge.appendChild(fileNameSpan);
            badge.appendChild(removeBtn);
            editSelectedFilesContainer.appendChild(badge);
        });
    }

    document.addEventListener('click', function(event) {
        const detailModal = document.getElementById('detailModal');
        const imageModal = document.getElementById('imagePreviewModal');

        if (event.target === imageModal) {
            window.closeImageModal();
        }

        if (event.target === detailModal) {
            window.closeModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') {
            return;
        }

        const imageModal = document.getElementById('imagePreviewModal');
        const detailModal = document.getElementById('detailModal');

        if (
            imageModal &&
            imageModal.style.display === 'flex'
        ) {
            window.closeImageModal();
            return;
        }

        if (
            detailModal &&
            detailModal.style.display === 'flex'
        ) {
            window.closeModal();
        }
    });
})();
</script>
