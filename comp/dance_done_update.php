<?php
header('Content-Type: application/json');
$comp_id = $_POST['comp_id'] ?? '';
$event_no = $_POST['event_no'] ?? '';
$dance_code = $_POST['dance_code'] ?? '';
$done = ($_POST['done'] ?? '') === 'true';

// BOM 제거
if (substr($event_no, 0, 3) === "\xEF\xBB\xBF") {
    $event_no = substr($event_no, 3);
}

if (!$comp_id || !$event_no || !$dance_code) {
    echo json_encode(['success'=>false, 'error'=>'필수값 누락']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
if (!is_dir($data_dir)) {
    echo json_encode(['success'=>false, 'error'=>'대회 디렉토리 없음']);
    exit;
}

$file = "$data_dir/dance_done_$event_no.json";
$dance_done = [];
if (is_file($file)) {
    $content = file_get_contents($file);
    $dance_done = json_decode($content, true);
    if (!is_array($dance_done)) $dance_done = [];
}

$dance_done[$dance_code] = $done;

if (file_put_contents($file, json_encode($dance_done, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['success'=>false, 'error'=>'파일 저장 실패']);
    exit;
}

echo json_encode(['success'=>true]);