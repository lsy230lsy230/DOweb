<?php
// 매우 간단한 테스트 API
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'message' => 'Test API is working',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
