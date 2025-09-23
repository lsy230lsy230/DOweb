<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>모니터링 제어</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 5px;
            font-weight: bold;
        }
        .status.online {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.offline {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .log-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
        }
        .controls {
            text-align: center;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 댄스스코어 결과 모니터링 제어</h1>
        
        <div class="info">
            <strong>사용법:</strong><br>
            1. "모니터링 시작" 버튼을 클릭하여 Y:/results 폴더를 실시간 모니터링합니다.<br>
            2. "대시보드 열기" 버튼으로 모니터링 대시보드를 엽니다.<br>
            3. "테스트 페이지" 버튼으로 현재 파일들을 테스트합니다.<br>
            4. "상태 확인" 버튼으로 현재 모니터링 상태를 확인합니다.
        </div>
        
        <div class="controls">
            <button class="btn btn-success" onclick="startMonitoring()">▶️ 모니터링 시작</button>
            <button class="btn" onclick="openDashboard()">📊 대시보드 열기</button>
            <button class="btn" onclick="openTestPage()">🧪 테스트 페이지</button>
            <button class="btn btn-primary" onclick="openEventUpload()">📁 이벤트 업로드</button>
            <button class="btn btn-info" onclick="openCompetitionSettings()">🏆 대회 설정</button>
            <button class="btn" onclick="openEventControl()">🎯 이벤트 제어</button>
            <button class="btn" onclick="openEventMonitor()">🖥️ 이벤트 모니터링</button>
            <button class="btn" onclick="openEventMonitorV2()">📺 그룹 모니터링</button>
            <button class="btn" onclick="openDebugEvents()">🔍 이벤트 디버깅</button>
            <button class="btn" onclick="checkStatus()">🔍 상태 확인</button>
            <button class="btn btn-danger" onclick="stopMonitoring()">⏹️ 모니터링 중지</button>
        </div>
        
        <div id="status" class="status offline">
            모니터링 상태를 확인하세요.
        </div>
        
        <div class="log-container" id="logContainer">
            로그가 여기에 표시됩니다...
        </div>
    </div>

    <script>
        function startMonitoring() {
            fetch('result_watcher.php?action=start')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStatus('모니터링이 시작되었습니다.', 'online');
                        addLog('모니터링 시작됨');
                    } else {
                        updateStatus('모니터링 시작 실패: ' + data.message, 'offline');
                        addLog('모니터링 시작 실패: ' + data.message);
                    }
                })
                .catch(error => {
                    updateStatus('오류 발생: ' + error.message, 'offline');
                    addLog('오류: ' + error.message);
                });
        }
        
        function stopMonitoring() {
            fetch('result_watcher.php?action=stop')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStatus('모니터링이 중지되었습니다.', 'offline');
                        addLog('모니터링 중지됨');
                    } else {
                        updateStatus('모니터링 중지 실패: ' + data.message, 'offline');
                        addLog('모니터링 중지 실패: ' + data.message);
                    }
                })
                .catch(error => {
                    updateStatus('오류 발생: ' + error.message, 'offline');
                    addLog('오류: ' + error.message);
                });
        }
        
        function checkStatus() {
            fetch('result_watcher.php?action=status')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStatus('모니터링 상태 확인됨', 'online');
                        if (data.logs) {
                            displayLogs(data.logs);
                        }
                    } else {
                        updateStatus('상태 확인 실패: ' + data.message, 'offline');
                        addLog('상태 확인 실패: ' + data.message);
                    }
                })
                .catch(error => {
                    updateStatus('오류 발생: ' + error.message, 'offline');
                    addLog('오류: ' + error.message);
                });
        }
        
        function openDashboard() {
            window.open('result_dashboard.php', '_blank');
        }
        
        function openTestPage() {
            window.open('test_results.php', '_blank');
        }
        
        function openFullscreenMonitor() {
            window.open('fullscreen_monitor.php', '_blank');
        }
        
        function openSimpleFullscreen() {
            window.open('simple_fullscreen.php', '_blank');
        }
        
        function openEventControl() {
            window.open('event_control.php', '_blank');
        }
        
        function openSmartFullscreen() {
            window.open('smart_fullscreen.php', '_blank');
        }
        
        function openEventUpload() {
            window.open('event_upload.php', '_blank');
        }
        
        function openEventMonitor() {
            window.open('event_monitor_v2.php', '_blank');
        }
        
        function openCompetitionSettings() {
            window.open('competition_settings.php', '_blank');
        }
        
        function openEventMonitorV2() {
            window.open('event_monitor_v2.php', '_blank');
        }
        
        function openDebugEvents() {
            window.open('debug_events.php', '_blank');
        }
        
        function updateStatus(message, type) {
            const statusDiv = document.getElementById('status');
            statusDiv.textContent = message;
            statusDiv.className = 'status ' + type;
        }
        
        function addLog(message) {
            const logContainer = document.getElementById('logContainer');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.textContent = `[${timestamp}] ${message}`;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        function displayLogs(logs) {
            const logContainer = document.getElementById('logContainer');
            logContainer.innerHTML = '';
            logs.forEach(log => {
                if (log.trim()) {
                    const logEntry = document.createElement('div');
                    logEntry.textContent = log;
                    logContainer.appendChild(logEntry);
                }
            });
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        // 페이지 로드 시 상태 확인
        window.onload = function() {
            checkStatus();
        };
    </script>
</body>
</html>
