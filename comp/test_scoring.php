<?php
// 에러 리포팅 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>채점 시스템 테스트</h1>";

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

echo "<p>comp_id: " . htmlspecialchars($comp_id) . "</p>";
echo "<p>event_no: " . htmlspecialchars($event_no) . "</p>";

// 세션 확인
session_start();
echo "<p>세션 로그인 상태: " . (isset($_SESSION['scoring_logged_in']) ? 'YES' : 'NO') . "</p>";
echo "<p>심사위원 ID: " . ($_SESSION['scoring_judge_id'] ?? 'N/A') . "</p>";

// 파일 존재 확인
$data_dir = __DIR__ . "/data/$comp_id";
echo "<p>데이터 디렉토리: " . $data_dir . "</p>";
echo "<p>디렉토리 존재: " . (is_dir($data_dir) ? 'YES' : 'NO') . "</p>";

$runorder_file = "$data_dir/RunOrder_Tablet.txt";
echo "<p>RunOrder 파일: " . $runorder_file . "</p>";
echo "<p>파일 존재: " . (file_exists($runorder_file) ? 'YES' : 'NO') . "</p>";

$players_file = "$data_dir/players_$event_no.txt";
echo "<p>선수 파일: " . $players_file . "</p>";
echo "<p>파일 존재: " . (file_exists($players_file) ? 'YES' : 'NO') . "</p>";

echo "<p><a href='judge_scoring.php?comp_id=$comp_id&event_no=$event_no&lang=ko'>원래 채점 페이지로 이동</a></p>";
?>






