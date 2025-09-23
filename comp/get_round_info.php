<?php
header('Content-Type: application/json; charset=utf-8');

$comp_id = $_GET['comp_id'] ?? '';
$comp_id = preg_replace('/[^\d-]/', '', $comp_id);

if (!$comp_id) {
    echo json_encode(['success' => false, 'error' => 'comp_id 누락']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
$round_info_file = "$data_dir/round_info.json";

if (file_exists($round_info_file)) {
    $content = file_get_contents($round_info_file);
    $round_data = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($round_data['round_info'])) {
        echo json_encode(['success' => true, 'round_info' => $round_data['round_info']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'JSON 파싱 오류']);
    }
} else {
    echo json_encode(['success' => true, 'round_info' => []]);
}
?>
