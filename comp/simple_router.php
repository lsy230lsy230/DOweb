<?php
session_start();

echo "<h1>Simple Router Test - Step 2</h1>";

$event_no = $_GET['event_no'] ?? '8';
$comp_id = $_GET['comp_id'] ?? '20250907-001';

echo "<p>Event No: " . htmlspecialchars($event_no) . "</p>";
echo "<p>Comp ID: " . htmlspecialchars($comp_id) . "</p>";

// functions.php 파일 포함 시도
echo "<p>Attempting to include functions.php...</p>";
try {
    require_once __DIR__ . '/scoring/shared/functions.php';
    echo "<p style='color: green;'>functions.php included successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error including functions.php: " . htmlspecialchars($e->getMessage()) . "</p>";
    die();
}

// 이벤트 데이터 로드 시도
echo "<p>Attempting to load event data...</p>";
try {
    $event_data = loadEventData($comp_id, $event_no);
    if ($event_data) {
        echo "<p style='color: green;'>Event Data Loaded: " . htmlspecialchars($event_data['name']) . "</p>";
    } else {
        echo "<p style='color: red;'>Failed to load event data.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading event data: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 선수 데이터 로드 시도
echo "<p>Attempting to load players data...</p>";
try {
    $players = loadPlayersData($comp_id, $event_no);
    if ($players) {
        echo "<p style='color: green;'>Players Data Loaded: " . count($players) . " players</p>";
    } else {
        echo "<p style='color: red;'>Failed to load players data.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading players data: " . htmlspecialchars($e->getMessage()) . "</p>";
}

die("Script stopped after loading basic data.");
?>
