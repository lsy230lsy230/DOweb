<?php
/**
 * results/ 폴더의 기존 파일들로 테스트하는 페이지
 */

require_once 'result_monitor.php';

$monitor = new ResultMonitor();

echo "<!DOCTYPE html>
<html lang='ko'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Results 폴더 테스트</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .section h2 { color: #333; margin-top: 0; }
        .file-list { max-height: 300px; overflow-y: auto; }
        .file-item { padding: 10px; margin: 5px 0; background: #f8f9fa; border-radius: 3px; }
        .btn { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Results 폴더 테스트</h1>
        <p>현재 Y:/results 폴더의 파일들을 분석하고 종합 결과를 생성합니다.</p>";

// 1. 폴더 존재 확인
echo "<div class='section'>
        <h2>📁 폴더 상태</h2>";

$resultsPath = __DIR__ . "/../results";
$resultsResultsPath = __DIR__ . "/../results/results";

if (is_dir($resultsPath)) {
    echo "<p class='success'>✅ results 폴더가 존재합니다.</p>";
} else {
    echo "<p class='error'>❌ results 폴더가 존재하지 않습니다.</p>";
    echo "</div></div></body></html>";
    exit;
}

if (is_dir($resultsResultsPath)) {
    echo "<p class='success'>✅ results/results 폴더가 존재합니다.</p>";
} else {
    echo "<p class='error'>❌ results/results 폴더가 존재하지 않습니다.</p>";
}

echo "</div>";

// 2. 파일 쌍 찾기
echo "<div class='section'>
        <h2>🔍 결과 파일 쌍 찾기</h2>";

try {
    $pairs = $monitor->findResultPairs();
    echo "<p class='info'>📊 총 " . count($pairs) . "개의 결과 파일 쌍을 찾았습니다.</p>";
    
    if (count($pairs) > 0) {
        echo "<div class='file-list'>";
        foreach ($pairs as $pair) {
            echo "<div class='file-item'>
                    <strong>이벤트 ID:</strong> {$pair['event_id']}<br>
                    <strong>요약 파일:</strong> " . basename($pair['summary']) . "<br>
                    <strong>상세 파일:</strong> " . basename($pair['detailed']) . "
                  </div>";
        }
        echo "</div>";
    } else {
        echo "<p class='error'>❌ 결과 파일 쌍을 찾을 수 없습니다.</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ 오류: " . $e->getMessage() . "</p>";
}

echo "</div>";

// 3. 샘플 파일 파싱 테스트
echo "<div class='section'>
        <h2>🧪 파일 파싱 테스트</h2>";

if (count($pairs) > 0) {
    $testPair = $pairs[0]; // 첫 번째 쌍으로 테스트
    
    try {
        echo "<p class='info'>테스트 파일: {$testPair['event_id']}</p>";
        
        // 요약 파일 파싱
        echo "<h3>📄 요약 파일 파싱 결과</h3>";
        $summaryData = $monitor->parseResultFile($testPair['summary']);
        echo "<pre>";
        echo "이벤트 제목: " . $summaryData['event_title'] . "\n";
        echo "이벤트 날짜: " . $summaryData['event_date'] . "\n";
        echo "경쟁자 수: " . count($summaryData['competitors']) . "\n";
        echo "심판 수: " . count($summaryData['adjudicators']) . "\n";
        echo "\n경쟁자 목록:\n";
        foreach ($summaryData['competitors'] as $competitor) {
            echo "- 순위: {$competitor['place']}, 번호: {$competitor['tag']}, 이름: {$competitor['name']}, 점수: {$competitor['final_score']}\n";
        }
        echo "</pre>";
        
        // 상세 파일 파싱
        echo "<h3>📊 상세 파일 파싱 결과</h3>";
        $detailedData = $monitor->parseDetailedFile($testPair['detailed']);
        echo "<pre>";
        echo "댄스 종류: " . implode(", ", array_keys($detailedData)) . "\n";
        foreach ($detailedData as $danceName => $danceResults) {
            echo "\n{$danceName}:\n";
            foreach ($danceResults as $result) {
                echo "- 번호: {$result['tag']}, 이름: {$result['name']}, 순위: {$result['placing']}\n";
            }
        }
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ 파싱 오류: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ 테스트할 파일 쌍이 없습니다.</p>";
}

echo "</div>";

// 4. 종합 결과 생성 테스트
echo "<div class='section'>
        <h2>🎨 종합 결과 생성 테스트</h2>";

if (count($pairs) > 0) {
    $testPair = $pairs[0];
    
    try {
        echo "<p class='info'>종합 결과를 생성합니다...</p>";
        
        $summaryData = $monitor->parseResultFile($testPair['summary']);
        $detailedData = $monitor->parseDetailedFile($testPair['detailed']);
        $combinedHtml = $monitor->generateCombinedResult($summaryData, $detailedData, $testPair['event_id']);
        
        $savedFile = $monitor->saveCombinedResult($combinedHtml, $testPair['event_id']);
        
        echo "<p class='success'>✅ 종합 결과가 생성되었습니다!</p>";
        echo "<p><strong>저장 위치:</strong> " . $savedFile . "</p>";
        echo "<p><a href='combined_result_{$testPair['event_id']}.html' target='_blank' class='btn'>🎯 종합 결과 보기</a></p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ 종합 결과 생성 오류: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ 테스트할 파일 쌍이 없습니다.</p>";
}

echo "</div>";

// 5. 모든 결과 처리
echo "<div class='section'>
        <h2>⚡ 모든 결과 처리</h2>
        <p>모든 결과 파일 쌍을 처리하여 종합 결과를 생성합니다.</p>
        <button onclick='processAllResults()' class='btn'>🚀 모든 결과 처리</button>
        <div id='processResult'></div>
      </div>";

echo "</div>

<script>
async function processAllResults() {
    const resultDiv = document.getElementById('processResult');
    resultDiv.innerHTML = '<p class=\"info\">처리 중...</p>';
    
    try {
        const response = await fetch('result_monitor.php?action=process');
        const data = await response.json();
        
        if (data.success) {
            const processed = data.processed || [];
            let html = '<p class=\"success\">✅ ' + processed.length + '개의 결과가 처리되었습니다!</p>';
            html += '<ul>';
            processed.forEach(result => {
                html += '<li>';
                html += '<a href=\"combined_result_' + result.event_id + '.html\" target=\"_blank\">' + result.event_title + ' (' + result.event_id + ')</a>';
                if (result.web_url) {
                    html += ' <a href=\"' + result.web_url + '\" target=\"_blank\" style=\"color: #667eea; text-decoration: none;\">🌐 웹에서 보기</a>';
                }
                html += '</li>';
            });
            html += '</ul>';
            resultDiv.innerHTML = html;
        } else {
            resultDiv.innerHTML = '<p class=\"error\">❌ 처리 실패: ' + (data.message || '알 수 없는 오류') + '</p>';
        }
    } catch (error) {
        resultDiv.innerHTML = '<p class=\"error\">❌ 오류: ' + error.message + '</p>';
    }
}
</script>

</body>
</html>";
?>
