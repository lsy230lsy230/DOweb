<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';

if (empty($comp_id) || empty($event_no)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// 대회 데이터 디렉토리
$data_dir = __DIR__ . "/data/$comp_id";
$players_file = "$data_dir/players_$event_no.txt";

try {
    if (!file_exists($players_file)) {
        echo json_encode(['success' => true, 'players' => []]);
        exit;
    }
    
    // 선수 파일 읽기
    $content = file_get_contents($players_file);
    $player_numbers = array_filter(explode("\n", $content));
    
    // 선수 이름 정보 (임시로 기본값 사용)
    $competitors = [];
    
    // 기본 선수 이름 생성
    foreach ($player_numbers as $number) {
        $number = trim($number);
        if (!empty($number)) {
            $competitors[$number] = "선수 $number";
        }
    }
    
    // 선수 목록 생성
    $players = [];
    foreach ($player_numbers as $number) {
        $number = trim($number);
        if (!empty($number)) {
            $players[] = [
                'number' => $number,
                'name' => $competitors[$number] ?? "선수 $number"
            ];
        }
    }
    
    echo json_encode(['success' => true, 'players' => $players]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
