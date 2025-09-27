<?php
// 1-1과 1-2 이벤트 비교 테스트
$comp_id = '20250913-001';

function testEvent($event_id) {
    echo "=== $event_id 이벤트 테스트 ===\n";
    
    $_GET['comp_id'] = $comp_id;
    $_GET['event'] = $event_id;
    
    ob_start();
    include 'api/get_judge_status.php';
    $output = ob_get_clean();
    
    echo "API 응답: $output\n";
    
    $data = json_decode($output, true);
    if ($data && isset($data['judges'])) {
        echo "심사위원 상태:\n";
        foreach ($data['judges'] as $judge) {
            echo "  {$judge['code']} ({$judge['name']}): {$judge['status']} ({$judge['completed']}/{$judge['total']})\n";
        }
    }
    echo "\n";
}

testEvent('1-1');
testEvent('1-2');
?>


