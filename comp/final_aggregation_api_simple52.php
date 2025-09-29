<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

try {
    $comp_id = $_GET['comp_id'] ?? '';
    $event_no = $_GET['event_no'] ?? '';

    if (empty($comp_id) || empty($event_no)) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        exit;
    }

    // 이벤트 52 전용 간단한 처리
    if ($event_no === '52') {
        $current_event = [
            'no' => $event_no,
            'desc' => '프로페셔널 라틴',
            'round' => 'Final',
            'dances' => ['6', '7', '8', '9', '10'],
            'panel' => 'LC'
        ];

        // 심사위원 하드코딩
        $event_judges = ['12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24'];
        
        // 전체 심사위원 정보 로드
        $adjudicators_file = "data/{$comp_id}/adjudicators.txt";
        $all_adjudicators = [];
        if (file_exists($adjudicators_file)) {
            $lines = file($adjudicators_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = explode(",", $line);
                if (count($parts) >= 2) {
                    $all_adjudicators[$parts[0]] = [
                        'code' => trim($parts[0]),
                        'name' => trim($parts[1])
                    ];
                }
            }
        }

        // 심사위원 배열 생성
        $adjudicators = [];
        $judge_index = 0;
        foreach ($event_judges as $judge_code) {
            if (isset($all_adjudicators[$judge_code])) {
                $adjudicators[] = [
                    'code' => chr(65 + $judge_index),
                    'name' => $all_adjudicators[$judge_code]['name'],
                    'original_code' => $judge_code
                ];
                $judge_index++;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Event 52 simple processing completed',
            'comp_id' => $comp_id,
            'event_no' => $event_no,
            'event_info' => $current_event,
            'adjudicators_count' => count($adjudicators),
            'adjudicators' => $adjudicators
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'This API is only for event 52']);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
