<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 간단한 테스트 응답
echo json_encode([
    'success' => true,
    'message' => 'Test API working',
    'timestamp' => date('Y-m-d H:i:s'),
    'memory_usage' => memory_get_usage(true),
    'memory_peak' => memory_get_peak_usage(true)
]);
?>