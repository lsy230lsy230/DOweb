<?php
header('Content-Type: application/json');

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

echo json_encode([
    'success' => true,
    'comp_id' => $comp_id,
    'event_no' => $event_no,
    'message' => 'Simple test working'
]);
?>