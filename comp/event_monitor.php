<?php
/**
 * 이벤트 모니터링 시스템
 * 업로드된 이벤트 순서에 따른 대기화면 및 결과 표시
 */

$currentEventIndex = isset($_GET['event']) ? (int)$_GET['event'] : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'waiting';

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이벤트 모니터링</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #000;
            color: #fff;
        }
        
        .screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: none;
        }
        
        .screen.active {
            display: block;
        }
        
        /* 대기화면 */
        .waiting-screen {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            animation: pulse 2s infinite;
        }
        
        .waiting-screen h1 {
            font-size: 4em;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .event-info {
            background: rgba(255,255,255,0.1);
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
            min-width: 600px;
        }
        
        .event-name {
            font-size: 2.5em;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .event-id {
            font-size: 1.5em;
            opacity: 0.8;
        }
        
        .event-status {
            margin-top: 20px;
            font-size: 1.2em;
        }
        
        .status-waiting {
            color: #ffeb3b;
        }
        
        .status-ready {
            color: #4caf50;
        }
        
        .next-events {
            margin-top: 30px;
            font-size: 1.2em;
            opacity: 0.7;
        }
        
        .next-events h3 {
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        
        .next-event-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
        }
        
        .next-event-item {
            background: rgba(255,255,255,0.1);
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.9em;
        }
        
        /* 결과 화면 */
        .results-screen {
            background: #f5f5f5;
            color: #333;
        }
        
        .results-content {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        
        /* 제어 패널 */
        .control-panel {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(0,0,0,0.8);
            padding: 15px;
            border-radius: 10px;
            display: flex;
            gap: 10px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .control-panel:hover {
            opacity: 1;
        }
        
        .control-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .control-btn:hover {
            background: #5a6fd8;
        }
        
        .control-btn.active {
            background: #28a745;
        }
        
        .control-btn.danger {
            background: #dc3545;
        }
        
        /* 상태 표시 */
        .status-bar {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 14px;
        }
        
        /* 진행률 표시 */
        .progress-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.2);
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }
        
        /* 애니메이션 */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        /* 반응형 */
        @media (max-width: 768px) {
            .waiting-screen h1 { font-size: 2.5em; }
            .event-name { font-size: 1.8em; }
            .event-id { font-size: 1.2em; }
            .event-info { min-width: 90%; }
        }
    </style>
</head>
<body>
    <!-- 대기화면 -->
    <div id="waitingScreen" class="screen waiting-screen">
        <h1>🏆 댄스스포츠 대회</h1>
        
        <div class="event-info" id="eventInfo">
            <div class="event-name" id="currentEventName">이벤트를 불러오는 중...</div>
            <div class="event-id" id="currentEventId">이벤트 ID: -</div>
            <div class="event-status" id="eventStatus">
                <span class="status-waiting">결과 대기 중...</span>
            </div>
        </div>
        
        <div class="next-events" id="nextEvents">
            <h3>다음 이벤트</h3>
            <div class="next-event-list" id="nextEventList">
                <!-- 동적으로 생성 -->
            </div>
        </div>
    </div>
    
    <!-- 결과 화면 -->
    <div id="resultsScreen" class="screen results-screen">
        <iframe src="" class="results-content" id="resultFrame"></iframe>
    </div>
    
    <!-- 제어 패널 -->
    <div class="control-panel" id="controlPanel">
        <button class="control-btn" onclick="showMode('waiting')">⏳ 대기화면</button>
        <button class="control-btn" onclick="showMode('results')">📊 결과</button>
        <button class="control-btn" onclick="previousEvent()">⬅️ 이전</button>
        <button class="control-btn" onclick="nextEvent()">다음 ➡️</button>
        <button class="control-btn" onclick="checkResult()">🔍 결과확인</button>
        <button class="control-btn danger" onclick="exitFullscreen()">❌ 종료</button>
    </div>
    
    <!-- 상태 표시 -->
    <div class="status-bar" id="statusBar">
        현재 이벤트: <span id="currentEventIndex"><?php echo $currentEventIndex; ?></span> | 
        모드: <span id="currentMode"><?php echo $mode; ?></span> | 
        총 이벤트: <span id="totalEvents">-</span>
    </div>
    
    <!-- 진행률 표시 -->
    <div class="progress-bar">
        <div class="progress-fill" id="progressFill" style="width: 0%"></div>
    </div>

    <script>
        let currentEventIndex = <?php echo $currentEventIndex; ?>;
        let currentMode = '<?php echo $mode; ?>';
        let events = [];
        let resultCheckInterval;
        
        // 이벤트 데이터 로드
        async function loadEvents() {
            try {
                const response = await fetch('get_event_schedule.php');
                const data = await response.json();
                
                if (data.success) {
                    events = data.events;
                    document.getElementById('totalEvents').textContent = events.length;
                    updateCurrentEvent();
                    updateNextEvents();
                } else {
                    console.error('이벤트 로드 실패:', data.message);
                }
            } catch (error) {
                console.error('이벤트 로드 오류:', error);
            }
        }
        
        // 현재 이벤트 업데이트
        function updateCurrentEvent() {
            if (events.length > 0 && currentEventIndex < events.length) {
                const event = events[currentEventIndex];
                document.getElementById('currentEventName').textContent = event.name;
                document.getElementById('currentEventId').textContent = `이벤트 ID: ${event.id}`;
                document.getElementById('currentEventIndex').textContent = currentEventIndex + 1;
                
                // 상태 업데이트
                const statusElement = document.getElementById('eventStatus');
                if (event.show_result) {
                    statusElement.innerHTML = '<span class="status-ready">결과 발표 예정</span>';
                } else {
                    statusElement.innerHTML = '<span class="status-waiting">결과 발표 안함</span>';
                }
            }
        }
        
        // 다음 이벤트들 업데이트
        function updateNextEvents() {
            const nextEventList = document.getElementById('nextEventList');
            nextEventList.innerHTML = '';
            
            // 다음 3개 이벤트 표시
            for (let i = 1; i <= 3; i++) {
                const nextIndex = currentEventIndex + i;
                if (nextIndex < events.length) {
                    const event = events[nextIndex];
                    const eventItem = document.createElement('div');
                    eventItem.className = 'next-event-item';
                    eventItem.textContent = `${event.id}: ${event.name}`;
                    nextEventList.appendChild(eventItem);
                }
            }
        }
        
        // 모드 전환
        function showMode(mode) {
            document.querySelectorAll('.screen').forEach(screen => {
                screen.classList.remove('active');
            });
            
            currentMode = mode;
            document.getElementById('currentMode').textContent = mode;
            
            switch(mode) {
                case 'waiting':
                    document.getElementById('waitingScreen').classList.add('active');
                    break;
                case 'results':
                    document.getElementById('resultsScreen').classList.add('active');
                    loadCurrentResult();
                    break;
            }
        }
        
        // 현재 결과 로드
        function loadCurrentResult() {
            if (events.length > 0 && currentEventIndex < events.length) {
                const event = events[currentEventIndex];
                const iframe = document.getElementById('resultFrame');
                iframe.src = `combined_result_${event.id}.html`;
            }
        }
        
        // 이전 이벤트
        function previousEvent() {
            if (currentEventIndex > 0) {
                currentEventIndex--;
                updateCurrentEvent();
                updateNextEvents();
                showMode('waiting');
            }
        }
        
        // 다음 이벤트
        function nextEvent() {
            if (currentEventIndex < events.length - 1) {
                currentEventIndex++;
                updateCurrentEvent();
                updateNextEvents();
                showMode('waiting');
            }
        }
        
        // 결과 확인
        function checkResult() {
            if (events.length > 0 && currentEventIndex < events.length) {
                const event = events[currentEventIndex];
                if (event.show_result) {
                    showMode('results');
                } else {
                    alert('이 이벤트는 결과 발표가 설정되지 않았습니다.');
                }
            }
        }
        
        // 결과 파일 존재 여부 확인
        async function checkResultFile() {
            if (events.length > 0 && currentEventIndex < events.length) {
                const event = events[currentEventIndex];
                
                try {
                    const response = await fetch(`check_result_file.php?event=${event.id}`);
                    const data = await response.json();
                    
                    if (data.exists && event.show_result) {
                        showMode('results');
                        startProgress();
                    }
                } catch (error) {
                    console.error('결과 파일 확인 오류:', error);
                }
            }
        }
        
        // 진행률 시작
        function startProgress() {
            let progress = 0;
            const duration = 10000; // 10초
            const increment = 100 / (duration / 100);
            
            const progressInterval = setInterval(() => {
                progress += increment;
                document.getElementById('progressFill').style.width = progress + '%';
                
                if (progress >= 100) {
                    clearInterval(progressInterval);
                    nextEvent();
                }
            }, 100);
        }
        
        // 전체화면 종료
        function exitFullscreen() {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
        
        // 키보드 단축키
        document.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'F11':
                    e.preventDefault();
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen();
                    } else {
                        exitFullscreen();
                    }
                    break;
                case 'ArrowLeft':
                    previousEvent();
                    break;
                case 'ArrowRight':
                    nextEvent();
                    break;
                case ' ':
                    e.preventDefault();
                    checkResult();
                    break;
                case '1':
                    showMode('waiting');
                    break;
                case '2':
                    showMode('results');
                    break;
                case 'Escape':
                    exitFullscreen();
                    break;
            }
        });
        
        // 초기화
        document.addEventListener('DOMContentLoaded', function() {
            loadEvents();
            showMode(currentMode);
            
            // 3초 후 제어 패널 숨기기
            setTimeout(() => {
                document.getElementById('controlPanel').style.opacity = '0';
            }, 3000);
            
            // 5초마다 결과 파일 확인
            resultCheckInterval = setInterval(checkResultFile, 5000);
        });
        
        // 마우스 움직임 감지로 제어 패널 표시
        let mouseTimer;
        document.addEventListener('mousemove', function() {
            document.getElementById('controlPanel').style.opacity = '1';
            clearTimeout(mouseTimer);
            mouseTimer = setTimeout(() => {
                document.getElementById('controlPanel').style.opacity = '0';
            }, 3000);
        });
    </script>
</body>
</html>




