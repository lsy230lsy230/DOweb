<?php
echo "<h1>Debug Simple Test</h1>";

echo "<p>Step 1: Starting...</p>";

echo "<p>Step 2: Including functions.php...</p>";
require_once __DIR__ . '/scoring/shared/functions.php';

echo "<p>Step 3: functions.php included successfully!</p>";

echo "<p>Step 4: Testing basic functions...</p>";

$comp_id = '20250907-001';
$event_no = '8';

echo "<p>Step 5: Loading event data...</p>";
$event_data = loadEventData($comp_id, $event_no);
echo "<p>Event data loaded: " . (isset($event_data['name']) ? $event_data['name'] : 'No name') . "</p>";

echo "<p>Step 6: Loading players data...</p>";
$players = loadPlayersData($comp_id, $event_no);
echo "<p>Players loaded: " . count($players) . " players</p>";

echo "<p style='color: green; font-weight: bold;'>All tests completed successfully!</p>";
?>






