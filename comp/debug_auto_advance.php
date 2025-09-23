<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>자동 전환 디버깅</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        .info { background: #d1ecf1; border-color: #bee5eb; }
        .btn { background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #5a6fd8; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .log { background: #000; color: #0f0; padding: 15px; border-radius: 5px; font-family: monospace; height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 자동 전환 디버깅</h1>
        
        <div class="section info">
            <h2>📋 현재 상태</h2>
            <div id="currentStatus">로딩 중...</div>
        </div>
        
        <div class="section">
            <h2>🎯 이벤트 그룹 정보</h2>
            <div id="eventGroups">로딩 중...</div>
        </div>
        
        <div class="section">
            <h2>📁 결과 파일 확인</h2>
            <div id="resultFiles">로딩 중...</div>
        </div>
        
        <div class="section">
            <h2>⚙️ 자동 전환 설정</h2>
            <div>
                <button class="btn" onclick="testAutoAdvance()">자동 전환 테스트</button>
                <button class="btn" onclick="checkResultFile()">결과 파일 확인</button>
                <button class="btn" onclick="forceAdvance()">강제 다음 그룹</button>
                <button class="btn" onclick="resetToFirst()">첫 번째 그룹으로</button>
            </div>
        </div>
        
        <div class="section">
            <h2>📝 실시간 로그</h2>
            <div id="log" class="log"></div>
        </div>
    </div>

    <script>
        let currentGroupIndex = 0;
        let events = [];
        let eventGroups = {};
        let autoAdvanceEnabled = true;
        let autoAdvanceTime = 5000;
        
        // 로그 함수
        function log(message) {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            logDiv.innerHTML += `[${timestamp}] ${message}\n`;
            logDiv.scrollTop = logDiv.scrollHeight;
            console.log(message);
        }
        
        // 이벤트 로드
        async function loadEvents() {
            try {
                log('이벤트 로드 시작...');
                const response = await fetch('get_event_schedule.php');
                const data = await response.json();
                
                if (data.success) {
                    events = data.events;
                    eventGroups = groupEvents(events);
                    log(`이벤트 로드 완료: ${events.length}개 이벤트, ${Object.keys(eventGroups).length}개 그룹`);
                    updateDisplay();
                } else {
                    log('이벤트 로드 실패: ' + data.message);
                }
            } catch (error) {
                log('이벤트 로드 오류: ' + error.message);
            }
        }
        
        // 이벤트 그룹화
        function groupEvents(events) {
            const groups = {};
            events.forEach(event => {
                const eventNumber = event.id.replace(/[^0-9]/g, '');
                if (eventNumber && eventNumber !== '') {
                    if (!groups[eventNumber]) {
                        groups[eventNumber] = [];
                    }
                    groups[eventNumber].push(event);
                }
            });
            
            const sortedGroups = {};
            Object.keys(groups).sort((a, b) => parseInt(a) - parseInt(b)).forEach(key => {
                sortedGroups[key] = groups[key];
            });
            
            return sortedGroups;
        }
        
        // 화면 업데이트
        function updateDisplay() {
            // 현재 상태
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            const currentGroupNumber = groupNumbers[currentGroupIndex];
            const currentGroupEvents = eventGroups[currentGroupNumber] || [];
            
            document.getElementById('currentStatus').innerHTML = `
                <p><strong>현재 그룹:</strong> ${currentGroupIndex + 1}/${groupNumbers.length} (이벤트 ${currentGroupNumber})</p>
                <p><strong>자동 전환:</strong> ${autoAdvanceEnabled ? 'ON' : 'OFF'}</p>
                <p><strong>전환 시간:</strong> ${autoAdvanceTime/1000}초</p>
            `;
            
            // 이벤트 그룹 정보
            let groupsHtml = '';
            groupNumbers.forEach((groupNum, index) => {
                const groupEvents = eventGroups[groupNum];
                const isActive = index === currentGroupIndex;
                groupsHtml += `
                    <div style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; ${isActive ? 'background: #e7f3ff;' : ''}">
                        <strong>이벤트 ${groupNum} ${isActive ? '(현재)' : ''}</strong>
                        <ul>
                            ${groupEvents.map(event => `
                                <li>${event.id}: ${event.name} (결과발표: ${event.show_result ? 'ON' : 'OFF'})</li>
                            `).join('')}
                        </ul>
                    </div>
                `;
            });
            document.getElementById('eventGroups').innerHTML = groupsHtml;
            
            // 결과 파일 확인
            checkResultFiles();
        }
        
        // 결과 파일 확인
        async function checkResultFiles() {
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            const currentGroupNumber = groupNumbers[currentGroupIndex];
            const currentGroupEvents = eventGroups[currentGroupNumber] || [];
            
            let resultHtml = '';
            for (const event of currentGroupEvents) {
                try {
                    const response = await fetch(`check_result_file.php?event=${event.id}`);
                    const data = await response.json();
                    resultHtml += `
                        <p><strong>${event.id}:</strong> 
                            ${data.exists ? '✅ 결과 파일 존재' : '❌ 결과 파일 없음'}
                            ${data.file_info ? ` (크기: ${data.file_info.size}bytes)` : ''}
                        </p>
                    `;
                } catch (error) {
                    resultHtml += `<p><strong>${event.id}:</strong> ❌ 확인 오류</p>`;
                }
            }
            document.getElementById('resultFiles').innerHTML = resultHtml || '<p>현재 그룹에 이벤트가 없습니다.</p>';
        }
        
        // 자동 전환 테스트
        async function testAutoAdvance() {
            log('자동 전환 테스트 시작...');
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            const currentGroupNumber = groupNumbers[currentGroupIndex];
            const currentGroupEvents = eventGroups[currentGroupNumber] || [];
            
            log(`현재 그룹 ${currentGroupNumber}: ${currentGroupEvents.length}개 이벤트 확인 중...`);
            
            let foundResult = false;
            for (const event of currentGroupEvents) {
                log(`결과 파일 확인 중: ${event.id}`);
                
                try {
                    const response = await fetch(`check_result_file.php?event=${event.id}`);
                    const data = await response.json();
                    
                    if (data.exists) {
                        log(`✅ 결과 파일 발견: ${event.id}`);
                        foundResult = true;
                        break;
                    } else {
                        log(`❌ 결과 파일 없음: ${event.id}`);
                    }
                } catch (error) {
                    log(`결과 파일 확인 오류 (${event.id}): ${error.message}`);
                }
            }
            
            if (foundResult) {
                log(`자동 전환 시뮬레이션: ${autoAdvanceTime/1000}초 후 다음 그룹으로...`);
                
                setTimeout(() => {
                    if (currentGroupIndex < groupNumbers.length - 1) {
                        currentGroupIndex++;
                        log(`자동 전환 완료: 이벤트 ${groupNumbers[currentGroupIndex]}로 이동`);
                        updateDisplay();
                    } else {
                        log('모든 이벤트 완료');
                    }
                }, autoAdvanceTime);
            } else {
                log('현재 그룹에 결과 파일이 없습니다.');
            }
        }
        
        // 결과 파일 확인
        function checkResultFile() {
            log('결과 파일 확인 중...');
            checkResultFiles();
        }
        
        // 강제 다음 그룹
        function forceAdvance() {
            const groupNumbers = Object.keys(eventGroups).sort((a, b) => parseInt(a) - parseInt(b));
            if (currentGroupIndex < groupNumbers.length - 1) {
                currentGroupIndex++;
                log(`강제 다음 그룹: 이벤트 ${groupNumbers[currentGroupIndex]}로 이동`);
                updateDisplay();
            } else {
                log('마지막 그룹입니다.');
            }
        }
        
        // 첫 번째 그룹으로
        function resetToFirst() {
            currentGroupIndex = 0;
            log('첫 번째 그룹으로 리셋');
            updateDisplay();
        }
        
        // 페이지 로드 시 시작
        document.addEventListener('DOMContentLoaded', function() {
            log('디버깅 페이지 로드 완료');
            loadEvents();
        });
    </script>
</body>
</html>
