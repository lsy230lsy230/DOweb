<?php
/**
 * 이벤트 모니터링 시스템 v2
 * 그룹별 이벤트 표시 및 대회 제목 설정
 */

require_once 'event_group_manager.php';

$currentGroupIndex = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'waiting';

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이벤트 모니터링 v2</title>
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
            padding: 20px;
        }
        
        .competition-title {
            font-size: 4em;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            font-weight: bold;
        }
        
        .competition-subtitle {
            font-size: 2em;
            margin-bottom: 50px;
            opacity: 0.9;
        }
        
        .event-group {
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            min-width: 600px;
            max-width: 800px;
            text-align: center;
            margin: 0 auto;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        .group-title {
            font-size: 3.5em;
            margin-bottom: 30px;
            font-weight: bold;
            color: #ffeb3b;
            text-align: center;
        }
        
        .event-list {
            list-style: none;
            padding: 0;
            text-align: center;
        }
        
        .event-item {
            font-size: 2.5em;
            margin-bottom: 25px;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            text-align: center;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-name {
            color: #fff;
            font-weight: 500;
            display: block;
            text-align: center;
        }
        
        .next-groups {
            margin-top: 40px;
            font-size: 1.8em;
            opacity: 0.7;
            text-align: center;
            width: 100%;
        }
        
        .next-groups h3 {
            margin-bottom: 20px;
            font-size: 2.2em;
        }
        
        .next-group-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            align-items: center;
        }
        
        .next-group-item {
            background: rgba(255,255,255,0.1);
            padding: 15px 25px;
            border-radius: 10px;
            font-size: 1.4em;
            text-align: center;
        }
        
        /* 결과 화면 */
        .results-screen {
            background: #f5f5f5;
            color: #333;
            overflow: hidden;
        }
        
        .results-content {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
            transform: scale(0.8);
            transform-origin: top left;
            overflow: hidden;
        }
        
        /* 결과 화면 전체화면 최적화 */
        .results-screen.fullscreen-optimized .results-content {
            transform: scale(0.7);
            width: 142.86%; /* 100 / 0.7 */
            height: 142.86%; /* 100 / 0.7 */
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
            transition: opacity 0.3s;
        }
        
        .status-bar.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        /* 반응형 */
        @media (max-width: 768px) {
            .competition-title { font-size: 2.5em; }
            .competition-subtitle { font-size: 1.5em; }
            .group-title { font-size: 2.5em; }
            .event-item { font-size: 1.8em; }
            .next-groups { font-size: 1.4em; }
            .next-groups h3 { font-size: 1.8em; }
            .next-group-item { font-size: 1.2em; }
            .event-group { 
                min-width: 90%; 
                max-width: 95%;
                padding: 20px; 
                text-align: center;
                margin: 0 auto;
            }
            .event-list {
                text-align: center;
            }
            .event-name {
                text-align: center;
            }
            .next-groups {
                text-align: center;
            }
            .next-group-list {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- 대기화면 -->
    <div id="waitingScreen" class="screen waiting-screen">
        <div class="competition-title" id="competitionTitle">대회 제목을 불러오는 중...</div>
        <div class="competition-subtitle" id="competitionSubtitle">부제목을 불러오는 중...</div>
        
        <div class="event-group" id="eventGroup">
            <div class="group-title" id="groupTitle">이벤트 그룹을 불러오는 중...</div>
            <ul class="event-list" id="eventList">
                <!-- 동적으로 생성 -->
            </ul>
        </div>
        
        <div class="next-groups" id="nextGroups">
            <h3>다음 이벤트 그룹</h3>
            <div class="next-group-list" id="nextGroupList">
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
        <button class="control-btn" onclick="previousGroup()">⬅️ 이전 그룹</button>
        <button class="control-btn" onclick="nextGroup()">다음 그룹 ➡️</button>
        <button class="control-btn" onclick="checkResult()">🔍 결과확인</button>
        <button class="control-btn" onclick="toggleResultScale()">🔍 크기조절</button>
        <button class="control-btn" onclick="toggleAutoAdvance()">⚡ 자동전환</button>
        <button class="control-btn" onclick="toggleStatusBar()">📊 상태바</button>
        <button class="control-btn" onclick="hideControlPanel()">👁️ 숨기기</button>
        <button class="control-btn danger" onclick="exitFullscreen()">❌ 종료</button>
    </div>
    
    <!-- 상태 표시 -->
    <div class="status-bar" id="statusBar">
        현재 그룹: <span id="currentGroupIndex"><?php echo $currentGroupIndex; ?></span> | 
        모드: <span id="currentMode"><?php echo $mode; ?></span> | 
        총 그룹: <span id="totalGroups">-</span> | 
        자동전환: <span id="autoAdvanceStatus">ON</span> | 
        단축키: S(크기조절) F11(전체화면) A(자동전환) B(상태바) H(숨기기)
    </div>

    <script>
        let currentGroupIndex = <?php echo $currentGroupIndex; ?>;
        let currentMode = '<?php echo $mode; ?>';
        let events = [];
        let eventGroups = [];
        let competitionSettings = {};
        let autoAdvanceTime = 5000; // 자동 전환 시간 (밀리초)
        let autoAdvanceEnabled = true; // 자동 전환 활성화 상태
        let isAdvancing = false; // 자동 전환 중인지 확인
        let isFullscreen = false; // 전체화면 상태
        let statusBarHidden = false; // 상태 표시바 숨김 상태
        let advanceTimer = null; // 자동 전환 타이머
        let monitoringInterval = null; // 모니터링 인터벌
        
        // 대회 설정 로드
        async function loadCompetitionSettings() {
            try {
                const response = await fetch('get_competition_settings.php');
                const data = await response.json();
                
                if (data.success) {
                    competitionSettings = data.settings;
                    document.getElementById('competitionTitle').textContent = competitionSettings.competition_title;
                    document.getElementById('competitionSubtitle').textContent = competitionSettings.competition_subtitle;
                }
            } catch (error) {
                console.error('대회 설정 로드 오류:', error);
            }
        }
        
        // 이벤트 데이터 로드
        async function loadEvents() {
            try {
                const response = await fetch('get_event_schedule.php');
                const data = await response.json();
                
                if (data.success) {
                    events = data.events;
                    console.log('로드된 이벤트:', events);
                    eventGroups = groupEvents(events);
                    console.log('그룹화된 이벤트:', eventGroups);
                    document.getElementById('totalGroups').textContent = Object.keys(eventGroups).length;
                    updateCurrentGroup();
                    updateNextGroups();
                } else {
                    console.error('이벤트 로드 실패:', data.message);
                }
            } catch (error) {
                console.error('이벤트 로드 오류:', error);
            }
        }
        
        // 이벤트를 그룹별로 분류
        function groupEvents(events) {
            const groups = {};
            
            events.forEach(event => {
                // 이벤트 ID에서 숫자 부분 추출 (예: 1A -> 1, 2B -> 2)
                const eventNumber = event.id.replace(/[^0-9]/g, '');
                
                if (eventNumber && eventNumber !== '') {
                    if (!groups[eventNumber]) {
                        groups[eventNumber] = [];
                    }
                    groups[eventNumber].push(event);
                }
            });
            
            // 숫자 순으로 정렬
            const sortedGroups = {};
            Object.keys(groups).sort((a, b) => parseInt(a) - parseInt(b)).forEach(key => {
                sortedGroups[key] = groups[key];
            });
            
            return sortedGroups;
        }
        
        // 현재 그룹 업데이트
        function updateCurrentGroup() {
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            
            if (groupNumbers.length > 0 && currentGroupIndex < groupNumbers.length) {
                const currentGroupNumber = groupNumbers[currentGroupIndex];
                const currentGroupEvents = eventGroups[currentGroupNumber];
                
                document.getElementById('groupTitle').textContent = `이벤트 ${currentGroupNumber}`;
                document.getElementById('currentGroupIndex').textContent = currentGroupIndex + 1;
                
                // 이벤트 목록 업데이트
                const eventList = document.getElementById('eventList');
                eventList.innerHTML = '';
                
                currentGroupEvents.forEach(event => {
                    const eventItem = document.createElement('li');
                    eventItem.className = 'event-item';
                    eventItem.innerHTML = `
                        <span class="event-name">${event.name}</span>
                    `;
                    eventList.appendChild(eventItem);
                });
            }
        }
        
        // 다음 그룹들 업데이트
        function updateNextGroups() {
            const nextGroupList = document.getElementById('nextGroupList');
            nextGroupList.innerHTML = '';
            
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            
            // 다음 3개 그룹 표시
            for (let i = 1; i <= 3; i++) {
                const nextIndex = currentGroupIndex + i;
                if (nextIndex < groupNumbers.length) {
                    const groupNumber = groupNumbers[nextIndex];
                    const groupEvents = eventGroups[groupNumber];
                    
                    const groupItem = document.createElement('div');
                    groupItem.className = 'next-group-item';
                    groupItem.textContent = `이벤트 ${groupNumber} (${groupEvents.length}개)`;
                    nextGroupList.appendChild(groupItem);
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
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            
            if (groupNumbers.length > 0 && currentGroupIndex < groupNumbers.length) {
                const currentGroupNumber = groupNumbers[currentGroupIndex];
                const currentGroupEvents = eventGroups[currentGroupNumber];
                
                // 첫 번째 이벤트의 결과 로드
                if (currentGroupEvents.length > 0) {
                    const firstEvent = currentGroupEvents[0];
                    const iframe = document.getElementById('resultFrame');
                    iframe.src = `combined_result_${firstEvent.id}.html`;
                }
            }
        }
        
        // 이전 그룹
        function previousGroup() {
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            
            if (currentGroupIndex > 0) {
                // 기존 타이머 취소
                if (advanceTimer) {
                    clearTimeout(advanceTimer);
                    advanceTimer = null;
                }
                
                // 기존 모니터링 완전 중단
                stopResultFileMonitoring();
                
                currentGroupIndex--;
                updateCurrentGroup();
                updateNextGroups();
                showMode('waiting');
                isAdvancing = false; // 수동 전환 시에도 플래그 리셋
                
                // 잠시 대기 후 새로운 이벤트 모니터링 시작
                setTimeout(() => {
                    startResultFileMonitoring();
                }, 1000);
            }
        }
        
        // 다음 그룹
        function nextGroup() {
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            
            if (currentGroupIndex < groupNumbers.length - 1) {
                // 기존 타이머 취소
                if (advanceTimer) {
                    clearTimeout(advanceTimer);
                    advanceTimer = null;
                }
                
                // 기존 모니터링 완전 중단
                stopResultFileMonitoring();
                
                currentGroupIndex++;
                updateCurrentGroup();
                updateNextGroups();
                showMode('waiting');
                isAdvancing = false; // 수동 전환 시에도 플래그 리셋
                
                // 잠시 대기 후 새로운 이벤트 모니터링 시작
                setTimeout(() => {
                    startResultFileMonitoring();
                }, 1000);
            }
        }
        
        // 결과 확인
        function checkResult() {
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            
            if (groupNumbers.length > 0 && currentGroupIndex < groupNumbers.length) {
                const currentGroupNumber = groupNumbers[currentGroupIndex];
                const currentGroupEvents = eventGroups[currentGroupNumber];
                
                // 그룹 내에서 결과 발표가 설정된 이벤트가 있는지 확인
                const hasResultEvent = currentGroupEvents.some(event => event.show_result);
                
                if (hasResultEvent) {
                    showMode('results');
                } else {
                    alert('이 그룹에는 결과 발표가 설정된 이벤트가 없습니다.');
                }
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
        
        // 결과 화면 크기 조절
        function toggleResultScale() {
            const resultsScreen = document.getElementById('resultsScreen');
            const resultFrame = document.getElementById('resultFrame');
            
            if (resultsScreen.classList.contains('fullscreen-optimized')) {
                // 일반 크기로 복원
                resultsScreen.classList.remove('fullscreen-optimized');
                resultFrame.style.transform = 'scale(0.8)';
                resultFrame.style.width = '100%';
                resultFrame.style.height = '100%';
            } else {
                // 전체화면 최적화
                resultsScreen.classList.add('fullscreen-optimized');
                resultFrame.style.transform = 'scale(0.7)';
                resultFrame.style.width = '142.86%';
                resultFrame.style.height = '142.86%';
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
                    previousGroup();
                    break;
                case 'ArrowRight':
                    nextGroup();
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
                case 'S':
                    toggleResultScale();
                    break;
                case 'A':
                case 'a':
                    toggleAutoAdvance();
                    break;
                case 'H':
                case 'h':
                    hideControlPanel();
                    break;
                case 'B':
                case 'b':
                    toggleStatusBar();
                    break;
                case 'Escape':
                    exitFullscreen();
                    break;
            }
        });
        
        // 결과 파일 자동 확인
        async function checkResultFileAndShow() {
            if (events.length > 0 && currentGroupIndex >= 0 && currentGroupIndex < Object.keys(eventGroups).length) {
                const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
                const currentGroupNumber = groupNumbers[currentGroupIndex];
                const currentGroupEvents = eventGroups[currentGroupNumber];
                
                // 현재 그룹이 올바른지 확인
                if (!currentGroupEvents) {
                    console.log('❌ 현재 그룹 이벤트를 찾을 수 없습니다:', currentGroupNumber);
                    return;
                }
                
                console.log(`이벤트 ${currentGroupNumber} 확인 중... (${currentGroupEvents.length}개 이벤트)`);
                
                // 그룹 내의 모든 이벤트에 대해 combined_result 파일 확인
                let hasAnyResult = false;
                
                for (const event of currentGroupEvents) {
                    try {
                        const response = await fetch(`check_result_file.php?event=${event.id}`);
                        const data = await response.json();
                        
                        if (data.exists) {
                            hasAnyResult = true;
                            console.log(`✅ 결과 파일 발견: ${event.id}`);
                            break; // 하나라도 찾으면 중단
                        } else {
                            console.log(`❌ 결과 파일 없음: ${event.id}`);
                        }
                    } catch (error) {
                        console.error(`결과 파일 확인 오류 (${event.id}):`, error);
                    }
                }
                
                // 결과 파일이 있으면 자동으로 다음 이벤트로 전환 (중복 실행 방지)
                if (hasAnyResult && autoAdvanceEnabled && !isAdvancing) {
                    isAdvancing = true;
                    console.log(`이벤트 ${currentGroupNumber}에서 결과 파일 발견! ${autoAdvanceTime/1000}초 후 다음 이벤트로 전환합니다...`);
                    
                    // 기존 타이머가 있으면 취소
                    if (advanceTimer) {
                        clearTimeout(advanceTimer);
                    }
                    
                    advanceTimer = setTimeout(() => {
                        autoAdvanceToNextEvent();
                        isAdvancing = false; // 전환 완료 후 플래그 리셋
                        advanceTimer = null; // 타이머 리셋
                    }, autoAdvanceTime);
                } else if (!hasAnyResult) {
                    console.log(`이벤트 ${currentGroupNumber}에 결과 파일이 없습니다. 대기 중...`);
                } else if (isAdvancing) {
                    console.log(`이벤트 ${currentGroupNumber}에서 결과 파일 발견했지만 이미 전환 중입니다.`);
                }
            }
        }
        
        // 자동으로 다음 이벤트로 전환
        function autoAdvanceToNextEvent() {
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            
            if (currentGroupIndex < groupNumbers.length - 1) {
                console.log('자동으로 다음 이벤트로 전환합니다...');
                
                // 기존 모니터링 완전 중단
                stopResultFileMonitoring();
                
                // 기존 타이머도 취소
                if (advanceTimer) {
                    clearTimeout(advanceTimer);
                    advanceTimer = null;
                }
                
                currentGroupIndex++;
                updateCurrentGroup();
                updateNextGroups();
                showMode('waiting');
                
                // 다음 이벤트 그룹 정보 표시
                const nextGroupNumber = groupNumbers[currentGroupIndex];
                console.log(`이제 이벤트 ${nextGroupNumber}를 모니터링합니다.`);
                
                // 전환 완료 후 플래그 리셋
                isAdvancing = false;
                
                // 잠시 대기 후 새로운 이벤트 모니터링 시작
                setTimeout(() => {
                    startResultFileMonitoring();
                }, 1000);
            } else {
                console.log('모든 이벤트가 완료되었습니다.');
                // 모든 이벤트 완료 시 대기화면으로
                showMode('waiting');
                isAdvancing = false;
                advanceTimer = null;
                // 모니터링 중단
                stopResultFileMonitoring();
            }
        }
        
        // 자동 전환 토글
        function toggleAutoAdvance() {
            autoAdvanceEnabled = !autoAdvanceEnabled;
            const button = event.target;
            
            if (autoAdvanceEnabled) {
                button.textContent = '⚡ 자동전환 ON';
                button.style.background = '#28a745';
                document.getElementById('autoAdvanceStatus').textContent = 'ON';
                console.log('자동 전환이 활성화되었습니다.');
            } else {
                button.textContent = '⚡ 자동전환 OFF';
                button.style.background = '#dc3545';
                document.getElementById('autoAdvanceStatus').textContent = 'OFF';
                console.log('자동 전환이 비활성화되었습니다.');
            }
        }
        
        // 제어 패널 숨기기
        function hideControlPanel() {
            document.getElementById('controlPanel').style.opacity = '0';
            console.log('제어 패널이 숨겨졌습니다.');
        }
        
        // 상태 표시바 토글
        function toggleStatusBar() {
            const statusBar = document.getElementById('statusBar');
            statusBarHidden = !statusBarHidden;
            
            if (statusBarHidden) {
                statusBar.classList.add('hidden');
                console.log('상태 표시바가 숨겨졌습니다.');
            } else {
                statusBar.classList.remove('hidden');
                console.log('상태 표시바가 표시되었습니다.');
            }
        }
        
        // 결과 파일 모니터링 시작
        function startResultFileMonitoring() {
            // 기존 인터벌이 있으면 중단
            if (monitoringInterval) {
                clearInterval(monitoringInterval);
                console.log('기존 모니터링 중단됨');
            }
            // 5초마다 결과 파일 확인
            monitoringInterval = setInterval(checkResultFileAndShow, 5000);
            console.log('새로운 모니터링 시작됨 - 이벤트', Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b))[currentGroupIndex]);
        }
        
        // 결과 파일 모니터링 중단
        function stopResultFileMonitoring() {
            if (monitoringInterval) {
                clearInterval(monitoringInterval);
                monitoringInterval = null;
                console.log('모니터링 중단됨');
            }
        }
        
        // 초기화
        document.addEventListener('DOMContentLoaded', function() {
            loadCompetitionSettings();
            loadEvents();
            showMode(currentMode);
            
            // 결과 파일 자동 모니터링 시작
            startResultFileMonitoring();
            
            // 3초 후 제어 패널 숨기기
            setTimeout(() => {
                document.getElementById('controlPanel').style.opacity = '0';
            }, 3000);
        });
        
        // 마우스 움직임 감지로 제어 패널 표시 (전체화면 모드에서만)
        let mouseTimer;
        let isMouseMoving = false;
        
        document.addEventListener('mousemove', function() {
            if (isFullscreen) {
                document.getElementById('controlPanel').style.opacity = '1';
                clearTimeout(mouseTimer);
                mouseTimer = setTimeout(() => {
                    document.getElementById('controlPanel').style.opacity = '0';
                }, 3000);
            }
        });
        
        // 전체화면 상태 업데이트
        function updateFullscreenStatus() {
            isFullscreen = !!(document.fullscreenElement || 
                            document.webkitFullscreenElement || 
                            document.mozFullScreenElement || 
                            document.msFullscreenElement);
        }
        
        // 전체화면 진입 시 제어 패널과 상태 표시바 숨기기
        document.addEventListener('fullscreenchange', function() {
            updateFullscreenStatus();
            if (isFullscreen) {
                setTimeout(() => {
                    document.getElementById('controlPanel').style.opacity = '0';
                    document.getElementById('statusBar').classList.add('hidden');
                    statusBarHidden = true;
                }, 2000);
            } else {
                document.getElementById('controlPanel').style.opacity = '1';
                document.getElementById('statusBar').classList.remove('hidden');
                statusBarHidden = false;
            }
        });
        
        document.addEventListener('webkitfullscreenchange', updateFullscreenStatus);
        document.addEventListener('mozfullscreenchange', updateFullscreenStatus);
        document.addEventListener('MSFullscreenChange', updateFullscreenStatus);
    </script>
</body>
</html>

