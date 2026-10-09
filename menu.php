<!-- ==========================================
     menu.php : 상단 메뉴, 드롭다운, 모달 및 스크립트
     ========================================== -->

<!-- 오늘의 할일 첨부파일 설정: 여기서 최대 개수/허용 확장자를 변경할 수 있습니다. -->
<script>
window.TODO_ATTACHMENT_MAX_FILES = 5;
window.TODO_ATTACHMENT_EXTENSIONS = ['jpg','jpeg','png','gif','webp','pdf','txt','doc','docx','xls','xlsx','hwp','hwpx','zip'];
</script>

<!-- 1. 상단 메뉴 영역 (헤더) -->
<header>
    <!-- 왼쪽 햄버거 아이콘 -->
    <button class="icon-btn" onclick="toggleMenu('sidebar')">☰</button>
    
    <!-- 중앙 타이틀 영역 -->
    <div class="header-titles">
        <span class="header-title inactive" id="title-daily" onclick="handleTitleClick('dailytasks.php', '오늘의 할일')">오늘의 할일</span>
        <span class="header-title active" id="title-farm" onclick="handleTitleClick('farmmng.php', '밭 관리')">밭 관리</span>
        <span class="header-title inactive" id="title-memo" onclick="handleTitleClick('memo.php', '메모장')">메모장</span>
    </div>

    <!-- 오른쪽 아이콘 묶음 영역 -->
    <div class="header-right">
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
            <li><a href="#" onclick="loadPage('calendar.php', '달력'); return false;">달력</a></li>
            <li><a href="#" onclick="loadPage('farmSet.php', '밭 설정'); return false;">밭 설정</a></li>
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
            <label for="todo-date">날짜 (task_date)</label>
            <input type="date" id="todo-date" required>
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

		if (titleDaily) titleDaily.className = 'header-title inactive';
		if (titleFarm) titleFarm.className = 'header-title inactive';
		if (titleMemo) titleMemo.className = 'header-title inactive';

		if (pageUrl.includes('dailytasks.php') && titleDaily) {
			titleDaily.className = 'header-title active';
		} else if ((pageUrl.includes('farmmng.php') || pageUrl.includes('farmmngdtl.php')) && titleFarm) { 
			titleFarm.className = 'header-title active';
		} else if (pageUrl.includes('memo.php') && titleMemo) {
			titleMemo.className = 'header-title active';
		}
	}

    function openTodoModal() {
        closeAllMenus();
        const today = new Date();
        const localDate = new Date(today.getTime() - (today.getTimezoneOffset() * 60000)).toISOString().split('T')[0];
        
        const dateInput = document.getElementById('todo-date');
        if (dateInput) {
            dateInput.value = localDate;
            dateInput.min = localDate;
        }

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
        const taskDate = document.getElementById('todo-date').value;
        const content = document.getElementById('todo-content').value;

        if (!taskDate || !content.trim()) {
            alert('날짜와 할일 내용을 모두 입력해 줘!');
            return;
        }

        const formData = new FormData();
        formData.append('task_date', taskDate);
        formData.append('content', content);
        
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
            // 현재 보고 있던 페이지가 할 일 페이지면, 해당 날짜(파라미터 포함된 URL)를 그대로 유지하며 새로고침
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