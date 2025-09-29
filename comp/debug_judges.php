<?php
$comp_id = "20250913-001";
$event_no = "1-1";

// 현재 디렉토리 확인
echo "Current directory: " . getcwd() . "\n";

// 이벤트의 실제 채점 파일들을 분석해서 심사위원 찾기
$event_judges = [];
$dance_codes = ['2']; // Tango

foreach ($dance_codes as $dance_code) {
    $pattern = "data/{$comp_id}/{$event_no}_{$dance_code}_*.adj";
    echo "Pattern: $pattern\n";
    
    // 절대 경로로 시도
    $absolute_pattern = __DIR__ . "/" . $pattern;
    echo "Absolute pattern: $absolute_pattern\n";
    
    $files = glob($absolute_pattern);
    echo "Files found: " . count($files) . "\n";
    
    // 상대 경로로도 시도
    $files2 = glob($pattern);
    echo "Files found (relative): " . count($files2) . "\n";
    
    $all_files = array_merge($files, $files2);
    foreach ($all_files as $file) {
        echo "File: $file\n";
        $filename = basename($file);
        echo "Filename: $filename\n";
        if (preg_match('/' . preg_quote($event_no) . '_' . preg_quote($dance_code) . '_(\d+)\.adj$/', $filename, $matches)) {
            $judge_code = $matches[1];
            echo "Judge code found: $judge_code\n";
            if (!in_array($judge_code, $event_judges)) {
                $event_judges[] = $judge_code;
            }
        }
    }
}

echo "Event judges: " . implode(', ', $event_judges) . "\n";
echo "Count: " . count($event_judges) . "\n";
?>
