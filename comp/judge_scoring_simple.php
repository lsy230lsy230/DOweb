<?php
// 에러 리포팅 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Simple judge scoring test -->\n";

session_start();

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$lang = $_GET['lang'] ?? 'ko';

echo "<h1>심사위원 채점 시스템 (간단 테스트)</h1>";
echo "<p>comp_id: " . htmlspecialchars($comp_id) . "</p>";
echo "<p>event_no: " . htmlspecialchars($event_no) . "</p>";
echo "<p>lang: " . htmlspecialchars($lang) . "</p>";

// 세션 확인
echo "<p>세션 로그인 상태: " . (isset($_SESSION['scoring_logged_in']) ? 'YES' : 'NO') . "</p>";
echo "<p>심사위원 ID: " . ($_SESSION['scoring_judge_id'] ?? 'N/A') . "</p>";

// 파일 존재 확인
$data_dir = __DIR__ . "/data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$players_file = "$data_dir/players_$event_no.txt";

echo "<p>데이터 디렉토리: " . $data_dir . "</p>";
echo "<p>디렉토리 존재: " . (is_dir($data_dir) ? 'YES' : 'NO') . "</p>";
echo "<p>RunOrder 파일: " . $runorder_file . "</p>";
echo "<p>RunOrder 파일 존재: " . (file_exists($runorder_file) ? 'YES' : 'NO') . "</p>";
echo "<p>선수 파일: " . $players_file . "</p>";
echo "<p>선수 파일 존재: " . (file_exists($players_file) ? 'YES' : 'NO') . "</p>";

// RunOrder 파일 읽기 테스트
if (file_exists($runorder_file)) {
    echo "<h2>RunOrder 파일 내용 (처음 5줄):</h2>";
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    for ($i = 0; $i < min(5, count($lines)); $i++) {
        echo "<p>" . ($i + 1) . ": " . htmlspecialchars($lines[$i]) . "</p>";
    }
}

// 선수 파일 읽기 테스트
if (file_exists($players_file)) {
    echo "<h2>선수 파일 내용:</h2>";
    $players = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($players as $player) {
        echo "<p>선수: " . htmlspecialchars($player) . "</p>";
    }
}

echo "<p><a href='judge_scoring.php?comp_id=$comp_id&event_no=$event_no&lang=$lang'>원래 채점 페이지로 이동</a></p>";
?>






