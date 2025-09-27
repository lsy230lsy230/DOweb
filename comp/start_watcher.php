<?php
// 폴더 감시 시작 스크립트
$comp_id = '20250913-001';
$event_id = '30'; // 감시할 이벤트 ID

echo "폴더 감시 시작: data/{$comp_id}/scoring_files/Event_{$event_id}/\n";
echo "실시간 결과 페이지: live_results.php?event_id={$event_id}\n";
echo "감시 중... (Ctrl+C로 종료)\n\n";

$scoring_dir = "data/{$comp_id}/scoring_files/Event_{$event_id}";
$last_files = [];

while (true) {
    if (is_dir($scoring_dir)) {
        $current_files = glob($scoring_dir . '/*.json');
        
        // 새로운 파일이 있는지 확인
        if (count($current_files) > count($last_files)) {
            $new_files = array_diff($current_files, $last_files);
            
            foreach ($new_files as $new_file) {
                echo "[" . date('Y-m-d H:i:s') . "] 새 파일 발견: " . basename($new_file) . "\n";
                
                // 실시간 결과 페이지에 알림 (선택사항)
                // 여기서 웹소켓이나 다른 실시간 알림 방법을 사용할 수 있습니다
            }
        }
        
        $last_files = $current_files;
    }
    
    sleep(5); // 5초마다 체크
}
?>
