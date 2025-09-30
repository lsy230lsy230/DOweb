<?php
// 52번 이벤트 간단 테스트
$comp_id = '20250913-001';
$event_no = '52';

echo "=== 52번 이벤트 테스트 시작 ===\n";

// 1. RunOrder 파일 확인
$runorder_file = __DIR__ . "/comp/data/{$comp_id}/RunOrder_Tablet.txt";
echo "RunOrder 파일: " . $runorder_file . "\n";
echo "파일 존재: " . (file_exists($runorder_file) ? 'YES' : 'NO') . "\n";

if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "총 라인 수: " . count($lines) . "\n";
    
    // 52번 이벤트 찾기
    $event_found = false;
    foreach ($lines as $i => $line) {
        $parts = explode(",", $line);
        if (count($parts) >= 10 && trim($parts[0]) == $event_no) {
            echo "52번 이벤트 발견 (라인 " . ($i + 1) . "): " . $line . "\n";
            $event_found = true;
            break;
        }
    }
    
    if (!$event_found) {
        echo "52번 이벤트를 찾을 수 없습니다.\n";
    }
}

// 2. 결과 디렉토리 확인
$result_dir = __DIR__ . "/comp/data/{$comp_id}/Results/Event_{$event_no}";
echo "\n결과 디렉토리: " . $result_dir . "\n";
echo "디렉토리 존재: " . (is_dir($result_dir) ? 'YES' : 'NO') . "\n";

if (is_dir($result_dir)) {
    $files = scandir($result_dir);
    echo "디렉토리 내용: " . implode(', ', $files) . "\n";
}

// 3. 플레이어 파일 확인
$players_file = __DIR__ . "/comp/data/{$comp_id}/players_{$event_no}.txt";
echo "\n플레이어 파일: " . $players_file . "\n";
echo "파일 존재: " . (file_exists($players_file) ? 'YES' : 'NO') . "\n";

if (file_exists($players_file)) {
    $content = file_get_contents($players_file);
    echo "플레이어 파일 내용:\n" . $content . "\n";
}

echo "\n=== 테스트 완료 ===\n";
?>
