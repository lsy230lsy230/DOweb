<?php
/**
 * 댄스스코어 결과 파일 모니터링 및 종합 결과 표시 시스템
 * DanceScore 결과 파일을 실시간으로 모니터링하고 요약/상세 파일을 합쳐서 표시
 */

class ResultMonitor {
    private $resultsPath;
    private $lastCheckTime;
    private $processedFiles;
    
    public function __construct($resultsPath = __DIR__ . "/../results/results") {
        $this->resultsPath = $resultsPath;
        $this->lastCheckTime = time();
        $this->processedFiles = [];
    }
    
    /**
     * 결과 파일들을 모니터링하고 새로운 파일이 생성되었는지 확인
     */
    public function checkForNewResults() {
        $files = glob($this->resultsPath . "/*.html");
        $newFiles = [];
        
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime > $this->lastCheckTime) {
                $newFiles[] = $file;
            }
        }
        
        $this->lastCheckTime = time();
        return $newFiles;
    }
    
    /**
     * 요약 파일과 상세 파일 쌍을 찾아서 반환
     */
    public function findResultPairs() {
        $files = glob($this->resultsPath . "/*.html");
        $pairs = [];
        
        foreach ($files as $file) {
            $basename = basename($file, '.html');
            
            // 상세 파일인지 확인 (-d로 끝나는 파일)
            if (strpos($basename, '-d') !== false) {
                $summaryFile = str_replace('-d', '', $file);
                if (file_exists($summaryFile)) {
                    $pairs[] = [
                        'summary' => $summaryFile,
                        'detailed' => $file,
                        'event_id' => str_replace('-d', '', $basename)
                    ];
                }
            }
        }
        
        return $pairs;
    }
    
    /**
     * HTML 파일에서 결과 데이터를 파싱
     */
    public function parseResultFile($filePath) {
        $html = file_get_contents($filePath);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        $result = [
            'event_title' => '',
            'event_date' => '',
            'competitors' => [],
            'adjudicators' => []
        ];
        
        // 이벤트 제목 추출
        $titleNodes = $xpath->query("//td[@style='font-weight:bold; padding-top:1em;']");
        if ($titleNodes->length > 0) {
            $result['event_title'] = trim($titleNodes->item(0)->textContent);
        }
        
        // 날짜 추출
        $dateNodes = $xpath->query("//td[@style='font-weight:bold;']");
        if ($dateNodes->length > 0) {
            $result['event_date'] = trim($dateNodes->item(0)->textContent);
        }
        
        // 경쟁자 데이터 추출
        $competitorRows = $xpath->query("//tr[td[@style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; background-color:#eee; font-weight:bold;'] or td[@style='margin-top:2em; padding-left:1em; padding-top:5px; padding-bottom:5px; background-color:#ccc; font-weight:bold;']]");
        
        foreach ($competitorRows as $row) {
            $cells = $xpath->query(".//td", $row);
            if ($cells->length >= 3) {
                $competitor = [
                    'place' => trim($cells->item(0)->textContent),
                    'tag' => trim($cells->item(1)->textContent),
                    'name' => trim($cells->item(2)->textContent),
                    'scores' => []
                ];
                
                // 점수 데이터 추출 (요약 파일의 경우)
                for ($i = 3; $i < $cells->length - 1; $i++) {
                    $score = trim($cells->item($i)->textContent);
                    if ($score !== '') {
                        $competitor['scores'][] = $score;
                    }
                }
                
                // 최종 점수 (마지막 셀)
                if ($cells->length > 0) {
                    $finalScore = trim($cells->item($cells->length - 1)->textContent);
                    $competitor['final_score'] = $finalScore;
                }
                
                $result['competitors'][] = $competitor;
            }
        }
        
        // 심판 정보 추출
        $adjudicatorRows = $xpath->query("//tr[td[@style='padding-left:2em;']]");
        foreach ($adjudicatorRows as $row) {
            $cells = $xpath->query(".//td", $row);
            if ($cells->length >= 2) {
                $letter = trim($cells->item(0)->textContent);
                $name = trim($cells->item(1)->textContent);
                if ($letter && $name) {
                    $result['adjudicators'][] = [
                        'letter' => $letter,
                        'name' => $name
                    ];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 상세 파일에서 댄스별 점수 데이터를 파싱
     */
    public function parseDetailedFile($filePath) {
        $html = file_get_contents($filePath);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        $dances = [];
        
        // 각 댄스 섹션 찾기
        $danceSections = $xpath->query("//tr[th[@colspan='28']]");
        
        foreach ($danceSections as $section) {
            $danceName = trim($section->textContent);
            $dances[$danceName] = [];
            
            // 해당 댄스의 경쟁자 데이터 찾기
            $nextSibling = $section->nextSibling;
            while ($nextSibling) {
                if ($nextSibling->nodeType === XML_ELEMENT_NODE && $nextSibling->tagName === 'tr') {
                    $cells = $xpath->query(".//td", $nextSibling);
                    if ($cells->length >= 3) {
                        $tag = trim($cells->item(0)->textContent);
                        $name = trim($cells->item(1)->textContent);
                        $scores = [];
                        
                        // 심판별 점수 추출
                        for ($i = 2; $i < $cells->length - 1; $i++) {
                            $score = trim($cells->item($i)->textContent);
                            if ($score !== '') {
                                $scores[] = $score;
                            }
                        }
                        
                        // 최종 순위 (마지막 셀)
                        $placing = trim($cells->item($cells->length - 1)->textContent);
                        
                        $dances[$danceName][] = [
                            'tag' => $tag,
                            'name' => $name,
                            'scores' => $scores,
                            'placing' => $placing
                        ];
                    }
                } elseif ($nextSibling->nodeType === XML_ELEMENT_NODE && $nextSibling->tagName === 'table') {
                    // 다음 댄스 섹션을 만나면 중단
                    break;
                }
                $nextSibling = $nextSibling->nextSibling;
            }
        }
        
        return $dances;
    }
    
    /**
     * 종합 결과 HTML 생성
     */
    public function generateCombinedResult($summaryData, $detailedData, $eventId) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>종합 결과 - ' . htmlspecialchars($summaryData['event_title']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .header h1 { margin: 0; font-size: 2.5em; }
        .header p { margin: 10px 0 0 0; font-size: 1.2em; opacity: 0.9; }
        .content { padding: 30px; }
        .summary-section { margin-bottom: 40px; }
        .detailed-section { margin-bottom: 40px; }
        .section-title { font-size: 1.8em; color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #667eea; }
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .summary-table th, .summary-table td { padding: 15px; text-align: center; border: 1px solid #ddd; }
        .summary-table th { background: #f8f9fa; font-weight: bold; color: #333; }
        .summary-table tr:nth-child(even) { background: #f8f9fa; }
        .summary-table tr:nth-child(odd) { background: white; }
        .place-1 { background: linear-gradient(135deg, #FFD700, #FFA500) !important; color: white; font-weight: bold; }
        .place-2 { background: linear-gradient(135deg, #C0C0C0, #A0A0A0) !important; color: white; font-weight: bold; }
        .place-3 { background: linear-gradient(135deg, #CD7F32, #B8860B) !important; color: white; font-weight: bold; }
        .dance-section { margin-bottom: 30px; }
        .dance-title { font-size: 1.4em; color: #667eea; margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-left: 5px solid #667eea; }
        .dance-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .dance-table th, .dance-table td { padding: 10px; text-align: center; border: 1px solid #ddd; font-size: 0.9em; }
        .dance-table th { background: #667eea; color: white; font-weight: bold; }
        .dance-table tr:nth-child(even) { background: #f8f9fa; }
        .dance-table tr:nth-child(odd) { background: white; }
        .adjudicators { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px; }
        .adjudicators h3 { margin-top: 0; color: #333; }
        .adjudicator-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .adjudicator-item { padding: 10px; background: white; border-radius: 5px; border-left: 3px solid #667eea; }
        .refresh-btn { position: fixed; top: 20px; right: 20px; background: #667eea; color: white; border: none; padding: 15px 25px; border-radius: 25px; cursor: pointer; font-size: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .refresh-btn:hover { background: #5a6fd8; transform: translateY(-2px); }
        .timestamp { text-align: center; color: #666; font-size: 0.9em; margin-top: 20px; }
    </style>
    <script>
        function refreshResults() {
            location.reload();
        }
        
        // 30초마다 자동 새로고침
        setInterval(refreshResults, 30000);
    </script>
</head>
<body>
    <button class="refresh-btn" onclick="refreshResults()">🔄 새로고침</button>
    
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($summaryData['event_title']) . '</h1>
            <p>' . htmlspecialchars($summaryData['event_date']) . '</p>
        </div>
        
        <div class="content">
            <div class="summary-section">
                <h2 class="section-title">📊 최종 순위</h2>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>순위</th>
                            <th>번호</th>
                            <th>선수명</th>
                            <th>점수</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($summaryData['competitors'] as $competitor) {
            $placeClass = '';
            if ($competitor['place'] == 1) $placeClass = 'place-1';
            elseif ($competitor['place'] == 2) $placeClass = 'place-2';
            elseif ($competitor['place'] == 3) $placeClass = 'place-3';
            
            $html .= '<tr class="' . $placeClass . '">
                <td>' . $competitor['place'] . '</td>
                <td>' . $competitor['tag'] . '</td>
                <td>' . htmlspecialchars($competitor['name']) . '</td>
                <td>' . $competitor['final_score'] . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
                </table>
            </div>
            
            <div class="detailed-section">
                <h2 class="section-title">💃 댄스별 상세 결과</h2>';
        
        foreach ($detailedData as $danceName => $danceResults) {
            $html .= '<div class="dance-section">
                <h3 class="dance-title">' . htmlspecialchars($danceName) . '</h3>
                <table class="dance-table">
                    <thead>
                        <tr>
                            <th>번호</th>
                            <th>선수명</th>';
            
            // 심판 컬럼 헤더 생성
            for ($i = 0; $i < 15; $i++) {
                $html .= '<th>' . chr(65 + $i) . '</th>';
            }
            
            $html .= '<th>순위</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach ($danceResults as $result) {
                $html .= '<tr>
                    <td>' . $result['tag'] . '</td>
                    <td>' . htmlspecialchars($result['name']) . '</td>';
                
                // 심판별 점수
                for ($i = 0; $i < 15; $i++) {
                    $score = isset($result['scores'][$i]) ? $result['scores'][$i] : '';
                    $html .= '<td>' . $score . '</td>';
                }
                
                $html .= '<td><strong>' . $result['placing'] . '</strong></td>
                </tr>';
            }
            
            $html .= '</tbody>
                </table>
            </div>';
        }
        
        $html .= '</div>
            
            <div class="adjudicators">
                <h3>👨‍⚖️ 심판진</h3>
                <div class="adjudicator-grid">';
        
        foreach ($summaryData['adjudicators'] as $adjudicator) {
            $html .= '<div class="adjudicator-item">
                <strong>' . $adjudicator['letter'] . '</strong> ' . htmlspecialchars($adjudicator['name']) . '
            </div>';
        }
        
        $html .= '</div>
            </div>
            
            <div class="timestamp">
                마지막 업데이트: ' . date('Y-m-d H:i:s') . '
            </div>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * 결과 파일을 저장
     */
    public function saveCombinedResult($html, $eventId) {
        $filename = "combined_result_{$eventId}.html";
        
        // 1. 원본 위치에 저장 (results/results 폴더)
        $originalPath = $this->resultsPath . "/" . $filename;
        file_put_contents($originalPath, $html);
        
        // 2. 웹에서 접근 가능한 위치에도 저장 (comp 폴더)
        $webPath = __DIR__ . "/" . $filename;
        file_put_contents($webPath, $html);
        
        return [
            'original' => $originalPath,
            'web' => $webPath,
            'web_url' => "https://www.danceoffice.net/comp/" . $filename
        ];
    }
    
    /**
     * 모든 결과 쌍을 처리하고 종합 결과 생성
     */
    public function processAllResults() {
        $pairs = $this->findResultPairs();
        $processed = [];
        
        foreach ($pairs as $pair) {
            try {
                $summaryData = $this->parseResultFile($pair['summary']);
                $detailedData = $this->parseDetailedFile($pair['detailed']);
                $combinedHtml = $this->generateCombinedResult($summaryData, $detailedData, $pair['event_id']);
                $savedFile = $this->saveCombinedResult($combinedHtml, $pair['event_id']);
                
                $processed[] = [
                    'event_id' => $pair['event_id'],
                    'summary_file' => $pair['summary'],
                    'detailed_file' => $pair['detailed'],
                    'combined_file' => $savedFile['original'],
                    'web_file' => $savedFile['web'],
                    'web_url' => $savedFile['web_url'],
                    'event_title' => $summaryData['event_title']
                ];
            } catch (Exception $e) {
                error_log("Error processing result pair {$pair['event_id']}: " . $e->getMessage());
            }
        }
        
        return $processed;
    }
}

// 사용 예시
if (isset($_GET['action'])) {
    $monitor = new ResultMonitor();
    
    switch ($_GET['action']) {
        case 'process':
            $results = $monitor->processAllResults();
            echo json_encode(['success' => true, 'processed' => $results]);
            break;
            
        case 'check':
            $newFiles = $monitor->checkForNewResults();
            echo json_encode(['success' => true, 'new_files' => $newFiles]);
            break;
            
        case 'list':
            $pairs = $monitor->findResultPairs();
            echo json_encode(['success' => true, 'pairs' => $pairs]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
?>
