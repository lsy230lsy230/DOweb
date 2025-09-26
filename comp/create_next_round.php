<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST 요청만 허용됩니다.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$eventNumber = $input['eventNumber'] ?? '';
$eventName = $input['eventName'] ?? '';
$players = $input['players'] ?? [];
$comp_id = $input['comp_id'] ?? '20250913-001';

error_log("=== create_next_round.php 시작 ===");
error_log("eventNumber: $eventNumber");
error_log("eventName: $eventName");
error_log("players 수: " . count($players));
error_log("comp_id: $comp_id");

if (!$eventNumber || !$eventName || empty($players)) {
    echo json_encode(['success' => false, 'error' => '필수 정보가 누락되었습니다.']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$players_file = "$data_dir/players_$eventNumber.txt";

try {
    // 1. 기존 이벤트 확인
    if (!file_exists($runorder_file)) {
        throw new Exception("RunOrder_Tablet.txt 파일을 찾을 수 없습니다.");
    }
    
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $eventExists = false;
    
    // 해당 이벤트가 이미 존재하는지 확인
    foreach ($lines as $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 1 && trim($parts[0]) == $eventNumber) {
            $eventExists = true;
            break;
        }
    }
    
    // 이벤트가 존재하지 않는 경우에만 새로 추가
    if (!$eventExists) {
        // 진출자 수를 players 배열 길이로 설정
        $recallCount = count($players);
        $newEventLine = "$eventNumber,$eventName,Semi-Final,,,,$recallCount,7,8,9,10,LC,1.5,,0";
        $lines[] = $newEventLine;
        
        $content = implode("\n", $lines) . "\n";
        if (file_put_contents($runorder_file, $content) === false) {
            throw new Exception("RunOrder_Tablet.txt 파일 저장에 실패했습니다.");
        }
    }
    
    // 2. 이벤트용 선수 파일 업데이트 (기존 파일 덮어쓰기)
    $playerContent = "";
    foreach ($players as $player) {
        // 등번호만 저장 (선수명 제외)
        $playerContent .= $player['oldNumber'] . "\n";
    }
    
    if (file_put_contents($players_file, $playerContent) === false) {
        throw new Exception("선수 파일 생성에 실패했습니다.");
    }
    
    // 3. 새 이벤트용 히트 파일 생성 (빈 파일)
    $hits_file = "$data_dir/players_hits_$eventNumber.json";
    $hitsContent = json_encode([], JSON_PRETTY_PRINT);
    file_put_contents($hits_file, $hitsContent);
    
    // 4. 새 이벤트용 심사위원 파일 생성 (기존 패널 사용)
    $adjudicators_file = "$data_dir/adjudicators_$eventNumber.txt";
    $adjudicatorsContent = "12\n13\n14\n15\n16\n17\n18\n19\n20\n21\n22\n23\n24\n";
    file_put_contents($adjudicators_file, $adjudicatorsContent);
    
    $message = $eventExists ? 
        "이벤트 $eventNumber의 진출자가 업데이트되었습니다." : 
        "이벤트 $eventNumber이 성공적으로 생성되었습니다.";
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'eventNumber' => $eventNumber,
        'eventName' => $eventName,
        'playersCount' => count($players),
        'eventExisted' => $eventExists,
        'filesUpdated' => [
            'players' => $players_file,
            'hits' => $hits_file,
            'adjudicators' => $adjudicators_file
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
