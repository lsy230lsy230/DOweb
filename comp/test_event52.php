<?php
$comp_id = "20250913-001";
$event_no = "52";

// 이벤트 정보 로드
$event_info_file = "data/{$comp_id}/event_info.json";
$event_data = [];
if (file_exists($event_info_file)) {
    $event_data = json_decode(file_get_contents($event_info_file), true);
}

// 현재 이벤트 찾기
$current_event = null;
foreach ($event_data as $event_no_key => $event_info) {
    if ($event_no_key === $event_no) {
        $current_event = array_merge($event_info, ['no' => $event_no_key]);
        break;
    }
}

if (!$current_event) {
    // 이벤트가 event_info.json에 없는 경우, 기본값으로 설정
    $current_event = [
        'no' => $event_no,
        'dances' => ['6', '7', '8', '9', '10'], // 기본값: 라틴 5종목
        'panel' => 'LC' // 기본값: 라틴 패널
    ];
}

echo "Event found: " . json_encode($current_event) . "\n";

// 이벤트의 실제 채점 파일들을 분석해서 심사위원 찾기
$event_judges = [];
$dance_codes = $current_event['dances'] ?? ['6', '7', '8', '9', '10'];

foreach ($dance_codes as $dance_code) {
    $pattern = "data/{$comp_id}/{$event_no}_{$dance_code}_*.adj";
    $absolute_pattern = __DIR__ . "/" . $pattern;
    echo "Looking for pattern: $absolute_pattern\n";
    $files = glob($absolute_pattern);
    echo "Files found: " . count($files) . "\n";
    foreach ($files as $file) {
        $filename = basename($file);
        if (preg_match('/' . preg_quote($event_no) . '_' . preg_quote($dance_code) . '_(\d+)\.adj$/', $filename, $matches)) {
            $judge_code = $matches[1];
            if (!in_array($judge_code, $event_judges)) {
                $event_judges[] = $judge_code;
            }
        }
    }
}

echo "Event judges: " . implode(', ', $event_judges) . "\n";
echo "Count: " . count($event_judges) . "\n";
?>
