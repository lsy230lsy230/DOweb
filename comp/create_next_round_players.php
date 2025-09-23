<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$comp_id = $input['comp_id'] ?? '';
$eventNo = $input['eventNo'] ?? '';
$players = $input['players'] ?? [];

if (empty($comp_id) || empty($eventNo) || empty($players)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// 대회 데이터 디렉토리
$data_dir = __DIR__ . "/data/$comp_id";

// 디렉토리가 없으면 생성
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}

// 선수 파일 경로
$players_file = "$data_dir/players_$eventNo.txt";

try {
    // 선수 목록을 파일에 저장
    $content = implode("\n", $players);
    $result = file_put_contents($players_file, $content);
    
    if ($result === false) {
        throw new Exception('Failed to write players file');
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Players file created successfully: players_$eventNo.txt",
        'file_path' => $players_file,
        'player_count' => count($players)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error creating players file: ' . $e->getMessage()
    ]);
}
?>





