<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$comp_id = $input['comp_id'] ?? '';
$event_no = $input['eventNo'] ?? '';
$recall = $input['recall'] ?? '';
$heats = $input['heats'] ?? '';

// 디버그: 입력 데이터 확인
error_log("Save request: comp_id='$comp_id', event_no='$event_no', recall='$recall', heats='$heats'");

if (!$comp_id || !$event_no) {
    echo json_encode(['success' => false, 'error' => '필수 파라미터가 없습니다.']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";

if (!file_exists($runorder_file)) {
    echo json_encode(['success' => false, 'error' => 'RunOrder_Tablet.txt 파일이 없습니다.']);
    exit;
}

// 파일 읽기
$lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$updated = false;

foreach ($lines as $idx => $line) {
    $cols = explode(',', $line);
    if (count($cols) >= 13) {
        // 이벤트 번호와 세부번호 모두 비교
        $current_base_no = preg_replace('/\x{FEFF}/u', '', $cols[0] ?? '');
        $current_base_no = preg_replace('/\D+/', '', $current_base_no);
        $current_detail_no = trim($cols[13] ?? '');
        
        // 현재 라인의 실제 이벤트 번호 (세부번호가 있으면 세부번호, 없으면 기본번호)
        $current_event_no = !empty($current_detail_no) ? $current_detail_no : $current_base_no;
        
        // 디버그: 28번 이벤트 매칭 확인
        if ($current_base_no === '28') {
            error_log("Found event 28: base_no='$current_base_no', detail_no='$current_detail_no', current_event_no='$current_event_no', target='$event_no'");
        }
        
        // 디버그: 이벤트 번호 매칭 확인
        error_log("Event matching: base_no='$current_base_no', detail_no='$current_detail_no', current_event_no='$current_event_no', target='$event_no'");
        
        if ($current_event_no === $event_no) {
            // Recall 업데이트 (5번째 컬럼, 인덱스 4)
            $cols[4] = $recall;
            
            // Heats 업데이트 (6번째 컬럼, 인덱스 5)
            // 컬럼이 부족하면 추가
            while (count($cols) < 6) {
                $cols[] = '';
            }
            $cols[5] = $heats;
            
            $lines[$idx] = implode(',', $cols);
            $updated = true;
            error_log("Updated event $event_no: recall=$recall, heats=$heats");
            error_log("Updated line: " . $lines[$idx]);
            break;
        }
    }
}

if ($updated) {
    // 파일 저장
    $result = file_put_contents($runorder_file, implode("\n", $lines) . "\n");
    if ($result !== false) {
        // 저장 후 검증
        $verify_lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $verification_passed = false;
        
        foreach ($verify_lines as $line) {
            $cols = explode(',', $line);
            if (count($cols) >= 13) {
                $current_base_no = preg_replace('/\x{FEFF}/u', '', $cols[0] ?? '');
                $current_base_no = preg_replace('/\D+/', '', $current_base_no);
                $current_detail_no = trim($cols[13] ?? '');
                $current_event_no = !empty($current_detail_no) ? $current_detail_no : $current_base_no;
                
                if ($current_event_no === $event_no) {
                    $saved_recall = $cols[4] ?? '';
                    $saved_heats = $cols[5] ?? '';
                    error_log("Verification: Event $event_no, saved recall=$saved_recall, saved heats=$saved_heats");
                    $verification_passed = true;
                    break;
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'verified' => $verification_passed,
            'message' => '이벤트 정보가 저장되었습니다.'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => '파일 저장 실패']);
    }
} else {
    echo json_encode(['success' => false, 'error' => '해당 이벤트를 찾을 수 없습니다.']);
}
?>
