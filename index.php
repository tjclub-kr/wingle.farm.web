<?php
require_once __DIR__ . '/db_connect.php';


// 1. common_code 테이블 전체 데이터 조회
try {
    $stmt = $pdo->prepare("SELECT code_group, code, code_nm, description, sort_order, extra_value1, extra_value2, extra_value3, extra_value4, extra_value5 FROM common_code WHERE use_yn = 'Y' ORDER BY code_group ASC, sort_order ASC, code ASC");
    $stmt->execute();
    $commonCodeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 그룹별 구조로 재구성
    $clientCodeData = [];
    foreach ($commonCodeRows as $row) {
        $clientCodeData[$row['code_group']][$row['code']] = $row;
    }
} catch (PDOException $e) {
    $clientCodeData = [];
}
?>
<script>
// 부모 창 전역 변수에 공통코드 세팅
window.GLOBAL_COMMON_CODES = <?= json_encode($clientCodeData, JSON_UNESCAPED_UNICODE) ?>;

/**
 * 특정 그룹의 특정 코드명(code_nm)을 리턴하는 펑션
 */
function getCodeName(groupName, code) {
    const allCodes = window.GLOBAL_COMMON_CODES;
    if (allCodes && allCodes[groupName] && allCodes[groupName][code]) {
        return allCodes[groupName][code].code_nm;
    }
    return '';
}

/**
 * 특정 그룹의 전체 코드 목록을 리턴하는 펑션
 */
function getCodesByGroup(groupName) {
    const allCodes = window.GLOBAL_COMMON_CODES;
    if (allCodes && allCodes[groupName]) {
        return allCodes[groupName]; // 객체 형태로 반환
    }
    return {};
}
</script>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <!-- 모바일 화면 맞춤 뷰포트 설정 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>모바일 웹 App</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh; /* 모바일 하단바를 위한 최신 스펙 적용 */
            overflow: hidden; /* 전체 페이지 스크롤 방지 */
            max-width: 412px;
            margin: 0 auto;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            position: relative;
        }

        /* 1. 상단 헤더 (메뉴 영역) */
        header {
            flex-shrink: 0;
            height: 56px;
            background-color: #2c3e50;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 16px;
            position: relative;
            z-index: 20; 
        }

        .icon-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }

        .header-titles {
            display: flex;
            gap: 16px; 
            align-items: center;
        }

        .header-title {
            font-size: 16px; 
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: color 0.2s;
        }

        .header-title.active {
            color: #ffffff;
        }

        .header-title.inactive {
            color: #95a5a6;
        }

        .header-right {
            display: flex;
            align-items: center;
        }

        /* 사이드바 메뉴 */
        .sidebar-menu {
            display: none;
            position: absolute;
            top: 56px;
            left: 0;
            width: 200px;
            background-color: #ffffff;
            box-shadow: 2px 5px 10px rgba(0,0,0,0.2);
            z-index: 30; 
            border-bottom-right-radius: 8px;
        }

        .sidebar-menu ul { list-style: none; }

        .sidebar-menu li a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #eee;
            font-weight: 500;
        }

        .sidebar-menu li a:active { background-color: #e9ecef; }

        /* 옵션 메뉴 */
        .more-menu {
            display: none;
            position: absolute;
            top: 56px;
            right: 0;
            width: 150px;
            background-color: #ffffff;
            box-shadow: -2px 5px 10px rgba(0,0,0,0.2);
            z-index: 30; 
            border-bottom-left-radius: 8px;
        }

        .more-menu ul { list-style: none; }

        .more-menu li a {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            border-bottom: 1px solid #eee;
        }

        /* 2. 중간 컨텐츠 영역 */
        main#content-area {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background-color: #ffffff;
        }

        .overlay {
            display: none;
            position: absolute;
            top: 56px;
            left: 0;
            width: 100%;
            height: calc(100vh - 56px);
            background-color: rgba(0,0,0,0.3);
            z-index: 15;
        }

        /* 모달 레이아웃 */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            width: 90%;
            max-width: 340px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .modal-content h3 {
            margin-bottom: 15px;
            font-size: 18px;
            color: #2c3e50;
            text-align: center;
        }

        .modal-content .form-group { margin-bottom: 15px; }

        .modal-content label {
            display: block;
            font-size: 14px;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }

        .modal-content input[type="date"],
        .modal-content textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            font-family: inherit;
        }

        .modal-content textarea { resize: none; overflow-y: auto; }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-actions button {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-cancel { background-color: #e0e0e0; color: #333; }
        .btn-save { background-color: #2c3e50; color: #fff; }
    </style>
</head>
<body>

    <!-- [메뉴 영역] menu.php 불러오기 -->
    <?php include_once 'menu.php'; ?>

    <!-- [중간 컨텐츠 영역] -->
    <main id="content-area">
        <!-- 동적으로 외부 php 파일이 로드되는 위치 -->
    </main>

    <script>
        // 페이지 최초 진입 시 기본으로 'farmmng.php' 호출
        window.onload = function() {
            if (typeof loadPage === 'function') {
                loadPage('farmmng.php', '밭 관리');
            }
        };
    </script>
</body>
</html>