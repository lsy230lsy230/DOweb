<?php
/**
 * 경로 디버깅 파일
 */

echo "<h1>🔍 경로 디버깅</h1>";

// 다양한 경로 형식 테스트
$paths = [
    "Y:/results/results",
    "Y:\\results\\results", 
    "Y:/results/results/",
    "Y:\\results\\results\\",
    "Y:/results",
    "Y:\\results"
];

echo "<h2>📁 경로 테스트</h2>";

foreach ($paths as $path) {
    echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px;'>";
    echo "<strong>경로:</strong> " . $path . "<br>";
    echo "<strong>폴더 존재:</strong> " . (is_dir($path) ? "✅ YES" : "❌ NO") . "<br>";
    echo "<strong>읽기 가능:</strong> " . (is_readable($path) ? "✅ YES" : "❌ NO") . "<br>";
    
    if (is_dir($path)) {
        $files = glob($path . "/*.html");
        echo "<strong>HTML 파일 수:</strong> " . count($files) . "<br>";
        
        if (count($files) > 0) {
            echo "<strong>파일 목록 (처음 5개):</strong><br>";
            for ($i = 0; $i < min(5, count($files)); $i++) {
                echo "- " . basename($files[$i]) . "<br>";
            }
        }
    }
    echo "</div>";
}

// 현재 작업 디렉토리 확인
echo "<h2>📂 현재 작업 디렉토리</h2>";
echo "<strong>getcwd():</strong> " . getcwd() . "<br>";
echo "<strong>__DIR__:</strong> " . __DIR__ . "<br>";

// 상대 경로 테스트
$relativePath = __DIR__ . "/../results/results";
echo "<h2>🔗 상대 경로 테스트</h2>";
echo "<strong>상대 경로:</strong> " . $relativePath . "<br>";
echo "<strong>폴더 존재:</strong> " . (is_dir($relativePath) ? "✅ YES" : "❌ NO") . "<br>";
echo "<strong>읽기 가능:</strong> " . (is_readable($relativePath) ? "✅ YES" : "❌ NO") . "<br>";

if (is_dir($relativePath)) {
    $files = glob($relativePath . "/*.html");
    echo "<strong>HTML 파일 수:</strong> " . count($files) . "<br>";
}

// 드라이브 확인
echo "<h2>💾 드라이브 확인</h2>";
$drives = ['Y:', 'C:', 'D:'];
foreach ($drives as $drive) {
    echo "<strong>{$drive} 드라이브:</strong> " . (is_dir($drive) ? "✅ 존재" : "❌ 없음") . "<br>";
}
?>




