<?php
/**
 * 이벤트 결과 파일을 읽어서 반환하는 API
 */

// CORS 헤더 추가
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS 요청 처리 (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$detail_no = $_GET['detail_no'] ?? '';

if (empty($comp_id) || empty($event_no)) {
    echo json_encode(['success' => false, 'message' => '필수 매개변수가 누락되었습니다.']);
    exit;
}

try {
    // 결과 파일 경로 결정
    $comp_id_clean = str_replace('comp_', '', $comp_id);
    $result_dir = __DIR__ . "/data/{$comp_id_clean}/Results";
    
    if ($detail_no && $detail_no !== $event_no) {
        // 세부번호가 있는 경우
        $result_file = "{$result_dir}/Event_{$event_no}-{$detail_no}/Event_{$event_no}-{$detail_no}_result.html";
    } else {
        // 세부번호가 없는 경우
        $result_file = "{$result_dir}/Event_{$event_no}/Event_{$event_no}_result.html";
    }
    
    if (!file_exists($result_file)) {
        echo json_encode(['success' => false, 'message' => '결과 파일을 찾을 수 없습니다.', 'file_path' => $result_file]);
        exit;
    }
    
    $html_content = file_get_contents($result_file);
    if ($html_content === false) {
        echo json_encode(['success' => false, 'message' => '결과 파일을 읽을 수 없습니다.']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html_content,
        'file_path' => $result_file
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => '오류가 발생했습니다: ' . $e->getMessage()
    ]);
}
?>