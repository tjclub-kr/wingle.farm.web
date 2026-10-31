<!-- ==========================================
     menu.php : 상단 메뉴, 드롭다운, 모달 및 스크립트
     ========================================== -->

<!-- 오늘의 할일 첨부파일 설정: 여기서 최대 개수/허용 확장자를 변경할 수 있습니다. -->
<script>
window.TODO_ATTACHMENT_MAX_FILES = 5;
window.TODO_ATTACHMENT_EXTENSIONS = ['jpg','jpeg','png','gif','webp','pdf','txt','doc','docx','xls','xlsx','hwp','hwpx','zip'];
</script>

<!-- 상단 헤더 간격 조정 : 갤럭시 S24/S25/S26 등 폭 360px 안팎 화면에서 메뉴 이름이 줄바꿈되지 않도록 함 -->
<style>
    /* 메뉴 이름은 어떤 경우에도 한 줄로 표시 */
    header .header-title {
        white-space: nowrap;
        word-break: keep-all;
    }
    header .icon-btn,
    header .header-titles,
    header .header-right {
        flex-shrink: 0;
    }

    /* 휴대폰 화면 (412px 이하) : 좌우 여백, 메뉴 간격, 아이콘 여백을 줄임 */
    @media (max-width: 420px) {
        header {
            padding: 0 6px;
        }
        header .icon-btn {
            padding: 3px;
        }
        header > .icon-btn {
            font-size: 22px;      /* 햄버거 */
        }
        header .header-titles {
            gap: 8px;
        }
        header .header-title {
            font-size: 14px;
        }
        header .header-right .icon-btn {
            font-size: 18px !important; /* 버튼에 지정된 인라인 20px 보다 우선 */
        }
    }

    /* 더 좁은 화면 (350px 이하) : 한 단계 더 축소 */
    @media (max-width: 350px) {
        header {
            padding: 0 4px;
        }
        header .header-titles {
            gap: 6px;
        }
        header .header-title {
            font-size: 13px;
        }
        header .header-right .icon-btn {
            font-size: 17px !important;
            padding: 2px;
        }
    }
</style>

<!-- 1. 상단 메뉴 영역 (헤더) -->
<header>
    <!-- 왼쪽 햄버거 아이콘 -->
    <button class="icon-btn" onclick="toggleMenu('sidebar')">☰</button>
    
    <!-- 중앙 타이틀 영역 -->
    <div class="header-titles">
        <span class="header-title inactive" id="title-daily" onclick="handleTitleClick('dailytasks.php', '오늘의 할일')">오늘의 할일</span>
        <span class="header-title active" id="title-farm" onclick="handleTitleClick('farmmng.php', '밭 관리')">밭 관리</span>
        <span class="header-title inactive" id="title-memo" onclick="handleTitleClick('memo.php', '메모장')">메모장</span>
        <span class="header-title inactive" id="title-shipping" onclick="handleTitleClick('outbound.php', '출고 관리')">출고</span>
    </div>

    <!-- 오른쪽 아이콘 묶음 영역 -->
    <div class="header-right">
        <!-- 원(₩) 아이콘 : 수입 내역(farm_income.php) -->
        <button
            class="icon-btn"
            onclick="loadPage('farm_income.php', '수입 내역')"
            title="수입 내역"
            aria-label="수입 내역"
            style="font-size: 20px; font-weight: bold;"
        >💰</button>

        <!-- 달력 아이콘 -->
        <button
            class="icon-btn"
            onclick="loadPage('calendar.php', '달력')"
            title="달력"
            aria-label="달력"
            style="font-size: 20px;"
        >📅</button>

        <!-- 새 할일 아이콘 -->
        <button
            class="icon-btn"
            onclick="openTodoModal()"
            title="새 할일"
            aria-label="새 할일"
            style="font-size: 20px;"
        >📝</button>
    </div>

    <!-- 햄버거 메뉴 목록 -->
    <nav class="sidebar-menu" id="sidebar">
        <ul>
            <li><a href="#" onclick="loadPage('dailytasks.php', '오늘의 할일'); return false;">오늘의 할일</a></li>
            <li><a href="#" onclick="loadPage('farmmng.php', '밭 관리'); return false;">밭 관리</a></li>
            <li><a href="#" onclick="loadPage('memo.php', '메모장'); return false;">메모장</a></li>
            <li><a href="#" onclick="loadPage('outbound.php', '출고 관리'); return false;">출고</a></li>
            <li><a href="#" onclick="loadPage('calendar.php', '달력'); return false;">달력</a></li>
            <li><a href="#" onclick="loadPage('farmSet.php', '밭 설정'); return false;">밭 설정</a></li>
<!--            <li><a href="#" onclick="loadPage('parsing.php', '분석'); return false;">분석</a></li> -->
<!--            <li><a href="#" onclick="loadPage('environment_monitoring.php', '환경정보'); return false;">환경정보</a></li> -->
            <!-- li><a href="#" onclick="loadPage('dataClear.php', '데이터클리어'); return false;">데이터클리어</a></li -->
        </ul>
    </nav>

    <!-- 점세개 옵션 메뉴 -->
    <nav class="more-menu" id="more">
        <ul>
            <li><a href="#" onclick="alert('설정 기능 준비 중입니다.'); return false;">설정</a></li>
            <li><a href="#" onclick="location.reload(); return false;">새로고침</a></li>
        </ul>
    </nav>
</header>

<!-- 바깥 영역 클릭 시 메뉴 닫기용 오버레이 -->
<div class="overlay" id="overlay" onclick="closeAllMenus()"></div>

<!-- 할일 추가 팝업(모달) 영역 -->
<div class="modal-overlay" id="todoModal">
    <div class="modal-content">
        <h3>새 할일 추가</h3>
        <div class="form-group">
            <label for="todo-date">일자 (선택)</label>
            <div style="display:flex; gap:6px; align-items:stretch;">
                <!-- 기본값은 빈 값 / 달력(입력칸)을 누르면 오늘이 표시됨 / 오늘 이전은 선택 불가 -->
                <input type="date" id="todo-date" style="flex:1; min-width:0;" onclick="fillTodoDateWithToday()">
                <button type="button" onclick="clearTodoDate()" style="padding:0 10px; border:1px solid #ddd; background:#f5f5f5; border-radius:6px; font-size:13px; cursor:pointer; white-space:nowrap;">지움</button>
            </div>
        </div>
        <div class="form-group">
            <label for="todo-importance">중요도</label>
            <select id="todo-importance" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:15px; font-family:inherit; background:#fff;">
                <option value="1">상</option>
                <option value="2">중</option>
                <option value="3" selected>하</option>
            </select>
        </div>
        <div class="form-group">
            <label for="todo-content">할일 내용 (content)</label>
            <textarea id="todo-content" rows="7" placeholder="무엇을 해야 하나요?" required></textarea>
        </div>
        <!-- 첨부파일 폼 추가 -->
        <div class="form-group">
            <label for="todo-attachments">첨부파일 (선택)</label>
            <input type="file" id="todo-attachments" multiple>
            <div id="todo-attachment-help" style="font-size:11px; color:#888; margin-top:6px;"></div>
        </div>
        <div class="modal-actions" style="margin-top: 15px;">
            <button class="btn-cancel" onclick="closeTodoModal()">취소</button>
            <button class="btn-save" onclick="saveTodo()">저장</button>
        </div>
    </div>
</div>

<!-- 메뉴 및 동적 콘텐츠 제어 스크립트 -->
<script>
    let currentPageUrl = 'farmmng.php';

    function toggleMenu(menuType) {
        const sidebar = document.getElementById('sidebar');
        const more = document.getElementById('more');
        const overlay = document.getElementById('overlay');

        if (menuType === 'sidebar') {
            const isVisible = sidebar.style.display === 'block';
            closeAllMenus();
            if (!isVisible) {
                sidebar.style.display = 'block';
                overlay.style.display = 'block';
            }
        } else if (menuType === 'more') {
            const isVisible = more.style.display === 'block';
            closeAllMenus();
            if (!isVisible) {
                more.style.display = 'block';
                overlay.style.display = 'block';
            }
        }
    }

    function closeAllMenus() {
        const sidebar = document.getElementById('sidebar');
        const more = document.getElementById('more');
        const overlay = document.getElementById('overlay');
        
        if (sidebar) sidebar.style.display = 'none';
        if (more) more.style.display = 'none';
        if (overlay) overlay.style.display = 'none';
    }

    function handleTitleClick(pageUrl, title) {
        loadPage(pageUrl, title);
    }

    // 상단 타이틀 스타일 업데이트
	function updateHeaderTitles(pageUrl) {
		const titleDaily = document.getElementById('title-daily');
		const titleFarm = document.getElementById('title-farm');
		const titleMemo = document.getElementById('title-memo');
		const titleShipping = document.getElementById('title-shipping');

		if (titleDaily) titleDaily.className = 'header-title inactive';
		if (titleFarm) titleFarm.className = 'header-title inactive';
		if (titleMemo) titleMemo.className = 'header-title inactive';
		if (titleShipping) titleShipping.className = 'header-title inactive';

		if (pageUrl.includes('dailytasks.php') && titleDaily) {
			titleDaily.className = 'header-title active';
		} else if ((pageUrl.includes('farmmng.php') || pageUrl.includes('farmmngdtl.php')) && titleFarm) { 
			titleFarm.className = 'header-title active';
		} else if (pageUrl.includes('memo.php') && titleMemo) {
			titleMemo.className = 'header-title active';
		} else if (pageUrl.includes('outbound.php') && titleShipping) {
			titleShipping.className = 'header-title active';
		}
	}

    function openTodoModal() {
        closeAllMenus();
        const today = new Date();
        const localDate = new Date(today.getTime() - (today.getTimezoneOffset() * 60000)).toISOString().split('T')[0];
        
        // 등록일자 기본값은 빈 값 (오늘 이전은 선택 불가)
        const dateInput = document.getElementById('todo-date');
        if (dateInput) {
            dateInput.value = '';
            dateInput.min = localDate;
        }

        // 중요도 기본값은 3 하
        const importanceInput = document.getElementById('todo-importance');
        if (importanceInput) importanceInput.value = '3';

        const contentInput = document.getElementById('todo-content');
        if (contentInput) contentInput.value = '';
        
        // 첨부파일 초기화 및 확장자 설정 연동
        const fileInput = document.getElementById('todo-attachments');
        if (fileInput) { 
            fileInput.value = ''; 
            fileInput.accept = (window.TODO_ATTACHMENT_EXTENSIONS || []).map(e => '.' + e).join(','); 
        }
        updateTodoAttachmentHelp();
        
        const todoModal = document.getElementById('todoModal');
        if (todoModal) todoModal.style.display = 'flex';
    }

    // 오늘 날짜(YYYY-MM-DD, 로컬 기준)
    function getTodayLocalDate() {
        const now = new Date();
        return new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().split('T')[0];
    }

    // 등록일자가 비어 있을 때 달력을 누르면 오늘이 표시되도록 함
    function fillTodoDateWithToday() {
        const dateInput = document.getElementById('todo-date');
        if (dateInput && !dateInput.value) dateInput.value = getTodayLocalDate();
    }

    // 등록일자 지우기 (등록일자 없이 등록)
    function clearTodoDate() {
        const dateInput = document.getElementById('todo-date');
        if (dateInput) dateInput.value = '';
    }

    function closeTodoModal() {
        const todoModal = document.getElementById('todoModal');
        if (todoModal) todoModal.style.display = 'none';
    }

    function updateTodoAttachmentHelp() {
        const el = document.getElementById('todo-attachment-help'); 
        if(!el) return;
        const max = Number(window.TODO_ATTACHMENT_MAX_FILES || 5); 
        const ext = (window.TODO_ATTACHMENT_EXTENSIONS || []).join(', ').toUpperCase();
        el.textContent = `최대 ${max}개 / ${ext} / 파일당 20MB`;
    }

    function saveTodo() {
        const taskDate = document.getElementById('todo-date').value; // 선택 입력 (빈 값이면 등록일자 없이 등록)
        const content = document.getElementById('todo-content').value;
        const importance = document.getElementById('todo-importance').value || '3';

        if (!content.trim()) {
            alert('할일 내용을 입력해 줘!');
            return;
        }
        if (taskDate && taskDate < getTodayLocalDate()) {
            alert('오늘 이전 날짜로는 등록할 수 없어!');
            return;
        }

        const formData = new FormData();
        if (taskDate) formData.append('task_date', taskDate);
        formData.append('content', content);
        formData.append('importance', importance);
        
        const files = document.getElementById('todo-attachments')?.files || [];
        const maxFiles = Number(window.TODO_ATTACHMENT_MAX_FILES || 5);
        if (files.length > maxFiles) { 
            alert('첨부파일은 최대 ' + maxFiles + '개까지 등록할 수 있습니다.'); 
            return; 
        }
        for (const file of files) formData.append('attachments[]', file);

        fetch('dailytasks.php?action=add_todo', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('서버 통신 오류');
            return response.text();
        })
        .then(data => {
            alert('할일이 성공적으로 저장됐어!');
            closeTodoModal();
            // 현재 보고 있던 페이지가 할 일 페이지면, 보고 있던 날짜(파라미터 포함된 URL)를 그대로 유지하며 새로고침
            if (currentPageUrl.includes('dailytasks.php')) {
                loadPage(currentPageUrl, '오늘의 할일');
            }
        })
        .catch(error => {
            alert('저장 중 문제가 발생했어: ' + error.message);
        });
    }

    // AJAX로 동적 페이지 로드
    function loadPage(pageUrl, title) {
        closeAllMenus();
        currentPageUrl = pageUrl;
        updateHeaderTitles(pageUrl);

        fetch('./' + pageUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`파일을 불러올 수 없습니다. (HTTP ${response.status} ${response.statusText})`);
                }
                return response.text();
            })
            .then(html => {
                const container = document.getElementById('content-area');
                if (!container) return;
                container.innerHTML = html;

                const scripts = container.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            })
            .catch(error => {
                const container = document.getElementById('content-area');
                if (container) {
                    container.innerHTML = 
                        `<div style="color:red; text-align:center; padding-top:20px;">
                            <p><strong>오류 발생!</strong></p>
                            <p>${error.message}</p>
                            <p style="font-size:12px; color:#666; margin-top:10px;">서버 경로에 <strong>${pageUrl}</strong> 파일이 있는지 확인해 줘.</p>
                        </div>`;
                    }
            });
    }
</script>