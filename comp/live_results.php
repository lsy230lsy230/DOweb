<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>실시간 경기결과 - 제12회 서초구청장배 댄스스포츠 대회</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        
        .header .subtitle {
            margin: 10px 0 0 0;
            font-size: 1.2em;
            opacity: 0.8;
        }
        
        .status-bar {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .refresh-info {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .content {
            padding: 30px;
        }
        
        .event-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #2196f3;
        }
        
        .event-title {
            font-size: 1.8em;
            font-weight: bold;
            color: #1976d2;
            margin: 0 0 10px 0;
        }
        
        .event-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .detail-label {
            font-weight: bold;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1.2em;
            color: #333;
        }
        
        .results-section {
            margin-top: 30px;
        }
        
        .section-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .player-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .player-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .player-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #28a745, #20c997);
        }
        
        .rank {
            font-size: 2em;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 10px;
        }
        
        .player-number {
            font-size: 1.1em;
            color: #666;
            margin-bottom: 5px;
        }
        
        .player-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .recall-count {
            background: #e8f5e8;
            color: #155724;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            display: inline-block;
        }
        
        .loading {
            text-align: center;
            padding: 50px;
            color: #6c757d;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
            margin: 20px 0;
        }
        
        .no-data {
            text-align: center;
            padding: 50px;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏆 실시간 경기결과</h1>
            <div class="subtitle">제12회 서초구청장배 댄스스포츠 대회</div>
        </div>
        
        <div class="status-bar">
            <div class="status-indicator">
                <div class="status-dot"></div>
                <span id="statusText">실시간 결과를 로딩 중입니다...</span>
            </div>
            <div class="refresh-info">
                <span id="lastUpdate">마지막 업데이트: -</span> | 
                <span>30초마다 자동 갱신</span>
            </div>
        </div>
        
        <div class="content">
            <div id="eventInfo" class="event-info" style="display: none;">
                <div class="event-title" id="eventTitle"></div>
                <div class="event-details">
                    <div class="detail-item">
                        <div class="detail-label">라운드</div>
                        <div class="detail-value" id="eventRound"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">진출자 수</div>
                        <div class="detail-value" id="recallCount"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">총 참가자</div>
                        <div class="detail-value" id="totalParticipants"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">파일 생성</div>
                        <div class="detail-value" id="fileCreated"></div>
                    </div>
                </div>
            </div>
            
            <div class="results-section">
                <div class="section-title">
                    🏅 진출자 명단
                </div>
                <div id="resultsContainer">
                    <div class="loading">결과를 불러오는 중...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let eventId = '30'; // 기본 이벤트 ID
        let refreshInterval;
        
        // URL에서 이벤트 ID 가져오기
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('event_id')) {
            eventId = urlParams.get('event_id');
        }
        
        function updateStatus(text) {
            document.getElementById('statusText').textContent = text;
        }
        
        function updateLastUpdate() {
            const now = new Date();
            document.getElementById('lastUpdate').textContent = 
                `마지막 업데이트: ${now.toLocaleTimeString()}`;
        }
        
        function loadResults() {
            fetch('watch_scoring_files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event_id: eventId,
                    comp_id: '20250913-001'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatus('실시간 결과 업데이트 중...');
                    displayResults(data);
                    updateLastUpdate();
                } else {
                    updateStatus('결과를 찾을 수 없습니다.');
                    showError(data.error || '알 수 없는 오류가 발생했습니다.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                updateStatus('연결 오류');
                showError('서버와의 연결에 문제가 있습니다.');
            });
        }
        
        function displayResults(data) {
            // 이벤트 정보 표시
            document.getElementById('eventTitle').textContent = data.event_name;
            document.getElementById('eventRound').textContent = data.round;
            document.getElementById('recallCount').textContent = `${data.recall_count}명`;
            document.getElementById('totalParticipants').textContent = `${data.total_participants}명`;
            document.getElementById('fileCreated').textContent = data.file_created;
            document.getElementById('eventInfo').style.display = 'block';
            
            // 진출자 결과 표시
            const container = document.getElementById('resultsContainer');
            
            if (data.advancing_players && data.advancing_players.length > 0) {
                container.innerHTML = `
                    <div class="results-grid">
                        ${data.advancing_players.map(player => `
                            <div class="player-card">
                                <div class="rank">${player.rank}위</div>
                                <div class="player-number">등번호: ${player.player_number}</div>
                                <div class="player-name">${player.player_name}</div>
                                <div class="recall-count">리콜 ${player.recall_count}개</div>
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                container.innerHTML = '<div class="no-data">진출자 데이터가 없습니다.</div>';
            }
        }
        
        function showError(message) {
            document.getElementById('resultsContainer').innerHTML = 
                `<div class="error">오류: ${message}</div>`;
        }
        
        // 초기 로드
        loadResults();
        
        // 30초마다 자동 갱신
        refreshInterval = setInterval(loadResults, 30000);
        
        // 페이지 언로드 시 인터벌 정리
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>
