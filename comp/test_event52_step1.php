<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $comp_id = "20250913-001";
    $event_no = "52";

    // Step 1: 기본 이벤트 정보만 처리
    $current_event = [
        'no' => $event_no,
        'dances' => ['6', '7', '8', '9', '10'], // 라틴 5종목
        'panel' => 'LC'
    ];

    echo json_encode([
        'success' => true,
        'step' => 'Step 1 - Basic event info',
        'event_no' => $event_no,
        'current_event' => $current_event
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'step' => 'Step 1 failed'
    ]);
}
?>
