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

if (!$eventNumber || !$eventName || empty($players)) {
    echo json_encode(['success' => false, 'error' => '필수 정보가 누락되었습니다.']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$players_file = "$data_dir/players_$eventNumber.txt";

try {
    // 1. RunOrder_Tablet.txt에 새 이벤트 추가
    if (!file_exists($runorder_file)) {
        throw new Exception("RunOrder_Tablet.txt 파일을 찾을 수 없습니다.");
    }
    
    // 현재 이벤트 정보 읽기
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // 새 이벤트 라인 생성
    $newEventLine = "$eventNumber,$eventName,Semi-Final,,,,6,7,8,9,10,LC,1.5,,0";
    $lines[] = $newEventLine;
    
    // 파일에 다시 쓰기
    $content = implode("\n", $lines) . "\n";
    if (file_put_contents($runorder_file, $content) === false) {
        throw new Exception("RunOrder_Tablet.txt 파일 저장에 실패했습니다.");
    }
    
    // 2. 새 이벤트용 선수 파일 생성
    $playerContent = "";
    foreach ($players as $player) {
        $playerContent .= $player['newNumber'] . "\t" . $player['name'] . "\n";
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
    
    echo json_encode([
        'success' => true,
        'message' => "이벤트 $eventNumber이 성공적으로 생성되었습니다.",
        'eventNumber' => $eventNumber,
        'eventName' => $eventName,
        'playersCount' => count($players),
        'filesCreated' => [
            'runorder' => $runorder_file,
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
