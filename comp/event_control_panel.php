<?php
/**
 * 이벤트 제어 패널
 * 실시간으로 이벤트 상태를 제어하고 모니터링
 */

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'update_status') {
    $eventId = $_POST['event_id'] ?? '';
    $showResult = $_POST['show_result'] === 'true';
    
    if ($eventId) {
        $statusFile = __DIR__ . '/uploads/event_status.json';
        $status = [];
        
        if (file_exists($statusFile)) {
            $status = json_decode(file_get_contents($statusFile), true) ?: [];
        }
        
        $status[$eventId] = $showResult;
        
        if (file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT))) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => '파일 저장 실패']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '이벤트 ID 필요']);
    }
    exit;
}

// 이벤트 데이터 로드
$eventFile = __DIR__ . '/uploads/event_schedule.txt';
$statusFile = __DIR__ . '/uploads/event_status.json';

$events = [];
$status = [];

if (file_exists($eventFile)) {
    $lines = file($eventFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 3) {
            $events[] = [
                'id' => trim($parts[0]),
                'name' => trim($parts[1]),
                'show_result' => trim($parts[2]) === '1'
            ];
        }
    }
}

if (file_exists($statusFile)) {
    $status = json_decode(file_get_contents($statusFile), true) ?: [];
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이벤트 제어 패널</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 1400px;
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
        
        .header h1 {
            margin: 0;
            font-size: 2em;
        }
        
        .controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 15px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-align: center;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.3);
        }
        
        .btn.success {
            background: #28a745;
        }
        
        .btn.success:hover {
            background: #218838;
        }
        
        .btn.info {
            background: #17a2b8;
        }
        
        .btn.info:hover {
            background: #138496;
        }
        
        .btn.danger {
            background: #dc3545;
        }
        
        .btn.danger:hover {
            background: #c82333;
        }
        
        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .event-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            background: white;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .event-card.current {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f0ff 100%);
        }
        
        .event-id {
            font-size: 1.2em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .event-name {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .event-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #2196F3;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-show {
            background: #d4edda;
            color: #155724;
        }
        
        .status-hide {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-panel {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .status-item {
            display: inline-block;
            margin-right: 20px;
            font-weight: bold;
        }
        
        .status-value {
            color: #007bff;
        }
        
        .current-event {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .current-event h2 {
            margin: 0 0 10px 0;
            font-size: 1.5em;
        }
        
        .current-event p {
            margin: 0;
            opacity: 0.9;
        }
        
        /* 반응형 디자인 */
        @media (max-width: 768px) {
            .controls {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .btn {
                padding: 12px 15px;
                font-size: 13px;
                min-height: 45px;
            }
            
            .container {
                margin: 10px;
                padding: 15px;
            }
            
            .event-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 이벤트 제어 패널</h1>
            <p>실시간으로 이벤트 상태를 제어하고 모니터링하세요</p>
        </div>
        
        <div class="status-panel">
            <div class="status-item">
                총 이벤트: <span class="status-value"><?php echo count($events); ?></span>
            </div>
            <div class="status-item">
                결과 발표 예정: <span class="status-value" id="showCount">-</span>
            </div>
            <div class="status-item">
                결과 발표 안함: <span class="status-value" id="hideCount">-</span>
            </div>
        </div>
        
        <div class="controls">
            <button class="btn success" onclick="openFullscreenMonitor()">🖥️ 전체화면 모니터링</button>
            <button class="btn info" onclick="openEventUpload()">📁 이벤트 업로드</button>
            <button class="btn" onclick="openResultMonitoring()">🔍 결과 모니터링</button>
            <button class="btn" onclick="openResultDashboard()">📊 결과 대시보드</button>
            <button class="btn" onclick="openResultGenerator()">⚙️ 결과 생성기</button>
            <button class="btn" onclick="refreshPage()">🔄 새로고침</button>
            <button class="btn danger" onclick="resetAllStatus()">🔄 모든 상태 초기화</button>
        </div>
        
        <div class="current-event" id="currentEventInfo">
            <h2>현재 이벤트</h2>
            <p id="currentEventText">이벤트를 선택하세요</p>
        </div>
        
        <h3>📋 이벤트 목록</h3>
        <div class="event-grid" id="eventGrid">
            <?php foreach ($events as $index => $event): ?>
            <div class="event-card" data-event-id="<?php echo htmlspecialchars($event['id']); ?>" data-event-index="<?php echo $index; ?>">
                <div class="event-id"><?php echo htmlspecialchars($event['id']); ?></div>
                <div class="event-name"><?php echo htmlspecialchars($event['name']); ?></div>
                <div class="event-controls">
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               <?php echo (isset($status[$event['id']]) && $status[$event['id']]) ? 'checked' : ''; ?>
                               onchange="updateEventStatus('<?php echo $event['id']; ?>', this.checked)">
                        <span class="slider"></span>
                    </label>
                    <span class="status-badge <?php echo (isset($status[$event['id']]) && $status[$event['id']]) ? 'status-show' : 'status-hide'; ?>">
                        <?php echo (isset($status[$event['id']]) && $status[$event['id']]) ? '발표' : '대기' ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        let currentEventIndex = 0;
        let events = <?php echo json_encode($events); ?>;
        
        function updateEventStatus(eventId, showResult) {
            fetch('event_control_panel.php?action=update_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `event_id=${eventId}&show_result=${showResult}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 상태 배지 업데이트
                    const eventCard = document.querySelector(`[data-event-id="${eventId}"]`);
                    const statusBadge = eventCard.querySelector('.status-badge');
                    
                    if (showResult) {
                        statusBadge.textContent = '발표';
                        statusBadge.className = 'status-badge status-show';
                    } else {
                        statusBadge.textContent = '대기';
                        statusBadge.className = 'status-badge status-hide';
                    }
                    
                    updateStatusCounts();
                } else {
                    alert('상태 업데이트에 실패했습니다: ' + (data.message || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            });
        }
        
        function updateStatusCounts() {
            const showCount = document.querySelectorAll('.status-badge.status-show').length;
            const hideCount = document.querySelectorAll('.status-badge.status-hide').length;
            
            document.getElementById('showCount').textContent = showCount;
            document.getElementById('hideCount').textContent = hideCount;
        }
        
        function setCurrentEvent(eventIndex) {
            currentEventIndex = eventIndex;
            
            // 모든 카드에서 current 클래스 제거
            document.querySelectorAll('.event-card').forEach(card => {
                card.classList.remove('current');
            });
            
            // 현재 이벤트 카드에 current 클래스 추가
            const currentCard = document.querySelector(`[data-event-index="${eventIndex}"]`);
            if (currentCard) {
                currentCard.classList.add('current');
            }
            
            // 현재 이벤트 정보 업데이트
            if (events[eventIndex]) {
                const event = events[eventIndex];
                document.getElementById('currentEventText').textContent = 
                    `${event.id}: ${event.name}`;
            }
        }
        
        function openFullscreenMonitor() {
            window.open('event_monitor_v2.php', '_blank');
        }
        
        function openEventUpload() {
            window.open('event_upload.php', '_blank');
        }
        
        function openResultMonitoring() {
            window.open('monitor_control.php', '_blank');
        }
        
        function openResultDashboard() {
            window.open('result_dashboard.php', '_blank');
        }
        
        function openResultGenerator() {
            window.open('test_results.php', '_blank');
        }
        
        function refreshPage() {
            location.reload();
        }
        
        function resetAllStatus() {
            if (confirm('모든 이벤트의 상태를 초기화하시겠습니까?')) {
                const checkboxes = document.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = false;
                    updateEventStatus(checkbox.getAttribute('onchange').match(/'([^']+)'/)[1], false);
                });
            }
        }
        
        // 이벤트 카드 클릭으로 현재 이벤트 설정
        document.querySelectorAll('.event-card').forEach(card => {
            card.addEventListener('click', function() {
                const eventIndex = parseInt(this.getAttribute('data-event-index'));
                setCurrentEvent(eventIndex);
            });
        });
        
        // 초기화
        document.addEventListener('DOMContentLoaded', function() {
            updateStatusCounts();
            setCurrentEvent(0);
            
            // 30초마다 자동 새로고침
            setInterval(refreshPage, 30000);
        });
    </script>
</body>
</html>
