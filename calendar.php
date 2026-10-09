<?php
/**
 * monthly_calendar.php (calendar.php)
 *
 * 월간 날짜 관리 화면 (메모장 영역 인라인 로드 지원)
 * - 공통 DB 접속 파일 포함
 * - 1일부터 해당 월의 마지막 날짜까지 자동 표시
 * - 이전 달 / 다음 달 이동 (AJAX 연동)
 * - 현재 날짜 표시 (AJAX 연동)
 * - 날짜 클릭 가능
 * - DB 연동을 통한 날짜별 이벤트 조회 및 스크롤 처리
 * - 일자 클릭 시 상세 이벤트 팝업 표시 및 이동 기능 포함
 */

declare(strict_types=1);

/* --------------------------------------------------
 * DB 연결 설정
 * -------------------------------------------------- */
require_once __DIR__ . '/db_connect.php';

/* --------------------------------------------------
 * 날짜 설정
 * -------------------------------------------------- */

// GET으로 년/월을 전달받음
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

$calendarTimezone = new DateTimeZone('Asia/Seoul');

// 잘못된 날짜가 들어오는 경우 현재 날짜로 초기화
if ($year < 1) {$year = (int)date('Y');
}

if ($month < 1 || $month > 12) {$month = (int)date('n');
}

/* --------------------------------------------------
 * 이벤트 데이터 조회 (밭 작업 기록: $sql)
 * -------------------------------------------------- */
$eventsData = []; // 밭 작업 데이터를 담을 배열

// 조회 대상 월의 시작일과 다음 달 시작일 계산 (파라미터 중복 방지)
$startDate = sprintf('%04d-%02d-01', $year,$month);
$endDate   = date('Y-m-d', strtotime("$startDate +1 month"));

$sql = "
	SELECT
		r.farm_id,
		f.farm_name,
		CASE
			WHEN r.task_id BETWEEN '40' AND '46' THEN '40'
			WHEN r.task_id BETWEEN '70' AND '72' THEN '70'
			ELSE r.task_id
		END AS task_id,
		DATE_FORMAT(r.work_date, '%Y-%m-%d') AS work_date,
		COUNT(*) AS y_count
	FROM ridge_records r
	INNER JOIN farms f
		ON r.farm_id = f.farm_id
	WHERE r.work_date >= :start_date
	  AND r.work_date < :end_date
	  AND r.check_status = 'Y'
	  AND f.use_yn = 'Y'
	GROUP BY
		r.farm_id,
		f.farm_name,
		CASE
			WHEN r.task_id BETWEEN '40' AND '46' THEN '40'
			WHEN r.task_id BETWEEN '70' AND '72' THEN '70'
			ELSE r.task_id
		END,
		DATE_FORMAT(r.work_date, '%Y-%m-%d')
	ORDER BY
		work_date,
		r.farm_id,
		task_id;
";

try {
    $stmt =$pdo->prepare($sql);$stmt->execute([
        'start_date' => $startDate,
        'end_date'   => $endDate
    ]);
    $rows =$stmt->fetchAll();

    // 날짜(YYYY-MM-DD)를 키값으로 하여 배열 재구성
    foreach ($rows as $row) {$formattedDate = date('Y-m-d', strtotime($row['work_date']));$eventsData[$formattedDate][] =$row;
    }
} catch (PDOException $e) {
    // 에러 발생 시 로그 기록 (필요시 활성화)
    // error_log("DB 오류 (events): " . $e->getMessage());
}

/* --------------------------------------------------
 * 오늘의 할 일 데이터 조회 ($sql2)
 * -------------------------------------------------- */
$tasksData = [];

$sql2 = "
    SELECT
        task_date,
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN completion_status = '완료' THEN 1 ELSE 0 END) AS completed_tasks,
        SUM(CASE WHEN completion_status != '완료' AND completion_status is not null THEN 1 ELSE 0 END) AS transfer_tasks,
        SUM(CASE WHEN completion_status is null THEN 1 ELSE 0 END) AS incomplete_tasks
    FROM dailytasksT
    WHERE task_date >= :start_date
      AND task_date < :end_date
    GROUP BY task_date
";

try {
    $stmt2 =$pdo->prepare($sql2);$stmt2->execute([
        'start_date' => $startDate,
        'end_date'   => $endDate
    ]);
    $rows2 =$stmt2->fetchAll();

    foreach ($rows2 as$row) {
        $formattedDate = date('Y-m-d', strtotime($row['task_date']));
        $tasksData[$formattedDate] = [
            'total'      => (int)$row['total_tasks'],
            'completed'  => (int)$row['completed_tasks'],
            'transfer'   => (int)$row['transfer_tasks'],
            'incomplete' => (int)$row['incomplete_tasks']
        ];
    }
} catch (PDOException $e) {
    // error_log("DB 오류 (tasks): " . $e->getMessage());
}

// Task ID별 아이콘 매핑 (DB 결과의 task_id 값 반영)
$taskIcons = [
    10 => '💧',
    20 => '🌾',
    30 => '✂️',
    40 => '💊',
    70 => '🐛',
];

/* --------------------------------------------------
 * 해당 월 정보 계산
 * -------------------------------------------------- */

$firstDate = new DateTime(sprintf('%04d-%02d-01',$year, $month),$calendarTimezone);

$lastDate = clone$firstDate;
$lastDate->modify('last day of this month');

$daysInMonth = (int)$lastDate->format('j');
$firstDayOfWeek = (int)$firstDate->format('w');

/* --------------------------------------------------
 * 이전 달 / 다음 달 계산
 * -------------------------------------------------- */

$prevDate = clone$firstDate;
$prevDate->modify('-1 month');

$nextDate = clone$firstDate;
$nextDate->modify('+1 month');

$prevYear  = (int)$prevDate->format('Y');
$prevMonth = (int)$prevDate->format('n');

$nextYear  = (int)$nextDate->format('Y');
$nextMonth = (int)$nextDate->format('n');

/* --------------------------------------------------
 * 현재 날짜
 * -------------------------------------------------- */

$today = (new DateTime('now',$calendarTimezone))->format('Y-m-d');
$currentYear = date('Y');$currentMonth = date('n');

/* --------------------------------------------------
 * 월 이름 및 URL 설정
 * -------------------------------------------------- */

$monthNames = [
    1  => '1월', 2  => '2월', 3  => '3월', 4  => '4월',
    5  => '5월', 6  => '6월', 7  => '7월', 8  => '8월',
    9  => '9월', 10 => '10월', 11 => '11월', 12 => '12월'
];

$monthName = $monthNames[$month];

$prevPageUrl  = 'calendar.php?year=' . $prevYear . '&month=' .$prevMonth;
$nextPageUrl  = 'calendar.php?year=' . $nextYear . '&month=' .$nextMonth;
$todayPageUrl = 'calendar.php?year=' . $currentYear . '&month=' .$currentMonth;

?>

<style>
    .calendar-wrap { width: 100%; background: #fff; border-radius: 8px; overflow: hidden; }
    .calendar-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
    .calendar-title { margin: 0; font-size: 16px; font-weight: 700; color: #222; text-align: center; flex: 1; }
    .calendar-nav { display: flex; align-items: center; gap: 4px; }
    .calendar-btn { min-width: 36px; height: 28px; padding: 0 8px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; color: #333; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: 0.15s; }
    .calendar-btn:hover { background: #f3f4f6; }
    .calendar-btn.today-btn { min-width: 52px; }
    .calendar-week { display: grid; grid-template-columns: repeat(7, 1fr); background: #fafafa; border-bottom: 1px solid #e5e7eb; }
    .weekday { padding: 6px 2px; text-align: center; font-weight: 700; font-size: 12px; color: #555; border-right: 1px solid #eee; }
    .weekday:last-child { border-right: none; }
    .weekday.sun { color: #d32f2f; }
    .weekday.sat { color: #1976d2; }
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
    .calendar-cell { min-height: 110px; border-right: 1px solid #eee; border-bottom: 1px solid #eee; padding: 4px 2px; position: relative; background: #fff; display: flex; flex-direction: column; }
    .calendar-cell:nth-child(7n) { border-right: none; }
    .calendar-cell.empty { background: #fafafa; }
    .date-number { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 12px; font-weight: 600; color: #333; }
    .date-number.sun { color: #d32f2f; }
    .date-number.sat { color: #1976d2; }
    .date-header { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 0 2px; margin-bottom: 2px; }
    .date-count { font-size: 10px; color: #1976d2; font-weight: bold; }
    .calendar-cell.today { background: #fffde7; }
    .calendar-cell.today .date-number { background: #1976d2; color: #fff; }
    .date-content { flex: 1; font-size: 10px; line-height: 1.4; color: #555; width: 100%; max-height: 84px; overflow-y: auto; overflow-x: hidden; }
    .date-content::-webkit-scrollbar { width: 3px; }
    .date-content::-webkit-scrollbar-track { background: transparent; }
    .date-content::-webkit-scrollbar-thumb { background: #ccc; border-radius: 3px; }
    .date-content::-webkit-scrollbar-thumb:hover { background: #aaa; }
    .date-item { display: block; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: -0.5px; padding: 1px 2px; }
    .date-link { display: flex; flex-direction: column; width: 100%; height: 100%; color: inherit; text-decoration: none; cursor: pointer; }
    
    /* 팝업 모달 */
    .calendar-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 2000; display: none; justify-content: center; align-items: center; }
    .calendar-modal-content { background: #fff; border-radius: 8px; width: 90%; max-width: 400px; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .calendar-modal-header { padding: 12px 16px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .calendar-modal-header h3 { margin: 0; font-size: 1.1rem; color: #333; }
    .calendar-modal-close { cursor: pointer; font-size: 1.2rem; color: #666; }
    .calendar-modal-body { padding: 0; overflow-y: auto; flex: 1; }
    
    /* 오늘의 할 일 요약 UI (아이콘 제거, 마진/패딩 최소화, 한 줄 한정 표출) */
    .task-summary-box { margin: 6px 10px 2px 10px; padding: 6px 8px; background-color: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 6px; cursor: pointer; text-align: center; transition: all 0.2s ease; }
    .task-summary-box:hover { background-color: #e3f2fd; border-color: #90caf9; }
    .task-summary-text { font-size: 16px; color: #333; white-space: nowrap; letter-spacing: -0.5px; overflow: hidden; text-overflow: ellipsis; }
    
    .event-list { list-style: none; padding: 0; margin: 0; border-top: 1px solid #f0f0f0; }
    .event-list-item { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s; font-size: 0.95rem; color: #444; }
    .event-list-item:hover { background: #f1f8e9; }
    .event-list-item:last-child { border-bottom: none; }
    .event-list-empty { padding: 20px; text-align: center; color: #888; font-size: 0.95rem; }
</style>

<div class="calendar-wrap">

    <div class="calendar-header">
        <div class="calendar-nav">
            <button type="button" class="calendar-btn" onclick="loadPage('<?php echo $prevPageUrl; ?>', '달력')" title="이전 달">◀</button>
        </div>

        <h1 class="calendar-title">
            <?php echo htmlspecialchars((string)$year . '년 ' . $monthName, ENT_QUOTES, 'UTF-8'); ?>
        </h1>

        <div class="calendar-nav">
            <button type="button" class="calendar-btn" onclick="loadPage('<?php echo $nextPageUrl; ?>', '달력')" title="다음 달">▶</button>
            <button type="button" class="calendar-btn today-btn" onclick="loadPage('<?php echo $todayPageUrl; ?>', '달력')">오늘</button>
        </div>
    </div>

    <div class="calendar-week">
        <div class="weekday sun">일</div>
        <div class="weekday">월</div>
        <div class="weekday">화</div>
        <div class="weekday">수</div>
        <div class="weekday">목</div>
        <div class="weekday">금</div>
        <div class="weekday sat">토</div>
    </div>

    <div class="calendar-grid">
        <?php
        for ($i = 0; $i < $firstDayOfWeek; $i++) {
            echo '<div class="calendar-cell empty"></div>';
        }

        for ($day = 1; $day <=$daysInMonth; $day++) {$dateString = sprintf('%04d-%02d-%02d', $year,$month, $day);$dayOfWeek = (int)date('w', strtotime($dateString));$isToday = ($dateString ===$today);

            $cellClass = 'calendar-cell';
            if ($isToday) {$cellClass .= ' today';
            }

            $numberClass = 'date-number';
            if ($dayOfWeek === 0) {$numberClass .= ' sun';
            } elseif ($dayOfWeek === 6) {$numberClass .= ' sat';
            }
            
            $dailyEvents = isset($eventsData[$dateString]) ? $eventsData[$dateString] : [];
            // SQL2에서 가져온 할 일 총 개수 적용
            $dailyTaskCount = isset($tasksData[$dateString]) ? $tasksData[$dateString]['total'] : 0;
            ?>

            <div class="<?php echo $cellClass; ?>" data-date="<?php echo $dateString; ?>">
                <a href="javascript:void(0);" class="date-link" onclick="selectCalendarDate('<?php echo $dateString; ?>');">
                    <div class="date-header">
                        <span class="<?php echo $numberClass; ?>"><?php echo $day; ?></span>
                        <?php if ($dailyTaskCount > 0): ?>
                            <span class="date-count">(<?php echo $dailyTaskCount; ?>)</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="date-content">
                        <?php
                        foreach ($dailyEvents as $event) {$farmName = htmlspecialchars($event['farm_name'], ENT_QUOTES, 'UTF-8');$taskId   = (int)$event['task_id'];$yCount   = (int)$event['y_count'];$icon     = isset($taskIcons[$taskId]) ? $taskIcons[$taskId] : '📌';
                            
                            $displayText =$farmName . $icon .$yCount;
                            echo '<div class="date-item" title="' . $displayText . '">';
                            echo $displayText;
                            echo '</div>';
                        }
                        ?>
                    </div>
                </a>
            </div>

            <?php
        }

        $totalCells = $firstDayOfWeek +$daysInMonth;
        $remainingCells = (7 - ($totalCells % 7)) % 7;

        for ($i = 0; $i < $remainingCells; $i++) {
            echo '<div class="calendar-cell empty"></div>';
        }
        ?>
    </div>

</div>

<div id="calendarEventModal" class="calendar-modal-overlay">
    <div class="calendar-modal-content">
        <div class="calendar-modal-header">
            <h3 id="calendarModalTitle">상세 내역</h3>
            <span class="calendar-modal-close" onclick="closeCalendarModal()">✖</span>
        </div>
        <div class="calendar-modal-body">
            <!-- 오늘의 할 일 통계가 출력되는 영역 -->
            <div id="calendarTaskSummary"></div>
            <!-- 밭 작업 이벤트 리스트 영역 -->
            <ul id="calendarEventList" class="event-list"></ul>
        </div>
    </div>
</div>

<script>
window.calendarEventsData = <?php echo json_encode($eventsData, JSON_UNESCAPED_UNICODE); ?>;
window.calendarTasksData = <?php echo json_encode($tasksData, JSON_UNESCAPED_UNICODE); ?>;

function selectCalendarDate(date) {
    const events = window.calendarEventsData[date] || [];
    const tasks = window.calendarTasksData[date] || null;
    
    const modal = document.getElementById('calendarEventModal');
    const title = document.getElementById('calendarModalTitle');
    const summaryContainer = document.getElementById('calendarTaskSummary');
    const listContainer = document.getElementById('calendarEventList');

    title.innerText = date + ' 상세 내역';
    
    // 오늘의 할 일 요약 정보 한 줄로 간결하게 표출 (아이콘 제거 & 마진/패딩 최소화)
    if (tasks && tasks.total > 0) {
        summaryContainer.innerHTML = `
            <div class="task-summary-box" onclick="goToDailyTasks('${date}')">
                <div class="task-summary-text">
                    오늘의 할 일 : 완료 <strong style="color:#2ecc71;">${tasks.completed}</strong>개, 미완료 <strong style="color:#e74c3c;">${tasks.incomplete}</strong>개, 이관 <strong style="color:#f39c12;">${tasks.transfer}</strong>개, 총 <strong>${tasks.total}</strong>개
                </div>
            </div>
        `;
        summaryContainer.style.display = 'block';
    } else {
        summaryContainer.innerHTML = '';
        summaryContainer.style.display = 'none';
    }

    // 이벤트 리스트 렌더링
    listContainer.innerHTML = '';
    if (events.length === 0) {
        listContainer.innerHTML = '<li class="event-list-empty">해당 일자에 등록된 밭 작업 기록이 없습니다.</li>';
    } else {
        events.forEach(function(ev) {
            const li = document.createElement('li');
            li.className = 'event-list-item';
            
            const taskId = parseInt(ev.task_id, 10);
            let eventText = '';
            
            switch (taskId) {
                case 10:
                    eventText = `${ev.farm_name}밭 ${ev.y_count}개 이랑에 물을 줌`;
                    break;
                case 20:
                    eventText = `${ev.farm_name}밭 ${ev.y_count}개 이랑에서 수확`;
                    break;
                case 30:
                    eventText = `${ev.farm_name}밭 ${ev.y_count}개 이랑에서 옆순 제거`;
                    break;
                case 40:
                    eventText = `${ev.farm_name}밭 ${ev.y_count}개 이랑에 농약 살포`;
                    break;
                case 70:
                    eventText = `${ev.farm_name}밭 ${ev.y_count}개 이랑에서 병충해 발견`;
                    break;
                default:
                    eventText = `${ev.farm_name}밭 ${ev.y_count}개 이랑에 작업(${ev.task_id}) 적용`;
                    break;
            }
            
            li.innerText = '🌱 ' + eventText;
            
            li.onclick = function() {
                closeCalendarModal();
                if (typeof loadPage === 'function') {
                    loadPage('farmmngdtl.php?farm_id=' + ev.farm_id, '밭 상세정보');
                } else {
                    location.href = 'farmmngdtl.php?farm_id=' + ev.farm_id;
                }
            };
            
            listContainer.appendChild(li);
        });
    }

    modal.style.display = 'flex';
}

function goToDailyTasks(date) {
    closeCalendarModal();
    if (typeof loadPage === 'function') {
        loadPage('dailytasks.php?d=' + date, '오늘의 할일');
    } else {
        location.href = 'dailytasks.php?d=' + date;
    }
}

function closeCalendarModal() {
    document.getElementById('calendarEventModal').style.display = 'none';
}
</script>