<?php
/**
 * 공용 신규 메모 작성 컴포넌트
 *
 * 전제:
 * - 부모 PHP에서 PDO 객체 $pdo가 먼저 생성되어 있어야 합니다.
 * - include_once 한 번으로 신규 메모 작성 UI + 저장 처리를 함께 사용할 수 있습니다.
 *
 * 사용:
 *   include_once __DIR__ . '/memo_write_component.php';
 *   echo renderMemoWriteButton($groupId, $title);
 *
 * 선택 설정:
 *   $memo_write_max_file_count
 *   $memo_write_allowed_extensions
 *   $memo_write_attachment_table
 *   $memo_write_upload_base_dir
 *
 * 연결:
 *   memoT.id -> attachment.att_grp_id
 */

// --------------------------------------------------
// memo2.php와 동일한 첨부파일 기본 설정
// --------------------------------------------------
$memo_write_max_file_count = $memo_write_max_file_count ?? 5;

$memo_write_allowed_extensions = $memo_write_allowed_extensions ?? [
    'jpg', 'jpeg', 'png', 'gif',
    'pdf', 'doc', 'docx',
    'xls', 'xlsx', 'zip', 'txt', 'hwp'
];

$memo_write_attachment_table = $memo_write_attachment_table ?? 'attachment';

if (!isset($memo_write_upload_base_dir) || $memo_write_upload_base_dir === '') {
    $memo_write_upload_base_dir =
        rtrim(dirname($_SERVER['SCRIPT_FILENAME'] ?? __DIR__), '/\\')
        . DIRECTORY_SEPARATOR . 'uploads';
}

// 부모 페이지가 AJAX/partial 형태로 컴포넌트를 포함하는 경우에도
// 신규 메모 저장 요청을 정확한 PHP 엔드포인트로 전송하기 위한 URL 설정
$memo_write_submit_url = $memo_write_submit_url ?? '';

// --------------------------------------------------
// 신규 메모 작성 버튼
// --------------------------------------------------
if (!function_exists('renderMemoWriteButton')) {
    function renderMemoWriteButton($groupId, $title = '')
    {
        $groupJs = htmlspecialchars(
            json_encode(
                (string)$groupId,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            ENT_QUOTES,
            'UTF-8'
        );

        $titleJs = htmlspecialchars(
            json_encode(
                (string)$title,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            ENT_QUOTES,
            'UTF-8'
        );

        return '<span class="memo-write-trigger" title="새 메모 작성"'
            . ' onclick="openMemoWriteModal(' . $groupJs . ',' . $titleJs . ')">📝</span>';
    }
}

// --------------------------------------------------
// post_max_size 초과 처리
// memo2.php와 동일한 방식
// --------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    empty($_POST) &&
    empty($_FILES) &&
    isset($_SERVER['CONTENT_LENGTH']) &&
    (int)$_SERVER['CONTENT_LENGTH'] > 0
) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo '업로드 허용 용량(post_max_size)을 초과하였습니다.';
    exit;
}

// --------------------------------------------------
// 신규 메모 + 첨부파일 저장
// --------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'save_memo_component'
) {
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json; charset=utf-8');

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'DB 연결 객체($pdo)가 준비되지 않았습니다.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $groupId = trim((string)($_POST['group_id'] ?? ''));
    $date    = trim((string)($_POST['date'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));

    if ($groupId === '' || $date === '' || $content === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '필수 입력값이 누락되었습니다.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uploadedFiles = [];

    try {
        $pdo->beginTransaction();

        // 1. 메모 저장
        $memoStmt = $pdo->prepare("
            INSERT INTO memoT (
                group_id,
                reg_date,
                content
            ) VALUES (
                :group_id,
                :reg_date,
                :content
            )
        ");

        $memoStmt->execute([
            ':group_id' => $groupId,
            ':reg_date' => $date . ' ' . date('H:i:s'),
            ':content'  => $content
        ]);

        $memoId = (int)$pdo->lastInsertId();

        // 2. 첨부파일 저장
        if (
            isset($_FILES['files']['name']) &&
            is_array($_FILES['files']['name'])
        ) {
            $fileCount = count($_FILES['files']['name']);

            if ($fileCount > $memo_write_max_file_count) {
                throw new RuntimeException(
                    "파일은 최대 {$memo_write_max_file_count}개까지 업로드 가능합니다."
                );
            }

            $dateFolder  = date('Ymd');
            $relativeDir = 'uploads/' . $dateFolder;
            $targetDir   = rtrim(
                $memo_write_upload_base_dir,
                '/\\'
            ) . DIRECTORY_SEPARATOR . $dateFolder;

            if (
                !is_dir($targetDir) &&
                !mkdir($targetDir, 0755, true) &&
                !is_dir($targetDir)
            ) {
                throw new RuntimeException(
                    '업로드 디렉토리 생성에 실패했습니다.'
                );
            }

            $attachStmt = $pdo->prepare("
                INSERT INTO {$memo_write_attachment_table} (
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
                )
            ");

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

                $tmpName     = (string)($_FILES['files']['tmp_name'][$i] ?? '');
                $originalName = (string)($_FILES['files']['name'][$i] ?? '');
                $fileSize    = (int)($_FILES['files']['size'][$i] ?? 0);

                if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                    throw new RuntimeException(
                        '정상적인 업로드 파일이 아닙니다: ' . $originalName
                    );
                }

                $extension = strtolower(
                    pathinfo($originalName, PATHINFO_EXTENSION)
                );

                if (
                    $extension === '' ||
                    !in_array(
                        $extension,
                        $memo_write_allowed_extensions,
                        true
                    )
                ) {
                    throw new RuntimeException(
                        "허용되지 않는 파일 확장자입니다: .{$extension}"
                    );
                }

                $mimeType = $_FILES['files']['type'][$i] ?? '';

                if (
                    $mimeType === '' &&
                    function_exists('mime_content_type')
                ) {
                    $mimeType = mime_content_type($tmpName);
                }

                if ($mimeType === '') {
                    $mimeType = 'application/octet-stream';
                }

                $storedName =
                    date('YmdHis') . '_' .
                    bin2hex(random_bytes(4)) . '.' .
                    $extension;

                $storagePath =
                    $relativeDir . '/' . $storedName;

                $fullPath =
                    rtrim($targetDir, '/\\') .
                    DIRECTORY_SEPARATOR .
                    $storedName;

                if (!move_uploaded_file($tmpName, $fullPath)) {
                    throw new RuntimeException(
                        '파일 저장 처리 중 오류가 발생했습니다: ' . $originalName
                    );
                }

                $uploadedFiles[] = $fullPath;

                $attachStmt->execute([
                    ':att_grp_id'    => $memoId,
                    ':original_name' => $originalName,
                    ':stored_name'   => $storedName,
                    ':storage_path'  => $storagePath,
                    ':mime_type'     => $mimeType,
                    ':file_size'     => $fileSize,
                    ':extension'     => $extension,
                    ':checksum'      => hash_file('sha256', $fullPath)
                ]);
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'memo_id' => $memoId
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // DB 롤백 시 실제 파일도 정리
        foreach ($uploadedFiles as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}
?>

<?php
$memo_write_render_mode = $memo_write_render_mode ?? 'modal';
$memo_write_inline_group_id = $memo_write_inline_group_id ?? '';
$memo_write_inline_title = $memo_write_inline_title ?? '새 메모 작성';
$memoWriteIsInline = ($memo_write_render_mode === 'inline');
?>

<style>
.memo-write-trigger {
    cursor:pointer;
    font-size:1.1rem;
    padding:2px 4px;
    border-radius:4px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}
.memo-write-trigger:hover { background:rgba(0,0,0,.08); }

.memo-write-modal-overlay {
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.5);
    justify-content:center;
    align-items:center;
    z-index:1000;
}
.memo-write-modal {
    width:85%;
    max-width:380px;
    max-height:85vh;
    padding:20px;
    box-sizing:border-box;
    overflow-y:auto;
    background:#fff;
    border-radius:8px;
    box-shadow:0 4px 10px rgba(0,0,0,.3);
}

/* 같은 화면에 항상 표시되는 신규 메모 작성 영역 */
.memo-write-inline {
    width:100%;
    box-sizing:border-box;
    margin-bottom:15px;
}
.memo-write-inline-box {
    background:#fff;
    padding:15px;
    border-radius:8px;
    border:1px solid #e0e0e0;
    box-shadow:0 1px 3px rgba(0,0,0,.05);
    box-sizing:border-box;
}

.memo-write-title {
    margin:0 0 12px;
    font-size:1rem;
    color:#333;
    font-weight:bold;
}
.memo-write-form {
    display:flex;
    flex-direction:column;
    gap:10px;
}
.memo-write-form input[type="date"],
.memo-write-form textarea {
    width:100%;
    padding:8px;
    box-sizing:border-box;
    border:1px solid #ccc;
    border-radius:4px;
    font-size:.9rem;
}
.memo-write-form textarea {
    height:120px;
    resize:vertical;
    line-height:1.4;
}
.memo-write-file-area {
    display:flex;
    flex-direction:column;
    gap:4px;
    font-size:.82rem;
    color:#666;
}
.memo-write-file-area input[type="file"] {
    width:100%;
    padding:6px;
    box-sizing:border-box;
    border:1px solid #ccc;
    border-radius:4px;
    background:#f9f9f9;
    font-size:.85rem;
}
.memo-write-selected {
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:4px;
}
.memo-write-file-badge {
    display:inline-flex;
    align-items:center;
    max-width:100%;
    padding:4px 8px;
    box-sizing:border-box;
    border:1px solid #c5e1a5;
    border-radius:4px;
    background:#f1f8e9;
    color:#2e7d32;
    font-size:.82rem;
}
.memo-write-file-name {
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.memo-write-file-remove {
    margin-left:6px;
    cursor:pointer;
    color:#d32f2f;
    font-weight:bold;
}
.memo-write-actions {
    display:flex;
    justify-content:flex-end;
    gap:8px;
    margin-top:5px;
}
.memo-write-save,
.memo-write-close {
    padding:8px 14px;
    border:0;
    border-radius:4px;
    color:#fff;
    cursor:pointer;
    font-weight:bold;
}
.memo-write-save { background:#4CAF50; }
.memo-write-save:disabled {
    background:#9e9e9e;
    cursor:not-allowed;
}
.memo-write-close { background:#666; }
</style>

<?php if ($memoWriteIsInline): ?>
<div id="memoWriteInline" class="memo-write-inline">
    <div class="memo-write-inline-box">
<?php else: ?>
<div id="memoWriteModal" class="memo-write-modal-overlay">
    <div class="memo-write-modal">
<?php endif; ?>

        <h3 id="memoWriteTitle" class="memo-write-title">
            <?php echo htmlspecialchars($memoWriteIsInline ? $memo_write_inline_title : '새 메모 작성', ENT_QUOTES, 'UTF-8'); ?>
        </h3>

        <form
            id="memoWriteForm"
            class="memo-write-form"
            enctype="multipart/form-data"
        >
            <input
                type="hidden"
                name="action"
                value="save_memo_component"
            >

            <input
                type="hidden"
                name="group_id"
                id="memoWriteGroupId"
                value="<?php echo $memoWriteIsInline ? htmlspecialchars((string)$memo_write_inline_group_id, ENT_QUOTES, 'UTF-8') : ''; ?>"
            >

            <input
                type="date"
                name="date"
                id="memoWriteDate"
                value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"
                required
            >

            <textarea
                name="content"
                id="memoWriteContent"
                placeholder="내용을 입력하세요..."
                required
            ></textarea>

            <div class="memo-write-file-area">
                <label for="memoWriteFiles">
                    <strong>
                        첨부파일
                        (최대 <?php echo (int)$memo_write_max_file_count; ?>개)
                    </strong>
                </label>

                <input
                    type="file"
                    id="memoWriteFiles"
                    name="files[]"
                    multiple
                    accept="<?php echo htmlspecialchars('.' . implode(',.', $memo_write_allowed_extensions), ENT_QUOTES, 'UTF-8'); ?>"
                >

                <div id="memoWriteSelected" class="memo-write-selected"></div>

                <span>
                    * 허용 확장자:
                    <?php echo htmlspecialchars(implode(', ', $memo_write_allowed_extensions), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>

            <div class="memo-write-actions">
                <button
                    type="submit"
                    id="memoWriteSave"
                    class="memo-write-save"
                >저장</button>

<?php if (!$memoWriteIsInline): ?>
                <button
                    type="button"
                    class="memo-write-close"
                    onclick="closeMemoWriteModal()"
                >닫기</button>
<?php endif; ?>
            </div>
        </form>

<?php if ($memoWriteIsInline): ?>
    </div>
</div>
<?php else: ?>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const isInline = <?php echo $memoWriteIsInline ? 'true' : 'false'; ?>;
    const inlineGroupId = <?php echo json_encode((string)$memo_write_inline_group_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const inlineTitle = <?php echo json_encode((string)$memo_write_inline_title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const maxFileCount = <?php echo (int)$memo_write_max_file_count; ?>;

    const submitUrl = <?php echo json_encode((string)$memo_write_submit_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || window.location.href;

    const allowedExtensions = <?php echo json_encode(array_values($memo_write_allowed_extensions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const modal = document.getElementById('memoWriteModal');
    const form = document.getElementById('memoWriteForm');
    const groupId = document.getElementById('memoWriteGroupId');
    const title = document.getElementById('memoWriteTitle');
    const date = document.getElementById('memoWriteDate');
    const content = document.getElementById('memoWriteContent');
    const fileInput = document.getElementById('memoWriteFiles');
    const selectedBox = document.getElementById('memoWriteSelected');
    const saveButton = document.getElementById('memoWriteSave');

    let selectedFiles = [];
    let saving = false;

    function syncInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(function (file) {
            dt.items.add(file);
        });
        fileInput.files = dt.files;
    }

    function renderFiles() {
        selectedBox.innerHTML = '';

        selectedFiles.forEach(function (file, index) {
            const item = document.createElement('div');
            item.className = 'memo-write-file-badge';

            const name = document.createElement('span');
            name.className = 'memo-write-file-name';
            name.textContent = '📎 ' + file.name;

            const remove = document.createElement('span');
            remove.className = 'memo-write-file-remove';
            remove.innerHTML = '&times;';
            remove.title = '선택 취소';

            remove.onclick = function (event) {
                event.stopPropagation();
                selectedFiles.splice(index, 1);
                syncInput();
                renderFiles();
            };

            item.appendChild(name);
            item.appendChild(remove);
            selectedBox.appendChild(item);
        });
    }

    function resetForm() {
        selectedFiles = [];
        groupId.value = isInline ? inlineGroupId : '';
        title.textContent = isInline ? inlineTitle : '새 메모 작성';
        date.value = new Date().toISOString().slice(0, 10);
        content.value = '';
        fileInput.value = '';
        renderFiles();
    }

    window.openMemoWriteModal = function (targetGroupId, targetTitle) {
        if (isInline) {
            groupId.value = String(targetGroupId || inlineGroupId);
            title.textContent = targetTitle
                ? '새 메모 작성 (' + String(targetTitle) + ')'
                : inlineTitle;
            content.focus();
            return;
        }

        if (!targetGroupId) {
            alert('메모 그룹 ID가 없습니다.');
            return;
        }

        resetForm();
        groupId.value = String(targetGroupId);

        if (targetTitle) {
            title.textContent = '새 메모 작성 (' + String(targetTitle) + ')';
        }

        modal.style.display = 'flex';
        content.focus();
    };

    window.closeMemoWriteModal = function () {
        if (isInline) {
            return;
        }
        if (modal) {
            modal.style.display = 'none';
        }
        saving = false;
    };

    // inline 모드에서는 처음부터 같은 화면의 입력창을 표시
    if (isInline) {
        resetForm();
    }

    fileInput.addEventListener('change', function () {
        const files = Array.from(this.files || []);

        for (const file of files) {
            if (selectedFiles.length >= maxFileCount) {
                alert('파일은 최대 ' + maxFileCount + '개까지 선택할 수 있습니다.');
                break;
            }

            const parts = file.name.split('.');
            const ext = parts.length > 1 ? parts.pop().toLowerCase() : '';

            if (!allowedExtensions.includes(ext)) {
                alert(
                    '허용되지 않는 파일 형식입니다: ' + file.name +
                    '\n(허용 확장자: ' + allowedExtensions.join(', ') + ')'
                );
                continue;
            }

            const duplicate = selectedFiles.some(function (item) {
                return item.name === file.name &&
                    item.size === file.size &&
                    item.lastModified === file.lastModified;
            });

            if (!duplicate) {
                selectedFiles.push(file);
            }
        }

        syncInput();
        renderFiles();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (saving) {
            return;
        }

        if (!groupId.value.trim()) {
            alert('메모 그룹 ID가 없습니다.');
            return;
        }

        if (!date.value.trim()) {
            alert('날짜를 선택해주세요.');
            return;
        }

        if (!content.value.trim()) {
            alert('내용을 입력해주세요.');
            content.focus();
            return;
        }

        if (selectedFiles.length > maxFileCount) {
            alert('파일은 최대 ' + maxFileCount + '개까지 첨부할 수 있습니다.');
            return;
        }

        syncInput();

        saving = true;
        saveButton.disabled = true;
        saveButton.textContent = '저장 중...';

        const formData = new FormData(form);

        fetch(submitUrl, {
            method: 'POST',
            body: formData,
            cache: 'no-store'
        })
        .then(async function (response) {
            const text = await response.text();
            let result;

            try {
                result = JSON.parse(text);
            } catch (e) {
                throw new Error(
                    text.replace(/<[^>]*>?/gm, '').trim() ||
                    '서버 응답을 확인할 수 없습니다.'
                );
            }

            if (!response.ok || !result.success) {
                throw new Error(result.message || '메모 저장에 실패했습니다.');
            }

            if (isInline) {
                resetForm();
            } else {
                closeMemoWriteModal();
            }

            if (typeof window.memoWriteSuccessCallback === 'function') {
                window.memoWriteSuccessCallback(result);
            } else {
                window.location.reload();
            }
        })
        .catch(function (error) {
            alert(error.message || '메모 저장 중 오류가 발생했습니다.');
            console.error(error);
        })
        .finally(function () {
            saving = false;
            saveButton.disabled = false;
            saveButton.textContent = '저장';
        });
    });

    if (!isInline) {
        window.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeMemoWriteModal();
            }
        });
    }
})();
</script>
