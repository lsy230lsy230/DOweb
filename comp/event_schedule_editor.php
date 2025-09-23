<?php
/**
 * 이벤트 순서 편집기
 */

require_once 'event_schedule_manager.php';

$scheduleManager = new EventScheduleManager();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 액션 처리
if ($action === 'add_event') {
    $eventId = $_POST['event_id'] ?? '';
    $eventName = $_POST['event_name'] ?? '';
    $showResult = $_POST['show_result'] === 'true';
    
    if ($eventId && $eventName) {
        // 새 이벤트 추가 로직
        echo json_encode(['success' => true, 'message' => '이벤트가 추가되었습니다.']);
    } else {
        echo json_encode(['success' => false, 'message' => '필수 정보가 누락되었습니다.']);
    }
    exit;
}

if ($action === 'delete_event') {
    $eventId = $_POST['event_id'] ?? '';
    // 이벤트 삭제 로직
    echo json_encode(['success' => true, 'message' => '이벤트가 삭제되었습니다.']);
    exit;
}

if ($action === 'reorder_events') {
    $newOrder = $_POST['event_order'] ?? [];
    // 이벤트 순서 재정렬 로직
    echo json_encode(['success' => true, 'message' => '순서가 업데이트되었습니다.']);
    exit;
}

$schedule = $scheduleManager->getSchedule();

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이벤트 순서 편집기</title>
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
        
        .event-list {
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .event-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            background: white;
            transition: background 0.3s;
        }
        
        .event-item:hover {
            background: #f8f9fa;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-order {
            width: 50px;
            text-align: center;
            font-weight: bold;
            color: #667eea;
        }
        
        .event-id {
            width: 100px;
            font-weight: bold;
            color: #333;
        }
        
        .event-name {
            flex: 1;
            margin: 0 15px;
            color: #555;
        }
        
        .event-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .drag-handle {
            cursor: move;
            color: #999;
            font-size: 18px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
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
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
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
        
        .add-event-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 이벤트 순서 편집기</h1>
            <p>대회 순서를 추가, 수정, 삭제하고 표시 여부를 설정하세요</p>
        </div>
        
        <div class="controls">
            <button class="btn success" onclick="openEventControl()">🎯 이벤트 제어</button>
            <button class="btn info" onclick="openSmartFullscreen()">📺 스마트 전체화면</button>
            <button class="btn" onclick="refreshPage()">🔄 새로고침</button>
            <button class="btn danger" onclick="resetAllVisibility()">🔄 모든 표시 초기화</button>
        </div>
        
        <!-- 새 이벤트 추가 폼 -->
        <div class="add-event-form">
            <h3>➕ 새 이벤트 추가</h3>
            <form id="addEventForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="newEventId">이벤트 ID</label>
                        <input type="text" id="newEventId" placeholder="예: 26A" required>
                    </div>
                    <div class="form-group">
                        <label for="newEventName">이벤트명</label>
                        <input type="text" id="newEventName" placeholder="예: 26. 청각 스탠더드 왈츠" required>
                    </div>
                    <div class="form-group">
                        <label for="newShowResult">결과 표시</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="newShowResult" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn success">➕ 이벤트 추가</button>
            </form>
        </div>
        
        <!-- 이벤트 목록 -->
        <h3>📋 현재 이벤트 순서</h3>
        <div class="event-list" id="eventList">
            <?php foreach ($schedule as $index => $event): ?>
            <div class="event-item" data-event-id="<?php echo htmlspecialchars($event['event_id']); ?>">
                <div class="drag-handle">⋮⋮</div>
                <div class="event-order"><?php echo $index + 1; ?></div>
                <div class="event-id"><?php echo htmlspecialchars($event['event_id']); ?></div>
                <div class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></div>
                <div class="event-controls">
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               <?php echo $event['show_result'] ? 'checked' : ''; ?>
                               onchange="updateVisibility('<?php echo $event['event_id']; ?>', this.checked)">
                        <span class="slider"></span>
                    </label>
                    <span class="status-badge <?php echo $event['show_result'] ? 'status-show' : 'status-hide'; ?>">
                        <?php echo $event['show_result'] ? '표시' : '숨김'; ?>
                    </span>
                    <button class="btn danger" onclick="deleteEvent('<?php echo $event['event_id']; ?>')" style="padding: 5px 10px; font-size: 12px;">
                        삭제
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // 새 이벤트 추가
        document.getElementById('addEventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const eventId = document.getElementById('newEventId').value;
            const eventName = document.getElementById('newEventName').value;
            const showResult = document.getElementById('newShowResult').checked;
            
            if (eventId && eventName) {
                addEvent(eventId, eventName, showResult);
            }
        });
        
        function addEvent(eventId, eventName, showResult) {
            // 실제로는 서버에 전송하여 처리
            const eventList = document.getElementById('eventList');
            const newEvent = document.createElement('div');
            newEvent.className = 'event-item';
            newEvent.setAttribute('data-event-id', eventId);
            newEvent.innerHTML = `
                <div class="drag-handle">⋮⋮</div>
                <div class="event-order">${eventList.children.length + 1}</div>
                <div class="event-id">${eventId}</div>
                <div class="event-name">${eventName}</div>
                <div class="event-controls">
                    <label class="toggle-switch">
                        <input type="checkbox" ${showResult ? 'checked' : ''} onchange="updateVisibility('${eventId}', this.checked)">
                        <span class="slider"></span>
                    </label>
                    <span class="status-badge ${showResult ? 'status-show' : 'status-hide'}">
                        ${showResult ? '표시' : '숨김'}
                    </span>
                    <button class="btn danger" onclick="deleteEvent('${eventId}')" style="padding: 5px 10px; font-size: 12px;">
                        삭제
                    </button>
                </div>
            `;
            eventList.appendChild(newEvent);
            
            // 폼 초기화
            document.getElementById('newEventId').value = '';
            document.getElementById('newEventName').value = '';
            document.getElementById('newShowResult').checked = true;
        }
        
        function updateVisibility(eventId, showResult) {
            // 실제로는 서버에 전송하여 처리
            console.log(`이벤트 ${eventId} 표시 여부: ${showResult}`);
        }
        
        function deleteEvent(eventId) {
            if (confirm(`이벤트 ${eventId}를 삭제하시겠습니까?`)) {
                const eventItem = document.querySelector(`[data-event-id="${eventId}"]`);
                if (eventItem) {
                    eventItem.remove();
                    updateEventOrder();
                }
            }
        }
        
        function updateEventOrder() {
            const eventItems = document.querySelectorAll('.event-item');
            eventItems.forEach((item, index) => {
                const orderElement = item.querySelector('.event-order');
                orderElement.textContent = index + 1;
            });
        }
        
        function openEventControl() {
            window.open('event_control.php', '_blank');
        }
        
        function openSmartFullscreen() {
            window.open('smart_fullscreen.php', '_blank');
        }
        
        function refreshPage() {
            location.reload();
        }
        
        function resetAllVisibility() {
            if (confirm('모든 이벤트의 표시 설정을 초기화하시겠습니까?')) {
                const checkboxes = document.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = true;
                    updateVisibility(checkbox.getAttribute('onchange').match(/'([^']+)'/)[1], true);
                });
            }
        }
        
        // 드래그 앤 드롭 기능 (간단한 버전)
        let draggedElement = null;
        
        document.querySelectorAll('.event-item').forEach(item => {
            item.draggable = true;
            
            item.addEventListener('dragstart', function(e) {
                draggedElement = this;
                e.dataTransfer.effectAllowed = 'move';
            });
            
            item.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            
            item.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedElement && draggedElement !== this) {
                    const parent = this.parentNode;
                    const nextSibling = this.nextSibling;
                    parent.insertBefore(draggedElement, nextSibling);
                    updateEventOrder();
                }
            });
        });
    </script>
</body>
</html>




