<?php
/**
 * 스마트 전체화면 모니터링 시스템
 * 대회 순서에 따른 자동 전환 및 대기화면 표시
 */

require_once 'event_schedule_manager.php';
require_once 'result_monitor.php';

$scheduleManager = new EventScheduleManager();
$monitor = new ResultMonitor();

$currentEvent = isset($_GET['event']) ? $_GET['event'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'waiting';
$autoRotate = isset($_GET['auto']) ? $_GET['auto'] : 'true';

// 현재 이벤트 정보 가져오기
$currentEventInfo = $scheduleManager->getCurrentEvent($currentEvent);
$nextEventInfo = $scheduleManager->getNextEvent($currentEvent);
$schedule = $scheduleManager->getSchedule();

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>스마트 전체화면 모니터링</title>
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
        
        .waiting-screen .event-info {
            background: rgba(255,255,255,0.1);
            padding: 30px;
            border-radius: 15px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }
        
        .waiting-screen .event-name {
            font-size: 2.5em;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .waiting-screen .event-id {
            font-size: 1.5em;
            opacity: 0.8;
        }
        
        .waiting-screen .next-event {
            margin-top: 30px;
            font-size: 1.2em;
            opacity: 0.7;
        }
        
        .event-status {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .status-item:last-child {
            margin-bottom: 0;
        }
        
        .status-label {
            font-weight: bold;
            opacity: 0.8;
        }
        
        .status-value {
            color: #ffeb3b;
            font-weight: bold;
        }
        
        /* 로고 화면 */
        .logo-screen {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .logo-screen img {
            max-width: 80%;
            max-height: 60%;
            margin-bottom: 30px;
        }
        
        .logo-screen h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }
        
        .logo-screen p {
            font-size: 1.5em;
            opacity: 0.9;
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
            .waiting-screen .event-name { font-size: 1.8em; }
            .waiting-screen .event-id { font-size: 1.2em; }
            .logo-screen h1 { font-size: 2em; }
            .logo-screen p { font-size: 1.2em; }
        }
    </style>
</head>
<body>
    <!-- 대기화면 -->
    <div id="waitingScreen" class="screen waiting-screen">
        <h1>🏆 2025 경기도지사배 전국장애인댄스스포츠선수권대회</h1>
        
        <?php if ($currentEventInfo): ?>
        <div class="event-info">
            <div class="event-name"><?php echo htmlspecialchars($currentEventInfo['event_name']); ?></div>
            <div class="event-id">이벤트 ID: <?php echo htmlspecialchars($currentEventInfo['event_id']); ?></div>
        </div>
        <?php endif; ?>
        
        <p>결과를 기다려주세요...</p>
        
        <div class="event-status" id="eventStatus">
            <div class="status-item">
                <span class="status-label">현재 상태:</span>
                <span class="status-value" id="currentStatus">대기 중</span>
            </div>
            <div class="status-item">
                <span class="status-label">결과 파일:</span>
                <span class="status-value" id="fileStatus">확인 중...</span>
            </div>
        </div>
        
        <?php if ($nextEventInfo): ?>
        <div class="next-event">
            다음: <?php echo htmlspecialchars($nextEventInfo['event_name']); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- 로고 화면 -->
    <div id="logoScreen" class="screen logo-screen">
        <img src="assets/danceoffice-logo.png" alt="DanceOffice 로고" onerror="this.style.display='none'">
        <h1>DanceOffice</h1>
        <p>댄스스포츠 관리 시스템</p>
    </div>
    
    <!-- 결과 화면 -->
    <div id="resultsScreen" class="screen results-screen">
        <?php if ($currentEvent && $mode === 'results'): ?>
            <iframe src="combined_result_<?php echo htmlspecialchars($currentEvent); ?>.html" 
                    class="results-content" id="resultFrame"></iframe>
        <?php else: ?>
            <div style="display: flex; justify-content: center; align-items: center; height: 100vh; font-size: 2em; color: #666;">
                결과를 불러오는 중...
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 제어 패널 -->
    <div class="control-panel" id="controlPanel">
        <button class="control-btn" onclick="showMode('waiting')">⏳ 대기화면</button>
        <button class="control-btn" onclick="showMode('logo')">🏢 로고</button>
        <button class="control-btn" onclick="showMode('results')">📊 결과</button>
        <button class="control-btn" onclick="toggleAutoRotate()">🔄 자동순환</button>
        <button class="control-btn" onclick="previousEvent()">⬅️ 이전</button>
        <button class="control-btn" onclick="nextEvent()">다음 ➡️</button>
        <button class="control-btn danger" onclick="exitFullscreen()">❌ 종료</button>
    </div>
    
    <!-- 상태 표시 -->
    <div class="status-bar" id="statusBar">
        현재 모드: <span id="currentMode"><?php echo $mode; ?></span> | 
        이벤트: <span id="currentEvent"><?php echo $currentEvent; ?></span> | 
        자동순환: <span id="autoStatus"><?php echo $autoRotate === 'true' ? 'ON' : 'OFF'; ?></span>
    </div>
    
    <!-- 진행률 표시 -->
    <div class="progress-bar">
        <div class="progress-fill" id="progressFill" style="width: 0%"></div>
    </div>

    <script>
        let currentMode = '<?php echo $mode; ?>';
        let currentEvent = '<?php echo $currentEvent; ?>';
        let autoRotate = <?php echo $autoRotate === 'true' ? 'true' : 'false'; ?>;
        let eventIndex = <?php echo $scheduleManager->getCurrentEventIndex($currentEvent); ?>;
        let events = <?php echo json_encode(array_column($schedule, 'event_id')); ?>;
        let eventNames = <?php echo json_encode(array_column($schedule, 'event_name')); ?>;
        let showResults = <?php echo json_encode(array_column($schedule, 'show_result')); ?>;
        let rotationInterval;
        let progressInterval;
        
        // 모드 전환 함수
        function showMode(mode) {
            // 모든 화면 숨기기
            document.querySelectorAll('.screen').forEach(screen => {
                screen.classList.remove('active');
            });
            
            // 선택된 모드 표시
            currentMode = mode;
            document.getElementById('currentMode').textContent = mode;
            
            switch(mode) {
                case 'waiting':
                    document.getElementById('waitingScreen').classList.add('active');
                    break;
                case 'logo':
                    document.getElementById('logoScreen').classList.add('active');
                    break;
                case 'results':
                    document.getElementById('resultsScreen').classList.add('active');
                    loadCurrentEvent();
                    break;
            }
            
            // 제어 버튼 활성화 상태 업데이트
            updateControlButtons();
        }
        
        // 이벤트 로드 함수
        function loadCurrentEvent() {
            if (events.length > 0 && eventIndex >= 0 && eventIndex < events.length) {
                currentEvent = events[eventIndex];
                document.getElementById('currentEvent').textContent = currentEvent;
                
                const iframe = document.getElementById('resultFrame');
                if (iframe) {
                    iframe.src = `combined_result_${currentEvent}.html`;
                }
            }
        }
        
        // 이전 이벤트
        function previousEvent() {
            if (events.length > 0) {
                eventIndex = (eventIndex - 1 + events.length) % events.length;
                loadCurrentEvent();
            }
        }
        
        // 다음 이벤트
        function nextEvent() {
            if (events.length > 0) {
                eventIndex = (eventIndex + 1) % events.length;
                loadCurrentEvent();
            }
        }
        
        // 자동 순환 토글
        function toggleAutoRotate() {
            autoRotate = !autoRotate;
            document.getElementById('autoStatus').textContent = autoRotate ? 'ON' : 'OFF';
            
            if (autoRotate) {
                startAutoRotation();
            } else {
                stopAutoRotation();
            }
        }
        
        // 자동 순환 시작
        function startAutoRotation() {
            if (rotationInterval) clearInterval(rotationInterval);
            
            rotationInterval = setInterval(() => {
                if (currentMode === 'waiting' && events.length > 0) {
                    // 대기화면에서 결과가 있는 이벤트로 자동 전환
                    if (showResults[eventIndex]) {
                        checkResultFileAndShow();
                    } else {
                        nextEvent();
                    }
                } else if (currentMode === 'results') {
                    // 결과 화면에서 다음 이벤트로 전환
                    nextEvent();
                    if (showResults[eventIndex]) {
                        checkResultFileAndShow();
                    } else {
                        showMode('waiting');
                    }
                }
            }, 10000); // 10초마다 순환
        }
        
        // 결과 파일 존재 여부 확인 후 표시
        function checkResultFileAndShow() {
            if (events.length > 0 && eventIndex >= 0 && eventIndex < events.length) {
                const eventId = events[eventIndex];
                
                // 상태 업데이트
                updateEventStatus('결과 파일 확인 중...', '확인 중...');
                
                // 결과 파일 존재 여부 확인
                fetch(`check_result_file.php?event=${eventId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            updateEventStatus('결과 준비 완료', '존재함');
                            showMode('results');
                            startProgress();
                        } else {
                            // 결과 파일이 없으면 대기화면 유지
                            updateEventStatus('결과 대기 중', '없음');
                            console.log(`결과 파일이 없습니다: ${eventId}`);
                            showMode('waiting');
                        }
                    })
                    .catch(error => {
                        console.error('결과 파일 확인 오류:', error);
                        updateEventStatus('오류 발생', '확인 실패');
                        showMode('waiting');
                    });
            }
        }
        
        // 이벤트 상태 업데이트
        function updateEventStatus(currentStatus, fileStatus) {
            const currentStatusElement = document.getElementById('currentStatus');
            const fileStatusElement = document.getElementById('fileStatus');
            
            if (currentStatusElement) {
                currentStatusElement.textContent = currentStatus;
            }
            if (fileStatusElement) {
                fileStatusElement.textContent = fileStatus;
            }
        }
        
        // 자동 순환 중지
        function stopAutoRotation() {
            if (rotationInterval) {
                clearInterval(rotationInterval);
                rotationInterval = null;
            }
            stopProgress();
        }
        
        // 진행률 시작
        function startProgress() {
            if (progressInterval) clearInterval(progressInterval);
            
            let progress = 0;
            const duration = 10000; // 10초
            const increment = 100 / (duration / 100);
            
            progressInterval = setInterval(() => {
                progress += increment;
                document.getElementById('progressFill').style.width = progress + '%';
                
                if (progress >= 100) {
                    clearInterval(progressInterval);
                }
            }, 100);
        }
        
        // 진행률 중지
        function stopProgress() {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
            document.getElementById('progressFill').style.width = '0%';
        }
        
        // 제어 버튼 상태 업데이트
        function updateControlButtons() {
            const buttons = document.querySelectorAll('.control-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // 현재 모드 버튼 활성화
            const modeButtons = {
                'waiting': buttons[0],
                'logo': buttons[1],
                'results': buttons[2]
            };
            
            if (modeButtons[currentMode]) {
                modeButtons[currentMode].classList.add('active');
            }
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
                    toggleAutoRotate();
                    break;
                case '1':
                    showMode('waiting');
                    break;
                case '2':
                    showMode('logo');
                    break;
                case '3':
                    showMode('results');
                    break;
                case 'Escape':
                    exitFullscreen();
                    break;
            }
        });
        
        // 초기화
        document.addEventListener('DOMContentLoaded', function() {
            showMode(currentMode);
            
            if (autoRotate) {
                startAutoRotation();
            }
            
            // 3초 후 제어 패널 숨기기
            setTimeout(() => {
                document.getElementById('controlPanel').style.opacity = '0';
            }, 3000);
            
            // 결과 파일 모니터링 시작 (5초마다 확인)
            startResultFileMonitoring();
        });
        
        // 결과 파일 모니터링
        function startResultFileMonitoring() {
            setInterval(() => {
                if (currentMode === 'waiting' && events.length > 0) {
                    const eventId = events[eventIndex];
                    if (showResults[eventIndex]) {
                        checkResultFileAndShow();
                    }
                }
            }, 5000); // 5초마다 확인
        }
        
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
