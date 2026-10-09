<?php
date_default_timezone_set('Asia/Seoul');

require_once __DIR__ . '/db_connect.php';

$message = '';
$selectedTable =$_POST['target_table'] ?? '';

$memoGroups = [];
try {
    $groupStmt =$pdo->query("
        SELECT DISTINCT CASE WHEN m.group_id = 'memo' THEN 'memo' ELSE COALESCE(f.farm_name, m.group_id) END AS group_id 
        FROM memoT m 
        LEFT JOIN farms f ON m.group_id = f.farm_id
        WHERE m.group_id IS NOT NULL AND m.group_id != ''
    ");
    $memoGroups =$groupStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

$farmsList = [];
try {
    $farmStmt =$pdo->query("SELECT farm_id, farm_name FROM farms ORDER BY farm_id ASC");
    $farmsList =$farmStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'delete') {
    try {
        $now = date('Y년 m월 d일 H시 i분 s초');
        if ($selectedTable === 'dailytasksT') {
            $deleteType =$_POST['dailytasksT_type'] ?? 'all';
            if ($deleteType === 'all') {
                $stmt =$pdo->prepare("DELETE FROM dailytasksT");
                $stmt->execute();$count = $stmt->rowCount();$message = "[{$now}] {$count} 건 삭제";
            } elseif ($deleteType === 'date') {
                $date =$_POST['dailytasksT_date'] ?? '';
                if ($date) {
                    $stmt =$pdo->prepare("DELETE FROM dailytasksT WHERE task_date = ?");
                    $stmt->execute([$date]);$count = $stmt->rowCount();$message = "[{$now}] {$count} 건 삭제";
                } else { $message = "날짜를 지정해주세요."; }
            }
        } 
        elseif ($selectedTable === 'farms') {
            $deleteType =$_POST['farms_type'] ?? 'all';
            if ($deleteType === 'all') {
                $stmt =$pdo->prepare("DELETE FROM farms");
                $stmt->execute();$count = $stmt->rowCount();$message = "[{$now}] {$count} 건 삭제";
            }
        } 
        elseif ($selectedTable === 'memoT') {
            $deleteType =$_POST['memoT_type'] ?? 'all';
            if ($deleteType === 'all') {
                $stmt =$pdo->prepare("DELETE FROM memoT");
                $stmt->execute();$count = $stmt->rowCount();$message = "[{$now}] {$count} 건 삭제";
            } elseif ($deleteType === 'conditional') {
                $group =$_POST['memoT_group'] ?? '';
                $date =$_POST['memoT_date'] ?? '';
                $whereClauses = [];$params = [];
                if ($group !== '') {$whereClauses[] = "(m.group_id = ? OR (CASE WHEN m.group_id = 'memo' THEN 'memo' ELSE COALESCE(f.farm_name, m.group_id) END) = ?)";
                    $params[] =$group; $params[] =$group;
                }
                if ($date !== '') {$whereClauses[] = "DATE(reg_date) = ?"; $params[] =$date; }
                if (count($whereClauses) > 0) {$sql = "DELETE m FROM memoT m LEFT JOIN farms f ON m.group_id = f.farm_id WHERE " . implode(' AND ', $whereClauses);
                    $stmt =$pdo->prepare($sql);$stmt->execute($params);$count = $stmt->rowCount();$message = "[{$now}] {$count} 건 삭제";
                } else { $message = "조건별 삭제를 위해 그룹 또는 날짜 중 하나 이상을 지정해주세요."; }
            }
        } 
        elseif ($selectedTable === 'ridge_records') {
            $deleteType =$_POST['ridge_records_type'] ?? 'all';
            if ($deleteType === 'all') {
                $stmt =$pdo->prepare("DELETE FROM ridge_records");
                $stmt->execute();$count = $stmt->rowCount();$message = "[{$now}] {$count} 건 삭제";
            } elseif ($deleteType === 'farm') {
                $farm =$_POST['ridge_records_farm'] ?? '';
                if ($farm) {
                    $stmt =$pdo->prepare("DELETE FROM ridge_records WHERE farm_id = ?");
                    $stmt->execute([$farm]);$count = $stmt->rowCount();$message = "[{$now}] {$count} 건 삭제";
                } else { $message = "삭제할 밭을 선택해주세요."; }
            }
        }
    } catch (PDOException $e) {
        $message = "삭제 중 오류가 발생했습니다: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>데이터 삭제 관리 (Data Clear)</title>
    <style>
        body { font-family: 'Malgun Gothic', sans-serif; background-color: #f4f7f6; padding: 20px; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 20px 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-area { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; }
        h2 { text-align: center; color: #e74c3c; margin: 0; }
        .bottom-space-link { vertical-align: bottom; cursor: pointer; color: #888; font-size: 0.9rem; user-select: none; }
        .bottom-space-link:hover { color: #333; text-decoration: underline; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 8px; }
        select, input[type="text"], input[type="date"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 8px; }
        .radio-group { margin: 10px 0; padding: 10px; background: #f9f9f9; border: 1px solid #eee; border-radius: 4px; }
        .radio-group label { display: inline-block; font-weight: normal; margin-right: 15px; cursor: pointer; }
        .condition-block { display: none; margin-top: 15px; }
        .sub-input-block { display: none; margin-top: 10px; padding-left: 10px; border-left: 3px solid #3498db; }
        .btn-delete { width: 100%; background-color: #e74c3c; color: white; border: none; padding: 15px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; transition: background 0.3s; margin-top: 15px; }
        .btn-delete:hover { background-color: #c0392b; }
        .alert { padding: 12px 15px; margin-top: 15px; border-radius: 4px; background-color: #dff0d8; color: #3c763d; border: 1px solid #d6e9c6; text-align: center; font-weight: bold; }
        .alert.error { background-color: #f2dede; color: #a94442; border-color: #ebccd1; }
        #content-area { margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header-area">
        <h2>데이터 삭제 관리</h2>
        <span class="bottom-space-link" onclick="loadMemo()">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
    </div>
    <form method="POST" onsubmit="return confirmDelete()">
        <input type="hidden" name="action" value="delete">
        <div class="form-group">
            <label for="target_table">테이블 선택</label>
            <select name="target_table" id="target_table" onchange="changeTable()" required>
                <option value="">-- 테이블을 선택하세요 --</option>
                <option value="dailytasksT" <?= $selectedTable === 'dailytasksT' ? 'selected' : '' ?>>dailytasksT (오늘의 할일)</option>
                <option value="farms" <?= $selectedTable === 'farms' ? 'selected' : '' ?>>farms (밭 정보)</option>
                <option value="memoT" <?= $selectedTable === 'memoT' ? 'selected' : '' ?>>memoT (메모)</option>
                <option value="ridge_records" <?= $selectedTable === 'ridge_records' ? 'selected' : '' ?>>ridge_records (밭(이랑) 이력)</option>
            </select>
        </div>
        <div id="block_dailytasksT" class="condition-block">
            <label>삭제 조건 선택</label>
            <div class="radio-group">
                <label><input type="radio" name="dailytasksT_type" value="all" checked onchange="toggleInputs('dailytasksT')"> 전체 삭제</label>
                <label><input type="radio" name="dailytasksT_type" value="date" onchange="toggleInputs('dailytasksT')"> 날짜별 삭제</label>
            </div>
            <div id="input_dailytasksT_date" class="sub-input-block"><input type="date" name="dailytasksT_date"></div>
        </div>
        <div id="block_farms" class="condition-block">
            <label>삭제 조건 선택</label>
            <div class="radio-group"><label><input type="radio" name="farms_type" value="all" checked> 전체 삭제</label></div>
        </div>
        <div id="block_memoT" class="condition-block">
            <label>삭제 조건 선택</label>
            <div class="radio-group">
                <label><input type="radio" name="memoT_type" value="all" checked onchange="toggleInputs('memoT')"> 전체 삭제</label>
                <label><input type="radio" name="memoT_type" value="conditional" onchange="toggleInputs('memoT')"> 조건별 삭제</label>
            </div>
            <div id="input_memoT_conditional" class="sub-input-block">
                <label style="font-size: 0.9rem; font-weight: normal; margin-bottom: 4px;">그룹 선택 (선택사항)</label>
                <select name="memoT_group">
                    <option value="">-- 전체 그룹 --</option>
                    <?php foreach ($memoGroups as$groupVal): ?>
                        <option value="<?= htmlspecialchars($groupVal) ?>"><?= htmlspecialchars($groupVal) ?></option>
                    <?php endforeach; ?>
                </select>
                <label style="font-size: 0.9rem; font-weight: normal; margin-bottom: 4px; margin-top: 8px;">날짜 선택 (선택사항)</label>
                <input type="date" name="memoT_date">
            </div>
        </div>
        <div id="block_ridge_records" class="condition-block">
            <label>삭제 조건 선택</label>
            <div class="radio-group">
                <label><input type="radio" name="ridge_records_type" value="all" checked onchange="toggleInputs('ridge_records')"> 전체 삭제</label>
                <label><input type="radio" name="ridge_records_type" value="farm" onchange="toggleInputs('ridge_records')"> 밭별 삭제</label>
            </div>
            <div id="input_ridge_records_farm" class="sub-input-block">
                <select name="ridge_records_farm">
                    <option value="">-- 밭 선택 --</option>
                    <?php foreach ($farmsList as$farm): ?>
                        <option value="<?= htmlspecialchars($farm['farm_id']) ?>"><?= htmlspecialchars($farm['farm_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php if ($message): ?>
            <div class="alert <?= (strpos($message, '오류') !== false || strpos($message, '선택') !== false \vert{}\vert{} strpos($message, '지정') !== false) ? 'error' : '' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn-delete" id="submitBtn" style="display:none;">선택 조건으로 데이터 삭제</button>
    </form>
    <div id="content-area"></div>
</div>
<script>
    function changeTable() {
        const selectedTable = document.getElementById('target_table').value;
        const blocks = document.querySelectorAll('.condition-block');
        const submitBtn = document.getElementById('submitBtn');
        blocks.forEach(block => block.style.display = 'none');
        if (selectedTable) {
            document.getElementById('block_' + selectedTable).style.display = 'block';
            submitBtn.style.display = 'block';
            toggleInputs(selectedTable);
        } else { submitBtn.style.display = 'none'; }
    }
    function toggleInputs(tableName) {
        const inputs = document.querySelectorAll('#block_' + tableName + ' .sub-input-block');
        inputs.forEach(input => input.style.display = 'none');
        const checkedRadio = document.querySelector('input[name="' + tableName + '_type"]:checked');
        if (checkedRadio && checkedRadio.value !== 'all') {
            const targetInput = document.getElementById('input_' + tableName + '_' + checkedRadio.value);
            if (targetInput) targetInput.style.display = 'block';
        }
    }
    function confirmDelete() {
        const tableSelect = document.getElementById('target_table');
        if (!tableSelect.value) { alert('삭제할 테이블을 선택해주세요.'); return false; }
        return confirm(`[경고] 데이터를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.`);
    }
    function loadMemo() {
        fetch('memo.php')
            .then(res => res.text())
            .then(html => { document.getElementById('content-area').innerHTML = html; })
            .catch(err => { alert('memo.php를 불러오는 중 오류가 발생했습니다.'); });
    }
    window.onload = function() { if (document.getElementById('target_table').value) { changeTable(); } };
</script>
</body>
</html>