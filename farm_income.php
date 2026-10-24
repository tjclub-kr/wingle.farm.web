<?php
/**
 * farm_income.php : 월별 수익금액 합계 화면
 *
 * - farm_income 테이블에서 선택한 년도의 월별 수익금액(amount) 총합을 조회하여 그리드로 표시한다.
 * - 년도는 ◀ / ▶ 버튼 또는 년도 선택으로 변경하며, menu.php 의 loadPage() 로 content 영역만 갱신한다.
 * - 데이터가 없는 달은 '-' 로 표시하고, 마지막 행에 해당 년도 합계를 표시한다.
 * - 월 Row 를 터치하면 팝업이 열리고, 해당 월의 수익 상세(출고날짜, 밭이름, 상품종류, 수량, 폐기,
 *   경매단가, 입금액)를 그리드로 보여준다.
 *     · farm_income(수익) + outbound(출고, outbound_id 로 연결) + farms(밭 이름, farm_id 로 연결)
 *     · 상세 데이터는 GET ?action=detail&year=YYYY&month=M 요청에 JSON 으로 응답한다.
 * - 상세 그리드는 사용 중인 밭(farms.use_yn = 'Y')의 수익만 표시한다.
 * - 상세 그리드 정렬 : 1) 일자 ASC  2) 밭 정렬순서(farms.sort_order) ASC
 *     · 같은 일자는 날짜 셀을, 같은 일자 안에서 같은 밭(farm_id)은 밭 이름 셀과 메모 셀을 세로로 병합(rowspan)한다.
 * - 팝업 구성 : 타이틀(YYYY년 M월 수익 내역) → [밭별 합계](한 Row 에 밭 2개) → [일자별 상세] 그리드
 *     · 밭별 합계 : 해당 월 입금액을 밭(farm_id) 별로 합산, 밭 정렬순서(farms.sort_order) 순
 *       수익이 없는 밭도 사용 중인 모든 밭(farms.use_yn = 'Y')을 표시한다. (수익 없는 밭의 금액은 '-')
 *       사용 안 함(use_yn <> 'Y') 밭은 밭별 합계와 일자별 상세 모두에 표시하지 않는다.
 *     · 일자별 상세 그리드는 헤더 + 데이터 15행 + 풋터(합계) 높이로 고정하고, 15행을 넘으면 헤더/풋터를 제외한
 *       본문만 스크롤된다. 15행 미만이면 빈 행으로 채워 같은 높이를 유지한다.
 * - 상세 그리드 마지막에 [메모] 컬럼이 있다.
 *     · 해당 출고일자 + 해당 밭의 메모(memoT : group_id='outbound', id=YYYYMMDD+밭ID)가 있으면 메모 아이콘 표시
 *       (내용이 없거나 0 바이트이면 아이콘 숨김)
 *     · 메모 아이콘을 터치하면 상세 팝업 위에 메모 내용 팝업이 추가로 열린다. (읽기 전용 / 닫기 버튼만 있음)
 */
declare(strict_types=1);

/* --------------------------------------------------
 * DB 연결 설정
 * -------------------------------------------------- */
require_once __DIR__ . '/db_connect.php';

if (!function_exists('farm_income_h')) {
    function farm_income_h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('farm_income_json')) {
    function farm_income_json(array $data, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* --------------------------------------------------
 * 월 상세 조회 (팝업용 JSON)
 *   farm_income 의 해당 월 수익 Row 를 outbound / farms 와 조합하여 반환
 * -------------------------------------------------- */
if (($_GET['action'] ?? '') === 'detail') {
    $dYear  = (string)($_GET['year'] ?? '');
    $dMonth = (string)($_GET['month'] ?? '');

    if (!preg_match('/^\d{4}$/', $dYear) || !preg_match('/^\d{1,2}$/', $dMonth)
        || (int)$dYear < 2000 || (int)$dYear > 2100 || (int)$dMonth < 1 || (int)$dMonth > 12) {
        farm_income_json(['success' => false, 'message' => '조회 조건이 올바르지 않습니다.'], 400);
    }

    $dStart = sprintf('%04d-%02d-01', (int)$dYear, (int)$dMonth);
    $dEnd   = (new DateTimeImmutable($dStart))->modify('+1 month')->format('Y-m-d');

    try {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(o.outbound_date, fi.income_date) AS outbound_date,
                fi.farm_id                                AS farm_id,
                COALESCE(f.farm_name, '')                 AS farm_name,
                COALESCE(f.sort_order, 2147483647)        AS farm_sort,
                COALESCE(o.product_type, '')              AS product_type,
                COALESCE(o.quantity, 0)                   AS quantity,
                COALESCE(o.disposal, 0)                   AS disposal,
                COALESCE(o.auction_unit_price, 0)         AS auction_unit_price,
                COALESCE(o.deposit_amount, fi.amount)     AS deposit_amount
            FROM farm_income fi
            LEFT JOIN outbound o
                   ON o.outbound_id = fi.outbound_id
            LEFT JOIN farms f
                   ON f.farm_id = fi.farm_id
            WHERE fi.income_date >= :start_date
              AND fi.income_date <  :end_date
              AND f.use_yn = 'Y'                                -- 사용 중인 밭만 (사용 안 함 밭은 제외)
            ORDER BY
                COALESCE(o.outbound_date, fi.income_date) ASC,   -- 1. 일자 오름차순
                COALESCE(f.sort_order, 2147483647) ASC,          -- 2. 밭 정렬순서(farms.sort_order) 오름차순
                fi.farm_id ASC,                                  --    (정렬순서가 같은 밭도 같은 밭끼리 붙도록)
                o.product_type ASC,
                fi.farm_income_id ASC
        ");
        $stmt->bindValue(':start_date', $dStart, PDO::PARAM_STR);
        $stmt->bindValue(':end_date', $dEnd, PDO::PARAM_STR);
        $stmt->execute();

        $items = [];
        $memoIds = []; // 조회 대상 메모 ID 목록 (중복 제거)
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            // 메모 ID = YYYYMMDD + 밭ID  (memoT.id 는 INT)
            $memoIdStr = str_replace('-', '', (string)$r['outbound_date']) . (int)$r['farm_id'];
            $memoKey = null;
            if (strlen($memoIdStr) <= 10 && (int)$memoIdStr <= 2147483647) {
                $memoKey = (int)$memoIdStr;
                $memoIds[$memoKey] = true;
            }

            $items[] = [
                'memo_key'           => $memoKey,
                'farm_id'            => (int)$r['farm_id'],
                'outbound_date'      => (string)$r['outbound_date'],
                'farm_name'          => (string)$r['farm_name'],
                'farm_sort'          => (int)$r['farm_sort'],
                'product_type'       => (string)$r['product_type'],
                'quantity'           => (float)$r['quantity'],
                'disposal'           => (float)$r['disposal'],
                'auction_unit_price' => (float)$r['auction_unit_price'],
                'deposit_amount'     => (float)$r['deposit_amount'],
            ];
        }

        /* ----------------------------------------------
         * 메모 조회 : group_id = 'outbound' AND id IN (YYYYMMDD+밭ID ...)
         *   내용이 없거나 공백뿐(0 바이트)인 메모는 결과에서 제외 → 화면에 메모 아이콘이 나타나지 않음
         * ---------------------------------------------- */
        $memos = []; // memoId => 메모 내용
        if (count($memoIds) > 0) {
            $idList  = array_keys($memoIds);
            $holders = implode(',', array_fill(0, count($idList), '?'));
            $memoStmt = $pdo->prepare(
                "SELECT id, content FROM memoT WHERE group_id = 'outbound' AND id IN ($holders)"
            );
            $memoStmt->execute($idList);

            foreach ($memoStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $content = trim((string)($m['content'] ?? ''));
                if ($content !== '') {
                    $memos[(string)(int)$m['id']] = $content;
                }
            }
        }

        /* ----------------------------------------------
         * 사용 중인(use_yn = 'Y') 밭 목록 (수익이 없는 밭도 밭별 합계에 표시하기 위함) : 정렬순서(sort_order) → 밭 ID 순
         * ---------------------------------------------- */
        $farmList = [];
        $farmStmt = $pdo->query(
            "SELECT farm_id, COALESCE(farm_name, '') AS farm_name, COALESCE(sort_order, 2147483647) AS farm_sort
               FROM farms
              WHERE use_yn = 'Y'
              ORDER BY COALESCE(sort_order, 2147483647) ASC, farm_id ASC"
        );
        foreach ($farmStmt->fetchAll(PDO::FETCH_ASSOC) as $fr) {
            $farmList[] = [
                'farm_id'   => (int)$fr['farm_id'],
                'farm_name' => (string)$fr['farm_name'],
                'farm_sort' => (int)$fr['farm_sort'],
            ];
        }

        farm_income_json([
            'success' => true,
            'year'    => (int)$dYear,
            'month'   => (int)$dMonth,
            'items'   => $items,
            'farms'   => $farmList,
            'memos'   => (object)$memos, // { "메모ID": "내용" }  (비어 있어도 JSON 객체로 반환)
        ]);
    } catch (Throwable $e) {
        error_log('[farm_income detail] ' . $e->getMessage());
        farm_income_json(['success' => false, 'message' => '수익 상세 조회 중 DB 오류가 발생했습니다.'], 500);
    }
}

/* --------------------------------------------------
 * 조회 년도 (기본: 올해)
 * -------------------------------------------------- */
$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');

$searchYear = $currentYear;
if (isset($_GET['year']) && preg_match('/^\d{4}$/', (string)$_GET['year'])) {
    $y = (int)$_GET['year'];
    if ($y >= 2000 && $y <= 2100) {
        $searchYear = $y;
    }
}

$startDate = sprintf('%04d-01-01', $searchYear);
$endDate   = sprintf('%04d-01-01', $searchYear + 1);

/* --------------------------------------------------
 * 월별 합계 조회
 * -------------------------------------------------- */
$monthly    = array_fill(1, 12, 0.0); // 월 => 금액
$monthCount = array_fill(1, 12, 0);   // 월 => 건수
$yearOptions = [$searchYear, $currentYear];
$queryError = '';

try {
    $stmt = $pdo->prepare("
        SELECT
            MONTH(income_date) AS m,
            SUM(amount)        AS total_amount,
            COUNT(*)           AS cnt
        FROM farm_income
        WHERE income_date >= :start_date
          AND income_date <  :end_date
        GROUP BY MONTH(income_date)
    ");
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindValue(':end_date', $endDate, PDO::PARAM_STR);
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $m = (int)$r['m'];
        if ($m >= 1 && $m <= 12) {
            $monthly[$m]    = (float)$r['total_amount'];
            $monthCount[$m] = (int)$r['cnt'];
        }
    }

    // 년도 선택 목록 : 데이터가 존재하는 최소 ~ 최대 년도
    $range = $pdo->query('SELECT MIN(YEAR(income_date)) AS min_y, MAX(YEAR(income_date)) AS max_y FROM farm_income')
        ->fetch(PDO::FETCH_ASSOC);
    if ($range && $range['min_y'] !== null) {
        $yearOptions[] = (int)$range['min_y'];
        $yearOptions[] = (int)$range['max_y'];
    }
} catch (PDOException $e) {
    $queryError = $e->getMessage();
}

$minYear = min($yearOptions);
$maxYear = max($yearOptions);

$yearTotal = array_sum($monthly);
$yearCount = array_sum($monthCount);
?>

<style>
    .income-wrap {
        padding: 5px;
        background: #fff;
        border-radius: 8px;
    }
    .income-header-area {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center;
        margin-bottom: 10px;
        width: 100%;
    }
    .income-year-nav {
        grid-column: 1;
        justify-self: start;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .income-year-btn {
        background-color: #4a148c;
        color: #fff;
        border: none;
        border-radius: 4px;
        width: 28px;
        height: 28px;
        font-size: 13px;
        font-weight: bold;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .income-year-btn:disabled {
        background-color: #cfc3da;
        cursor: default;
    }
    .income-year-select {
        height: 28px;
        padding: 2px 4px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
        font-family: inherit;
        background-color: #fff;
        color: #333;
        cursor: pointer;
    }
    .income-title {
        grid-column: 2;
        font-size: 16px;
        font-weight: bold;
        color: #2c3e50;
        margin: 0;
        text-align: center;
        white-space: nowrap;
    }
    .income-table-container {
        width: 100%;
        overflow-x: auto;
        border-top: 1px solid #d1c4e9;
        border-left: 1px solid #d1c4e9;
    }
    .income-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
        text-align: center;
        white-space: nowrap;
    }
    .income-table th {
        background-color: #f3e5f5;
        color: #4a148c;
        font-weight: bold;
        padding: 6px 4px;
        border-width: 0 1px 1px 0;
        border-style: solid;
        border-color: #d1c4e9;
    }
    .income-table td {
        padding: 7px 6px;
        border-width: 0 1px 1px 0;
        border-style: solid;
        border-color: #e0e0e0;
        background-color: #fff;
    }
    .income-table td.text-right { text-align: right; }
    .income-table td.text-center { text-align: center; }
    .income-table td.empty { color: #aaa; }
    .income-table tr.current-month td {
        background-color: #fffde7;
    }
    .income-table tr.total-row td {
        background-color: #ede7f6;
        font-weight: bold;
    }
    .income-table tbody tr.month-row td {
        cursor: pointer;
    }
    .income-table tbody tr.month-row:active td {
        background-color: #f3e5f5;
    }

    /* 월 상세 팝업 */
    .income-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    .income-modal-content {
        background: #fff;
        border-radius: 8px;
        width: 96%;
        max-width: 520px;
        max-height: 88vh;
        display: flex;
        flex-direction: column;
        padding: 14px 10px 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .income-modal-content h3 {
        margin: 0 0 10px;
        font-size: 15px;
        color: #2c3e50;
        text-align: center;
    }
    /* 메모 아이콘 / 메모 내용 팝업 */
    .income-memo-icon {
        background: none;
        border: none;
        padding: 0 2px;
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
    }
    .income-memo-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.45);
        z-index: 1100; /* 상세 팝업(1000) 위 */
        justify-content: center;
        align-items: center;
    }
    .income-memo-content {
        background: #fff;
        border-radius: 8px;
        width: 88%;
        max-width: 380px;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
        padding: 14px 12px 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.35);
    }
    .income-memo-content h3 {
        margin: 0 0 10px;
        font-size: 15px;
        color: #2c3e50;
        text-align: center;
    }
    .income-memo-text {
        flex: 1 1 auto;
        min-height: 60px;
        overflow-y: auto;
        padding: 8px;
        border: 1px solid #d1c4e9;
        border-radius: 4px;
        background-color: #fafafa;
        color: #333;
        font-size: 13px;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-all;
    }
    /* 팝업 본문 영역 (밭별 합계 + 일자별 상세). 화면이 작아 넘칠 때만 이 영역이 스크롤된다. */
    .income-detail-wrap {
        flex: 1 1 auto;
        min-height: 60px;
        overflow-y: auto;
    }
    .income-detail-subtitle {
        margin: 10px 0 6px;
        font-size: 13px;
        font-weight: bold;
        color: #4a148c;
        text-align: center;
    }
    .income-detail-subtitle.first { margin-top: 2px; }
    /* 밭별 합계 : 밭이름 | 금액 | 밭이름 | 금액 */
    /* 밭이 7개 이상(4줄 이상)이면 이 영역이 3줄(밭 6개) 높이로 제한되어 스크롤된다. (높이 제한은 JS) */
    .income-farm-sum-wrap {
        box-sizing: content-box;
        overflow-y: auto;
        border-top: 1px solid #d1c4e9;
        border-left: 1px solid #d1c4e9;
    }
    .income-farm-sum {
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 12px;
    }
    .income-farm-sum td {
        height: 26px;
        padding: 0 6px;
        border-width: 0 1px 1px 0;
        border-style: solid;
        border-color: #d1c4e9;
        background-color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .income-farm-sum td.name {
        text-align: center;
        font-weight: bold;
        color: #4a148c;
        background-color: #f3e5f5;
    }
    .income-farm-sum td.amt { text-align: right; }
    /* 일자별 상세 그리드 : 높이는 JS 가 (헤더 + 15행 + 풋터) 로 고정, 본문만 스크롤 */
    .income-detail-container {
        box-sizing: content-box;
        overflow: auto;
        border-top: 1px solid #d1c4e9;
        border-left: 1px solid #d1c4e9;
    }
    .income-detail-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 12px;
        text-align: center;
        white-space: nowrap;
    }
    .income-detail-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background-color: #f3e5f5;
        color: #4a148c;
        font-weight: bold;
        padding: 4px 3px;
        border-width: 0 1px 1px 0;
        border-style: solid;
        border-color: #d1c4e9;
    }
    .income-detail-table td {
        height: 26px;
        padding: 0 4px;
        line-height: 1.2;
        border-width: 0 1px 1px 0;
        border-style: solid;
        border-color: #e0e0e0;
        background-color: #fff;
    }
    .income-detail-table td.text-right { text-align: right; }
    .income-detail-table td.merged {
        vertical-align: middle;
        background-color: #fafafa;
    }
    .income-detail-table tr.detail-total td {
        background-color: #ede7f6;
        font-weight: bold;
        position: sticky;   /* 풋터는 스크롤되지 않고 아래에 고정 */
        bottom: 0;
        z-index: 2;
    }
    .income-detail-msg {
        padding: 20px;
        text-align: center;
        color: #888;
        font-size: 12px;
    }
    .income-detail-msg.error { color: #c62828; }
    .income-modal-actions {
        display: flex;
        margin-top: 10px;
    }
    .income-modal-actions button {
        flex: 1;
        padding: 8px 14px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
        background-color: #90a4ae;
        color: #fff;
    }

    .income-error,
    .income-empty {
        padding: 20px;
        text-align: center;
    }
    .income-error { color: #c62828; }
    .income-empty { color: #888; }
</style>

<div class="income-wrap">
    <div class="income-header-area">
        <div class="income-year-nav">
            <button type="button" class="income-year-btn" onclick="farmIncomeMoveYear(<?= $searchYear - 1 ?>)"
                    title="이전 년도" <?= $searchYear <= 2000 ? 'disabled' : '' ?>>◀</button>
            <select class="income-year-select" onchange="farmIncomeMoveYear(this.value)">
                <?php for ($y = $maxYear; $y >= $minYear; $y--) { ?>
                    <option value="<?= $y ?>" <?= $y === $searchYear ? 'selected' : '' ?>><?= $y ?>년</option>
                <?php } ?>
            </select>
            <button type="button" class="income-year-btn" onclick="farmIncomeMoveYear(<?= $searchYear + 1 ?>)"
                    title="다음 년도" <?= $searchYear >= 2100 ? 'disabled' : '' ?>>▶</button>
        </div>

        <h2 class="income-title">💰 월별 수익</h2>
    </div>

    <div class="income-table-container">
        <table class="income-table" id="incomeTable">
            <thead>
                <tr>
                    <th style="width: 25%;">월</th>
                    <th style="width: 25%;">건수</th>
                    <th>수익 금액</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($queryError !== '') { ?>
                    <tr>
                        <td colspan="3" class="income-error">
                            수익 데이터 조회 오류: <?= farm_income_h($queryError) ?>
                        </td>
                    </tr>
                <?php } else { ?>
                    <?php for ($m = 1; $m <= 12; $m++) {
                        $isCurrent = ($searchYear === $currentYear && $m === $currentMonth);
                        $hasData   = $monthCount[$m] > 0;
                    ?>
                        <tr class="month-row<?= $isCurrent ? ' current-month' : '' ?>"
                            onclick="farmIncomeOpenDetail(<?= $searchYear ?>, <?= $m ?>)">
                            <td class="text-center"><?= $m ?>월</td>
                            <td class="text-center<?= $hasData ? '' : ' empty' ?>"><?= $hasData ? number_format($monthCount[$m]) : '-' ?></td>
                            <td class="text-right<?= $hasData ? '' : ' empty' ?>"><?= $hasData ? number_format($monthly[$m]) : '-' ?></td>
                        </tr>
                    <?php } ?>
                    <tr class="total-row">
                        <td class="text-center"><?= $searchYear ?>년 합계</td>
                        <td class="text-center"><?= number_format($yearCount) ?></td>
                        <td class="text-right"><?= number_format($yearTotal) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 월 상세 팝업 -->
<div class="income-modal-overlay" id="incomeDetailModal" onclick="if (event.target === this) farmIncomeCloseDetail();">
    <div class="income-modal-content">
        <h3 id="incomeDetailTitle">수익 내역</h3>
        <div class="income-detail-wrap" id="incomeDetailBody"></div>
        <div class="income-modal-actions">
            <button type="button" onclick="farmIncomeCloseDetail()">닫기</button>
        </div>
    </div>
</div>

<!-- 메모 내용 팝업 (상세 팝업 위에 추가로 열림 / 읽기 전용 / 닫기 버튼만) -->
<div class="income-memo-overlay" id="incomeMemoModal" onclick="if (event.target === this) farmIncomeCloseMemo();">
    <div class="income-memo-content">
        <h3>메모</h3>
        <div class="income-memo-text" id="incomeMemoText"></div>
        <div class="income-modal-actions">
            <button type="button" onclick="farmIncomeCloseMemo()">닫기</button>
        </div>
    </div>
</div>

<script>
// 년도 이동 : 다른 화면들과 동일하게 menu.php 의 loadPage() 로 content 영역만 갱신
function farmIncomeMoveYear(year) {
    if (typeof loadPage === 'function') {
        loadPage('farm_income.php?year=' + encodeURIComponent(year), '수입 내역');
    } else {
        location.href = 'farm_income.php?year=' + encodeURIComponent(year);
    }
}

function farmIncomeEsc(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function farmIncomeNum(val) {
    const n = Math.round(Number(val));
    return isNaN(n) ? '-' : n.toLocaleString();
}

function farmIncomeCloseDetail() {
    window.farmIncomeDetailToken = (window.farmIncomeDetailToken || 0) + 1; // 진행 중인 요청 결과 무시
    const modal = document.getElementById('incomeDetailModal');
    if (modal) modal.style.display = 'none';
    farmIncomeCloseMemo();
}

// 월 Row 터치 : 해당 월의 수익 상세(출고날짜, 밭이름, 상품종류, 수량, 폐기, 경매단가, 입금액) 팝업
function farmIncomeOpenDetail(year, month) {
    const modal = document.getElementById('incomeDetailModal');
    const body = document.getElementById('incomeDetailBody');
    if (!modal || !body) return;

    document.getElementById('incomeDetailTitle').innerText = year + '년 ' + month + '월 수익 내역';
    window.farmIncomeMemos = {};
    window.farmIncomeFarms = [];
    body.innerHTML = '<div class="income-detail-msg">불러오는 중...</div>';
    modal.style.display = 'flex';

    const token = (window.farmIncomeDetailToken = (window.farmIncomeDetailToken || 0) + 1);

    fetch('farm_income.php?action=detail&year=' + encodeURIComponent(year) + '&month=' + encodeURIComponent(month), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (res) {
            return res.json().catch(function () { return { success: false, message: '서버 응답이 올바르지 않습니다.' }; });
        })
        .then(function (data) {
            if (token !== window.farmIncomeDetailToken) return; // 이미 닫혔거나 다른 월 요청
            if (!data || !data.success) {
                body.innerHTML = '<div class="income-detail-msg error">' + farmIncomeEsc((data && data.message) || '조회 중 오류가 발생했습니다.') + '</div>';
                return;
            }
            window.farmIncomeMemos = data.memos || {};
            window.farmIncomeFarms = data.farms || [];
            farmIncomeRenderDetail(body, data.items || []);
        })
        .catch(function () {
            if (token !== window.farmIncomeDetailToken) return;
            body.innerHTML = '<div class="income-detail-msg error">수익 상세를 조회하지 못했습니다. 네트워크를 확인해 주세요.</div>';
        });
}

// 메모 내용 팝업 열기 (상세 팝업 위, 읽기 전용)
function farmIncomeOpenMemo(memoKey) {
    const text = (window.farmIncomeMemos || {})[String(memoKey)];
    if (!text) return; // 내용이 없으면 열지 않음

    document.getElementById('incomeMemoText').textContent = text; // textContent : 읽기 전용, HTML 이스케이프 불필요
    document.getElementById('incomeMemoModal').style.display = 'flex';
}

function farmIncomeCloseMemo() {
    const modal = document.getElementById('incomeMemoModal');
    if (modal) modal.style.display = 'none';
}

// 밭별 합계 (한 Row 에 밭 2개 : 밭이름 | 금액 | 밭이름 | 금액)
function farmIncomeBuildFarmSummary(items) {
    const map = {};
    const keys = [];
    function ensureFarm(id, name, sort) {
        const k = String(id);
        if (!map[k]) {
            map[k] = { id: Number(id) || 0, sort: Number(sort), name: name || '', sum: 0, hasIncome: false };
            if (isNaN(map[k].sort)) map[k].sort = 2147483647;
            keys.push(k);
        }
        return map[k];
    }

    // 1) 사용 중인(use_yn = 'Y') 밭 등록 (수익이 없는 밭도 표시)
    (window.farmIncomeFarms || []).forEach(function (f) { ensureFarm(f.farm_id, f.farm_name, f.farm_sort); });
    if (keys.length === 0) return ''; // 표시할 밭이 없으면 밭별 합계 영역 자체를 숨김

    // 2) 해당 월 입금액 합산 (사용 안 함 밭의 수익은 밭별 합계에 포함하지 않음)
    items.forEach(function (it) {
        const f = map[String(it.farm_id)];
        if (!f) return;
        f.sum += Number(it.deposit_amount) || 0;
        f.hasIncome = true;
    });

    // 밭 정렬순서(farms.sort_order) → 밭 ID 순
    const farms = keys.map(function (k) { return map[k]; }).sort(function (a, b) {
        return (a.sort - b.sort) || (a.id - b.id);
    });

    let html = '<div class="income-detail-subtitle first">밭별 합계</div>'
        + '<div class="income-farm-sum-wrap"><table class="income-farm-sum"><colgroup><col style="width:20%"><col style="width:30%"><col style="width:20%"><col style="width:30%"></colgroup><tbody>';
    for (let i = 0; i < farms.length; i += 2) {
        const a = farms[i];
        const b = farms[i + 1]; // 밭 수가 홀수이면 마지막 Row 의 두 번째 칸은 빈 칸
        html += '<tr>'
            + '<td class="name">' + farmIncomeEsc(a.name) + '</td><td class="amt">' + (a.hasIncome ? farmIncomeNum(a.sum) : '-') + '</td>'
            + (b
                ? '<td class="name">' + farmIncomeEsc(b.name) + '</td><td class="amt">' + (b.hasIncome ? farmIncomeNum(b.sum) : '-') + '</td>'
                : '<td></td><td></td>')
            + '</tr>';
    }
    return html + '</tbody></table></div>';
}

// 밭별 합계 : 밭 6개(3줄)까지는 그대로 표시, 그 이상이면 3줄 높이로 제한하고 스크롤
function farmIncomeLimitSummary(body) {
    const SUMMARY_VISIBLE_ROWS = 3;
    const sumWrap = body.querySelector('.income-farm-sum-wrap');
    const sumRows = sumWrap ? sumWrap.querySelectorAll('tr') : [];
    if (sumWrap && sumRows.length > SUMMARY_VISIBLE_ROWS) {
        let sh = 0;
        for (let r = 0; r < SUMMARY_VISIBLE_ROWS; r++) sh += sumRows[r].offsetHeight;
        sumWrap.style.maxHeight = sh + 'px';
    }
}

function farmIncomeRenderDetail(body, items) {
    if (items.length === 0) {
        // 수익 내역이 없는 달도 밭별 합계(전 밭 '-')는 표시
        const emptySummary = (window.farmIncomeFarms || []).length > 0 ? farmIncomeBuildFarmSummary(items) : '';
        body.innerHTML = emptySummary + '<div class="income-detail-msg">해당 월의 수익 내역이 없어.</div>';
        farmIncomeLimitSummary(body);
        return;
    }

    const VISIBLE_ROWS = 15; // 일자별 상세 그리드에 항상 보이는 데이터 행 수 (초과하면 본문만 스크롤)

    let totalQty = 0, totalDisposal = 0, totalDeposit = 0;
    let html = farmIncomeBuildFarmSummary(items)
        + '<div class="income-detail-subtitle">일자별 상세</div>'
        + '<div class="income-detail-container"><table class="income-detail-table"><thead><tr>'
        + '<th>날짜</th><th>밭</th><th>상품<br>종류</th><th>수<br>량</th><th>폐<br>기</th><th>경매<br>단가</th><th>입금액</th><th>메모</th>'
        + '</tr></thead><tbody>';

    // 세로 병합(rowspan) 계산 : 서버에서 일자 → 밭 순으로 정렬되어 있으므로 연속된 같은 값끼리 묶는다.
    //  - dateSpan[i] : i 번째 행이 같은 일자 묶음의 첫 행이면 묶음 행 수, 아니면 0
    //  - farmSpan[i] : i 번째 행이 같은 일자+같은 밭(farm_id) 묶음의 첫 행이면 묶음 행 수, 아니면 0
    const dateSpan = new Array(items.length).fill(0);
    const farmSpan = new Array(items.length).fill(0);
    let di = 0;
    while (di < items.length) {
        let dj = di;
        while (dj < items.length && items[dj].outbound_date === items[di].outbound_date) dj++;
        dateSpan[di] = dj - di;

        let fk = di;
        while (fk < dj) {
            let fm = fk;
            while (fm < dj && items[fm].farm_id === items[fk].farm_id) fm++;
            farmSpan[fk] = fm - fk;
            fk = fm;
        }
        di = dj;
    }

    items.forEach(function (it, idx) {
        totalQty += Number(it.quantity) || 0;
        totalDisposal += Number(it.disposal) || 0;
        totalDeposit += Number(it.deposit_amount) || 0;

        const d = String(it.outbound_date || '');
        const dateText = d.length >= 10 ? d.substring(5, 7) + '/' + d.substring(8, 10) : d;

        // 해당 일자/밭의 메모 내용이 있을 때만 아이콘 표시
        const hasMemo = it.memo_key !== null && it.memo_key !== undefined
            && Object.prototype.hasOwnProperty.call(window.farmIncomeMemos || {}, String(it.memo_key));
        const memoIcon = hasMemo
            ? '<button type="button" class="income-memo-icon" title="메모" aria-label="메모" onclick="farmIncomeOpenMemo(' + Number(it.memo_key) + ')">📝</button>'
            : '';
        // 메모는 일자+밭 단위이므로 밭 이름 셀과 같은 묶음(farmSpan)으로 세로 병합
        const memoCell = farmSpan[idx] > 0
            ? '<td class="merged" rowspan="' + farmSpan[idx] + '">' + memoIcon + '</td>'
            : '';

        const dateCell = dateSpan[idx] > 0
            ? '<td class="merged" rowspan="' + dateSpan[idx] + '">' + farmIncomeEsc(dateText) + '</td>'
            : '';
        const farmCell = farmSpan[idx] > 0
            ? '<td class="merged" rowspan="' + farmSpan[idx] + '">' + farmIncomeEsc(it.farm_name) + '</td>'
            : '';

        html += '<tr>'
            + dateCell
            + farmCell
            + '<td>' + farmIncomeEsc(it.product_type) + '</td>'
            + '<td class="text-right">' + farmIncomeNum(it.quantity) + '</td>'
            + '<td class="text-right">' + farmIncomeNum(it.disposal) + '</td>'
            + '<td class="text-right">' + farmIncomeNum(it.auction_unit_price) + '</td>'
            + '<td class="text-right">' + farmIncomeNum(it.deposit_amount) + '</td>'
            + memoCell
            + '</tr>';
    });

    // 데이터가 15행 미만이면 빈 행으로 채워 (헤더 + 15행 + 풋터) 높이를 유지
    for (let f = items.length; f < VISIBLE_ROWS; f++) {
        html += '<tr class="detail-filler"><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
    }

    html += '</tbody><tfoot><tr class="detail-total">'
        + '<td colspan="3">합계 (' + items.length + '건)</td>'
        + '<td class="text-right">' + farmIncomeNum(totalQty) + '</td>'
        + '<td class="text-right">' + farmIncomeNum(totalDisposal) + '</td>'
        + '<td class="text-right"> </td>'
        + '<td class="text-right">' + farmIncomeNum(totalDeposit) + '</td>'
        + '<td> </td>'
        + '</tr></tfoot></table></div>';

    body.innerHTML = html;

    farmIncomeLimitSummary(body);

    // 그리드 높이 고정 : 헤더 + 데이터 15행 + 풋터 (실제 렌더링된 높이를 측정)
    const wrap = body.querySelector('.income-detail-container');
    const table = wrap ? wrap.querySelector('table') : null;
    if (wrap && table && table.tHead && table.tFoot) {
        let h = table.tHead.offsetHeight + table.tFoot.offsetHeight;
        for (let r = 0; r < VISIBLE_ROWS && r < table.tBodies[0].rows.length; r++) {
            h += table.tBodies[0].rows[r].offsetHeight;
        }
        wrap.style.height = h + 'px';
    }
}
</script>