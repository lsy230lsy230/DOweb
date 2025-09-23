<?php
// 에러 리포팅 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Minimal judge scoring -->\n";

session_start();

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$lang = $_GET['lang'] ?? 'ko';

echo "<h1>심사위원 채점 시스템 (최소 버전)</h1>";

// 기본 검증
if (!$comp_id || !$event_no) {
    echo "<p>잘못된 대회 ID 또는 이벤트 번호입니다.</p>";
    exit;
}

// 세션 검증
if (!isset($_SESSION['scoring_logged_in']) || !$_SESSION['scoring_logged_in']) {
    echo "<p>로그인이 필요합니다.</p>";
    exit;
}

echo "<p>comp_id: " . htmlspecialchars($comp_id) . "</p>";
echo "<p>event_no: " . htmlspecialchars($event_no) . "</p>";
echo "<p>심사위원 ID: " . ($_SESSION['scoring_judge_id'] ?? 'N/A') . "</p>";

// 파일 로딩
$data_dir = __DIR__ . "/data/$comp_id";
$players_file = "$data_dir/players_$event_no.txt";

if (!file_exists($players_file)) {
    echo "<p>선수 파일을 찾을 수 없습니다: " . $players_file . "</p>";
    exit;
}

// 선수 데이터 로딩
$players = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

echo "<h2>출전 선수:</h2>";
foreach ($players as $player) {
    echo "<p>선수 번호: " . htmlspecialchars($player) . "</p>";
}

echo "<p>기본 로딩 완료!</p>";
?>






