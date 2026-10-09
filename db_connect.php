<?php
/**
 * 공통 DB 접속 파일
 * 사용: require_once __DIR__ . '/db_connect.php';
 */
$db_host = 'www.tjclub.kr';
$db_port = '13306';
$db_name = 'wingleDB';
$db_user = 'wingle';
$db_pass = '!Chltjrghks2';

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo "<div style='color:red; text-align:center;'>DB 연결 실패: 잠시 후 다시 시도해 주세요.</div>";
    exit;
}
?>
