<?php
/**
 * 이벤트 제어 화면 (관리자용)
 */

require_once 'event_schedule_manager.php';
require_once 'result_monitor.php';

$scheduleManager = new EventScheduleManager();
$monitor = new ResultMonitor();

$currentEvent = isset($_GET['event']) ? $_GET['event'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 액션 처리
if ($action === 'update_visibility') {
    $eventId = $_POST['event_id'] ?? '';
    $showResult = $_POST['show_result'] === 'true';
    $scheduleManager->updateEventVisibility($eventId, $showResult);
    echo json_encode(['success' => true]);
    exit;
}

$schedule = $scheduleManager->getSchedule();
$pairs = $monitor->findResultPairs();

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이벤트 제어</title>
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
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn.success {
            background: #28a745;
        }
        
        .btn.success:hover {
            background: #218838;
        }
        
        .btn.danger {
            background: #dc3545;
        }
        
        .btn.danger:hover {
            background: #c82333;
        }
        
        .btn.info {
            background: #17a2b8;
        }
        
        .btn.info:hover {
            background: #138496;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .schedule-table th,
        .schedule-table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .schedule-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        .schedule-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .schedule-table tr:hover {
            background: #e9ecef;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
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
        
        .current-event {
            background: #fff3cd !important;
            border-left: 4px solid #ffc107;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 이벤트 제어 시스템</h1>
            <p>대회 순서 관리 및 결과 표시 제어</p>
        </div>
        
        <div class="status-panel">
            <div class="status-item">
                총 이벤트: <span class="status-value"><?php echo count($schedule); ?></span>
            </div>
            <div class="status-item">
                결과 파일: <span class="status-value"><?php echo count($pairs); ?></span>
            </div>
            <div class="status-item">
                현재 이벤트: <span class="status-value"><?php echo $currentEvent ?: '선택 안됨'; ?></span>
            </div>
        </div>
        
        <div class="controls">
            <button class="btn success" onclick="openFullscreen()">🖥️ 전체화면 열기</button>
            <button class="btn info" onclick="openWaitingScreen()">⏳ 대기화면 열기</button>
            <button class="btn" onclick="openDashboard()">📊 대시보드 열기</button>
            <button class="btn" onclick="refreshPage()">🔄 새로고침</button>
            <button class="btn danger" onclick="resetAllVisibility()">🔄 모든 표시 초기화</button>
        </div>
        
        <h2>📋 대회 순서 및 표시 설정</h2>
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>순서</th>
                    <th>이벤트 ID</th>
                    <th>이벤트명</th>
                    <th>결과 표시</th>
                    <th>상태</th>
                    <th>액션</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schedule as $index => $event): ?>
                <tr class="<?php echo $event['event_id'] === $currentEvent ? 'current-event' : ''; ?>">
                    <td><?php echo $index + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($event['event_id']); ?></strong></td>
                    <td><?php echo htmlspecialchars($event['event_name']); ?></td>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" 
                                   <?php echo $event['show_result'] ? 'checked' : ''; ?>
                                   onchange="updateVisibility('<?php echo $event['event_id']; ?>', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $event['show_result'] ? 'status-show' : 'status-hide'; ?>">
                            <?php echo $event['show_result'] ? '표시' : '숨김'; ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn" onclick="setCurrentEvent('<?php echo $event['event_id']; ?>')">
                            선택
                        </button>
                        <button class="btn info" onclick="openEventResult('<?php echo $event['event_id']; ?>')">
                            결과보기
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function updateVisibility(eventId, showResult) {
            fetch('event_control.php?action=update_visibility', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `event_id=${eventId}&show_result=${showResult}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('업데이트 실패');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            });
        }
        
        function setCurrentEvent(eventId) {
            window.location.href = `event_control.php?event=${eventId}`;
        }
        
        function openFullscreen() {
            const currentEvent = '<?php echo $currentEvent; ?>';
            if (currentEvent) {
                window.open(`simple_fullscreen.php?event=${currentEvent}&mode=results`, '_blank');
            } else {
                window.open('simple_fullscreen.php', '_blank');
            }
        }
        
        function openWaitingScreen() {
            const currentEvent = '<?php echo $currentEvent; ?>';
            if (currentEvent) {
                window.open(`simple_fullscreen.php?event=${currentEvent}&mode=waiting`, '_blank');
            } else {
                window.open('simple_fullscreen.php?mode=waiting', '_blank');
            }
        }
        
        function openDashboard() {
            window.open('result_dashboard.php', '_blank');
        }
        
        function openEventResult(eventId) {
            window.open(`combined_result_${eventId}.html`, '_blank');
        }
        
        function refreshPage() {
            location.reload();
        }
        
        function resetAllVisibility() {
            if (confirm('모든 이벤트의 표시 설정을 초기화하시겠습니까?')) {
                // 모든 이벤트를 표시로 설정
                const checkboxes = document.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    if (!checkbox.checked) {
                        checkbox.checked = true;
                        updateVisibility(checkbox.getAttribute('onchange').match(/'([^']+)'/)[1], true);
                    }
                });
            }
        }
        
        // 자동 새로고침 (30초마다)
        setInterval(refreshPage, 30000);
    </script>
</body>
</html>




