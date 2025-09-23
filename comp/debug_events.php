<?php
/**
 * 이벤트 그룹화 디버깅 페이지
 */

// 테스트용 이벤트 데이터
$testEvents = [
    ['id' => '1A', 'name' => '지적 라틴', 'show_result' => true],
    ['id' => '1B', 'name' => '시각 라틴', 'show_result' => false],
    ['id' => '1C', 'name' => '청각 라틴', 'show_result' => true],
    ['id' => '2A', 'name' => '지적 스탠더드', 'show_result' => true],
    ['id' => '2B', 'name' => '시각 스탠더드', 'show_result' => false],
    ['id' => '2C', 'name' => '청각 스탠더드', 'show_result' => true],
    ['id' => '3A', 'name' => '지적 왈츠', 'show_result' => true],
    ['id' => '3B', 'name' => '시각 왈츠', 'show_result' => false],
    ['id' => '3C', 'name' => '청각 왈츠', 'show_result' => true],
];

// 그룹화 함수
function groupEvents($events) {
    $groups = [];
    
    foreach ($events as $event) {
        // 이벤트 ID에서 숫자 부분 추출 (예: 1A -> 1, 2B -> 2)
        $eventNumber = preg_replace('/[^0-9]/', '', $event['id']);
        
        if ($eventNumber && $eventNumber !== '') {
            if (!isset($groups[$eventNumber])) {
                $groups[$eventNumber] = [];
            }
            $groups[$eventNumber][] = $event;
        }
    }
    
    // 숫자 순으로 정렬
    ksort($groups, SORT_NUMERIC);
    
    return $groups;
}

$groupedEvents = groupEvents($testEvents);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이벤트 그룹화 디버깅</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }
        
        .section h3 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .event-group {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: center;
        }
        
        .group-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .event-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
            text-align: center;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-name {
            color: #666;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .status-show {
            background: #d4edda;
            color: #155724;
        }
        
        .status-hide {
            background: #f8d7da;
            color: #721c24;
        }
        
        .json-output {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 이벤트 그룹화 디버깅</h1>
            <p>이벤트가 올바르게 그룹화되는지 확인하세요</p>
        </div>
        
        <div class="section">
            <h3>📋 원본 이벤트 데이터</h3>
            <div class="json-output"><?php echo json_encode($testEvents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></div>
        </div>
        
        <div class="section">
            <h3>🎯 그룹화된 이벤트</h3>
            <?php foreach ($groupedEvents as $groupNumber => $groupEvents): ?>
            <div class="event-group">
                <div class="group-title">이벤트 <?php echo $groupNumber; ?> (<?php echo count($groupEvents); ?>개)</div>
                <?php foreach ($groupEvents as $event): ?>
                <div class="event-item">
                    <span class="event-name"><?php echo htmlspecialchars($event['name']); ?></span>
                    <span class="status-badge <?php echo $event['show_result'] ? 'status-show' : 'status-hide'; ?>">
                        <?php echo $event['show_result'] ? '발표' : '대기'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section">
            <h3>📊 그룹화 결과 JSON</h3>
            <div class="json-output"><?php echo json_encode($groupedEvents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></div>
        </div>
        
        <div class="section">
            <h3>🧪 테스트 링크</h3>
            <p>
                <a href="event_monitor_v2.php" target="_blank" style="color: #007bff; text-decoration: none; font-weight: bold;">
                    📺 그룹 모니터링 테스트
                </a>
            </p>
        </div>
    </div>
</body>
</html>
