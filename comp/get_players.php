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
    
    // 전체 선수 정보 로드
    $all_players_file = "$data_dir/players.txt";
    $all_players = [];
    if (file_exists($all_players_file)) {
        $lines = file($all_players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) >= 3) {
                $number = (string)$cols[0];
                $male_name = $cols[1];
                $female_name = $cols[2];
                $all_players[$number] = [
                    'male' => $male_name,
                    'female' => $female_name,
                    'couple' => $male_name . ' & ' . $female_name
                ];
            }
        }
    }
    
    // 선수 목록 생성
    $players = [];
    foreach ($player_numbers as $number) {
        $number = trim($number);
        if (!empty($number)) {
            $player_info = $all_players[$number] ?? null;
            $players[] = [
                'number' => $number,
                'name' => $player_info ? $player_info['couple'] : "선수 $number",
                'male' => $player_info ? $player_info['male'] : '',
                'female' => $player_info ? $player_info['female'] : ''
            ];
        }
    }
    
    echo json_encode(['success' => true, 'players' => $players]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
