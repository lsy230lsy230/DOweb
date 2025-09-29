<?php
header('Content-Type: application/json');

$comp_id = "20250913-001";
$event_no = "52";

try {
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

    echo json_encode([
        'success' => true,
        'current_event' => $current_event,
        'message' => 'Event loaded successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
