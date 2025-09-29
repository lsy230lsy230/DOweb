<?php
// 시간표 페이지
$comp_id = $_GET['id'] ?? '';
$comp_id_clean = str_replace('comp_', '', $comp_id);

// RunOrder_Tablet.txt에서 이벤트 정보 가져오기
function getEventsFromRunOrder($comp_id) {
    $runorder_file = __DIR__ . "/data/{$comp_id}/RunOrder_Tablet.txt";
    
    if (file_exists($runorder_file)) {
        $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $events = [];
        $processed_events = []; // 중복 이벤트 방지
        
        foreach ($lines as $line_num => $line) {
            if (preg_match('/^bom/', $line)) continue; // 헤더 라인 스킵
            
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) >= 14) {
                $event_no = $cols[0];
                $event_name = $cols[1];
                $round = $cols[2];
                $display_number = isset($cols[13]) ? $cols[13] : ''; // 세부번호 (1-1, 1-2, 3-1, 3-2...)
                
                if (!empty($event_no) && is_numeric($event_no)) {
                    // 중복 이벤트 방지 (같은 이벤트 번호는 한 번만)
                    if (!in_array($event_no, $processed_events)) {
                        $processed_events[] = $event_no;
                        
                        $events[] = [
                            'id' => intval($event_no),
                            'display_number' => $display_number ?: $event_no, // display_number가 비어있으면 event_no 사용
                            'name' => $event_name,
                            'round' => $round,
                            'status' => 'processing' // 기본값
                        ];
                    }
                }
            }
        }
        
        return $events;
    }
    
    return [];
}

// 이벤트 데이터 가져오기
$events = getEventsFromRunOrder($comp_id_clean);

// 디버깅: 로드된 이벤트 수와 51번 이벤트 확인
$debug_info = "Total events loaded: " . count($events) . "\n";
$event51_count = 0;
foreach ($events as $event) {
    if ($event['id'] == 51) {
        $event51_count++;
        $debug_info .= "Event 51 found: " . json_encode($event) . "\n";
    }
}
$debug_info .= "Event 51 count: " . $event51_count . "\n";

// 디버깅 정보를 파일에 저장
file_put_contents("debug_schedule_events.txt", $debug_info);

// 웹페이지에 디버깅 정보 표시 (임시)
echo "<!-- DEBUG INFO: " . htmlspecialchars($debug_info) . " -->";
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $competition_info['title']; ?> - 시간표</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        .header p {
            margin: 5px 0;
            opacity: 0.9;
        }
        .events-list {
            padding: 30px;
        }
        .event-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .event-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .event-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }
        .event-round {
            background: #007bff;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .event-status {
            color: #666;
            font-size: 0.95em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $competition_info['title']; ?></h1>
            <p>📅 <?php echo $competition_info['date']; ?> 📍 <?php echo $competition_info['venue']; ?> 🏢 <?php echo $competition_info['organizer']; ?></p>
        </div>
        
        <div class="events-list">
            <h2>📋 이벤트 목록</h2>
            
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $event): ?>
                <div class="event-item">
                    <div class="event-header">
                        <div class="event-title">
                            <?php echo $event['display_number']; ?>번 <?php echo $event['name']; ?>
                        </div>
                        <div class="event-round"><?php echo $event['round']; ?></div>
                    </div>
                    <div class="event-status">
                        상태: <?php echo $event['status']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>이벤트가 없습니다.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
