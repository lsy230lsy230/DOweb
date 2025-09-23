<?php
echo "PHP is working!<br>";
echo "Current time: " . date('Y-m-d H:i:s') . "<br>";

// 세션 테스트
session_start();
echo "Session started successfully<br>";

// GET 파라미터 테스트
$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
echo "comp_id: " . htmlspecialchars($comp_id) . "<br>";
echo "event_no: " . htmlspecialchars($event_no) . "<br>";

// 파일 존재 테스트
$data_dir = __DIR__ . "/data/$comp_id";
echo "Data directory: " . $data_dir . "<br>";
echo "Directory exists: " . (is_dir($data_dir) ? 'YES' : 'NO') . "<br>";

if (is_dir($data_dir)) {
    $files = scandir($data_dir);
    echo "Files in directory: " . implode(', ', $files) . "<br>";
}

echo "Test completed successfully!<br>";
?>