<?php
// DB 연결 설정
require_once __DIR__ . '/db_connect.php';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// POST 요청 처리 (신규 등록 또는 수정)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    $farm_id    = isset($_POST['farm_id']) ? trim($_POST['farm_id']) : '';
    $farm_name  = isset($_POST['farm_name']) ? trim($_POST['farm_name']) : '';
    $row_count  = isset($_POST['row_count']) ? intval($_POST['row_count']) : 0;
    $col_count  = isset($_POST['col_count']) ? intval($_POST['col_count']) : 0;
    $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
    $farm_type  = isset($_POST['farm_type']) ? $_POST['farm_type'] : '노지';
    
    // disabled 상태 등으로 전달되지 않은 경우 기본값 'Y'
    $use_yn     = isset($_POST['use_yn']) ? $_POST['use_yn'] : 'Y';

    if (!empty($farm_name)) {
        try {
            if ($farm_id === '') {
                // 신규 등록 (INSERT)
                $stmt = $pdo->prepare("INSERT INTO farms (farm_name, row_count, col_count, farm_type, use_yn, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$farm_name, $row_count, $col_count, $farm_type, $use_yn, $sort_order]);
            } else {
                // 수정 (UPDATE)
                $stmt = $pdo->prepare("UPDATE farms SET farm_name = ?, row_count = ?, col_count = ?, farm_type = ?, use_yn = ?, sort_order = ? WHERE farm_id = ?");
                $stmt->execute([$farm_name, $row_count, $col_count, $farm_type, $use_yn, $sort_order, $farm_id]);
            }
            echo json_encode(['status' => 'success', 'message' => '저장되었습니다.']);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB 오류: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => '밭 이름을 입력해주세요.']);
    }
    exit;
}

// 페이징 처리 변수 설정
$limit = 10; // 한 페이지당 보여줄 갯수
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 1. 전체 데이터 갯수 조회 (use_yn = 'Y' 조건 추가)
$countStmt = $pdo->query("SELECT COUNT(*) FROM farms ");
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);
if ($totalPages < 1) $totalPages = 1;

// 2. 현재 페이지에 해당하는 밭 목록 조회 (sort_order 기준 정렬 추가)
$stmt = $pdo->prepare("SELECT * FROM farms ORDER BY sort_order ASC, farm_id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$farms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>밭 설정</title>
    <style>
        .farm-set-wrapper {
            display: flex;
            flex-direction: column;
            height: 100%;
            gap: 12px;
        }
        .form-box, .list-box {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            background: #fff;
            overflow-y: auto;
        }
        .form-box h3 {
            font-size: 15px;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .list-header h3 {
            font-size: 15px;
            color: #2c3e50;
            margin: 0;
        }
        .pagination {
            font-size: 12px;
            font-weight: bold;
        }
        .pagination a {
            color: #2c3e50;
            text-decoration: none;
            padding: 2px 5px;
            margin: 0 1px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background: #f8f9fa;
        }
        .pagination a:hover {
            background: #e9ecef;
        }
        .pagination span.active {
            color: #fff;
            background: #2c3e50;
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid #2c3e50;
        }
        .input-group {
            margin-bottom: 8px;
        }
        .input-group label {
            display: block;
            font-size: 12px;
            color: #555;
            margin-bottom: 2px;
            font-weight: bold;
        }
        .input-group input, .input-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .input-group select:disabled {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }
        .form-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        .form-actions button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-submit { background-color: #2c3e50; color: white; }
        .btn-reset { background-color: #e0e0e0; color: #333; }
        
        .farm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .farm-table th, .farm-table td {
            border: 1px solid #eee;
            padding: 8px;
            text-align: center;
        }
        .farm-table th {
            background-color: #fff3e0; /* 옅은 주황색으로 수정 */
            color: #333;
        }
        .farm-table tr {
            cursor: pointer;
        }
        .farm-table tr:hover {
            background-color: #f1f3f5;
        }
        
        /* 테이블 열 넓이 조정용 클래스 */
        .col-fit {
            width: 1%;
            white-space: nowrap;
        }
        .col-name {
            /* width를 지정하지 않으면 남은 여백을 모두 차지하게 됩니다 */
        }
    </style>
</head>
<body>
    <div class="farm-set-wrapper">
        <!-- 상단: 입력 및 수정 폼 영역 -->
        <div class="form-box">
            <h3 id="form-title">밭 신규 등록</h3>
            <form id="farmForm" method="POST">
                <input type="hidden" id="farm_id" name="farm_id" value="">
                <div style="display: flex; gap: 8px;">
                    <div class="input-group" style="flex:2;">
                        <label for="farm_name">밭 이름</label>
                        <input type="text" id="farm_name" name="farm_name" required placeholder="예: 앞밭">
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label for="sort_order">정렬순서</label>
                        <input type="number" id="sort_order" name="sort_order" value="0" min="0" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <div class="input-group" style="flex:1;">
                        <label for="row_count">행 개수</label>
                        <input type="number" id="row_count" name="row_count" value="0" min="0">
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label for="col_count">열 개수</label>
                        <input type="number" id="col_count" name="col_count" value="0" min="0">
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <div class="input-group" style="flex:1;">
                        <label for="farm_type">유형</label>
                        <select id="farm_type" name="farm_type">
                            <option value="노지">노지</option>
                            <option value="하우스">하우스</option>
                        </select>
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label for="use_yn">사용여부</label>
                        <select id="use_yn" name="use_yn" disabled>
                            <option value="Y">Y</option>
                            <option value="N">N</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-reset" onclick="resetForm()">신규입력 초기화</button>
                    <button type="submit" id="submit-btn" class="btn-submit">저장</button>
                </div>
            </form>
        </div>

        <!-- 하단: 목록 영역 -->
        <div class="list-box">
            <div class="list-header">
                <h3>밭 목록</h3>
                <div class="pagination">
                    <?php
                    $prevBlock = max(1, $page - 1);
                    echo '<a href="#" onclick="loadPage(\'farmSet.php?page=1\', \'밭 설정\'); return false;"><<</a>';
                    echo '<a href="#" onclick="loadPage(\'farmSet.php?page=' . $prevBlock . '\', \'밭 설정\'); return false;"><</a>';

                    for ($i = 1; $i <= $totalPages; $i++) {
                        if ($i == $page) {
                            echo '<span class="active">' . $i . '</span>';
                        } else {
                            echo '<a href="#" onclick="loadPage(\'farmSet.php?page=' . $i . '\', \'밭 설정\'); return false;">' . $i . '</a>';
                        }
                    }

                    $nextBlock = min($totalPages, $page + 1);
                    echo '<a href="#" onclick="loadPage(\'farmSet.php?page=' . $nextBlock . '\', \'밭 설정\'); return false;">></a>';
                    echo '<a href="#" onclick="loadPage(\'farmSet.php?page=' . $totalPages . '\', \'밭 설정\'); return false;">>></a>';
                    ?>
                </div>
            </div>
            <table class="farm-table">
                <thead>
                    <tr>
                        <th class="col-name">밭이름</th>
                        <th class="col-fit">정렬<br>순서</th>
                        <th class="col-fit">유형</th>
                        <th class="col-fit">행x열</th>
                        <th class="col-fit">사용<br>여부</th>
                        <th class="col-fit">등록일</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($farms)): ?>
                        <tr><td colspan="6" style="color:#888; padding: 15px;">등록된 밭이 없습니다.</td></tr>
                    <?php else: ?>
                        <?php foreach ($farms as $farm): ?>
                            <tr onclick="selectFarm(<?php echo htmlspecialchars(json_encode($farm), ENT_QUOTES, 'UTF-8'); ?>)">
                                <td class="col-name"><?php echo htmlspecialchars($farm['farm_name']); ?></td>
                                <td class="col-fit"><?php echo htmlspecialchars($farm['sort_order'] ?? '0'); ?></td>
                                <td class="col-fit"><?php echo htmlspecialchars($farm['farm_type']); ?></td>
                                <td class="col-fit"><?php echo $farm['row_count'] . ' × ' . $farm['col_count']; ?></td>
                                <td class="col-fit"><?php echo htmlspecialchars($farm['use_yn'] ?? 'Y'); ?></td>
                                <td class="col-fit"><?php echo date('m-d', strtotime($farm['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // 폼 제출 이벤트 바인딩 (비동기 전송 처리)
        document.getElementById('farmForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const useYnSelect = document.getElementById('use_yn');
            
            // disabled 처리된 select는 FormData에 자동 포함되지 않으므로 수동 보완
            if (useYnSelect.disabled) {
                formData.set('use_yn', useYnSelect.value);
            }

            fetch('farmSet.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    // loadPage 함수가 등록되어 있는 경우 현재 페이지 재로드, 아닐 경우 일반 리로드
                    if (typeof loadPage === 'function') {
                        loadPage('farmSet.php?page=<?php echo $page; ?>', '밭 설정');
                    } else {
                        location.reload();
                    }
                } else {
                    alert(data.message || '처리 중 오류가 발생했습니다.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('서버 통신 중 오류가 발생했습니다.');
            });
        });

        // 목록에서 항목 클릭 시 수정 모드로 전환
        function selectFarm(farm) {
            document.getElementById('farm_id').value = farm.farm_id;
            document.getElementById('farm_name').value = farm.farm_name;
            document.getElementById('sort_order').value = farm.sort_order !== undefined ? farm.sort_order : 0;
            document.getElementById('row_count').value = farm.row_count;
            document.getElementById('col_count').value = farm.col_count;
            document.getElementById('farm_type').value = farm.farm_type;
            
            const useYnSelect = document.getElementById('use_yn');
            useYnSelect.value = farm.use_yn ? farm.use_yn : 'Y';
            useYnSelect.disabled = false; // 수정 시 사용여부 변경 가능하도록 활성화

            document.getElementById('form-title').innerText = '밭 정보 수정 (ID: ' + farm.farm_id + ')';
            
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.innerText = '수정';
            submitBtn.style.backgroundColor = '#d35400';
        }

        // 폼 초기화 (신규 입력 모드)
        function resetForm() {
            document.getElementById('farm_id').value = '';
            document.getElementById('farm_name').value = '';
            document.getElementById('sort_order').value = '0';
            document.getElementById('row_count').value = '0';
            document.getElementById('col_count').value = '0';
            document.getElementById('farm_type').value = '노지';
            
            const useYnSelect = document.getElementById('use_yn');
            useYnSelect.value = 'Y';
            useYnSelect.disabled = true; // 신규 등록 시 기본 'Y' 고정

            document.getElementById('form-title').innerText = '밭 신규 등록';
            
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.innerText = '저장';
            submitBtn.style.backgroundColor = '#2c3e50';
        }
    </script>
</body>
</html>