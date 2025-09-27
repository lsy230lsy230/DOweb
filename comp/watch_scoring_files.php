<?php
header('Content-Type: application/json; charset=utf-8');

// GET 또는 POST 데이터 받기
$event_id = $_GET['event_id'] ?? '';
$comp_id = $_GET['comp_id'] ?? '20250913-001';

// POST 데이터가 있으면 우선 사용
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $event_id = $input['event_id'] ?? $event_id;
    $comp_id = $input['comp_id'] ?? $comp_id;
}

if (empty($event_id)) {
    echo json_encode(['success' => false, 'error' => 'Event ID is required']);
    exit;
}

// 이벤트별 scoring_files 디렉토리 경로
$scoring_dir = "data/{$comp_id}/scoring_files/Event_{$event_id}";

if (!is_dir($scoring_dir)) {
    echo json_encode(['success' => false, 'error' => 'Scoring directory not found']);
    exit;
}

// 최신 파일 찾기 (생성 시간 기준)
$files = glob($scoring_dir . '/*.json');
if (empty($files)) {
    echo json_encode(['success' => false, 'error' => 'No scoring files found']);
    exit;
}

// 파일 생성 시간으로 정렬 (최신순)
usort($files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

$latest_file = $files[0];
$file_content = file_get_contents($latest_file);
$scoring_data = json_decode($file_content, true);

if (!$scoring_data || !isset($scoring_data['scoring_data'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid scoring data']);
    exit;
}

$data = $scoring_data['scoring_data'];

// 진출자만 추출 (recall_count_from_file 기준)
$recall_count = $data['recall_count_from_file'] ?? 0;
$player_recalls = $data['player_recalls'] ?? [];

if (empty($player_recalls)) {
    echo json_encode(['success' => false, 'error' => 'No player data found']);
    exit;
}

// 리콜 수 기준으로 정렬 (높은 순)
usort($player_recalls, function($a, $b) {
    return $b['recall_count'] - $a['recall_count'];
});

// 진출자만 추출 (상위 N명) - recall_count_from_file 사용
$advancing_players = array_slice($player_recalls, 0, $recall_count);

// 디버깅을 위한 로그
error_log("watch_scoring_files.php - recall_count_from_file: $recall_count, advancing_players count: " . count($advancing_players));

// 결과 데이터 구성
$result = [
    'success' => true,
    'event_id' => $event_id,
    'event_name' => $data['event_info']['desc'] ?? "이벤트 {$event_id}",
    'round' => $data['event_info']['round'] ?? 'Semi-Final',
    'recall_count' => $recall_count,
    'total_participants' => count($player_recalls),
    'advancing_players' => [],
    'timestamp' => date('Y-m-d H:i:s'),
    'file_created' => date('Y-m-d H:i:s', filemtime($latest_file))
];

// 진출자 정보 정리
foreach ($advancing_players as $index => $player) {
    $result['advancing_players'][] = [
        'rank' => $index + 1,
        'player_number' => $player['player_number'],
        'player_name' => $player['player_name'] ?? "선수 {$player['player_number']}",
        'recall_count' => $player['recall_count'],
        'status' => '진출'
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>
