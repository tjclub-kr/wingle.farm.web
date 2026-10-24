<?php
/**
 * parsing.php : 문자 분석 화면
 *
 * - menu.php 의 loadPage() 로 #content-area 안에 불러오는 "화면 조각" 이다. (html/head/body 태그 없음)
 * - [분석] 버튼은 페이지 이동 없이 AJAX(fetch)로 이 파일에 POST 하고,
 *   서버는 결과 영역(HTML 조각)만 돌려준다. 받은 결과는 버튼 아래에 표시된다.
 *
 * - 유형 콤보박스의 기본값은 "자동 감지" : 각 파서의 matches() 로 문자 유형을 판별한다.
 *
 * [유형 추가 방법]  ※ 유형마다 파싱 방식이 달라지는 것을 전제로 만든 구조
 *   1) MessageParser 를 구현한 클래스를 새로 만든다. (BaseMessageParser 를 상속하면 공용 함수 사용 가능)
 *        - label()   : 콤보박스에 표시할 이름
 *        - columns() : 결과 표의 컬럼 정의 [키 => 헤더명]
 *        - matches() : 자동 감지용. 이 유형의 문자면 true
 *        - parse()   : 문자 -> ['records' => [...], 'warnings' => [...]]
 *   2) parsing_registry() 에 한 줄 등록한다.
 *   콤보박스, 결과 표, 검증은 등록된 파서를 기준으로 자동 처리되므로 화면 코드는 고칠 필요가 없다.
 *
 * [현재 등록된 유형]
 *   - 경매가   : 거래일자 / 등급 / 가격 을 읽는다. (문자 1건 또는 여러 건 붙여넣기 모두 지원)
 *   - 금일시세 : 금일 반입물량(총량 / 정품·찹찹·바라 전일대비) + 금일 경매시세(정품·봉지·찹찹·바라 최고/평균/최저가, 전일대비) 를 표 2개로 보여준다.
 */
declare(strict_types=1);

/* ==================================================
 * 파서 공통 규격
 * ================================================== */
interface MessageParser
{
    /** 콤보박스에 표시할 이름 */
    public function label(): string;

    /**
     * "자동 감지" 용 : 이 문자가 이 유형의 문자처럼 보이면 true
     * (문자 안의 고유한 키워드로 판단한다. 새 유형을 만들 때 함께 구현한다.)
     */
    public function matches(string $text): bool;

    /**
     * 결과 표의 컬럼 정의
     * @return array<string,string> [키 => 헤더명]
     */
    public function columns(): array;

    /**
     * 문자 분석
     * @return array{records: array<int,array<string,mixed>>, warnings: array<int,string>, sections?: array}
     *         records 의 각 Row 는 columns() 의 키를 가진다. (읽지 못한 값은 null)
     *         표를 여러 개 보여줘야 하면 'sections' => [['title'=>..., 'columns'=>[...], 'records'=>[...]], ...] 를 추가한다.
     *         (sections 가 있으면 화면은 이것을 우선 사용한다.)
     */
    public function parse(string $text): array;
}

/**
 * 파서들이 함께 쓰는 공용 함수 모음
 */
abstract class BaseMessageParser implements MessageParser
{
    /** 모든 공백 제거 (키워드 검색용) */
    protected function compact(string $text): string
    {
        return (string)preg_replace('/[\s\x{00A0}\x{3000}]+/u', '', $text);
    }

    /** 줄바꿈 통일 후 줄 단위로 분리 */
    protected function splitLines(string $text): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * "● 품  목 : 깻잎" 형태의 줄을 [라벨, 값] 으로 분리한다.
     * 라벨 안의 공백은 모두 제거한다. ("품  목" -> "품목")
     * "라벨 : 값" 형식이 아닌 줄은 null.
     *
     * @return array{0:string,1:string}|null
     */
    protected function parseLabelLine(string $line): ?array
    {
        $pattern = '/^[\s\x{25CF}\x{2022}\x{00B7}\*\-]*([^:\x{FF1A}]+?)\s*[:\x{FF1A}]\s*(.*?)\s*$/u';
        if (preg_match($pattern, $line, $m) !== 1) {
            return null;
        }

        $label = (string)preg_replace('/[\s\x{00A0}\x{3000}]+/u', '', $m[1]);
        if ($label === '') {
            return null;
        }

        return [$label, trim($m[2])];
    }

    /** "2026. 10. 14" / "2026-10-14" / "2026년 10월 14일" -> "2026-10-14" (실패 시 null) */
    protected function parseDate(string $value): ?string
    {
        if (preg_match('/(\d{4})\D+(\d{1,2})\D+(\d{1,2})/', $value, $m) !== 1) {
            return null;
        }

        $year  = (int)$m[1];
        $month = (int)$m[2];
        $day   = (int)$m[3];

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    /** "10,500원" -> 10500 (실패 시 null) */
    protected function parsePrice(string $value): ?int
    {
        if (preg_match('/\d[\d,]*/', $value, $m) !== 1) {
            return null;
        }

        return (int)str_replace(',', '', $m[0]);
    }
}

/* ==================================================
 * 유형별 파서
 * ================================================== */

/**
 * 경매가 : 가락시장 거래결과 알림 문자
 *
 *   ● 거래일자 : 2026. 10. 14
 *   ● 등  급 : 바라
 *   ● 가  격 : 10,500원
 *
 * 같은 항목이 다시 나오면 새 문자로 보고 Row 를 나눈다. (여러 건을 한꺼번에 붙여넣어도 분석됨)
 */
final class AuctionPriceParser extends BaseMessageParser
{
    /** 문자 안의 라벨 => 내부 키 */
    private const FIELD_MAP = [
        '거래일자' => 'date',
        '등급'     => 'grade',
        '가격'     => 'price',
    ];

    public function label(): string
    {
        return '경매가';
    }

    public function matches(string $text): bool
    {
        $c = $this->compact($text);
        return mb_strpos($c, '거래일자', 0, 'UTF-8') !== false
            && mb_strpos($c, '가격', 0, 'UTF-8') !== false;
    }

    public function columns(): array
    {
        return [
            'date'  => '날짜',
            'grade' => '상품 종류',
            'price' => '경매 단가',
        ];
    }

    public function parse(string $text): array
    {
        /* 1단계 : 필요한 항목만 모아 문자 단위로 묶는다. */
        $rawRecords = [];
        $current    = [];

        foreach ($this->splitLines($text) as $line) {
            $pair = $this->parseLabelLine($line);
            if ($pair === null || !isset(self::FIELD_MAP[$pair[0]])) {
                continue;
            }

            $key = self::FIELD_MAP[$pair[0]];
            if (array_key_exists($key, $current)) {
                $rawRecords[] = $current;
                $current      = [];
            }
            $current[$key] = $pair[1];
        }
        if ($current !== []) {
            $rawRecords[] = $current;
        }

        /* 2단계 : 값을 날짜 / 문자열 / 숫자로 변환한다. */
        $records  = [];
        $warnings = [];

        foreach ($rawRecords as $index => $raw) {
            $no    = $index + 1;
            $date  = isset($raw['date'])  ? $this->parseDate($raw['date'])   : null;
            $grade = isset($raw['grade']) && $raw['grade'] !== '' ? $raw['grade'] : null;
            $price = isset($raw['price']) ? $this->parsePrice($raw['price']) : null;

            if ($date === null) {
                $warnings[] = $no . '번째 문자: 거래일자를 읽지 못했어요.';
            }
            if ($grade === null) {
                $warnings[] = $no . '번째 문자: 등급을 읽지 못했어요.';
            }
            if ($price === null) {
                $warnings[] = $no . '번째 문자: 가격을 읽지 못했어요.';
            }

            $records[] = ['date' => $date, 'grade' => $grade, 'price' => $price];
        }

        if ($records === []) {
            $warnings[] = '거래일자, 등급, 가격을 찾지 못했어요. 문자 내용을 확인해 주세요.';
        }

        return ['records' => $records, 'warnings' => $warnings];
    }
}

/**
 * 금일시세 : 중앙청과 금산깻잎 일일시세 문자 (결과 표 2개: 반입물량 / 경매시세)
 *
 *   [10/14일자 중앙청과 금산깻잎 일일시세]
 *   * 금일 반입물량 : 6.9ton (전일대비 -1.1ton)
 *    - 정품(봉지포함) : 전일대비  조금감소
 *    - 벌크(찹찹) : 전일대비 조금감소
 *    - 바라 : 전일대비 조금감소
 *   * 금일 경매시세
 *    - 정품(묶음) : 21,000 / 14,000 / 9,500 (전일대비 소폭하락)
 *    - 봉지(Gap포함) : 17,500 / 15,500 / 13,500 (전일대비 소폭하락)
 *    - 벌크(2kg찹찹) : 23,500 / 16,500 / 11,500 (전일대비 소폭하락)
 *    - 바라(4kg) : 31,500 / 9,000 / 6,500 (전일대비 소폭상승)
 *
 * 경매시세의 가격 순서는 "최고가 / 평균가 / 최저가" 로 본다.
 * 등급 이름은 "정품 / 봉지 / 찹찹(벌크) / 바라" 로 통일해서 보여준다.
 * 문자 1건 기준으로 분석한다.
 */
final class TodayPriceParser extends BaseMessageParser
{
    /** 라벨에 들어 있는 단어 => 통일된 등급 이름 (위에서부터 먼저 맞는 것 사용) */
    private const GRADE_WORDS = [
        '정품' => ['정품'],
        '봉지' => ['봉지'],
        '찹찹' => ['찹찹', '벌크'],
        '바라' => ['바라'],
    ];

    private const INBOUND_GRADES = ['정품', '찹찹', '바라'];
    private const AUCTION_GRADES = ['정품', '봉지', '찹찹', '바라'];

    public function label(): string
    {
        return '금일시세';
    }

    public function matches(string $text): bool
    {
        $c = $this->compact($text);
        return mb_strpos($c, '금일반입물량', 0, 'UTF-8') !== false
            || mb_strpos($c, '금일경매시세', 0, 'UTF-8') !== false;
    }

    /** 이 유형은 parse() 결과의 sections 로 표를 정의하므로 사용하지 않는다. */
    public function columns(): array
    {
        return [];
    }

    private function detectGrade(string $label): ?string
    {
        foreach (self::GRADE_WORDS as $grade => $words) {
            foreach ($words as $word) {
                if (mb_strpos($label, $word, 0, 'UTF-8') !== false) {
                    return $grade;
                }
            }
        }
        return null;
    }

    public function parse(string $text): array
    {
        $dateLabel = null;
        $section   = null;      // 'inbound' | 'auction' | null
        $total     = null;      // 총 반입량
        $totalDiff = null;      // 총 반입량 전일대비
        $inbound   = [];        // 등급 => 전일대비 증감(문자열)
        $auction   = [];        // 등급 => [high, avg, low, trend]
        $warnings  = [];

        foreach ($this->splitLines($text) as $line) {
            if ($dateLabel === null && preg_match('/(\d{1,2})\s*\/\s*(\d{1,2})\s*일자/u', $line, $m) === 1) {
                $dateLabel = $m[1] . '/' . $m[2];
            }

            $compact = (string)preg_replace('/[\s\x{00A0}\x{3000}]+/u', '', $line);
            $pair    = $this->parseLabelLine($line);

            /* 구역 제목 줄 */
            if (mb_strpos($compact, '금일반입물량', 0, 'UTF-8') !== false) {
                $section = 'inbound';
                if ($pair !== null
                    && preg_match('/^([+\-]?\d[\d,]*(?:\.\d+)?\s*[A-Za-z톤]*)\s*(?:\(\s*전일대비\s*([^)]*?)\s*\))?/u', $pair[1], $m) === 1
                ) {
                    $total = trim($m[1]);
                    $diff  = trim($m[2] ?? '');
                    $totalDiff = $diff !== '' ? $diff : null;
                }
                continue;
            }
            if (mb_strpos($compact, '금일경매시세', 0, 'UTF-8') !== false) {
                $section = 'auction';
                continue;
            }
            if (mb_substr(ltrim($line), 0, 1, 'UTF-8') === '#') {
                $section = null;   // "#전일비교 ..." 요약 줄부터는 읽지 않는다.
                continue;
            }

            /* 구역 안의 "- 항목 : 값" 줄 */
            if ($section === null || $pair === null) {
                continue;
            }
            $grade = $this->detectGrade($pair[0]);
            if ($grade === null) {
                continue;
            }

            if ($section === 'inbound') {
                $value = trim((string)preg_replace('/^전일대비\s*/u', '', $pair[1]));
                $inbound[$grade] = $value !== '' ? $value : null;
            } else {
                $pattern = '/^([\d,]+)\s*\/\s*([\d,]+)\s*\/\s*([\d,]+)\s*(?:\(\s*전일대비\s*([^)]*?)\s*\))?/u';
                if (preg_match($pattern, $pair[1], $m) === 1) {
                    $trend = trim($m[4] ?? '');
                    $auction[$grade] = [
                        'high'  => (int)str_replace(',', '', $m[1]),
                        'avg'   => (int)str_replace(',', '', $m[2]),
                        'low'   => (int)str_replace(',', '', $m[3]),
                        'trend' => $trend !== '' ? $trend : null,
                    ];
                } else {
                    $warnings[] = '경매시세 ' . $grade . ' 항목의 가격을 읽지 못했어요.';
                }
            }
        }

        if ($total === null && $inbound === [] && $auction === []) {
            return [
                'records'  => [],
                'warnings' => ['금일 반입물량 / 금일 경매시세 내용을 찾지 못했어요. 문자 내용을 확인해 주세요.'],
            ];
        }

        $suffix = $dateLabel !== null ? ' (' . $dateLabel . '일자)' : '';

        /* 표 1 : 금일 반입물량 */
        if ($total === null) {
            $warnings[] = '금일 반입물량(총량)을 읽지 못했어요.';
        }
        $inboundRows = [['name' => '총량', 'amount' => $total, 'diff' => $totalDiff]];
        foreach (self::INBOUND_GRADES as $grade) {
            if (!array_key_exists($grade, $inbound)) {
                $warnings[] = '반입물량 ' . $grade . ' 항목을 읽지 못했어요.';
            }
            $inboundRows[] = ['name' => $grade, 'amount' => null, 'diff' => $inbound[$grade] ?? null];
        }

        /* 표 2 : 금일 경매시세 */
        $auctionRows = [];
        foreach (self::AUCTION_GRADES as $grade) {
            $row = $auction[$grade] ?? null;
            if ($row === null && !in_array('경매시세 ' . $grade . ' 항목의 가격을 읽지 못했어요.', $warnings, true)) {
                $warnings[] = '경매시세 ' . $grade . ' 항목을 읽지 못했어요.';
            }
            $auctionRows[] = [
                'name'  => $grade,
                'high'  => $row['high']  ?? null,
                'avg'   => $row['avg']   ?? null,
                'low'   => $row['low']   ?? null,
                'trend' => $row['trend'] ?? null,
            ];
        }

        return [
            'records'  => [],
            'warnings' => $warnings,
            'sections' => [
                [
                    'title'   => '금일 반입물량' . $suffix,
                    'columns' => ['name' => '구분', 'amount' => '반입량', 'diff' => '전일대비'],
                    'records' => $inboundRows,
                ],
                [
                    'title'   => '금일 경매시세' . $suffix,
                    'columns' => ['name' => '상품 종류', 'high' => '최고가', 'avg' => '평균가', 'low' => '최저가', 'trend' => '전일대비'],
                    'records' => $auctionRows,
                ],
            ],
        ];
    }
}

/* ==================================================
 * 유형 등록부 : 콤보박스에 나오는 순서 = 등록 순서
 * 새 유형은 여기에 한 줄만 추가한다.  '유형 코드' => new 파서클래스()
 * ================================================== */
function parsing_registry(): array
{
    return [
        'auction' => new AuctionPriceParser(),
        'today'   => new TodayPriceParser(),
    ];
}

/** 등록된 파서 중 이 문자에 맞는 첫 유형 코드를 찾는다. (없으면 null) */
function parsing_detect(array $parsers, string $text): ?string
{
    foreach ($parsers as $code => $parser) {
        if ($parser->matches($text)) {
            return (string)$code;
        }
    }
    return null;
}

function parsing_h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 결과 표의 셀 표시 : 값 없음 "-", 숫자는 천 단위 콤마 */
function parsing_cell($v): string
{
    if ($v === null || $v === '') {
        return '-';
    }
    if (is_int($v) || is_float($v)) {
        return number_format($v);
    }
    return parsing_h($v);
}

/**
 * 결과 영역 HTML 출력 (에러 / 경고 / 결과 표)
 * AJAX 응답과 최초 화면 렌더링에서 똑같이 사용한다.
 *
 * parse() 결과에 'sections' 가 있으면 표를 여러 개로, 없으면 파서의 columns() 로 표 1개를 그린다.
 */
function parsing_render_result(?string $error, ?array $result, MessageParser $parser): void
{
    if ($error !== null): ?>
        <div class="message-error" role="alert"><?= parsing_h($error) ?></div>
    <?php endif;

    if ($result === null) {
        return;
    }

    if (!empty($result['notice'])): ?>
        <div class="notice"><?= parsing_h($result['notice']) ?></div>
    <?php endif;

    if ($result['warnings'] !== []): ?>
        <ul class="warnings" role="alert">
            <?php foreach ($result['warnings'] as $warning): ?>
                <li><?= parsing_h($warning) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif;

    $sections = $result['sections'] ?? [];
    if ($sections === [] && $result['records'] !== []) {
        $sections[] = [
            'title'   => '분석 결과 (' . count($result['records']) . '건)',
            'columns' => $parser->columns(),
            'records' => $result['records'],
        ];
    }

    foreach ($sections as $sec):
        if ($sec['records'] === []) {
            continue;
        } ?>
        <div class="result">
            <p class="result-title"><?= parsing_h($sec['title']) ?></p>
            <div class="result-scroll">
                <table class="result-table">
                    <thead>
                        <tr>
                            <?php foreach ($sec['columns'] as $header): ?>
                                <th><?= parsing_h($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sec['records'] as $record): ?>
                            <tr>
                                <?php foreach ($sec['columns'] as $key => $header): ?>
                                    <?php $value = $record[$key] ?? null; ?>
                                    <td<?= is_int($value) || is_float($value) ? ' class="num"' : '' ?>><?= parsing_cell($value) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach;
}

/* ==================================================
 * 요청 처리
 * ================================================== */
const PARSING_MAX_LENGTH = 20000;

$parsers = parsing_registry();
reset($parsers);
$firstType    = (string)key($parsers);
$defaultType  = 'auto';          // 'auto' = 자동 감지
$selectedType = $defaultType;
$parserKey    = $firstType;      // 실제로 분석에 쓰는 파서
$content      = '';
$result       = null;
$error        = null;

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

if ($isPost) {
    $selectedType = (string)($_POST['type'] ?? '');
    $content      = (string)($_POST['content'] ?? '');

    if ($selectedType !== 'auto' && !isset($parsers[$selectedType])) {
        $error        = '알 수 없는 유형이에요. 유형을 다시 선택해 주세요.';
        $selectedType = $defaultType;
    } elseif (trim($content) === '') {
        $error = '내용을 입력해 주세요.';
    } elseif (mb_strlen($content, 'UTF-8') > PARSING_MAX_LENGTH) {
        $error = '내용이 너무 길어요. ' . number_format(PARSING_MAX_LENGTH) . '자 이하로 입력해 주세요.';
    } else {
        $parserKey = $selectedType;
        if ($selectedType === 'auto') {
            $detected = parsing_detect($parsers, $content);
            if ($detected === null) {
                $error     = '문자 유형을 자동으로 찾지 못했어요. 유형을 직접 선택해 주세요.';
                $parserKey = $firstType;
            } else {
                $parserKey = $detected;
            }
        }

        if ($error === null) {
            $result = $parsers[$parserKey]->parse($content);
            if ($selectedType === 'auto') {
                $result['notice'] = '자동 감지된 유형 : ' . $parsers[$parserKey]->label();
            }
        }
    }
}

$activeParser = $parsers[$parserKey];

/* AJAX 요청이면 결과 영역 조각만 돌려주고 끝낸다. */
if ($isPost && $isAjax) {
    header('Content-Type: text/html; charset=UTF-8');
    parsing_render_result($error, $result, $activeParser);
    exit;
}
?>
<style>
    #parsing-page { font-size: 13px; color: #333; max-width: 560px; margin: 0 auto; }
    #parsing-page .page-title {
        font-size: 16px;
        font-weight: bold;
        color: #2c3e50;
        text-align: center;
        margin: 4px 0 12px;
    }
    #parsing-page .field { margin-bottom: 10px; }
    #parsing-page .field label {
        display: block;
        font-size: 12px;
        font-weight: bold;
        color: #4a148c;
        margin-bottom: 3px;
    }
    #parsing-page .field select,
    #parsing-page .field textarea {
        width: 100%;
        border: 1px solid #d1c4e9;
        border-radius: 4px;
        font-family: inherit;
        font-size: 13px;
        color: #333;
        background-color: #fff;
    }
    #parsing-page .field select { height: 32px; padding: 0 6px; }
    #parsing-page .field textarea { min-height: 200px; padding: 6px; line-height: 1.5; resize: vertical; }
    #parsing-page .field select:focus,
    #parsing-page .field textarea:focus { outline: 2px solid #b39ddb; outline-offset: 0; }
    #parsing-page .btn-row { display: flex; gap: 6px; }
    #parsing-page .btn {
        height: 34px;
        border: none;
        border-radius: 4px;
        font-size: 13px;
        font-weight: bold;
        font-family: inherit;
        cursor: pointer;
    }
    #parsing-page .btn:disabled { opacity: .6; cursor: default; }
    #parsing-page .btn-run { background-color: #4a148c; color: #fff; flex: 2; }
    #parsing-page .btn-clear { background-color: #90a4ae; color: #fff; flex: 1; }
    #parsing-page .btn:focus-visible { outline: 2px solid #b39ddb; outline-offset: 2px; }

    #parsing-page .message-error { margin-top: 10px; font-size: 12px; color: #c62828; }
    #parsing-page .warnings {
        margin: 12px 0 0;
        padding: 6px 8px 6px 22px;
        font-size: 12px;
        color: #c62828;
        background-color: #ffebee;
        border-radius: 4px;
    }
    #parsing-page .notice { margin-top: 10px; font-size: 12px; font-weight: bold; color: #4a148c; }
    #parsing-page .result { margin-top: 14px; }
    #parsing-page .result-title { font-size: 12px; font-weight: bold; color: #2c3e50; margin: 0 0 4px; }
    #parsing-page .result-scroll { overflow-x: auto; border-top: 1px solid #d1c4e9; border-left: 1px solid #d1c4e9; }
    #parsing-page .result-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12px; text-align: center; white-space: nowrap; }
    #parsing-page .result-table th {
        background-color: #f3e5f5;
        color: #4a148c;
        padding: 5px 6px;
        border-width: 0 1px 1px 0;
        border-style: solid;
        border-color: #d1c4e9;
    }
    #parsing-page .result-table td { padding: 5px 6px; border-width: 0 1px 1px 0; border-style: solid; border-color: #e0e0e0; }
    #parsing-page .result-table td.num { text-align: right; }
    #parsing-page .loading { margin-top: 10px; font-size: 12px; color: #888; text-align: center; }
</style>

<div id="parsing-page">
    <h1 class="page-title">문자 분석</h1>

    <form id="parsing-form" method="post" action="parsing.php">
        <div class="field">
            <label for="parsing-type">유형</label>
            <select id="parsing-type" name="type">
                <option value="auto"<?= $selectedType === 'auto' ? ' selected' : '' ?>>자동 감지</option>
                <?php foreach ($parsers as $code => $parser): ?>
                    <option value="<?= parsing_h($code) ?>"<?= (string)$code === $selectedType ? ' selected' : '' ?>><?= parsing_h($parser->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="parsing-content">내용</label>
            <textarea id="parsing-content" name="content" placeholder="분석할 문자 내용을 붙여 넣어 주세요."><?= parsing_h($content) ?></textarea>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn btn-run" id="parsing-run">분석</button>
            <button type="button" class="btn btn-clear" id="parsing-clear">지우기</button>
        </div>
    </form>

    <!-- 분석 결과 표시 영역 : 분석 버튼 바로 아래 -->
    <div id="parsing-result" aria-live="polite"><?php parsing_render_result($error, $result, $activeParser); ?></div>
</div>

<script>
(function () {
    var form      = document.getElementById('parsing-form');
    var content   = document.getElementById('parsing-content');
    var resultBox = document.getElementById('parsing-result');
    var runBtn    = document.getElementById('parsing-run');
    var clearBtn  = document.getElementById('parsing-clear');
    if (!form || !content || !resultBox || !runBtn) return;

    // 지우기 : 입력 내용과 이전 결과를 함께 비운다.
    clearBtn.addEventListener('click', function () {
        content.value = '';
        resultBox.innerHTML = '';
        content.focus();
    });

    // 분석 : 페이지 이동 없이 AJAX 로 요청하고, 결과만 버튼 아래에 갱신한다.
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (runBtn.disabled) return;

        runBtn.disabled = true;
        runBtn.textContent = '분석 중...';
        resultBox.innerHTML = '<div class="loading">분석 중입니다...</div>';

        fetch(form.getAttribute('action') || 'parsing.php', {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.text();
        })
        .then(function (html) {
            resultBox.innerHTML = html;
        })
        .catch(function (error) {
            resultBox.innerHTML = '<div class="message-error" role="alert">분석 요청에 실패했어요. (' +
                String(error.message).replace(/[<>&]/g, '') + ')</div>';
        })
        .then(function () {
            runBtn.disabled = false;
            runBtn.textContent = '분석';
        });
    });
})();
</script>