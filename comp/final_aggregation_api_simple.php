<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $comp_id = $_GET['comp_id'] ?? '';
    $event_no = $_GET['event_no'] ?? '';

    if (empty($comp_id) || empty($event_no)) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit;
    }
    
    // 기본 응답
    echo json_encode([
        'success' => true,
        'message' => 'Final aggregation API is working',
        'comp_id' => $comp_id,
        'event_no' => $event_no,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
