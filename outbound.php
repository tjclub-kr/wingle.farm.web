<?php
/**
 * outbound.php : 출고 관리 화면 (DB 연동 및 동적 출력)
 *
 * - 화면 조회 (GET)  : search_ym(YYYY-MM) 조건으로 출고 데이터 조회
 * - 저장 처리 (POST) : JSON({action:'save', rows:[...], memos:[...]}) 수신
 *      · 신규 Row  → INSERT
 *      · 기존 Row  → outbound_id 를 Key 로 UPDATE
 *      · 삭제 Row  → mode 'delete' : 마감되지 않았고 경매단가·입금액이 모두 0 인 Row 만 DELETE
 *      · 메모      → memoT (group_id='outbound', id=YYYYMMDD+밭ID) INSERT / UPDATE
 *   저장 완료 후 화면에서 현재 선택된 년/월 조건으로 다시 조회한다.
 *
 * - 마감 : 수수료 칸을 터치하면 같은 날짜의 Row 가 체크박스로 나열된 마감/마감해제 팝업이 열린다.
 *   (저장 버튼과 무관하게 일괄 처리 후 재조회)
 *      · 마감     → outbound.is_closed = 1 + farm_income INSERT
 *      · 마감해제 → outbound.is_closed = 0 + farm_income DELETE (outbound_id 기준)
 *
 * - 계산 : 판매수량 = 수량 - 폐기
 *      · 경매총액 = 경매단가 × 판매수량
 *      · 입금단가 = 입금액 ÷ 판매수량
 *
 * - 메모 : 밭 이름을 터치하면 메모 등록/수정 팝업이 열린다.
 *   팝업에서 확인하면 화면에만 반영(밭 이름 푸른 글씨)되고,
 *   헤더 [저장] 버튼을 눌렀을 때 출고 Row 와 함께 DB 에 저장된다.
 */
declare(strict_types=1);

/* --------------------------------------------------
 * DB 연결 설정
 * -------------------------------------------------- */
require_once __DIR__ . '/db_connect.php';

/* --------------------------------------------------
 * 공용 함수
 * -------------------------------------------------- */
if (!function_exists('outbound_h')) {
    function outbound_h($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('outbound_uuid')) {
    /** UUID v4 생성 */
    function outbound_uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}

if (!function_exists('outbound_num')) {
    /** 숫자 값 검증 (빈 값은 0, 음수/범위 초과/숫자 아님은 예외) */
    function outbound_num($v, float $max, string $label): float
    {
        if (is_string($v)) {
            $v = str_replace(',', '', trim($v));
        }
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (!is_numeric($v)) {
            throw new InvalidArgumentException($label . ' 값이 숫자가 아닙니다.');
        }
        $n = (float)$v;
        if ($n < 0 || $n > $max) {
            throw new InvalidArgumentException($label . ' 값이 허용 범위를 벗어났습니다.');
        }
        return round($n, 2);
    }
}

if (!function_exists('outbound_json')) {
    function outbound_json(array $data, int $status = 200): void
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

if (!function_exists('outbound_set_closed')) {
    /**
     * 마감(true) / 마감해제(false) 1건 처리 (트랜잭션은 호출하는 쪽에서 관리)
     *   마감     : outbound.is_closed = 1 + farm_income INSERT (income_date = 출고일, amount = 입금액)
     *   마감해제 : outbound.is_closed = 0 + farm_income DELETE (outbound_id 기준)
     */
    function outbound_set_closed(PDO $pdo, string $targetId, bool $willClose): void
    {
        $selStmt = $pdo->prepare("
            SELECT outbound_id, outbound_date, farm_id, deposit_amount, is_closed
            FROM outbound
            WHERE outbound_id = :outbound_id
            FOR UPDATE
        ");
        $selStmt->execute([':outbound_id' => $targetId]);
        $target = $selStmt->fetch(PDO::FETCH_ASSOC);
        $selStmt->closeCursor();

        if (!$target) {
            throw new InvalidArgumentException('해당 출고 데이터를 찾을 수 없습니다.');
        }

        $isClosedNow = ((int)$target['is_closed'] === 1);

        $closeStmt = $pdo->prepare("
            UPDATE outbound
               SET is_closed = :is_closed
             WHERE outbound_id = :outbound_id
        ");
        $incomeDeleteStmt = $pdo->prepare("
            DELETE FROM farm_income
             WHERE outbound_id = :outbound_id
        ");

        if ($willClose) {
            if ($isClosedNow) {
                throw new InvalidArgumentException('이미 마감 처리된 데이터가 있습니다. 화면을 다시 조회해 주세요.');
            }
            if ((float)$target['deposit_amount'] < 1) {
                throw new InvalidArgumentException('입금액이 1 이상인 데이터만 마감할 수 있습니다.');
            }

            $closeStmt->execute([':is_closed' => 1, ':outbound_id' => $targetId]);

            // 중복 방지 : 기존 수익 데이터가 있으면 먼저 제거
            $incomeDeleteStmt->execute([':outbound_id' => $targetId]);

            $incomeInsertStmt = $pdo->prepare("
                INSERT INTO farm_income (income_date, farm_id, outbound_id, amount)
                VALUES (:income_date, :farm_id, :outbound_id, :amount)
            ");
            $incomeInsertStmt->execute([
                ':income_date' => (string)$target['outbound_date'],
                ':farm_id'     => (int)$target['farm_id'],
                ':outbound_id' => $targetId,
                ':amount'      => number_format((float)$target['deposit_amount'], 2, '.', ''),
            ]);
        } else {
            if (!$isClosedNow) {
                throw new InvalidArgumentException('이미 마감 해제된 데이터가 있습니다. 화면을 다시 조회해 주세요.');
            }

            $closeStmt->execute([':is_closed' => 0, ':outbound_id' => $targetId]);
            $incomeDeleteStmt->execute([':outbound_id' => $targetId]);
        }
    }
}

/* --------------------------------------------------
 * 밭(farms) 목록 조회 : 밭 이름 ↔ farm_id 매핑용
 * -------------------------------------------------- */
$farms = [];
try {
    $farms = $pdo->query('SELECT farm_id, farm_name FROM farms ORDER BY farm_id ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $farms = [];
}
$farmIdMap = [];
foreach ($farms as $f) {
    $farmIdMap[(int)$f['farm_id']] = true;
}

// 메모 그룹 ID (memoT.group_id)
$memoGroupId = 'outbound';

/* --------------------------------------------------
 * 저장 처리 (POST / JSON)
 * -------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode((string)file_get_contents('php://input'), true);

    /* ----------------------------------------------
     * 마감 / 마감해제 (같은 날짜 Row 일괄 처리)
     *   items : [{outbound_id, closed(1=마감, 0=마감해제)}, ...]
     *   건별 처리 로직(outbound_set_closed)은 동일하며 전체를 한 트랜잭션으로 처리
     * ---------------------------------------------- */
    if (is_array($payload) && ($payload['action'] ?? '') === 'set_closed_batch') {
        $items = $payload['items'] ?? null;
        if (!is_array($items) || count($items) === 0 || count($items) > 500) {
            outbound_json(['success' => false, 'message' => '처리할 데이터가 올바르지 않습니다.'], 400);
        }

        $closeCount = 0;
        $openCount  = 0;
        $seenIds    = [];

        try {
            $pdo->beginTransaction();

            foreach ($items as $idx => $it) {
                $no = $idx + 1;
                if (!is_array($it)) {
                    throw new InvalidArgumentException($no . '번째 항목이 올바르지 않습니다.');
                }

                $targetId = (string)($it['outbound_id'] ?? '');
                if (!preg_match('/^[0-9a-fA-F-]{36}$/', $targetId)) {
                    throw new InvalidArgumentException($no . '번째 항목의 고유번호가 올바르지 않습니다.');
                }
                if (isset($seenIds[strtolower($targetId)])) {
                    throw new InvalidArgumentException($no . '번째 항목이 중복되었습니다.');
                }
                $seenIds[strtolower($targetId)] = true;

                $willClose = ((int)($it['closed'] ?? 0) === 1);
                outbound_set_closed($pdo, $targetId, $willClose);

                if ($willClose) {
                    $closeCount++;
                } else {
                    $openCount++;
                }
            }

            $pdo->commit();

            outbound_json([
                'success'     => true,
                'closed'      => $closeCount,
                'reopened'    => $openCount,
                'message'     => "마감 {$closeCount}건, 마감해제 {$openCount}건 처리되었습니다.",
            ]);
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            outbound_json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[outbound close] ' . $e->getMessage());
            outbound_json(['success' => false, 'message' => '마감 처리 중 DB 오류가 발생했습니다.'], 500);
        }
    }

    if (
        !is_array($payload)
        || ($payload['action'] ?? '') !== 'save'
        || !isset($payload['rows'])
        || !is_array($payload['rows'])
    ) {
        outbound_json(['success' => false, 'message' => '잘못된 요청입니다.'], 400);
    }

    $memoPayload = $payload['memos'] ?? [];
    if (!is_array($memoPayload)) {
        outbound_json(['success' => false, 'message' => '메모 데이터가 올바르지 않습니다.'], 400);
    }

    if (count($payload['rows']) === 0 && count($memoPayload) === 0) {
        outbound_json(['success' => false, 'message' => '저장할 데이터가 없습니다.'], 400);
    }

    $insertCount = 0;
    $updateCount = 0;
    $deleteCount = 0;
    $memoInsertCount = 0;
    $memoUpdateCount = 0;

    try {
        $pdo->beginTransaction();

        $insertStmt = $pdo->prepare("
            INSERT INTO outbound (
                outbound_id, outbound_date, farm_id, product_type,
                quantity, disposal,
                auction_unit_price, auction_total_amount,
                deposit_amount, deposit_unit_price, commission_rate
            ) VALUES (
                :outbound_id, :outbound_date, :farm_id, :product_type,
                :quantity, :disposal,
                :auction_unit_price, :auction_total_amount,
                :deposit_amount, :deposit_unit_price, :commission_rate
            )
        ");

        $closedCheckStmt = $pdo->prepare(
            'SELECT is_closed FROM outbound WHERE outbound_id = :outbound_id FOR UPDATE'
        );

        // 삭제용 : 마감 여부 / 경매단가 / 입금액 확인 후 삭제
        $deleteCheckStmt = $pdo->prepare("
            SELECT is_closed, auction_unit_price, deposit_amount
              FROM outbound
             WHERE outbound_id = :outbound_id
               FOR UPDATE
        ");
        $deleteStmt = $pdo->prepare('DELETE FROM outbound WHERE outbound_id = :outbound_id');
        $deleteIncomeStmt = $pdo->prepare('DELETE FROM farm_income WHERE outbound_id = :outbound_id');

        $updateStmt = $pdo->prepare("
            UPDATE outbound SET
                outbound_date        = :outbound_date,
                farm_id              = :farm_id,
                product_type         = :product_type,
                quantity             = :quantity,
                disposal             = :disposal,
                auction_unit_price   = :auction_unit_price,
                auction_total_amount = :auction_total_amount,
                deposit_amount       = :deposit_amount,
                deposit_unit_price   = :deposit_unit_price,
                commission_rate      = :commission_rate
            WHERE outbound_id = :outbound_id
        ");

        foreach ($payload['rows'] as $idx => $r) {
            $no = $idx + 1;
            if (!is_array($r)) {
                throw new InvalidArgumentException($no . '번째 행 데이터가 올바르지 않습니다.');
            }

            // 삭제 → 마감되지 않았고 경매단가/입금액이 모두 0 인 Row 만 DELETE
            if (($r['mode'] ?? '') === 'delete') {
                $delId = (string)($r['outbound_id'] ?? '');
                if (!preg_match('/^[0-9a-fA-F-]{36}$/', $delId)) {
                    throw new InvalidArgumentException($no . '번째 행의 고유번호가 올바르지 않습니다.');
                }

                $deleteCheckStmt->execute([':outbound_id' => $delId]);
                $delRow = $deleteCheckStmt->fetch(PDO::FETCH_ASSOC);
                $deleteCheckStmt->closeCursor();

                if (!$delRow) {
                    throw new InvalidArgumentException($no . '번째 행의 출고 데이터를 찾을 수 없습니다. 화면을 다시 조회해 주세요.');
                }
                if ((int)$delRow['is_closed'] === 1) {
                    throw new InvalidArgumentException($no . '번째 행은 마감 처리되어 삭제할 수 없습니다.');
                }
                if ((float)$delRow['auction_unit_price'] != 0.0 || (float)$delRow['deposit_amount'] != 0.0) {
                    throw new InvalidArgumentException($no . '번째 행은 경매단가 또는 입금액이 입력되어 있어 삭제할 수 없습니다.');
                }

                $deleteIncomeStmt->execute([':outbound_id' => $delId]); // 안전장치 (미마감 Row 는 수익 데이터 없음)
                $deleteStmt->execute([':outbound_id' => $delId]);
                $deleteCount++;
                continue;
            }

            $isNew = (($r['mode'] ?? '') === 'insert');

            // 날짜
            $date = (string)($r['outbound_date'] ?? '');
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$dt || $dt->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException($no . '번째 행의 날짜가 올바르지 않습니다.');
            }

            // 밭
            $farmId = (int)($r['farm_id'] ?? 0);
            if ($farmId <= 0 || !isset($farmIdMap[$farmId])) {
                throw new InvalidArgumentException($no . '번째 행의 밭 정보가 올바르지 않습니다.');
            }

            // 상품 종류
            $productType = trim((string)($r['product_type'] ?? ''));
            if ($productType === '' || mb_strlen($productType, 'UTF-8') > 100) {
                throw new InvalidArgumentException($no . '번째 행의 상품 종류가 올바르지 않습니다.');
            }

            // 수치
            $qty          = outbound_num($r['quantity'] ?? 0, 99999999.99, $no . '번째 행 수량');
            $disposal     = outbound_num($r['disposal'] ?? 0, 99999999.99, $no . '번째 행 폐기');
            $auctionUnit  = outbound_num($r['auction_unit_price'] ?? 0, 9999999999.99, $no . '번째 행 경매단가');
            $deposit      = outbound_num($r['deposit_amount'] ?? 0, 9999999999999.99, $no . '번째 행 입금액');

            if ($disposal > $qty) {
                throw new InvalidArgumentException($no . '번째 행의 폐기는 수량보다 클 수 없습니다.');
            }

            // 파생 값 (서버에서 재계산) : 판매수량 = 수량 - 폐기
            //   경매총액 = 경매단가 × 판매수량 / 입금단가 = 입금액 ÷ 판매수량
            $netQty       = round($qty - $disposal, 2);
            $auctionTotal = round($auctionUnit * $netQty, 2);
            if ($auctionTotal > 9999999999999.99) {
                throw new InvalidArgumentException($no . '번째 행 경매총금액이 허용 범위를 벗어났습니다.');
            }
            $depositUnit = $netQty > 0 ? round($deposit / $netQty, 2) : 0.0;
            if ($depositUnit > 9999999999.99) {
                throw new InvalidArgumentException($no . '번째 행 입금단가가 허용 범위를 벗어났습니다.');
            }
            $commission  = $auctionTotal != 0.0
                ? round(($auctionTotal - $deposit) / $auctionTotal * 100, 2)
                : 0.0;
            $commission = max(-999.99, min(999.99, $commission)); // DECIMAL(5,2) 범위 보호

            $params = [
                ':outbound_date'        => $date,
                ':farm_id'              => $farmId,
                ':product_type'         => $productType,
                ':quantity'             => number_format($qty, 2, '.', ''),
                ':disposal'             => number_format($disposal, 2, '.', ''),
                ':auction_unit_price'   => number_format($auctionUnit, 2, '.', ''),
                ':auction_total_amount' => number_format($auctionTotal, 2, '.', ''),
                ':deposit_amount'       => number_format($deposit, 2, '.', ''),
                ':deposit_unit_price'   => number_format($depositUnit, 2, '.', ''),
                ':commission_rate'      => number_format($commission, 2, '.', ''),
            ];

            if ($isNew) {
                // 신규 → INSERT
                $params[':outbound_id'] = outbound_uuid();
                $insertStmt->execute($params);
                $insertCount++;
            } else {
                // 갱신 → outbound_id 를 Key 로 UPDATE
                $outboundId = (string)($r['outbound_id'] ?? '');
                if (!preg_match('/^[0-9a-fA-F-]{36}$/', $outboundId)) {
                    throw new InvalidArgumentException($no . '번째 행의 고유번호가 올바르지 않습니다.');
                }
                $params[':outbound_id'] = $outboundId;

                // 마감 처리된 Row 는 수정 불가 (화면이 오래된 경우 대비)
                $closedCheckStmt->execute([':outbound_id' => $outboundId]);
                $closedValue = $closedCheckStmt->fetchColumn();
                $closedCheckStmt->closeCursor();

                if ($closedValue === false) {
                    throw new InvalidArgumentException($no . '번째 행의 출고 데이터를 찾을 수 없습니다.');
                }
                if ((int)$closedValue === 1) {
                    throw new InvalidArgumentException($no . '번째 행은 마감 처리되어 수정할 수 없습니다.');
                }

                $updateStmt->execute($params);
                $updateCount++;
            }
        }

        /* ----------------------------------------------
         * 메모 저장 : group_id = 'outbound', id = YYYYMMDD + 밭ID
         *   - 없으면 INSERT, 같은 그룹에 이미 있으면 id 를 Key 로 UPDATE
         * ---------------------------------------------- */
        if (count($memoPayload) > 0) {
            $memoSelectStmt = $pdo->prepare('SELECT group_id FROM memoT WHERE id = :id FOR UPDATE');

            $memoInsertStmt = $pdo->prepare("
                INSERT INTO memoT (group_id, id, reg_date, content)
                VALUES (:group_id, :id, :reg_date, :content)
            ");

            $memoUpdateStmt = $pdo->prepare("
                UPDATE memoT
                   SET content = :content
                 WHERE id = :id
                   AND group_id = :group_id
            ");

            foreach ($memoPayload as $idx => $m) {
                $no = $idx + 1;
                if (!is_array($m)) {
                    throw new InvalidArgumentException('메모 ' . $no . '번째 데이터가 올바르지 않습니다.');
                }

                $mDate = (string)($m['date'] ?? '');
                $mdt = DateTimeImmutable::createFromFormat('!Y-m-d', $mDate);
                if (!$mdt || $mdt->format('Y-m-d') !== $mDate) {
                    throw new InvalidArgumentException('메모 ' . $no . '번째 날짜가 올바르지 않습니다.');
                }

                $mFarmId = (int)($m['farm_id'] ?? 0);
                if ($mFarmId <= 0 || !isset($farmIdMap[$mFarmId])) {
                    throw new InvalidArgumentException('메모 ' . $no . '번째 밭 정보가 올바르지 않습니다.');
                }

                $mContent = trim((string)($m['content'] ?? ''));
                if ($mContent === '') {
                    throw new InvalidArgumentException('메모 ' . $no . '번째 내용이 비어 있습니다.');
                }
                if (strlen($mContent) > 60000) {
                    throw new InvalidArgumentException('메모 ' . $no . '번째 내용이 너무 깁니다.');
                }

                // 메모 ID = YYYYMMDD + 밭ID  (memoT.id 는 INT)
                $memoIdStr = $mdt->format('Ymd') . $mFarmId;
                if (strlen($memoIdStr) > 10 || (int)$memoIdStr > 2147483647) {
                    throw new InvalidArgumentException('메모 ID(' . $memoIdStr . ')가 허용 범위를 벗어났습니다.');
                }
                $memoId = (int)$memoIdStr;

                $memoSelectStmt->execute([':id' => $memoId]);
                $existingGroup = $memoSelectStmt->fetchColumn();
                $memoSelectStmt->closeCursor();

                if ($existingGroup === false) {
                    // 신규 메모 → INSERT
                    $memoInsertStmt->execute([
                        ':group_id' => $memoGroupId,
                        ':id'       => $memoId,
                        ':reg_date' => $mDate . ' ' . date('H:i:s'),
                        ':content'  => $mContent,
                    ]);
                    $memoInsertCount++;
                } elseif ((string)$existingGroup === $memoGroupId) {
                    // 기존 메모 → UPDATE
                    $memoUpdateStmt->execute([
                        ':content'  => $mContent,
                        ':id'       => $memoId,
                        ':group_id' => $memoGroupId,
                    ]);
                    $memoUpdateCount++;
                } else {
                    throw new InvalidArgumentException('메모 ID(' . $memoId . ')가 다른 그룹에서 이미 사용 중입니다.');
                }
            }
        }

        $pdo->commit();

        $msgParts = [];
        if ($insertCount + $updateCount > 0) {
            $msgParts[] = "출고 신규 {$insertCount}건, 수정 {$updateCount}건";
        }
        if ($deleteCount > 0) {
            $msgParts[] = "출고 삭제 {$deleteCount}건";
        }
        if ($memoInsertCount + $memoUpdateCount > 0) {
            $msgParts[] = "메모 신규 {$memoInsertCount}건, 수정 {$memoUpdateCount}건";
        }

        outbound_json([
            'success'      => true,
            'inserted'     => $insertCount,
            'updated'      => $updateCount,
            'deleted'      => $deleteCount,
            'memoInserted' => $memoInsertCount,
            'memoUpdated'  => $memoUpdateCount,
            'message'      => '저장 완료 (' . implode(' / ', $msgParts) . ')',
        ]);
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        outbound_json(['success' => false, 'message' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[outbound save] ' . $e->getMessage());
        outbound_json(['success' => false, 'message' => '저장 중 DB 오류가 발생했습니다.'], 500);
    }
}

/* --------------------------------------------------
 * 조회 (GET)
 * -------------------------------------------------- */

// 년월 검색 파라미터 설정 (기본값: 현재 년월)
$search_ym = isset($_GET['search_ym']) && $_GET['search_ym'] !== ''
    ? (string)$_GET['search_ym']
    : date('Y-m');

// YYYY-MM 형식 검증
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $search_ym)) {
    $search_ym = date('Y-m');
}

$ymParts = explode('-', $search_ym);
$search_year = (int)($ymParts[0] ?? date('Y'));
$search_month = (int)($ymParts[1] ?? date('m'));

$start_date = sprintf('%04d-%02d-01', $search_year, $search_month);
$startDateObj = new DateTimeImmutable($start_date);
$end_date = $startDateObj->modify('+1 month')->format('Y-m-d');

$rows = [];
$queryError = '';

/*
 * 상세 행만 조회합니다.
 * 밭별 소계와 날짜별 합계는 아래 PHP 출력부에서 상세 행 전체를 기준으로 계산합니다.
 */
try {
    $sql = "
        SELECT
            o.outbound_id,
            o.farm_id,
            o.outbound_date,
            o.product_type,
            o.quantity,
            o.disposal,
            o.auction_unit_price,
            o.auction_total_amount,
            o.deposit_amount,
            o.deposit_unit_price,
            CAST(
                ROUND(
                    (o.auction_total_amount - o.deposit_amount)
                    / NULLIF(o.auction_total_amount, 0) * 100,
                    2
                ) AS DECIMAL(10, 2)
            ) AS commission_rate,
            o.remarks,
            o.is_closed,
            f.farm_name
        FROM outbound o
        INNER JOIN farms f
            ON o.farm_id = f.farm_id
        WHERE o.outbound_date >= :start_date
          AND o.outbound_date < :end_date
        ORDER BY
            o.outbound_date DESC,
            f.farm_name ASC,
            o.product_type ASC,
            o.outbound_id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':start_date', $start_date, PDO::PARAM_STR);
    $stmt->bindValue(':end_date', $end_date, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rows = [];
    $queryError = $e->getMessage();
}

/*
 * 메모 조회 : group_id = 'outbound' 이고, 메모 id(YYYYMMDD+밭ID)가
 * 선택된 년월(YYYYMM)로 시작하는 데이터
 */
$memos = [];
$memoError = '';
try {
    $memoStmt = $pdo->prepare("
        SELECT id, content
        FROM memoT
        WHERE group_id = :group_id
          AND CAST(id AS CHAR) LIKE :ym_prefix
        ORDER BY id ASC
    ");
    $memoStmt->bindValue(':group_id', $memoGroupId, PDO::PARAM_STR);
    $memoStmt->bindValue(':ym_prefix', sprintf('%04d%02d', $search_year, $search_month) . '%', PDO::PARAM_STR);
    $memoStmt->execute();
    foreach ($memoStmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $memos[(string)$m['id']] = (string)($m['content'] ?? '');
    }
} catch (PDOException $e) {
    $memos = [];
    $memoError = $e->getMessage();
}

// 날짜별, 밭별 데이터 구조화
$groupedData = [];
foreach ($rows as $row) {
    $date = (string)$row['outbound_date'];
    $field = (string)$row['farm_name'];
    $groupedData[$date][$field][] = $row;
}

// JS 로 전달할 밭 목록
$farmsForJs = array_map(
    static fn(array $f): array => ['id' => (int)$f['farm_id'], 'name' => (string)$f['farm_name']],
    $farms
);

?>

<style>
    .shipping-wrap {
        padding: 5px;
        background: #fff;
        border-radius: 8px;
    }
    .shipping-header-area {
        display: grid;
        /* 좌/우 영역을 동일한 1fr로 두어 제목이 항상 정중앙에 위치함
           (저장 버튼이 나타나거나 사라져도 제목 위치 불변) */
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center;
        margin-bottom: 10px;
        width: 100%;
    }
    /* 헤더 왼쪽 영역 : 년월 + 날짜 이동 콤보박스 */
    .shipping-header-left {
        grid-column: 1;
        justify-self: start;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .shipping-ym-form {
        display: flex;
        align-items: center;
    }
    .shipping-date-jump {
        flex: 0 0 auto;
        height: 24px;
        padding: 1px 1px;
        border: 1px solid #ddd;
        border-radius: 3px;
        font-size: 11px;
        font-family: inherit;
        background-color: #fff;
        color: #333;
        cursor: pointer;
        box-sizing: border-box;
    }
    .shipping-date-jump:disabled {
        color: #aaa;
        cursor: default;
    }
    .shipping-ym-input {
        /* 폭은 내용(년/월 + 달력 아이콘)에 맞는 최소 크기로 축소 */
        width: auto;
        min-width: 0;
        flex: 0 0 auto;
        height: 24px;
        padding: 1px 2px;
        border: 1px solid #ddd;
        border-radius: 3px;
        font-size: 11px;
        font-family: inherit;
        background-color: #fff;
        color: #333;
        cursor: pointer;
        box-sizing: border-box;
    }
    /* 브라우저 기본 내부 여백 제거 (글자는 잘리지 않고 폭만 줄어듦) */
    .shipping-ym-input::-webkit-datetime-edit,
    .shipping-ym-input::-webkit-datetime-edit-fields-wrapper {
        padding: 0;
    }
    .shipping-ym-input::-webkit-datetime-edit-year-field,
    .shipping-ym-input::-webkit-datetime-edit-month-field,
    .shipping-ym-input::-webkit-datetime-edit-text {
        padding: 0;
    }
    .shipping-ym-input::-webkit-calendar-picker-indicator {
        margin: 0 0 0 1px;
        padding: 0;
        width: 12px;
        height: 12px;
    }
    /* 년월 컴포넌트 가로 축소 : 화면에는 짧은 'YYYY-MM' 라벨만 보이고,
       투명한 실제 month 입력칸이 그 위를 덮고 있어서 터치/클릭하면 기본 년월 선택기가 열린다 */
    .shipping-ym-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 24px;
        padding: 0 6px;
        border: 1px solid #ddd;
        border-radius: 3px;
        background-color: #fff;
        box-sizing: border-box;
        flex: 0 0 auto;
    }
    .shipping-ym-label {
        font-size: 11px;
        color: #333;
        white-space: nowrap;
        pointer-events: none;
    }
    .shipping-ym-wrap .shipping-ym-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        min-width: 0;
        margin: 0;
        padding: 0;
        border: 0;
        opacity: 0;
        cursor: pointer;
    }
    .shipping-ym-wrap .shipping-ym-input::-webkit-calendar-picker-indicator {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        cursor: pointer;
    }
    .shipping-title {
        grid-column: 2;
        font-size: 16px;
        font-weight: bold;
        color: #2c3e50;
        margin: 0;
        text-align: center;
        white-space: nowrap;
    }
    .header-btn-group {
        grid-column: 3;
        justify-self: end;
        display: flex;
        align-items: center;
        gap: 5px;
        flex-shrink: 0;
    }
    .header-save-btn {
        background-color: #2e7d32;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
        display: none;
    }
    .header-save-btn:disabled {
        opacity: 0.6;
        cursor: wait;
    }
    .add-row-btn {
        background-color: #4a148c;
        color: white;
        border: none;
        border-radius: 4px;
        width: 28px;
        height: 28px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    /* 그리드 영역: 이 컨테이너만 스크롤되고, 헤더(th)는 sticky로 상단에 고정됨.
       헤더와 본문이 하나의 table이므로 컬럼 넓이가 항상 일치함 */
    .shipping-table-container {
        width: 100%;
        height: calc(100vh - 120px); /* JS(adjustGridHeight)가 실제 위치 기준으로 재계산 */
        min-height: 200px;
        overflow: auto;
        border-top: 1px solid #d1c4e9;
        border-left: 1px solid #d1c4e9;
        -webkit-overflow-scrolling: touch;
    }
    .shipping-table {
        width: 100%;
        /* sticky 헤더에서 테두리가 사라지지 않도록 separate 사용 */
        border-collapse: separate;
        border-spacing: 0;
        font-size: 12px;
        text-align: center;
        white-space: nowrap;
    }
    .shipping-table th {
        position: sticky;
        top: 0;
        z-index: 2;
        background-color: #f3e5f5;
        color: #4a148c;
        font-weight: bold;
        padding: 4px 2px;
        border-width: 0 1px 1px 0;
        border-style: solid;
        border-color: #d1c4e9;
    }
    .shipping-table td {
        padding: 3px 2px;
        border-width: 0 1px 1px 0;
        border-style: solid;
        border-color: #e0e0e0;
        background-color: #fff;
        cursor: pointer;
    }
    .shipping-table tr.group-divider td {
        border-bottom: 1px solid #e0e0e0;
    }
    .shipping-table tr.section-divider td {
        border-bottom: 1px solid #e0e0e0;
    }
    .text-right { text-align: right; }
    .text-center { text-align: center; }

    .farm-subtotal-row td {
        background-color: #fffde7 !important;
        font-weight: bold;
        cursor: default !important;
    }
    .date-total-row td {
        background-color: #ede7f6 !important;
        font-weight: bold;
        cursor: default !important;
    }

    .custom-row td {
        cursor: pointer;
        background-color: #fffde7 !important;
    }
    .custom-row td:hover {
        background-color: #fff9c4 !important;
    }

    /* 경매단가/입금액 팝업으로 입력 또는 수정한 Row : 붉은 글씨 (날짜/밭 rowspan 셀 제외) */
    .shipping-table tr.modified-row td:not([data-col="date"]):not([data-col="farm"]) {
        color: #d32f2f;
    }

    /* 메모가 새로 작성/수정된 밭 이름 : 푸른 글씨 */
    .shipping-table td.memo-modified {
        color: #1565c0;
    }

    /* 마감 처리된 Row : 상품종류 ~ 수수료 배경을 옅은 회색으로, 글자색은 약간 흐리게 표시 (날짜/밭 rowspan 셀 제외) */
    .shipping-table tr.closed-row td:not([data-col="date"]):not([data-col="farm"]) {
        background-color: #eeeeee;
        color: #757575;
    }

    /* 마감 / 마감해제 팝업 */
    .closing-modal-content {
        max-width: 380px;
    }
    .closing-modal-info {
        text-align: center;
        font-size: 12px;
        color: #666;
        margin-bottom: 6px;
    }

    .closing-toolbar {
        display: flex;
        gap: 6px;
        margin-bottom: 8px;
    }
    .closing-toolbar button {
        flex: 1;
        height: 28px;
        border: 1px solid #d1c4e9;
        border-radius: 4px;
        background: #f3e5f5;
        color: #4a148c;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
    }
    .closing-list {
        max-height: 260px;
        overflow-y: auto;
        border: 1px solid #eee;
        border-radius: 6px;
    }
    .closing-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 12px;
        cursor: pointer;
    }
    .closing-item:last-child {
        border-bottom: none;
    }
    .closing-item.touched {
        background: #fffde7;
    }
    .closing-item.disabled {
        color: #aaa;
        cursor: not-allowed;
    }
    .closing-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }
    .closing-item-text {
        flex: 1;
    }
    .closing-item-status {
        font-size: 11px;
        color: #888;
        white-space: nowrap;
    }
    .closing-summary {
        text-align: center;
        font-size: 12px;
        color: #555;
        margin-top: 8px;
    }

    /* 메모 팝업 */
    .memo-modal-content {
        max-width: 380px;
    }
    .memo-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        font-weight: bold;
        color: #555;
        border-bottom: 1px solid #ddd;
        padding-bottom: 8px;
        margin-bottom: 10px;
    }
    .memo-modal-header .memo-farm-name {
        color: #2e7d32;
    }
    .memo-textarea {
        width: 100%;
        height: 140px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        resize: vertical;
        font-size: 13px;
        font-family: inherit;
        line-height: 1.5;
        box-sizing: border-box;
        color: #333;
    }

    .delete-btn {
        background-color: #ffebee;
        color: #d32f2f;
        border: 1px solid #ef9a9a;
        border-radius: 4px;
        padding: 2px 6px;
        font-size: 11px;
        font-weight: bold;
        cursor: pointer;
        display: inline-block;
    }
    .delete-btn:hover {
        background-color: #ffcdd2;
    }

    .shipping-modal-overlay {
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
    .shipping-modal-content {
        background: #fff;
        border-radius: 8px;
        width: 92%;
        max-width: 480px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    .shipping-modal-content h3 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 16px;
        color: #2c3e50;
        text-align: center;
    }
    .shipping-top-row {
        display: grid;
        grid-template-columns: auto 1fr 1.2fr;
        gap: 8px;
        align-items: end;
        margin-bottom: 15px;
        padding-bottom: 12px;
        border-bottom: 1px solid #eee;
    }
    .shipping-form-group {
        margin-bottom: 10px;
    }
    .shipping-form-group label {
        display: block;
        font-size: 11px;
        font-weight: bold;
        color: #555;
        margin-bottom: 3px;
    }
    .shipping-form-group input[type="date"],
    .shipping-form-group input[type="text"],
    .shipping-form-group select,
    .shipping-form-group textarea {
        width: 100%;
        height: 30px;
        padding: 4px 6px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
        font-family: inherit;
        box-sizing: border-box;
    }
    .shipping-form-group input[type="date"] {
        width: 130px;
    }
    .shipping-form-group textarea {
        resize: none;
    }
    #dynamicProductList {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 250px;
        overflow-y: auto;
        margin-bottom: 15px;
        padding-right: 4px;
    }
    .dynamic-product-item {
        display: grid;
        grid-template-columns: 1.2fr 1fr 1fr;
        gap: 8px;
        background: #fdfbfd;
        padding: 8px;
        border: 1px solid #e1bee7;
        border-radius: 6px;
        align-items: center;
    }
    .dynamic-product-item .shipping-form-group select,
    .dynamic-product-item .shipping-form-group input {
        height: 30px;
    }
    .shipping-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 15px;
    }
    .shipping-modal-actions button {
        padding: 8px 14px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
    }
    .btn-cancel { background-color: #90a4ae; color: #fff; flex: 1; }
    .btn-save { background-color: #4a148c; color: #fff; flex: 2; }
</style>

<div class="shipping-wrap">
    <div class="shipping-header-area">
        <div class="shipping-header-left">
            <!-- 년월 통합 선택 컴포넌트 (왼쪽 정렬) -->
            <form method="GET" action="outbound.php" class="shipping-ym-form">
                <span class="shipping-ym-wrap">
                    <span class="shipping-ym-label" id="shippingYmLabel"><?= outbound_h($search_ym) ?></span>
                    <input type="month" name="search_ym" class="shipping-ym-input" value="<?= outbound_h($search_ym) ?>"
                           onclick="try { this.showPicker(); } catch (e) {}"
                           onchange="document.getElementById('shippingYmLabel').innerText = this.value || '<?= outbound_h($search_ym) ?>'; this.form.requestSubmit();">
                </span>
            </form>

            <!-- 날짜 이동 콤보박스 : 선택한 년월에 출고 데이터가 있는 날짜(일)를 표시, 선택하면 해당 날짜 위치로 스크롤 (재조회 아님) -->
            <select id="dateJumpSelect" class="shipping-date-jump" title="날짜로 이동" onchange="scrollToOutboundDate(this.value)"<?= empty($groupedData) ? ' disabled' : '' ?>>
                <option value="">일</option>
                <?php foreach (array_keys($groupedData) as $jumpDate) { ?>
                    <option value="<?= outbound_h($jumpDate) ?>"><?= (int)substr((string)$jumpDate, 8, 2) ?></option>
                <?php } ?>
            </select>
        </div>

        <h2 class="shipping-title">📦 출고 관리</h2>

        <div class="header-btn-group">
            <button type="button" class="header-save-btn" id="headerSaveBtn" onclick="saveNewRowAction()">저장</button>
            <button type="button" class="add-row-btn" onclick="openNewShippingModal()" title="한 줄 추가">+</button>
        </div>
    </div>

    <?php if ($memoError !== '') { ?>
        <div style="padding:4px 6px; margin-bottom:6px; font-size:12px; color:#c62828;">
            메모 조회 오류: <?= outbound_h($memoError) ?>
        </div>
    <?php } ?>

    <div class="shipping-table-container">
        <table class="shipping-table" id="shippingTable">
            <thead>
                <tr>
                    <th>날짜</th>
                    <th>밭<BR>이름</th>
                    <th>상품<BR>종류</th>
                    <th>수<BR>량</th>
                    <th>폐<BR>기</th>
                    <th>경매<BR>단가</th>
                    <th>경매<BR>총액</th>
                    <th>입금액</th>
                    <th>입금<BR>단가</th>
                    <th>수수료<BR>(%)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($queryError !== '') {
                    echo '<tr><td colspan="10" style="padding:20px; text-align:center; color:#c62828;">출고 데이터 조회 오류: '
                        . outbound_h($queryError)
                        . '</td></tr>';
                } elseif (empty($groupedData)) {
                    echo '<tr><td colspan="10" style="padding:20px; text-align:center; color:#888;">등록된 출고 데이터가 없어.</td></tr>';
                } else {
                    foreach ($groupedData as $date => $fields) {
                        $dateRowSpan = 0;
                        $dateTotalQty = 0;
                        $dateTotalDisposal = 0;
                        $dateTotalAuctionAmount = 0;
                        $dateTotalDepositAmount = 0;

                        foreach ($fields as $field => $items) {
                            $dateRowSpan += count($items) + 1;
                        }
                        $dateRowSpan += 1;

                        $dateFormatted = date('m', strtotime((string)$date)) . '<BR>/<BR>' . date('d', strtotime((string)$date));
                        $isFirstDateRow = true;

                        foreach ($fields as $field => $items) {
                            $fieldRowSpan = count($items) + 1;
                            $fieldTotalQty = 0;
                            $fieldTotalDisposal = 0;
                            $fieldTotalAuctionAmount = 0;
                            $fieldTotalDepositAmount = 0;
                            $isFirstFieldRow = true;

                            foreach ($items as $item) {
                                $fieldTotalQty += (float)$item['quantity'];
                                $fieldTotalDisposal += (float)$item['disposal'];
                                $fieldTotalAuctionAmount += (float)$item['auction_total_amount'];
                                $fieldTotalDepositAmount += (float)$item['deposit_amount'];

                                $dateTotalQty += (float)$item['quantity'];
                                $dateTotalDisposal += (float)$item['disposal'];
                                $dateTotalAuctionAmount += (float)$item['auction_total_amount'];
                                $dateTotalDepositAmount += (float)$item['deposit_amount'];
                ?>
                <tr class="<?= (int)$item['is_closed'] === 1 ? 'closed-row' : '' ?>"
                    data-outbound-id="<?= outbound_h($item['outbound_id']) ?>"
                    data-date="<?= outbound_h($date) ?>"
                    data-farm="<?= outbound_h($field) ?>"
                    data-farm-id="<?= (int)$item['farm_id'] ?>"
                    data-product="<?= outbound_h($item['product_type']) ?>"
                    data-disposal="<?= outbound_h($item['disposal']) ?>"
                    data-is-new="false"
                    data-is-closed="<?= (int)$item['is_closed'] ?>"
                    data-auction-unit="<?= outbound_h($item['auction_unit_price']) ?>"
                    data-auction-total="<?= outbound_h($item['auction_total_amount']) ?>"
                    data-deposit-amount="<?= outbound_h($item['deposit_amount']) ?>"
                    data-deposit-unit="<?= outbound_h($item['deposit_unit_price']) ?>"
                    data-qty="<?= outbound_h($item['quantity']) ?>"
                    data-auction-modified="false"
                    data-deposit-modified="false"
                    data-qty-modified="false">
                    <?php if ($isFirstDateRow) { ?>
                        <td rowspan="<?= $dateRowSpan ?>" style="vertical-align: middle; background-color: #fafafa; border-bottom: 1px solid #e0e0e0;" data-col="date"><?= $dateFormatted ?></td>
                        <?php $isFirstDateRow = false; ?>
                    <?php } ?>

                    <?php if ($isFirstFieldRow) { ?>
                        <td rowspan="<?= $fieldRowSpan ?>" style="vertical-align: middle; background-color: #fafafa;" data-col="farm" data-memo-date="<?= outbound_h($date) ?>" data-memo-farm-id="<?= (int)$item['farm_id'] ?>" data-memo-farm-name="<?= outbound_h($field) ?>"><?= outbound_h($field) ?></td>
                        <?php $isFirstFieldRow = false; ?>
                    <?php } ?>

                    <td data-col="product"><?= outbound_h($item['product_type']) ?></td>
                    <td class="text-right" data-col="qty"><?= number_format((float)$item['quantity']) ?></td>
                    <td class="text-right" data-col="disposal"><?= number_format((float)$item['disposal']) ?></td>
                    <td class="text-right" data-col="auction-unit"><?= number_format((float)$item['auction_unit_price']) ?></td>
                    <td class="text-right" data-col="auction-total"><?= number_format((float)$item['auction_total_amount']) ?></td>
                    <td class="text-right" data-col="deposit-amount"><?= number_format((float)$item['deposit_amount']) ?></td>
                    <td class="text-right" data-col="deposit-unit"><?= number_format((float)$item['deposit_unit_price']) ?></td>
                    <?php
                    // 마감되지 않았고 경매단가·입금액이 모두 비어 있거나 0 이면 New Row 처럼 삭제(-) 버튼 표시
                    $canDeleteRow = (int)$item['is_closed'] !== 1
                        && (float)$item['auction_unit_price'] == 0.0
                        && (float)$item['deposit_amount'] == 0.0;
                    ?>
                    <td class="text-center" data-col="fee"><?php if ($canDeleteRow) { ?><button type="button" class="delete-btn">-</button><?php } else { ?><?= ((float)$item['auction_total_amount'] != 0.0 && $item['commission_rate'] !== null) ? number_format((float)$item['commission_rate'], 2) : '-' ?><?php } ?></td>
                </tr>
                <?php
                            } // end foreach items

                            $fieldFee = $fieldTotalAuctionAmount != 0
                                ? (($fieldTotalAuctionAmount - $fieldTotalDepositAmount) / $fieldTotalAuctionAmount * 100)
                                : null;
                ?>
                <!-- 밭별 소계 -->
                <tr class="farm-subtotal-row group-divider" data-date="<?= outbound_h($date) ?>" data-farm="<?= outbound_h($field) ?>">
                    <td data-col="product">소계</td>
                    <td class="text-right" data-col="qty" data-sum="1"><?= number_format($fieldTotalQty) ?></td>
                    <td class="text-right" data-col="disposal" data-sum="1"><?= number_format($fieldTotalDisposal) ?></td>
                    <td class="text-right" data-col="auction-unit"> </td>
                    <td class="text-right" data-col="auction-total" data-sum="1"><?= number_format($fieldTotalAuctionAmount) ?></td>
                    <td class="text-right" data-col="deposit-amount" data-sum="1"><?= number_format($fieldTotalDepositAmount) ?></td>
                    <td class="text-right" data-col="deposit-unit"> </td>
                    <td class="text-center" data-col="fee" data-sum="1"><?= $fieldFee !== null ? number_format($fieldFee, 2) : '-' ?></td>
                </tr>
                <?php
                        } // end foreach fields

                        $dateFee = $dateTotalAuctionAmount != 0
                            ? (($dateTotalAuctionAmount - $dateTotalDepositAmount) / $dateTotalAuctionAmount * 100)
                            : null;
                ?>
                <!-- 날짜별 합계 -->
                <tr class="date-total-row section-divider" data-date="<?= outbound_h($date) ?>">
                    <td colspan="2">합계</td>
                    <td class="text-right" data-sum="1"><?= number_format($dateTotalQty) ?></td>
                    <td class="text-right" data-sum="1"><?= number_format($dateTotalDisposal) ?></td>
                    <td class="text-right"> </td>
                    <td class="text-right" data-sum="1"><?= number_format($dateTotalAuctionAmount) ?></td>
                    <td class="text-right" data-sum="1"><?= number_format($dateTotalDepositAmount) ?></td>
                    <td class="text-right"> </td>
                    <td class="text-center" data-sum="1"><?= $dateFee !== null ? number_format($dateFee, 2) : '-' ?></td>
                </tr>
                <?php
                    } // end foreach groupedData
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 팝업 모달 HTML 구성 -->
<div class="shipping-modal-overlay" id="shippingModal">
    <div class="shipping-modal-content">
        <h3 id="shippingModalTitle">출고 정보 입력</h3>
        <div class="shipping-top-row" id="shippingTopRowContainer">
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label for="shipping-date">날짜</label>
                <input type="date" id="shipping-date">
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label for="shipping-farm">밭 이름</label>
                <select id="shipping-farm">
                    <?php foreach ($farms as $f) { ?>
                        <option value="<?= (int)$f['farm_id'] ?>"><?= outbound_h($f['farm_name']) ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;" id="productCountGroup">
                <label for="shipping-product-count">상품 종류 수</label>
                <div style="display: flex; gap: 4px;">
                    <input type="text" class="digits-only" id="shipping-product-count" placeholder="숫자" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
                    <button type="button" onclick="generateProductInputs()" style="background:#4a148c; color:#fff; border:none; border-radius:4px; height:30px; padding:0 12px; font-weight:bold; cursor:pointer; white-space:nowrap; flex-shrink:0;">생성</button>
                </div>
            </div>
        </div>
        <div id="dynamicProductList"></div>
        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="closeShippingModal()">닫기</button>
            <button type="button" class="btn-save" onclick="saveShippingRow()">확인</button>
        </div>
    </div>
</div>

<div class="shipping-modal-overlay" id="auctionModal">
    <div class="shipping-modal-content">
        <h3>경매 정보 입력</h3>
        <div class="shipping-form-group">
            <label for="auction-unit">경매 단가</label>
            <input type="text" class="digits-only" id="auction-unit" placeholder="단가 입력" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
        </div>
        <div class="shipping-form-group">
            <label for="auction-disposal">폐기</label>
            <input type="text" class="digits-only" id="auction-disposal" placeholder="숫자만 입력" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
        </div>
        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="document.getElementById('auctionModal').style.display='none';">닫기</button>
            <button type="button" class="btn-save" onclick="saveAuctionRow()">확인</button>
        </div>
    </div>
</div>

<div class="shipping-modal-overlay" id="depositModal">
    <div class="shipping-modal-content">
        <h3>입금액 입력</h3>
        <div class="shipping-form-group">
            <label for="deposit-amount">입금액</label>
            <input type="text" class="digits-only" id="deposit-amount" placeholder="숫자만 입력" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
        </div>
        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="document.getElementById('depositModal').style.display='none';">닫기</button>
            <button type="button" class="btn-save" onclick="saveDepositRow()">확인</button>
        </div>
    </div>
</div>

<!-- 마감 / 마감해제 팝업 : 같은 날짜 Row 를 체크박스로 일괄 처리 (저장 버튼과 무관하게 즉시 DB 반영 후 재조회) -->
<div class="shipping-modal-overlay" id="closingModal">
    <div class="shipping-modal-content closing-modal-content">
        <h3 id="closingModalTitle">마감 / 마감해제</h3>
        <div class="closing-modal-info">체크 = 마감 · 체크 해제 = 마감해제</div>
        <div class="closing-toolbar">
            <button type="button" onclick="setAllClosingChecks(true)">전체 체크</button>
            <button type="button" onclick="setAllClosingChecks(false)">전체 체크해제</button>
        </div>
        <div class="closing-list" id="closingList"></div>
        <div class="closing-summary" id="closingSummary"></div>
        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="closeClosingModal()">닫기</button>
            <button type="button" class="btn-save" id="closingModalBtn" onclick="submitClosing()">저장</button>
        </div>
    </div>
</div>

<!-- 메모 등록/수정 팝업 : 확인 시 화면에만 반영, 헤더 [저장] 시 DB 반영 -->
<div class="shipping-modal-overlay" id="memoModal">
    <div class="shipping-modal-content memo-modal-content">
        <h3 id="memoModalTitle">새 메모 작성</h3>
        <div class="memo-modal-header">
            <span>밭: <span class="memo-farm-name" id="memoModalFarm"></span></span>
            <span id="memoModalDate"></span>
        </div>
        <textarea id="memoModalContent" class="memo-textarea" placeholder="내용을 입력하세요..."></textarea>
        <div class="shipping-modal-actions">
            <button type="button" class="btn-cancel" onclick="closeMemoModal()">닫기</button>
            <button type="button" class="btn-save" id="memoModalSaveBtn" onclick="confirmMemo()">저장</button>
        </div>
    </div>
</div>

<script>
window.currentEditingRow = window.currentEditingRow || null;
if (typeof currentEditingRow === 'undefined') {
    var currentEditingRow = null;
}

// 서버에서 전달받은 값 (재실행되어도 오류가 나지 않도록 window 에 보관)
window.OUTBOUND_FARMS = <?= json_encode($farmsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.OUTBOUND_SEARCH_YM = <?= json_encode($search_ym) ?>;

// 선택된 년월의 메모 (key = YYYYMMDD + 밭ID → 내용)
window.OUTBOUND_MEMOS = <?= json_encode((object)$memos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.OUTBOUND_MEMO_ERROR = <?= $memoError !== '' ? 'true' : 'false' ?>;
// 화면에서 새로 작성/수정되었지만 아직 DB 에 저장되지 않은 메모 (key → {date, farm_id, content})
window.OUTBOUND_MEMO_PENDING = {};
// 삭제 대기 중인 기존 Row : { outbound_id : {date, farm, farm_id, product} }  (헤더 [저장] 시 DB 에서 삭제)
window.OUTBOUND_DELETE_PENDING = {};
window.currentMemoCtx = null;

// 그리드 영역 높이를 화면 하단까지 맞춰 그리드 내부만 스크롤되게 함
function adjustGridHeight() {
    const box = document.querySelector('.shipping-table-container');
    if (!box) return;
    const top = box.getBoundingClientRect().top;
    const h = window.innerHeight - top - 12; // 하단 여백
    box.style.height = Math.max(200, h) + 'px';
}
adjustGridHeight();
window.addEventListener('load', adjustGridHeight);
window.addEventListener('resize', adjustGridHeight);
window.addEventListener('orientationchange', adjustGridHeight);

function formatNum(val) {
    if (val === '-' || val === '' || isNaN(val)) return '-';
    return Math.round(Number(val)).toLocaleString(); // PHP number_format(반올림)과 동일하게 표시
}

// DB 값("12.00")을 입력창용 정수 문자열("12")로 변환 (입력창은 숫자만 허용 / DB에 소수점 이하 값은 없다는 전제)
function plainNum(val) {
    const n = parseFloat(val);
    return isNaN(n) ? '' : String(Math.round(n));
}

// 판매수량 = 수량 - 폐기 (0 미만이면 0)
function netQty(qty, disposal) {
    return Math.max(0, (parseFloat(qty) || 0) - (parseFloat(disposal) || 0));
}

// 수수료(%) 표시 문자열 : (경매총액 - 입금액) / 경매총액 * 100, 소수점 2자리 (경매총액이 0이면 '-')
function calcFeeText(auctionTotal, depositAmount) {
    const total = parseFloat(auctionTotal) || 0;
    if (total === 0) return '-';
    const deposit = parseFloat(depositAmount) || 0;
    const rate = Math.round(((total - deposit) / total) * 10000) / 100;
    return rate.toFixed(2);
}

/*
 * 기존 Row 의 파생 값 재계산 + 화면 갱신 (수량/폐기/경매단가/입금액이 바뀐 뒤 호출)
 *   경매총액 = 경매단가 × (수량 - 폐기)
 *   입금단가 = 입금액 ÷ (수량 - 폐기)
 */
function recalcRow(row) {
    if (!row || row.dataset.isNew === 'true') return; // New Row 는 경매/입금 정보가 없음

    const net = netQty(row.dataset.qty, row.dataset.disposal);
    const unit = parseFloat(row.dataset.auctionUnit) || 0;
    const deposit = parseFloat(row.dataset.depositAmount) || 0;

    const total = Math.round(unit * net * 100) / 100;
    const depositUnit = net > 0 ? Math.round(deposit / net * 100) / 100 : 0;

    row.dataset.auctionTotal = total;
    row.dataset.depositUnit = depositUnit;

    function setCell(col, text) {
        const cell = row.querySelector('[data-col="' + col + '"]');
        if (cell) cell.innerText = text;
    }

    setCell('qty', formatNum(row.dataset.qty));
    setCell('disposal', formatNum(row.dataset.disposal));
    setCell('auction-total', total !== 0 ? formatNum(total) : '-');
    setCell('deposit-unit', depositUnit !== 0 ? formatNum(depositUnit) : '-');
    // 수수료 칸 : 삭제(-) 버튼은 조회 시에만 표시한다. (경매단가/입금액을 0 으로 수정해도 저장 후 재조회 전까지는 버튼이 나타나지 않음)
    //  - 조회 시 표시된 삭제 버튼은 경매단가·입금액이 여전히 0 이면 그대로 유지하고, 그 외에는 수수료 값을 표시한다.
    const feeCell = row.querySelector('[data-col="fee"]');
    if (feeCell) {
        const hasDeleteBtn = !!feeCell.querySelector('.delete-btn');
        if (!(hasDeleteBtn && unit === 0 && deposit === 0)) {
            feeCell.innerText = calcFeeText(total, deposit);
        }
    }
}

// 값이 0 이거나 비어 있으면 '' (입력창을 비워서 보여줄 때 사용)
function plainNumOrEmpty(val) {
    const t = plainNum(val);
    return t === '0' ? '' : t;
}

/* ------------------------------------------------------------
 * 숫자 전용 입력 : class="digits-only" 인 입력창은 0~9 외 문자 입력 불가
 *  (소수점, 콤마, 부호, 문자, 붙여넣기 포함 / 동적으로 생성된 입력창도 적용)
 * ------------------------------------------------------------ */
function sanitizeDigitsInput(el) {
    const before = el.value;
    // 전각 숫자(１２３)는 일반 숫자로 변환
    const normalized = before.replace(/[\uFF10-\uFF19]/g, function (c) {
        return String.fromCharCode(c.charCodeAt(0) - 0xFEE0);
    });
    const cleaned = normalized.replace(/[^0-9]/g, '');
    if (before === cleaned) return;

    let pos = null;
    try { pos = el.selectionStart; } catch (e) { pos = null; }

    el.value = cleaned;

    if (pos !== null) {
        const removed = normalized.slice(0, pos).replace(/[0-9]/g, '').length;
        const newPos = Math.max(0, pos - removed);
        try { el.setSelectionRange(newPos, newPos); } catch (e) { /* 무시 */ }
    }
}

if (!window.OUTBOUND_DIGITS_BOUND) {
    window.OUTBOUND_DIGITS_BOUND = true;

    // 숫자가 아닌 문자는 입력되기 전에 차단
    document.addEventListener('beforeinput', function (e) {
        const el = e.target;
        if (!el || !el.classList || !el.classList.contains('digits-only')) return;
        if (e.inputType && e.inputType.indexOf('insert') === 0
            && typeof e.data === 'string' && e.data !== '' && /[^0-9]/.test(e.data)) {
            e.preventDefault();
        }
    });

    // 붙여넣기, 한글/모바일 키보드(조합 입력) 등으로 들어온 값 정리
    document.addEventListener('input', function (e) {
        const el = e.target;
        if (el && el.classList && el.classList.contains('digits-only')) {
            sanitizeDigitsInput(el);
        }
    });
}

function escHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// 밭 select 의 <option> 목록 생성 (value = farm_id)
function buildFarmOptions(selectedId) {
    return (window.OUTBOUND_FARMS || []).map(function (f) {
        const sel = String(f.id) === String(selectedId) ? 'selected' : '';
        return '<option value="' + f.id + '" ' + sel + '>' + escHtml(f.name) + '</option>';
    }).join('');
}

function openNewShippingModal() {
    currentEditingRow = null;
    document.getElementById('shippingModalTitle').innerText = '출고 정보 입력';
    document.getElementById('shippingTopRowContainer').style.display = 'grid';

    const now = new Date();
    const todayStr = now.getFullYear() + '-'
        + String(now.getMonth() + 1).padStart(2, '0') + '-'
        + String(now.getDate()).padStart(2, '0');
    document.getElementById('shipping-date').value = todayStr;
    document.getElementById('shipping-farm').selectedIndex = 0;
    document.getElementById('shipping-product-count').value = '1';

    generateProductInputs(1);
    document.getElementById('shippingModal').style.display = 'flex';
}

function generateProductInputs(defaultCount = null) {
    const countInput = document.getElementById('shipping-product-count');
    const count = defaultCount !== null ? defaultCount : parseInt(countInput.value) || 0;
    const container = document.getElementById('dynamicProductList');
    container.innerHTML = '';

    if (count <= 0) {
        container.innerHTML = '<div style="text-align:center; color:#888; font-size:12px; padding:15px;">상품 종류 수를 입력하고 생성 버튼을 눌러줘</div>';
        return;
    }

    for (let i = 0; i < count; i++) {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'dynamic-product-item';
        itemDiv.innerHTML = `
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>상품 종류</label>
                <select class="dyn-product-select">
                    <option value="찹찹">찹찹</option>
                    <option value="바찹">바찹</option>
                    <option value="바라">바라</option>
                    <option value="옆순">옆순</option>
                </select>
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>수량</label>
                <input type="text" class="dyn-product-qty digits-only" placeholder="숫자만" value="1" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>폐기</label>
                <input type="text" class="dyn-product-disposal digits-only" placeholder="숫자만" value="0" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
            </div>
        `;
        container.appendChild(itemDiv);
    }
}

function checkAndOpenEdit(row) {
    const isNewRow = row.dataset.isNew === 'true';
    const auctionUnit = parseFloat(row.dataset.auctionUnit) || 0;
    const auctionModified = row.dataset.auctionModified === 'true';

    const canOpen = isNewRow || (auctionUnit === 0 && !auctionModified);

    if (!canOpen) {
        alert('이 행은 조건에 부합하지 않아 기본정보를 수정할 수 없어!');
        return;
    }

    openEditShippingModal(row);
}

function openEditShippingModal(row) {
    currentEditingRow = row;
    document.getElementById('shippingModalTitle').innerText = '출고 정보 수정';
    document.getElementById('shippingTopRowContainer').style.display = 'none';

    const container = document.getElementById('dynamicProductList');
    container.innerHTML = `
        <div class="shipping-form-group">
            <label>날짜</label>
            <input type="date" id="edit-single-date" value="${escHtml(row.dataset.date || '')}">
        </div>
        <div class="shipping-form-group">
            <label>밭 이름</label>
            <select id="edit-single-farm">
                ${buildFarmOptions(row.dataset.farmId)}
            </select>
        </div>
        <div class="dynamic-product-item" style="grid-template-columns: 1.2fr 1fr 1fr;">
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>상품 종류</label>
                <select id="edit-single-product" class="dyn-product-select">
                    <option value="찹찹" ${row.dataset.product==='찹찹'?'selected':''}>찹찹</option>
                    <option value="바찹" ${row.dataset.product==='바찹'?'selected':''}>바찹</option>
                    <option value="바라" ${row.dataset.product==='바라'?'selected':''}>바라</option>
                    <option value="옆순" ${row.dataset.product==='옆순'?'selected':''}>옆순</option>
                </select>
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>수량</label>
                <input type="text" id="edit-single-qty" class="dyn-product-qty digits-only" value="${escHtml(plainNum(row.dataset.qty))}" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
            </div>
            <div class="shipping-form-group" style="margin-bottom:0;">
                <label>폐기</label>
                <input type="text" id="edit-single-disposal" class="dyn-product-disposal digits-only" value="${escHtml(plainNum(row.dataset.disposal) || '0')}" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
            </div>
        </div>
    `;

    document.getElementById('shippingModal').style.display = 'flex';
}

function closeShippingModal() {
    document.getElementById('shippingModal').style.display = 'none';
    currentEditingRow = null;
}

function saveShippingRow() {
    if (currentEditingRow) {
        const dateVal = document.getElementById('edit-single-date').value;
        const farmSel = document.getElementById('edit-single-farm');
        const farmIdVal = farmSel.value;
        const farmNameVal = farmSel.selectedIndex >= 0 ? farmSel.options[farmSel.selectedIndex].text : '';
        const productVal = document.getElementById('edit-single-product').value;
        const qtyVal = document.getElementById('edit-single-qty').value || '0';
        const disposalVal = document.getElementById('edit-single-disposal').value || '0';

        if (!dateVal) {
            alert('날짜를 입력해 줘!');
            return;
        }
        if (!farmIdVal) {
            alert('밭을 선택해 줘!');
            return;
        }
        if ((parseFloat(disposalVal) || 0) > (parseFloat(qtyVal) || 0)) {
            alert('폐기는 수량보다 클 수 없어!');
            return;
        }

        const row = currentEditingRow;
        // 날짜 또는 밭이 바뀌었는지 / 조회된 묶음(날짜·밭 rowspan 병합) 안의 Row 인지
        const dateFarmChanged = dateVal !== row.dataset.date || String(farmIdVal) !== String(row.dataset.farmId);
        const isGrouped = row.dataset.isNew !== 'true' && !row.classList.contains('custom-row');

        if (!row.dataset.origDate) {
            row.dataset.origDate = row.dataset.date;
            row.dataset.origFarm = row.dataset.farm;
        }

        // 조회된 묶음 안의 Row 의 날짜/밭이 바뀌면 병합 셀(다른 Row 와 공유)을 건드리지 않도록
        // 원래 묶음에서 분리해 맨 위에 자체 날짜/밭 셀을 가진 Row 로 표시한다. (저장 후 재조회하면 제자리로 정렬됨)
        // ※ removeExistingRow 는 원래 날짜/밭 기준으로 동작하므로 dataset 변경 전에 호출
        if (isGrouped && dateFarmChanged) {
            removeExistingRow(row);
            row.classList.add('custom-row');
            const tbody = document.querySelector('#shippingTable tbody');
            tbody.insertBefore(row, tbody.firstChild);
        }

        row.dataset.date = dateVal;
        row.dataset.farmId = farmIdVal;
        row.dataset.farm = farmNameVal;
        row.dataset.product = productVal;
        row.dataset.qty = qtyVal;
        row.dataset.disposal = disposalVal;
        row.dataset.qtyModified = 'true';

        // 자체 날짜/밭 셀을 가진 Row(신규 Row, 분리된 Row) : 날짜/밭 셀을 새 값으로 다시 구성 (메모 키 정보 포함)
        if (!isGrouped || dateFarmChanged) {
            const dateFormatted = dateVal.substring(5, 7) + '/' + dateVal.substring(8, 10);
            row.querySelectorAll('td[data-col="date"], td[data-col="farm"]').forEach(function (td) { td.remove(); });
            row.insertAdjacentHTML('afterbegin',
                '<td style="background-color: #fffde7;" data-col="date">' + escHtml(dateFormatted) + '</td>'
                + '<td style="background-color: #fffde7;" data-col="farm"'
                + ' data-memo-date="' + escHtml(dateVal) + '"'
                + ' data-memo-farm-id="' + escHtml(farmIdVal) + '"'
                + ' data-memo-farm-name="' + escHtml(farmNameVal) + '">' + escHtml(farmNameVal) + '</td>');
        }

        const productCell = currentEditingRow.querySelector('[data-col="product"]');
        if (productCell) productCell.innerText = productVal;
        const qtyCell = currentEditingRow.querySelector('[data-col="qty"]');
        if (qtyCell) qtyCell.innerText = qtyVal;
        const disposalCell = currentEditingRow.querySelector('[data-col="disposal"]');
        if (disposalCell) disposalCell.innerText = disposalVal;

        recalcRow(currentEditingRow); // 수량/폐기 변경 → 경매총액·입금단가·수수료 재계산 및 표시
        pruneOrphanPendingMemos();    // 날짜/밭 이동으로 사라진 묶음의 저장 대기 메모 정리
        refreshMemoMarks();
        refreshSummaryMarks();        // 소계/합계는 재조회 전까지 '-' 표시

        document.getElementById('headerSaveBtn').style.display = 'inline-block';
        closeShippingModal();

    } else {
        const dateVal = document.getElementById('shipping-date').value;
        const farmSel = document.getElementById('shipping-farm');
        const farmIdVal = farmSel.value;
        const farmNameVal = farmSel.selectedIndex >= 0 ? farmSel.options[farmSel.selectedIndex].text : '';

        if (!dateVal) {
            alert('날짜를 입력해 줘!');
            return;
        }
        if (!farmIdVal) {
            alert('밭을 선택해 줘!');
            return;
        }

        const items = document.querySelectorAll('.dynamic-product-item');
        if (items.length === 0) {
            alert('상품 종류 수를 입력하고 생성해 줘!');
            return;
        }

        for (const item of items) {
            const q = parseFloat(item.querySelector('.dyn-product-qty').value) || 0;
            const d = parseFloat(item.querySelector('.dyn-product-disposal').value) || 0;
            if (d > q) {
                alert('폐기는 수량보다 클 수 없어!');
                return;
            }
        }

        const tableBody = document.querySelector('#shippingTable tbody');
        const dateFormatted = dateVal.substring(5, 7) + '/' + dateVal.substring(8, 10);

        items.forEach((item) => {
            const productVal = item.querySelector('.dyn-product-select').value;
            const qtyVal = item.querySelector('.dyn-product-qty').value || '0';
            const disposalVal = item.querySelector('.dyn-product-disposal').value || '0';

            const newRow = document.createElement('tr');
            newRow.className = 'custom-row';
            newRow.dataset.date = dateVal;
            newRow.dataset.farmId = farmIdVal;
            newRow.dataset.farm = farmNameVal;
            newRow.dataset.product = productVal;
            newRow.dataset.disposal = disposalVal;
            newRow.dataset.isNew = 'true';
            newRow.dataset.qty = qtyVal;
            newRow.dataset.auctionUnit = '0';
            newRow.dataset.auctionTotal = '0';
            newRow.dataset.depositAmount = '0';
            newRow.dataset.auctionModified = 'false';
            newRow.dataset.depositModified = 'false';
            newRow.dataset.qtyModified = 'false';

            newRow.innerHTML = `
                <td style="background-color: #fffde7;" data-col="date">${dateFormatted}</td>
                <td style="background-color: #fffde7;" data-col="farm" data-memo-date="${escHtml(dateVal)}" data-memo-farm-id="${escHtml(farmIdVal)}" data-memo-farm-name="${escHtml(farmNameVal)}">${escHtml(farmNameVal)}</td>
                <td style="background-color: #fffde7;" data-col="product">${escHtml(productVal)}</td>
                <td style="background-color: #fffde7;" class="text-right" data-col="qty">${escHtml(qtyVal)}</td>
                <td style="background-color: #fffde7;" class="text-right" data-col="disposal">${escHtml(disposalVal)}</td>
                <td class="text-right" data-col="auction-unit">-</td>
                <td class="text-right" data-col="auction-total">-</td>
                <td class="text-right" data-col="deposit-amount">-</td>
                <td class="text-right" data-col="deposit-unit">-</td>
                <td class="text-center" data-col="fee"><button type="button" class="delete-btn">-</button></td>
            `;

            tableBody.insertBefore(newRow, tableBody.firstChild);
        });

        refreshMemoMarks();
        refreshSummaryMarks();
        document.getElementById('headerSaveBtn').style.display = 'inline-block';
        closeShippingModal();
    }
}

function checkAndOpenAuction(row) {
    const isNewRow = row.dataset.isNew === 'true';
    if (isNewRow) {
        alert('New Row일 때는 경매단가 항목 수정팝업을 오픈할 수 없어!');
        return;
    }
    if (row.dataset.isClosed === '1') {
        alert('마감 처리된 Row는 경매단가를 수정할 수 없어! 마감을 해제한 뒤 수정해 줘.');
        return;
    }

    currentEditingRow = row;
    document.getElementById('auction-unit').value = plainNumOrEmpty(row.dataset.auctionUnit);
    document.getElementById('auction-disposal').value = plainNum(row.dataset.disposal) || '0';
    document.getElementById('auctionModal').style.display = 'flex';
}

function saveAuctionRow() {
    if (!currentEditingRow) return;

    const rawUnit = parseFloat(document.getElementById('auction-unit').value) || 0;
    const disposalVal = parseFloat(document.getElementById('auction-disposal').value) || 0;
    const qty = parseFloat(currentEditingRow.dataset.qty) || 0;

    if (disposalVal > qty) {
        alert('폐기는 수량보다 클 수 없어!');
        return;
    }

    currentEditingRow.dataset.disposal = disposalVal;
    currentEditingRow.dataset.auctionUnit = rawUnit;
    currentEditingRow.dataset.auctionModified = 'true';
    currentEditingRow.classList.add('modified-row'); // 붉은 글씨 표시

    const unitCell = currentEditingRow.querySelector('[data-col="auction-unit"]');
    if (unitCell) unitCell.innerText = rawUnit !== 0 ? formatNum(rawUnit) : '-';

    // 폐기 / 경매총액 / 입금단가 / 수수료 표시 갱신
    recalcRow(currentEditingRow);
    refreshSummaryMarks();

    document.getElementById('headerSaveBtn').style.display = 'inline-block';
    document.getElementById('auctionModal').style.display = 'none';
    currentEditingRow = null;
}

function checkAndOpenDeposit(row) {
    const isNewRow = row.dataset.isNew === 'true';
    if (isNewRow) {
        alert('New Row일 때는 입금액 항목 수정팝업을 오픈할 수 없어!');
        return;
    }
    if (row.dataset.isClosed === '1') {
        alert('마감 처리된 Row는 입금액을 수정할 수 없어! 마감을 해제한 뒤 수정해 줘.');
        return;
    }

    currentEditingRow = row;
    document.getElementById('deposit-amount').value = plainNumOrEmpty(row.dataset.depositAmount);
    document.getElementById('depositModal').style.display = 'flex';
}

function saveDepositRow() {
    if (!currentEditingRow) return;

    const rawDeposit = parseFloat(document.getElementById('deposit-amount').value) || 0;

    currentEditingRow.dataset.depositAmount = rawDeposit;
    currentEditingRow.dataset.depositModified = 'true';
    currentEditingRow.classList.add('modified-row'); // 붉은 글씨 표시

    const depositCell = currentEditingRow.querySelector('[data-col="deposit-amount"]');
    if (depositCell) depositCell.innerText = rawDeposit !== 0 ? formatNum(rawDeposit) : '-';

    // 입금단가 / 수수료 표시 갱신
    recalcRow(currentEditingRow);
    refreshSummaryMarks();

    document.getElementById('headerSaveBtn').style.display = 'inline-block';
    document.getElementById('depositModal').style.display = 'none';
    currentEditingRow = null;
}

/*
 * 소계 / 합계 행 표시 갱신
 *  - 저장 대기(신규/수정) Row 가 있는 날짜·밭의 소계, 해당 날짜의 합계는 값이 더 이상 맞지 않으므로
 *    숫자 컬럼을 '-' 로 표시한다. (저장 후 재조회하면 계산된 값이 표시됨)
 *  - 저장 대기 Row 가 없어지면(예: 신규 Row 삭제) 원래 값으로 되돌린다.
 */
function refreshSummaryMarks() {
    const dirtyDates = {};
    const dirtyGroups = {};

    // 삭제 대기 Row 가 있던 날짜/밭의 소계·합계도 값이 맞지 않으므로 '-' 표시
    Object.keys(window.OUTBOUND_DELETE_PENDING).forEach(function (id) {
        const d = window.OUTBOUND_DELETE_PENDING[id];
        dirtyDates[d.date] = true;
        dirtyGroups[d.date + '|' + d.farm] = true;
    });

    document.querySelectorAll('#shippingTable tbody tr').forEach(function (tr) {
        if (tr.classList.contains('farm-subtotal-row') || tr.classList.contains('date-total-row')) return;
        if (!tr.dataset.date) return;

        const pending = tr.dataset.isNew === 'true'
            || tr.dataset.auctionModified === 'true'
            || tr.dataset.depositModified === 'true'
            || tr.dataset.qtyModified === 'true';
        if (!pending) return;

        // 현재 날짜/밭 + (기본정보 팝업으로 바꾼 경우) 원래 날짜/밭 모두 영향
        const pairs = [[tr.dataset.date, tr.dataset.farm]];
        if (tr.dataset.origDate) pairs.push([tr.dataset.origDate, tr.dataset.origFarm]);

        pairs.forEach(function (pair) {
            dirtyDates[pair[0]] = true;
            dirtyGroups[pair[0] + '|' + pair[1]] = true;
        });
    });

    document.querySelectorAll('#shippingTable tbody tr.farm-subtotal-row, #shippingTable tbody tr.date-total-row').forEach(function (tr) {
        const isSubtotal = tr.classList.contains('farm-subtotal-row');
        const dirty = isSubtotal
            ? !!dirtyGroups[tr.dataset.date + '|' + tr.dataset.farm]
            : !!dirtyDates[tr.dataset.date];

        tr.querySelectorAll('td[data-sum]').forEach(function (td) {
            if (dirty) {
                if (td.dataset.orig === undefined) td.dataset.orig = td.innerText;
                td.innerText = '-';
            } else if (td.dataset.orig !== undefined) {
                td.innerText = td.dataset.orig;
                delete td.dataset.orig;
            }
        });
    });
}

/**
 * 저장 대상 Row 수집
 *  - 신규 Row(+ 버튼으로 추가)            → mode: 'insert'
 *  - 기존 Row 중 수정된 Row (outbound_id 기준) → mode: 'update'
 *  - 삭제 버튼으로 삭제한 기존 Row             → mode: 'delete'
 */
function collectSaveRows() {
    const result = [];
    document.querySelectorAll('#shippingTable tbody tr').forEach(function (tr) {
        if (tr.classList.contains('farm-subtotal-row') || tr.classList.contains('date-total-row')) return;
        if (!tr.dataset.date) return; // 안내/오류 메시지 행 제외

        const isNew = tr.dataset.isNew === 'true';
        const modified = tr.dataset.auctionModified === 'true'
            || tr.dataset.depositModified === 'true'
            || tr.dataset.qtyModified === 'true';

        if (!isNew && !modified) return;

        result.push({
            mode: isNew ? 'insert' : 'update',
            outbound_id: isNew ? null : (tr.dataset.outboundId || null),
            outbound_date: tr.dataset.date,
            farm_id: parseInt(tr.dataset.farmId, 10) || 0,
            product_type: tr.dataset.product,
            quantity: parseFloat(tr.dataset.qty) || 0,
            disposal: parseFloat(tr.dataset.disposal) || 0,
            auction_unit_price: parseFloat(tr.dataset.auctionUnit) || 0,
            auction_total_amount: parseFloat(tr.dataset.auctionTotal) || 0,
            deposit_amount: parseFloat(tr.dataset.depositAmount) || 0
        });
    });

    // 삭제 대기 Row (화면에서는 이미 제거됨)
    Object.keys(window.OUTBOUND_DELETE_PENDING).forEach(function (id) {
        const d = window.OUTBOUND_DELETE_PENDING[id];
        result.push({
            mode: 'delete',
            outbound_id: id,
            outbound_date: d.date,
            farm_id: d.farm_id,
            product_type: d.product
        });
    });
    return result;
}

/* ------------------------------------------------------------
 * 메모 (밭 이름 터치) : key = YYYYMMDD + 밭ID
 * ------------------------------------------------------------ */
function memoKey(dateStr, farmId) {
    return String(dateStr).replace(/-/g, '') + String(farmId);
}

function hasSavedMemo(key) {
    return Object.prototype.hasOwnProperty.call(window.OUTBOUND_MEMOS, key);
}

// 메모가 작성/수정된(저장 대기) 밭 이름을 푸른 글씨로 표시
function refreshMemoMarks() {
    document.querySelectorAll('#shippingTable td[data-col="farm"]').forEach(function (td) {
        const d = td.dataset.memoDate;
        const f = td.dataset.memoFarmId;
        if (!d || !f) return;
        td.classList.toggle('memo-modified', !!window.OUTBOUND_MEMO_PENDING[memoKey(d, f)]);
    });
}

// 삭제된 신규 Row 때문에 화면에서 사라진 밭에 대한 저장 대기 메모 정리
function pruneOrphanPendingMemos() {
    const alive = {};
    document.querySelectorAll('#shippingTable td[data-col="farm"]').forEach(function (td) {
        if (td.dataset.memoDate && td.dataset.memoFarmId) {
            alive[memoKey(td.dataset.memoDate, td.dataset.memoFarmId)] = true;
        }
    });
    Object.keys(window.OUTBOUND_MEMO_PENDING).forEach(function (k) {
        if (!alive[k]) delete window.OUTBOUND_MEMO_PENDING[k];
    });
}

function openMemoModal(td) {
    if (window.OUTBOUND_MEMO_ERROR) {
        alert('메모를 조회하지 못해서 메모 팝업을 열 수 없어!');
        return;
    }

    const date = td.dataset.memoDate;
    const farmId = td.dataset.memoFarmId;
    if (!date || !farmId) return;

    const key = memoKey(date, farmId);
    const pending = window.OUTBOUND_MEMO_PENDING[key];
    const saved = hasSavedMemo(key);
    const content = pending ? pending.content : (saved ? window.OUTBOUND_MEMOS[key] : '');

    window.currentMemoCtx = { key: key, date: date, farmId: farmId };

    document.getElementById('memoModalTitle').innerText = saved ? '메모 수정' : '새 메모 작성';
    document.getElementById('memoModalSaveBtn').innerText = saved ? '수정 저장' : '저장';
    document.getElementById('memoModalFarm').innerText = td.dataset.memoFarmName || td.innerText;
    document.getElementById('memoModalDate').innerText = date;
    document.getElementById('memoModalContent').value = content;

    document.getElementById('memoModal').style.display = 'flex';
    document.getElementById('memoModalContent').focus();
}

function closeMemoModal() {
    document.getElementById('memoModal').style.display = 'none';
    window.currentMemoCtx = null;
}

// 팝업 [저장]/[수정 저장] : 화면에만 반영 (DB 저장은 헤더 [저장] 버튼)
function confirmMemo() {
    const ctx = window.currentMemoCtx;
    if (!ctx) return;

    const content = document.getElementById('memoModalContent').value.trim();
    if (!content) {
        alert('내용을 입력해줘!');
        return;
    }

    const saved = hasSavedMemo(ctx.key);
    if (saved && String(window.OUTBOUND_MEMOS[ctx.key]).trim() === content) {
        // DB 에 있는 내용과 같으면 변경 없음
        delete window.OUTBOUND_MEMO_PENDING[ctx.key];
    } else {
        window.OUTBOUND_MEMO_PENDING[ctx.key] = {
            date: ctx.date,
            farm_id: parseInt(ctx.farmId, 10) || 0,
            content: content
        };
    }

    refreshMemoMarks();
    closeMemoModal();

    if (hasPendingChanges()) {
        document.getElementById('headerSaveBtn').style.display = 'inline-block';
    } else {
        document.getElementById('headerSaveBtn').style.display = 'none';
    }
}

// 저장 대상 메모 수집
function collectSaveMemos() {
    return Object.keys(window.OUTBOUND_MEMO_PENDING).map(function (k) {
        return window.OUTBOUND_MEMO_PENDING[k];
    });
}

function hasPendingChanges() {
    return collectSaveRows().length > 0 || collectSaveMemos().length > 0;
}

/* ------------------------------------------------------------
 * 날짜 이동 : 콤보박스에서 날짜를 선택하면 그리드를 해당 날짜의 첫 Row 위치로 스크롤 (재조회 아님)
 * ------------------------------------------------------------ */
function scrollToOutboundDate(dateStr) {
    if (!dateStr) return;

    const container = document.querySelector('.shipping-table-container');
    if (!container) return;

    // 저장된(기존) Row 중 해당 날짜의 첫 Row (+ 버튼으로 추가한 Row 는 제외)
    const rows = container.querySelectorAll('#shippingTable tbody tr[data-outbound-id]');
    let target = null;
    for (let i = 0; i < rows.length; i++) {
        if (rows[i].dataset.date === dateStr) {
            target = rows[i];
            break;
        }
    }
    if (!target) return;

    // sticky 헤더(thead) 높이만큼 위로 여유를 둔다
    const thead = container.querySelector('thead');
    const headH = thead ? thead.offsetHeight : 0;
    const top = target.getBoundingClientRect().top
        - container.getBoundingClientRect().top
        + container.scrollTop
        - headH;

    container.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
}

/* ------------------------------------------------------------
 * 마감 / 마감해제 (수수료 칸 터치) : 같은 날짜의 Row 를 한 번에 처리
 *  - 팝업 오픈 전 확인(기존과 동일) : 저장 대기 Row 가 없어야 하고, 터치한 Row 의 입금액이 1 이상
 *  - 팝업에는 같은 날짜의 모든 Row 가 체크박스로 표시 (체크 = 마감 상태)
 *      · 미마감 Row 를 체크      → 마감     (farm_income INSERT)
 *      · 마감 Row 를 체크 해제   → 마감해제 (farm_income DELETE)
 *  - 저장 버튼과 무관하게 일괄 저장 후 현재 년/월로 재조회
 * ------------------------------------------------------------ */
window.currentClosingCtx = null;

function checkAndOpenClosing(row) {
    // New Row 또는 수정된(붉은 글씨 등) Row 가 하나라도 있으면 어떤 Row 든 먼저 저장해야 함
    if (collectSaveRows().length > 0) {
        alert('수정된 내용이 있어! 먼저 저장 버튼으로 저장한 뒤에 마감 처리를 해 줘.');
        return;
    }

    const deposit = parseFloat(row.dataset.depositAmount) || 0;
    if (deposit < 1) {
        alert('입금액이 입력된 Row만 마감처리할 수 있어!');
        return;
    }

    if (!row.dataset.outboundId) return;

    // 같은 날짜의 Row 수집
    const date = row.dataset.date;
    const items = [];
    document.querySelectorAll('#shippingTable tbody tr').forEach(function (tr) {
        if (tr.classList.contains('farm-subtotal-row') || tr.classList.contains('date-total-row')) return;
        if (tr.dataset.date !== date || !tr.dataset.outboundId) return;

        const isClosed = tr.dataset.isClosed === '1';
        const dep = parseFloat(tr.dataset.depositAmount) || 0;
        items.push({
            outboundId: tr.dataset.outboundId,
            farm: tr.dataset.farm || '',
            product: tr.dataset.product || '',
            qty: tr.dataset.qty,
            deposit: dep,
            isClosed: isClosed,
            disabled: !isClosed && dep < 1, // 입금액이 없는 미마감 Row 는 마감 불가
            touched: tr === row
        });
    });

    if (items.length === 0) return;

    window.currentClosingCtx = { date: date };

    document.getElementById('closingModalTitle').innerText = date + ' 마감 / 마감해제';
    document.getElementById('closingList').innerHTML = items.map(function (it) {
        const cls = 'closing-item' + (it.disabled ? ' disabled' : '') + (it.touched ? ' touched' : '');
        const status = it.disabled ? '입금액 없음' : (it.isClosed ? '마감됨' : '미마감');
        return '<label class="' + cls + '">'
            + '<input type="checkbox" class="closing-check"'
            + ' data-outbound-id="' + escHtml(it.outboundId) + '"'
            + ' data-original="' + (it.isClosed ? '1' : '0') + '"'
            + (it.isClosed ? ' checked' : '')
            + (it.disabled ? ' disabled' : '')
            + ' onchange="updateClosingSummary()">'
            + '<span class="closing-item-text">' + escHtml(it.farm) + ' · ' + escHtml(it.product)
            + ' · 수량 ' + escHtml(formatNum(it.qty)) + ' · 입금 ' + escHtml(formatNum(it.deposit)) + '</span>'
            + '<span class="closing-item-status">' + status + '</span>'
            + '</label>';
    }).join('');

    const btn = document.getElementById('closingModalBtn');
    btn.disabled = false;
    updateClosingSummary();

    document.getElementById('closingModal').style.display = 'flex';
}

// 전체 체크 / 전체 체크해제 (비활성 항목 제외)
function setAllClosingChecks(checked) {
    document.querySelectorAll('#closingList .closing-check').forEach(function (cb) {
        if (!cb.disabled) cb.checked = checked;
    });
    updateClosingSummary();
}

// 원래 상태와 달라진 항목만 수집 (체크됨 → 마감, 체크 해제 → 마감해제)
function collectClosingChanges() {
    const changes = [];
    document.querySelectorAll('#closingList .closing-check').forEach(function (cb) {
        if (cb.disabled) return;
        const original = cb.dataset.original === '1';
        if (cb.checked !== original) {
            changes.push({ outbound_id: cb.dataset.outboundId, closed: cb.checked ? 1 : 0 });
        }
    });
    return changes;
}

function updateClosingSummary() {
    const changes = collectClosingChanges();
    const closeCnt = changes.filter(function (c) { return c.closed === 1; }).length;
    const openCnt = changes.length - closeCnt;
    document.getElementById('closingSummary').innerText = changes.length === 0
        ? '변경된 항목이 없어'
        : '마감 ' + closeCnt + '건 · 마감해제 ' + openCnt + '건 처리 예정';
}

function closeClosingModal() {
    document.getElementById('closingModal').style.display = 'none';
    window.currentClosingCtx = null;
}

async function submitClosing() {
    if (!window.currentClosingCtx) return;

    const changes = collectClosingChanges();
    if (changes.length === 0) {
        alert('변경된 항목이 없어! 마감할 항목은 체크하고, 해제할 항목은 체크를 풀어 줘.');
        return;
    }

    // 재조회하면 저장하지 않은 변경 내용(메모 등)은 사라지므로 한 번 더 확인
    if (hasPendingChanges()
        && !confirm('저장하지 않은 변경 내용이 있어. 처리 후 다시 조회하면 사라져. 계속할까?')) {
        return;
    }

    const btn = document.getElementById('closingModalBtn');
    btn.disabled = true;

    try {
        const res = await fetch('outbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'set_closed_batch', items: changes })
        });

        let data = null;
        try {
            data = await res.json();
        } catch (e) {
            data = null;
        }

        if (!res.ok || !data || !data.success) {
            alert('처리에 실패했어!\n' + (data && data.message ? data.message : '서버 응답 오류'));
            btn.disabled = false;
            return;
        }

        closeClosingModal();
        alert(data.message || '처리되었어!');
        reloadCurrentMonth(); // 현재 선택된 년/월 조건으로 재조회
    } catch (err) {
        alert('처리 중 통신 오류가 발생했어!');
        btn.disabled = false;
    }
}

// 현재 화면에서 선택된 년/월 조건으로 다시 조회
function reloadCurrentMonth() {
    const form = document.querySelector('.shipping-ym-form');
    if (!form) {
        window.location.reload();
        return;
    }
    const ymInput = form.querySelector('input[name="search_ym"]');
    if (ymInput && !ymInput.value) {
        ymInput.value = window.OUTBOUND_SEARCH_YM || '';
    }
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.submit();
    }
}

async function saveNewRowAction() {
    const rows = collectSaveRows();
    const memos = collectSaveMemos();

    if (rows.length === 0 && memos.length === 0) {
        alert('저장할 변경 내용이 없어!');
        document.getElementById('headerSaveBtn').style.display = 'none';
        return;
    }

    const invalid = rows.find(function (r) { return !r.farm_id || !r.outbound_date || !r.product_type; });
    if (invalid) {
        alert('날짜, 밭, 상품 종류가 비어 있는 행이 있어!');
        return;
    }

    if (!confirm('작성 및 수정된 모든 내용을 저장할까?')) return;

    const btn = document.getElementById('headerSaveBtn');
    btn.disabled = true;

    try {
        const res = await fetch('outbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', rows: rows, memos: memos })
        });

        let data = null;
        try {
            data = await res.json();
        } catch (e) {
            data = null;
        }

        if (!res.ok || !data || !data.success) {
            alert('저장에 실패했어!\n' + (data && data.message ? data.message : '서버 응답 오류'));
            return;
        }

        alert((data.message || '성공적으로 저장되었어!'));
        reloadCurrentMonth(); // 저장 완료 후 선택된 년/월 조건으로 재조회
    } catch (err) {
        alert('저장 중 통신 오류가 발생했어!');
    } finally {
        btn.disabled = false;
    }
}

/*
 * 기존(서버에서 조회된) Row 를 화면에서 제거
 *  - 날짜 / 밭 이름 셀은 rowspan 으로 병합되어 있으므로 제거하는 Row 가 그 셀을 가지고 있으면
 *    다음 Row 로 옮기고, rowspan 을 줄인다.
 *  - 밭의 마지막 Row 를 제거하면 해당 밭의 소계 Row, 날짜의 마지막 밭이면 날짜 합계 Row 도 함께 제거한다.
 */
function removeExistingRow(tr) {
    // 날짜/밭 변경으로 묶음에서 분리된 Row 는 자체 셀만 가지므로 그냥 제거
    if (tr.classList.contains('custom-row')) {
        tr.remove();
        return;
    }

    const date = tr.dataset.date;
    const farm = tr.dataset.farm;
    const allRows = Array.from(document.querySelectorAll('#shippingTable tbody tr'));

    function isItemRow(r) {
        return r !== tr && r.dataset.outboundId && r.dataset.isNew !== 'true' && !r.classList.contains('custom-row');
    }

    const sameFarmCount = allRows.filter(function (r) {
        return isItemRow(r) && r.dataset.date === date && r.dataset.farm === farm;
    }).length;
    const sameDateCount = allRows.filter(function (r) {
        return isItemRow(r) && r.dataset.date === date;
    }).length;

    const farmEmpty = sameFarmCount === 0;
    const dateEmpty = sameDateCount === 0;

    const subtotalRow = allRows.find(function (r) {
        return r.classList.contains('farm-subtotal-row') && r.dataset.date === date && r.dataset.farm === farm;
    });
    const totalRow = allRows.find(function (r) {
        return r.classList.contains('date-total-row') && r.dataset.date === date;
    });

    // 날짜 전체가 사라지는 경우 : 소계 / 합계 Row 와 함께 모두 제거 (셀은 Row 와 함께 사라짐)
    if (dateEmpty) {
        if (subtotalRow) subtotalRow.remove();
        if (totalRow) totalRow.remove();
        tr.remove();
        return;
    }

    // rowspan 셀 소유자 찾기 (제거하는 Row 가 아니면 위쪽 Row 에서 탐색)
    let dateOwner = tr.querySelector('td[data-col="date"][rowspan]');
    let farmOwner = tr.querySelector('td[data-col="farm"][rowspan]');
    for (let r = tr.previousElementSibling; r && (!dateOwner || !farmOwner); r = r.previousElementSibling) {
        if (r.dataset.date !== date) break;
        if (!dateOwner) dateOwner = r.querySelector('td[data-col="date"][rowspan]');
        if (!farmOwner && r.dataset.farm === farm) farmOwner = r.querySelector('td[data-col="farm"][rowspan]');
    }

    const removedRows = farmEmpty ? 2 : 1; // Row + (밭이 비면) 소계 Row
    const nextRow = farmEmpty && subtotalRow ? subtotalRow.nextElementSibling : tr.nextElementSibling;

    // 밭 이름 셀 : 밭에 Row 가 남아 있으면 rowspan 감소 + (제거 Row 소유이면) 다음 Row 로 이동
    if (!farmEmpty && farmOwner) {
        farmOwner.rowSpan = Math.max(1, farmOwner.rowSpan - 1);
        if (farmOwner.parentElement === tr && nextRow) {
            nextRow.insertBefore(farmOwner, nextRow.firstChild);
        }
    }

    // 날짜 셀 : rowspan 감소 + (제거 Row 소유이면) 다음 Row 로 이동 (밭 이름 셀보다 앞에 위치)
    if (dateOwner) {
        dateOwner.rowSpan = Math.max(1, dateOwner.rowSpan - removedRows);
        if (dateOwner.parentElement === tr && nextRow) {
            nextRow.insertBefore(dateOwner, nextRow.firstChild);
        }
    }

    if (farmEmpty && subtotalRow) subtotalRow.remove();
    tr.remove();
}

// 저장 대기 중인 변경(신규/수정 Row, 메모)이 하나도 없으면 저장 버튼 숨김
function checkHeaderSaveButton() {
    if (!hasPendingChanges()) {
        document.getElementById('headerSaveBtn').style.display = 'none';
    }
}

// 이벤트 위임(Event Delegation)을 통한 안정적인 클릭 처리
document.getElementById('shippingTable').addEventListener('click', (e) => {
    const td = e.target.closest('td');
    if (!td) return;
    const tr = td.closest('tr');

    // 소계 및 합계 행은 클릭 이벤트 완전 차단
    if (!tr || tr.classList.contains('farm-subtotal-row') || tr.classList.contains('date-total-row')) return;

    // 삭제 버튼 클릭 처리
    if (e.target.classList.contains('delete-btn')) {
        e.stopPropagation();
        if (tr.dataset.isNew === 'true') {
            // 신규 Row : 저장 전이므로 화면에서만 제거
            if (confirm('이 줄을 삭제할까?')) {
                tr.remove();
                pruneOrphanPendingMemos();
                refreshMemoMarks();
                refreshSummaryMarks();
                checkHeaderSaveButton();
            }
            return;
        }

        // 기존 Row : 경매단가·입금액이 모두 0 이고 마감되지 않은 Row 만 삭제 가능
        if (tr.dataset.isClosed === '1') {
            alert('마감 처리된 Row는 삭제할 수 없어!');
            return;
        }
        if ((parseFloat(tr.dataset.auctionUnit) || 0) !== 0 || (parseFloat(tr.dataset.depositAmount) || 0) !== 0) {
            alert('경매단가 또는 입금액이 입력된 Row는 삭제할 수 없어!');
            return;
        }
        if (confirm('이 줄을 삭제할까?\n(헤더 [저장] 버튼을 눌러야 DB 에서 삭제돼)')) {
            window.OUTBOUND_DELETE_PENDING[tr.dataset.outboundId] = {
                date: tr.dataset.date,
                farm: tr.dataset.farm,
                farm_id: parseInt(tr.dataset.farmId, 10) || 0,
                product: tr.dataset.product
            };
            removeExistingRow(tr);
            document.getElementById('headerSaveBtn').style.display = 'inline-block';
            pruneOrphanPendingMemos();
            refreshMemoMarks();
            refreshSummaryMarks();
            checkHeaderSaveButton();
        }
        return;
    }

    const col = td.dataset.col;
    if (!col) return;

    e.stopPropagation();

    // 밭 이름(farm) → 메모 등록/수정 팝업, 날짜(date)는 팝업 없음
    if (col === 'farm') {
        openMemoModal(td);
    } else if (['product', 'qty', 'disposal'].includes(col)) {
        checkAndOpenEdit(tr);
    } else if (['auction-unit', 'auction-total'].includes(col)) {
        checkAndOpenAuction(tr);
    } else if (['deposit-amount', 'deposit-unit'].includes(col)) {
        checkAndOpenDeposit(tr);
    } else if (col === 'fee') {
        checkAndOpenClosing(tr);
    }
});
</script>