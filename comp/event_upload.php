<?php
/**
 * 이벤트 순서 업로드 및 관리 시스템
 */

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['event_file'])) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadFile = $uploadDir . 'event_schedule.txt';
    
    // 파일 형식 검증
    $tempFile = $_FILES['event_file']['tmp_name'];
    $lines = file($tempFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $validFormat = true;
    $errorLines = [];
    
    foreach ($lines as $lineNum => $line) {
        $parts = explode(',', $line);
        if (count($parts) < 3) {
            $validFormat = false;
            $errorLines[] = $lineNum + 1;
        } else {
            $gabu = trim($parts[2]);
            if ($gabu !== '0' && $gabu !== '1') {
                $validFormat = false;
                $errorLines[] = $lineNum + 1;
            }
        }
    }
    
    if (!$validFormat) {
        $error = '파일 형식이 올바르지 않습니다. ' . implode(', ', $errorLines) . '번째 줄을 확인해주세요.';
    } else {
        if (move_uploaded_file($tempFile, $uploadFile)) {
            $message = '이벤트 순서가 성공적으로 업로드되었습니다. (총 ' . count($lines) . '개 이벤트)';
        } else {
            $error = '파일 업로드에 실패했습니다.';
        }
    }
}

// 현재 이벤트 순서 파일 읽기
$eventFile = __DIR__ . '/uploads/event_schedule.txt';
$events = [];

if (file_exists($eventFile)) {
    $lines = file($eventFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 3) {
            $events[] = [
                'id' => trim($parts[0]),
                'name' => trim($parts[1]),
                'show_result' => trim($parts[2]) === '1' // 1이면 true, 0이면 false
            ];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이벤트 순서 업로드</title>
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
        
        .upload-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
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
        
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            background: white;
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
        
        .event-id {
            width: 80px;
            font-weight: bold;
            color: #667eea;
        }
        
        .event-name {
            flex: 1;
            margin: 0 15px;
            color: #333;
        }
        
        .event-controls {
            display: flex;
            gap: 10px;
            align-items: center;
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
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .file-format {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-family: monospace;
        }
        
        .controls {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 이벤트 순서 업로드 및 관리</h1>
            <p>대회 순서를 업로드하고 실시간으로 결과 발표를 제어하세요</p>
        </div>
        
        <?php if ($message): ?>
        <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="controls">
            <button class="btn success" onclick="openFullscreenMonitor()">🖥️ 전체화면 모니터링</button>
            <button class="btn info" onclick="openEventControl()">🎯 이벤트 제어</button>
            <button class="btn" onclick="refreshPage()">🔄 새로고침</button>
        </div>
        
        <!-- 파일 업로드 섹션 -->
        <div class="upload-section">
            <h3>📁 이벤트 순서 파일 업로드</h3>
            <p>텍스트 파일 형식: <code>이벤트번호,이벤트명,가부</code></p>
            
            <div class="file-format">
                <strong>파일 형식 예시:</strong><br>
                1A,지적 라틴,1<br>
                1B,시각 라틴,0<br>
                1C,청각 라틴,1<br>
                2A,지적 스탠더드,1<br>
                2B,시각 스탠더드,0<br><br>
                <strong>가부 설명:</strong><br>
                1 = 결과 발표함, 0 = 결과 발표 안함
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="event_file">이벤트 순서 파일 선택</label>
                    <input type="file" id="event_file" name="event_file" accept=".txt" required>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="btn success">📤 파일 업로드</button>
                    <a href="sample_event_schedule.txt" download class="btn info">📥 샘플 파일 다운로드</a>
                </div>
            </form>
        </div>
        
        <!-- 현재 이벤트 목록 -->
        <?php if (!empty($events)): ?>
        <h3>📋 현재 이벤트 순서 (총 <?php echo count($events); ?>개)</h3>
        <div class="event-list" id="eventList">
            <?php foreach ($events as $index => $event): ?>
            <div class="event-item" data-event-id="<?php echo htmlspecialchars($event['id']); ?>">
                <div class="event-id"><?php echo htmlspecialchars($event['id']); ?></div>
                <div class="event-name"><?php echo htmlspecialchars($event['name']); ?></div>
                <div class="event-controls">
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               <?php echo $event['show_result'] ? 'checked' : ''; ?>
                               onchange="updateEventStatus('<?php echo $event['id']; ?>', this.checked)">
                        <span class="slider"></span>
                    </label>
                    <span class="status-badge <?php echo $event['show_result'] ? 'status-show' : 'status-hide'; ?>">
                        <?php echo $event['show_result'] ? '발표' : '대기' ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="message error">
            이벤트 순서 파일이 없습니다. 위에서 파일을 업로드해주세요.
        </div>
        <?php endif; ?>
    </div>

    <script>
        function updateEventStatus(eventId, showResult) {
            // AJAX로 서버에 상태 업데이트 요청
            fetch('update_event_status.php', {
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
                    const eventItem = document.querySelector(`[data-event-id="${eventId}"]`);
                    const statusBadge = eventItem.querySelector('.status-badge');
                    if (showResult) {
                        statusBadge.textContent = '발표';
                        statusBadge.className = 'status-badge status-show';
                    } else {
                        statusBadge.textContent = '대기';
                        statusBadge.className = 'status-badge status-hide';
                    }
                } else {
                    alert('상태 업데이트에 실패했습니다.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            });
        }
        
        function openFullscreenMonitor() {
            window.open('event_monitor_v2.php', '_blank');
        }
        
        function openEventControl() {
            window.open('event_control_panel.php', '_blank');
        }
        
        function refreshPage() {
            location.reload();
        }
    </script>
</body>
</html>
