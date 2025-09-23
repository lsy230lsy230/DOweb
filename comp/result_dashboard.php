<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>댄스스코어 결과 모니터링 대시보드</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 3em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease;
        }
        
        .panel:hover {
            transform: translateY(-5px);
        }
        
        .panel h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .status-online {
            background: #4CAF50;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
        }
        
        .status-offline {
            background: #f44336;
            box-shadow: 0 0 10px rgba(244, 67, 54, 0.5);
        }
        
        .file-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }
        
        .file-item:hover {
            background: #e9ecef;
        }
        
        .file-name {
            font-weight: bold;
            color: #333;
        }
        
        .file-time {
            font-size: 0.9em;
            color: #666;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            margin: 5px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }
        
        .controls {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auto-refresh {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 15px;
        }
        
        .auto-refresh input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }
        
        .auto-refresh label {
            color: white;
            font-weight: bold;
        }
        
        .log-container {
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .log-entry {
            margin-bottom: 5px;
            padding: 2px 0;
        }
        
        .log-time {
            color: #888;
        }
        
        .log-info {
            color: #00ff00;
        }
        
        .log-warning {
            color: #ffaa00;
        }
        
        .log-error {
            color: #ff4444;
        }
        
        .fullscreen-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 15px;
            width: 90%;
            max-width: 1200px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏆 댄스스코어 결과 모니터링</h1>
            <p>실시간 결과 파일 모니터링 및 종합 결과 생성</p>
        </div>
        
        <div class="controls">
            <button class="btn" onclick="checkNewFiles()">🔍 새 파일 확인</button>
            <button class="btn btn-success" onclick="processAllResults()">⚡ 모든 결과 처리</button>
            <button class="btn btn-warning" onclick="showFileList()">📁 파일 목록</button>
            <button class="btn btn-danger" onclick="clearLog()">🗑️ 로그 지우기</button>
            
            <div class="auto-refresh">
                <input type="checkbox" id="autoRefresh" onchange="toggleAutoRefresh()">
                <label for="autoRefresh">자동 새로고침 (30초)</label>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="totalFiles">0</div>
                <div class="stat-label">총 파일 수</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="processedFiles">0</div>
                <div class="stat-label">처리된 파일</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="newFiles">0</div>
                <div class="stat-label">새 파일</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="lastUpdate">-</div>
                <div class="stat-label">마지막 업데이트</div>
            </div>
        </div>
        
        <div class="dashboard">
            <div class="panel">
                <h2>📊 모니터링 상태</h2>
                <p><span class="status-indicator status-online" id="monitorStatus"></span>모니터링 활성</p>
                <p><span class="status-indicator status-offline" id="processStatus"></span>처리 상태</p>
                
                <div class="file-list" id="recentFiles">
                    <div class="file-item">
                        <span class="file-name">시스템 초기화 중...</span>
                        <span class="file-time">-</span>
                    </div>
                </div>
            </div>
            
            <div class="panel">
                <h2>📝 처리 로그</h2>
                <div class="log-container" id="logContainer">
                    <div class="log-entry">
                        <span class="log-time">[시작]</span>
                        <span class="log-info">시스템이 시작되었습니다.</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="fullscreen-btn">
            <button class="btn" onclick="openFullscreen()">🖥️ 전체화면</button>
        </div>
    </div>
    
    <!-- 모달 -->
    <div id="fileModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>📁 파일 목록</h2>
            <div id="fileListContent"></div>
        </div>
    </div>
    
    <script>
        let autoRefreshInterval;
        let isAutoRefresh = false;
        
        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            logMessage('시스템이 초기화되었습니다.', 'info');
            checkNewFiles();
            updateStats();
        });
        
        // 새 파일 확인
        async function checkNewFiles() {
            try {
                const response = await fetch('result_monitor.php?action=check');
                const data = await response.json();
                
                if (data.success) {
                    const newFiles = data.new_files || [];
                    updateNewFilesCount(newFiles.length);
                    
                    if (newFiles.length > 0) {
                        logMessage(`${newFiles.length}개의 새 파일이 발견되었습니다.`, 'warning');
                        updateRecentFiles(newFiles);
                    } else {
                        logMessage('새 파일이 없습니다.', 'info');
                    }
                } else {
                    logMessage('파일 확인 중 오류가 발생했습니다.', 'error');
                }
            } catch (error) {
                logMessage(`오류: ${error.message}`, 'error');
            }
        }
        
        // 모든 결과 처리
        async function processAllResults() {
            try {
                logMessage('결과 처리를 시작합니다...', 'info');
                setProcessStatus(true);
                
                const response = await fetch('result_monitor.php?action=process');
                const data = await response.json();
                
                if (data.success) {
                    const processed = data.processed || [];
                    updateProcessedFilesCount(processed.length);
                    
                    logMessage(`${processed.length}개의 결과가 처리되었습니다.`, 'info');
                    
                    processed.forEach(result => {
                        logMessage(`처리 완료: ${result.event_title} (${result.event_id})`, 'info');
                    });
                } else {
                    logMessage('결과 처리 중 오류가 발생했습니다.', 'error');
                }
            } catch (error) {
                logMessage(`오류: ${error.message}`, 'error');
            } finally {
                setProcessStatus(false);
            }
        }
        
        // 파일 목록 표시
        async function showFileList() {
            try {
                const response = await fetch('result_monitor.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    const pairs = data.pairs || [];
                    displayFileList(pairs);
                    document.getElementById('fileModal').style.display = 'block';
                } else {
                    logMessage('파일 목록을 가져올 수 없습니다.', 'error');
                }
            } catch (error) {
                logMessage(`오류: ${error.message}`, 'error');
            }
        }
        
        // 파일 목록 표시
        function displayFileList(pairs) {
            const content = document.getElementById('fileListContent');
            content.innerHTML = '';
            
            if (pairs.length === 0) {
                content.innerHTML = '<p>처리할 파일이 없습니다.</p>';
                return;
            }
            
            pairs.forEach(pair => {
                const div = document.createElement('div');
                div.className = 'file-item';
                div.innerHTML = `
                    <div>
                        <div class="file-name">${pair.event_id}</div>
                        <div style="font-size: 0.8em; color: #666;">
                            요약: ${pair.summary.split('/').pop()}<br>
                            상세: ${pair.detailed.split('/').pop()}
                        </div>
                    </div>
                    <div>
                        <button class="btn" onclick="viewResult('${pair.event_id}')">보기</button>
                    </div>
                `;
                content.appendChild(div);
            });
        }
        
        // 결과 보기
        function viewResult(eventId) {
            window.open(`combined_result_${eventId}.html`, '_blank');
        }
        
        // 모달 닫기
        function closeModal() {
            document.getElementById('fileModal').style.display = 'none';
        }
        
        // 전체화면 열기
        function openFullscreen() {
            const pairs = getFilePairs();
            if (pairs.length > 0) {
                const latestPair = pairs[pairs.length - 1];
                window.open(`combined_result_${latestPair.event_id}.html`, '_blank');
            } else {
                alert('표시할 결과가 없습니다.');
            }
        }
        
        // 자동 새로고침 토글
        function toggleAutoRefresh() {
            isAutoRefresh = document.getElementById('autoRefresh').checked;
            
            if (isAutoRefresh) {
                autoRefreshInterval = setInterval(() => {
                    checkNewFiles();
                    updateStats();
                }, 30000);
                logMessage('자동 새로고침이 활성화되었습니다.', 'info');
            } else {
                clearInterval(autoRefreshInterval);
                logMessage('자동 새로고침이 비활성화되었습니다.', 'info');
            }
        }
        
        // 로그 메시지 추가
        function logMessage(message, type = 'info') {
            const logContainer = document.getElementById('logContainer');
            const time = new Date().toLocaleTimeString();
            
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry log-${type}`;
            logEntry.innerHTML = `<span class="log-time">[${time}]</span> <span class="log-${type}">${message}</span>`;
            
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
            
            // 최대 100개 로그 유지
            while (logContainer.children.length > 100) {
                logContainer.removeChild(logContainer.firstChild);
            }
        }
        
        // 로그 지우기
        function clearLog() {
            document.getElementById('logContainer').innerHTML = '';
            logMessage('로그가 지워졌습니다.', 'info');
        }
        
        // 처리 상태 업데이트
        function setProcessStatus(processing) {
            const status = document.getElementById('processStatus');
            if (processing) {
                status.className = 'status-indicator status-online';
                status.nextSibling.textContent = '처리 중...';
            } else {
                status.className = 'status-indicator status-offline';
                status.nextSibling.textContent = '대기 중';
            }
        }
        
        // 통계 업데이트
        async function updateStats() {
            try {
                const response = await fetch('result_monitor.php?action=list');
                const data = await response.json();
                
                if (data.success) {
                    const pairs = data.pairs || [];
                    document.getElementById('totalFiles').textContent = pairs.length * 2; // 요약 + 상세
                    document.getElementById('processedFiles').textContent = pairs.length;
                    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
                }
            } catch (error) {
                console.error('통계 업데이트 오류:', error);
            }
        }
        
        // 새 파일 수 업데이트
        function updateNewFilesCount(count) {
            document.getElementById('newFiles').textContent = count;
        }
        
        // 처리된 파일 수 업데이트
        function updateProcessedFilesCount(count) {
            document.getElementById('processedFiles').textContent = count;
        }
        
        // 최근 파일 목록 업데이트
        function updateRecentFiles(files) {
            const container = document.getElementById('recentFiles');
            container.innerHTML = '';
            
            files.slice(0, 10).forEach(file => {
                const div = document.createElement('div');
                div.className = 'file-item';
                div.innerHTML = `
                    <span class="file-name">${file.split('/').pop()}</span>
                    <span class="file-time">${new Date().toLocaleTimeString()}</span>
                `;
                container.appendChild(div);
            });
        }
        
        // 파일 쌍 가져오기 (임시)
        function getFilePairs() {
            // 실제로는 서버에서 가져와야 함
            return [];
        }
        
        // 모달 외부 클릭 시 닫기
        window.onclick = function(event) {
            const modal = document.getElementById('fileModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>




