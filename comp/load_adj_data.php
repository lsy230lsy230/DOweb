<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// POST 요청으로 이벤트 번호와 댄스 정보를 받음
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['comp_id']) || !isset($input['event_no']) || !isset($input['dances'])) {
        echo json_encode(['success' => false, 'message' => '필수 파라미터가 누락되었습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $comp_id = $input['comp_id'];
    $event_no = $input['event_no'];
    $dances = $input['dances'];
    
    $adjData = [];
    
    foreach ($dances as $dance) {
        $adjData[$dance] = [];
        
        // 각 심사위원별 .adj 파일 읽기 (12-24)
        for ($judgeNum = 12; $judgeNum <= 24; $judgeNum++) {
            $filename = "data/$comp_id/{$event_no}_{$dance}_{$judgeNum}.adj";
            
            if (file_exists($filename)) {
                $content = file_get_contents($filename);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    // .adj 파일 형식: 각 줄에 선수 번호만 있음 (리콜된 선수)
                    $playerNumber = trim($line, '"');
                    if (!empty($playerNumber)) {
                        if (!isset($adjData[$dance][$playerNumber])) {
                            $adjData[$dance][$playerNumber] = [];
                        }
                        $adjData[$dance][$playerNumber][$judgeNum] = '1'; // 리콜됨
                    }
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $adjData
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    echo json_encode(['success' => false, 'message' => 'POST 요청만 허용됩니다.'], JSON_UNESCAPED_UNICODE);
}
?>
