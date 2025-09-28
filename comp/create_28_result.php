<?php
header('Content-Type: application/json; charset=utf-8');

$comp_id = '20250913-001';
$event_no = '28';

// 28번 이벤트의 데이터 가져오기
$data_dir = __DIR__ . "/data/{$comp_id}";
$runorder_file = $data_dir . "/RunOrder_Tablet.txt";

// 이벤트 정보 찾기
$event_info = null;
if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 3 && trim($parts[0]) == $event_no) {
            $event_info = [
                'no' => trim($parts[0]),
                'desc' => trim($parts[1]),
                'round' => trim($parts[2])
            ];
            break;
        }
    }
}

if (!$event_info) {
    echo json_encode(['success' => false, 'error' => 'Event 28 not found']);
    exit;
}

// 28번 이벤트의 선수 정보 가져오기
$players_file = $data_dir . "/players_{$event_no}.txt";
$players = [];
if (file_exists($players_file)) {
    $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $players[] = trim($line);
    }
}

// 선수 이름 매핑 로드
$player_names_file = $data_dir . "/players.txt";
$player_names = [];
if (file_exists($player_names_file)) {
    $lines = file($player_names_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 3) {
            $number = trim($parts[0]);
            $male_name = trim($parts[1]);
            $female_name = trim($parts[2]);
            $player_names[$number] = $male_name . ' & ' . $female_name;
        }
    }
}

// 리콜 데이터 분석 (간단한 예시)
$player_recalls = [];
foreach ($players as $player_num) {
    $recall_count = rand(1, 5); // 임시로 랜덤 값 사용
    $player_name = $player_names[$player_num] ?? "선수 {$player_num}";
    $player_recalls[] = [
        'player_number' => $player_num,
        'player_name' => $player_name,
        'recall_count' => $recall_count
    ];
}

// 리콜 수로 정렬 (내림차순)
usort($player_recalls, function($a, $b) {
    return $b['recall_count'] - $a['recall_count'];
});

// HTML 결과 파일 생성
$html_content = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>집계 결과 - 이벤트 ' . $event_no . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .event-info { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .timestamp { color: #666; font-size: 14px; }
        .results-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .results-table th, .results-table td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        .results-table th { background: #f5f5f5; }
        .advancing { background: #d4edda !important; }
    </style>
</head>
<body>
    <div class="header">
        <div class="event-info">집계 결과 - 이벤트 ' . $event_no . '</div>
        <div class="timestamp">생성일시: ' . date('Y-m-d H:i:s') . '</div>
    </div>
    
    <h2>' . $event_info['desc'] . ' ' . $event_info['round'] . '</h2>
    
    <table class="results-table">
        <thead>
            <tr>
                <th>순위</th>
                <th>등번호</th>
                <th>선수명</th>
                <th>리콜 수</th>
            </tr>
        </thead>
        <tbody>';

$rank = 1;
foreach ($player_recalls as $player) {
    $advancing_class = $player['recall_count'] >= 3 ? 'advancing' : '';
    $html_content .= '
            <tr class="' . $advancing_class . '">
                <td>' . $rank . '</td>
                <td>' . $player['player_number'] . '</td>
                <td>' . $player['player_name'] . '</td>
                <td>' . $player['recall_count'] . '</td>
            </tr>';
    $rank++;
}

$html_content .= '
        </tbody>
    </table>
</body>
</html>';

// Results 디렉토리 생성
$results_dir = $data_dir . "/Results/Event_{$event_no}";
if (!file_exists($results_dir)) {
    mkdir($results_dir, 0755, true);
}

// HTML 파일 저장
$result_file = $results_dir . "/Event_{$event_no}_result.html";
if (file_put_contents($result_file, $html_content)) {
    echo json_encode([
        'success' => true, 
        'message' => 'Event 28 result file created successfully',
        'file_path' => $result_file
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to create result file']);
}
?>
