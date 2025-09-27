// 실시간 경기결과 통합 JavaScript
(function() {
    'use strict';
    
    console.log('Live results integration script loaded');
    
    // 설정
    const UPDATE_INTERVAL = 30000; // 30초
    const EVENT_ID = '30'; // 기본 이벤트 ID
    
    let updateInterval;
    let isUpdating = false;
    
    // DOM 요소 찾기 - 더 많은 선택자 시도
    const liveResultsSection = document.querySelector('.live-results-section, #live-results, .live-results, [class*="live"]');
    const refreshButton = document.querySelector('.refresh-button, [data-refresh], button[class*="refresh"]');
    
    console.log('Live results section found:', liveResultsSection);
    console.log('Refresh button found:', refreshButton);
    
    if (!liveResultsSection) {
        console.error('실시간 경기결과 섹션을 찾을 수 없습니다.');
        // 모든 가능한 선택자 시도
        const allPossibleSelectors = [
            '.live-results-section',
            '#live-results',
            '.live-results',
            '[id*="live"]',
            '[class*="live"]'
        ];
        
        for (const selector of allPossibleSelectors) {
            const element = document.querySelector(selector);
            if (element) {
                console.log('Found element with selector:', selector, element);
            }
        }
        return;
    }
    
    // CSS 스타일 추가
    addStyles();
    
    // 초기 로드
    console.log('Starting initial load...');
    loadLiveResults();
    
    // 자동 갱신 시작
    console.log('Starting auto update...');
    startAutoUpdate();
    
    // 새로고침 버튼 이벤트
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            loadLiveResults();
        });
    }
    
    function addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .live-results-container {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            
            .event-header {
                border-bottom: 2px solid #007bff;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            
            .event-header h3 {
                color: #007bff;
                margin: 0 0 10px 0;
                font-size: 1.4em;
            }
            
            .event-stats {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
            }
            
            .stat-item {
                background: white;
                padding: 8px 12px;
                border-radius: 4px;
                border: 1px solid #dee2e6;
                font-size: 0.9em;
            }
            
            .advancing-players h4 {
                color: #28a745;
                margin: 0 0 15px 0;
                font-size: 1.2em;
            }
            
            .players-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }
            
            .player-card {
                background: white;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                padding: 15px;
                transition: all 0.3s ease;
                position: relative;
            }
            
            .player-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                border-color: #28a745;
            }
            
            .player-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, #28a745, #20c997);
                border-radius: 8px 8px 0 0;
            }
            
            .player-rank {
                font-size: 1.8em;
                font-weight: bold;
                color: #28a745;
                margin-bottom: 8px;
            }
            
            .player-info {
                margin-bottom: 10px;
            }
            
            .player-number {
                font-size: 0.9em;
                color: #6c757d;
                margin-bottom: 4px;
            }
            
            .player-name {
                font-size: 1.1em;
                font-weight: bold;
                color: #2c3e50;
                margin-bottom: 6px;
            }
            
            .player-recall {
                background: #e8f5e8;
                color: #155724;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 0.8em;
                font-weight: bold;
                display: inline-block;
            }
            
            .player-status {
                text-align: right;
                font-size: 0.9em;
                color: #28a745;
                font-weight: bold;
            }
            
            .no-results {
                text-align: center;
                padding: 40px;
                color: #6c757d;
                font-style: italic;
            }
            
            .loading {
                text-align: center;
                padding: 20px;
                color: #007bff;
            }
            
            .error {
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #f5c6cb;
                margin: 10px 0;
            }
            
            .update-indicator {
                display: inline-block;
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: #28a745;
                margin-right: 8px;
                animation: pulse 2s infinite;
            }
            
            .results-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .results-table th {
                background: #007bff;
                color: white;
                padding: 12px 8px;
                text-align: left;
                font-weight: bold;
                font-size: 0.9em;
            }
            
            .results-table td {
                padding: 10px 8px;
                border-bottom: 1px solid #dee2e6;
                font-size: 0.9em;
            }
            
            .results-table tbody tr:hover {
                background: #f8f9fa;
            }
            
            .results-table tbody tr:last-child td {
                border-bottom: none;
            }
            
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
    
    function loadLiveResults() {
        if (isUpdating) return;
        
        isUpdating = true;
        updateStatus('실시간 결과를 로딩 중입니다...');
        
        const url = `./update_live_results.php?event_id=${EVENT_ID}`;
        console.log('Fetching live results from:', url);
        
        fetch(url)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                if (data.success) {
                    const html = generateCustomLiveResultsHTML(data);
                    console.log('Generated HTML:', html);
                    liveResultsSection.innerHTML = html;
                    updateStatus('실시간 결과가 업데이트되었습니다.');
                } else {
                    console.error('API returned error:', data.error);
                    showError(data.error || '결과를 불러올 수 없습니다.');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showError('서버와의 연결에 문제가 있습니다: ' + error.message);
            })
            .finally(() => {
                isUpdating = false;
            });
    }
    
    function generateCustomLiveResultsHTML(data) {
        console.log('Generating HTML for data:', data);
        
        if (!data.event_info) {
            console.log('No event_info found, showing no results message');
            return '<div class="no-results">진행 중인 경기가 없습니다.</div>';
        }
        
        const eventInfo = data.event_info;
        const advancingPlayers = data.advancing_players || [];
        
        console.log('Event info:', eventInfo);
        console.log('Advancing players:', advancingPlayers);
        
        let html = '<div class="live-results-container">';
        
        // 이벤트 정보 헤더
        html += '<div class="event-header">';
        html += `<h3>${eventInfo.name} - ${eventInfo.round}</h3>`;
        html += `<p><strong>${eventInfo.recall_count}커플이 다음라운에 진출합니다</strong></p>`;
        html += `<p>리콜 정보: 파일 리콜 수: ${eventInfo.recall_count}명 | 심사위원 수: ${data.total_judges || 13}명 | 리콜 기준: ${eventInfo.recall_count}명 이상</p>`;
        html += '</div>';
        
        // 진출자 목록
        if (advancingPlayers.length > 0) {
            html += '<div class="advancing-players">';
            html += '<table class="results-table">';
            html += '<thead><tr><th>Marks</th><th>Tag</th><th>Competitor Name(s)</th><th>From</th></tr></thead>';
            html += '<tbody>';
            
            advancingPlayers.forEach((player, index) => {
                html += '<tr>';
                html += `<td>${index + 1}</td>`;
                html += `<td>(${player.recall_count})</td>`;
                html += `<td>${player.player_number} ${player.player_name} ✅ 진출</td>`;
                html += `<td>${eventInfo.name}</td>`;
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
        } else {
            html += '<div class="no-results">진행 중인 경기가 없습니다.</div>';
        }
        
        html += '</div>';
        
        console.log('Generated HTML length:', html.length);
        return html;
    }
    
    function updateStatus(message) {
        // 상태 메시지 업데이트 (기존 페이지의 상태 표시 영역에)
        const statusElement = document.querySelector('.status-text, .loading-text');
        if (statusElement) {
            statusElement.innerHTML = `<span class="update-indicator"></span>${message}`;
        }
    }
    
    function showError(message) {
        liveResultsSection.innerHTML = `
            <div class="error">
                <strong>오류:</strong> ${message}
            </div>
        `;
    }
    
    function startAutoUpdate() {
        updateInterval = setInterval(loadLiveResults, UPDATE_INTERVAL);
    }
    
    function stopAutoUpdate() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }
    
    // 페이지 가시성 변경 시 자동 갱신 제어
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopAutoUpdate();
        } else {
            startAutoUpdate();
            loadLiveResults(); // 페이지가 다시 보이면 즉시 업데이트
        }
    });
    
    // 페이지 언로드 시 정리
    window.addEventListener('beforeunload', function() {
        stopAutoUpdate();
    });
    
    // 전역 함수로 노출 (필요시 외부에서 호출 가능)
    window.liveResults = {
        load: loadLiveResults,
        start: startAutoUpdate,
        stop: stopAutoUpdate
    };
    
})();
