<?php
// 30번 이벤트 결과 파일 읽기 함수
function getEvent30Result() {
    $comp_id = $_GET['id'] ?? 'comp_20250913-001';
    $comp_id = str_replace('comp_', '', $comp_id);
    
    $result_file = "data/{$comp_id}/Results/Event_30/Event_30_result.html";
    
    if (file_exists($result_file)) {
        return file_get_contents($result_file);
    }
    
    // 고정된 파일이 없으면 최신 파일 찾기
    $event_dir = "data/{$comp_id}/Results/Event_30/";
    if (is_dir($event_dir)) {
        $files = glob($event_dir . "Event_30_*.html");
        if (!empty($files)) {
            // 파일 생성 시간으로 정렬 (최신순)
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            return file_get_contents($files[0]);
        }
    }
    
    return null;
}

$event30_result = getEvent30Result();

// RunOrder_Tablet.txt에서 이벤트 정보 가져오기 (순서와 이벤트명만)
function getEventsFromRunOrder($comp_id) {
    $runorder_file = __DIR__ . "/data/{$comp_id}/RunOrder_Tablet.txt";
    
    if (file_exists($runorder_file)) {
        $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $events = [];
        $processed_events = []; // 중복 이벤트 방지
        
        foreach ($lines as $line) {
            if (preg_match('/^bom/', $line)) continue; // 헤더 라인 스킵
            
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) >= 14) {
                $event_no = $cols[0];
                $event_name = $cols[1];
                $round = $cols[2];
                $display_number = isset($cols[13]) ? $cols[13] : ''; // 세부번호 (1-1, 1-2, 3-1, 3-2...)
                
                if (!empty($event_no) && is_numeric($event_no)) {
                    // 중복 이벤트 방지 (같은 이벤트 번호는 한 번만)
                    if (!in_array($event_no, $processed_events)) {
                        $processed_events[] = $event_no;
                        
                        $events[] = [
                            'id' => intval($event_no),
                            'display_number' => $display_number ?: $event_no, // display_number가 비어있으면 event_no 사용
                            'name' => $event_name,
                            'round' => $round,
                            'status' => 'processing' // 기본값
                        ];
                    }
                }
            }
        }
        
        return $events;
    }
    
    return [];
}

// RunOrder_Tablet.txt에서 이벤트 데이터 가져오기 (competition.php의 $events 무시)
$comp_id_clean = str_replace('comp_', '', $_GET['id'] ?? '20250913-001');
$events = getEventsFromRunOrder($comp_id_clean);

// 기존 완료된 이벤트 정보 추가
$completed_events = [
    20 => ['status' => 'completed', 'results' => [['rank' => 1, 'players' => ['선수 25', '선수 264']]]],
    28 => ['status' => 'completed', 'created' => '2025-09-24 23:50:27', 'reports' => ['detail', 'recall', 'combined']],
    31 => ['status' => 'completed', 'reports' => ['detail', 'recall', 'combined']]
];

// 완료된 이벤트 정보 병합
foreach ($events as &$event) {
    if (isset($completed_events[$event['id']])) {
        $event = array_merge($event, $completed_events[$event['id']]);
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $competition_info['title']; ?> - 종합결과</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.2em;
            font-weight: 300;
        }
        
        .header .subtitle {
            margin: 15px 0 0 0;
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .nav-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav-tab {
            padding: 15px 25px;
            text-decoration: none;
            color: #495057;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .nav-tab:hover {
            background: #e9ecef;
            color: #007bff;
        }
        
        .nav-tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background: white;
        }
        
        .content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #2c3e50;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .section h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        /* 실시간 결과 스타일 */
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
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* 이벤트 목록 스타일 */
        .event-item {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .event-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .event-header-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .event-title {
            font-weight: bold;
            font-size: 1.1em;
            color: #2c3e50;
        }
        
        .event-round {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .event-status {
            margin-top: 10px;
        }
        
        .status-loading {
            color: #6c757d;
            font-style: italic;
        }
        
        .status-completed {
            color: #28a745;
            font-weight: bold;
        }
        
        .refresh-button {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .refresh-button:hover {
            background: #218838;
        }
        
        .refresh-info {
            color: #6c757d;
            font-size: 0.9em;
            margin-left: 15px;
        }
        
        /* Live TV 스타일 */
        .live-tv-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .live-tv-header {
            text-align: center;
            margin-bottom: 20px;
            color: white;
        }
        
        .live-tv-header h4 {
            font-size: 1.8em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .advancement-text {
            font-size: 1.3em;
            font-weight: bold;
            margin: 10px 0;
            color: #ffeb3b;
        }
        
        .recall-info {
            font-size: 1.1em;
            margin: 10px 0;
            color: #e3f2fd;
        }
        
        .live-tv-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .live-tv-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .live-tv-table th {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
        }
        
        .live-tv-table td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        .live-tv-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .live-tv-table tr:hover {
            background: #e3f2fd;
        }
        
        .qualified {
            background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%) !important;
            color: white;
            font-weight: bold;
        }
        
        .qualified td {
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        
        .qualified-icon {
            margin-left: 10px;
            font-size: 1.2em;
        }
        
        .last-updated {
            text-align: center;
            margin-top: 15px;
            color: white;
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $competition_info['title']; ?></h1>
            <div class="subtitle">
                📅 <?php echo $competition_info['date']; ?> | 
                📍 <?php echo $competition_info['venue']; ?> | 
                👥 <?php echo $competition_info['organizer']; ?>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="?id=<?php echo $comp_id; ?>&page=overview" class="nav-tab">개요</a>
            <a href="#" class="nav-tab">시간표</a>
            <a href="#" class="nav-tab">공지사항</a>
            <a href="?id=<?php echo $comp_id; ?>&page=results" class="nav-tab active">종합결과</a>
            <a href="#" class="nav-tab">실시간 결과</a>
        </div>

        <div class="content">
            <div class="section">
                <h2>🏆 종합결과</h2>
                
                <div class="section">
                    <h3>📺 실시간 경기 결과</h3>
                    <div class="live-tv-container" id="live-tv-container">
                        <div class="loading" id="live-loading">
                            <span class="update-indicator"></span>
                            실시간 결과를 로딩 중입니다...
                        </div>
                        
                        <!-- Live TV 결과 표시 영역 -->
                        <div class="live-tv-content" id="live-tv-content" style="display: none;">
                            <div class="live-tv-header">
                                <h4 id="event-title">이벤트 정보 로딩 중...</h4>
                                <p class="advancement-text" id="advancement-text"></p>
                                <p class="recall-info" id="recall-info"></p>
                            </div>
                            
                            <div class="live-tv-table">
                                <table id="results-table">
                                    <thead>
                                        <tr>
                                            <th>Marks</th>
                                            <th>Tag</th>
                                            <th>Competitor Name(s)</th>
                                            <th>From</th>
                                        </tr>
                                    </thead>
                                    <tbody id="results-tbody">
                                        <!-- 결과 데이터가 여기에 동적으로 추가됩니다 -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="last-updated" id="last-updated"></div>
                        </div>
                        
                        <!-- 에러 메시지 영역 -->
                        <div class="error-message" id="error-message" style="display: none;">
                            <p>실시간 결과를 불러올 수 없습니다. 잠시 후 다시 시도해주세요.</p>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <button class="refresh-button" onclick="loadLiveTvResults()">새로고침</button>
                        <span class="refresh-info">
                            30초마다 자동 갱신됩니다. 최신 결과가 아닐 경우 새로고침(F5) 해주세요.
                        </span>
                    </div>
                </div>

                <div class="section">
                    <h3>📋 이벤트별 결과</h3>
                    
                    <?php 
                    // 이벤트를 ID 순으로 정렬
                    usort($events, function($a, $b) {
                        return $a['id'] - $b['id'];
                    });
                    
                    foreach ($events as $event): ?>
                    <div class="event-item">
                        <div class="event-header-item">
                            <div class="event-title">
                                <?php echo $event['display_number']; ?>번 <?php echo $event['name']; ?>
                            </div>
                            <div class="event-round"><?php echo $event['round']; ?></div>
                        </div>
                        <div class="event-status">
                            <?php if ($event['id'] == 30 && $event30_result): ?>
                                <!-- 30번 이벤트 특별 처리 -->
                                <div class="status-completed">
                                    <div style="margin-bottom: 15px;">
                                        <strong>📊 집계 결과</strong>
                                        <div style="margin-top: 10px; border: 1px solid #ddd; border-radius: 5px; padding: 15px; background: #f9f9f9;">
                                            <?php echo $event30_result; ?>
                                        </div>
                                    </div>
                                    <div style="margin-top: 10px;">
                                        <a href="/comp/data/<?php echo str_replace('comp_', '', $_GET['id'] ?? '20250913-001'); ?>/Results/Event_30/Event_30_result.html" target="_blank" style="margin-right: 10px;">📋 전체 결과 보기</a>
                                        <a href="#" style="margin-right: 10px;">📊 리콜 리포트</a>
                                        <a href="#">🏆 컴바인 리포트</a>
                                    </div>
                                </div>
                            <?php elseif ($event['status'] === 'completed'): ?>
                                <div class="status-completed">
                                    <?php if (isset($event['created'])): ?>
                                        생성: <?php echo $event['created']; ?>
                                    <?php endif; ?>
                                    <?php if (isset($event['results'])): ?>
                                        <h4>최종 순위</h4>
                                        <?php foreach ($event['results'] as $result): ?>
                                            <p><strong><?php echo $result['rank']; ?>위</strong> <?php echo implode(', ', $result['players']); ?></p>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (isset($event['reports'])): ?>
                                        <div style="margin-top: 10px;">
                                            <?php if (in_array('detail', $event['reports'])): ?>
                                                <a href="/comp/data/<?php echo str_replace('comp_', '', $_GET['id'] ?? '20250913-001'); ?>/Results/Event_<?php echo $event['id']; ?>/Event_<?php echo $event['id']; ?>_result.html" target="_blank" style="margin-right: 10px;">📋 상세 리포트</a>
                                            <?php endif; ?>
                                            <?php if (in_array('recall', $event['reports'])): ?>
                                                <a href="/comp/data/<?php echo str_replace('comp_', '', $_GET['id'] ?? '20250913-001'); ?>/Results/Event_<?php echo $event['id']; ?>/Event_<?php echo $event['id']; ?>_result.html" target="_blank" style="margin-right: 10px;">📊 리콜 리포트</a>
                                            <?php endif; ?>
                                            <?php if (in_array('combined', $event['reports'])): ?>
                                                <a href="/comp/data/<?php echo str_replace('comp_', '', $_GET['id'] ?? '20250913-001'); ?>/Results/Event_<?php echo $event['id']; ?>/Event_<?php echo $event['id']; ?>_result.html" target="_blank">🏆 컴바인 리포트</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="color: #94a3b8; text-align: center; padding: 20px;">
                                    <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 18px;">schedule</span>
                                    결과 데이터를 처리 중입니다...
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Live TV 실시간 결과 JavaScript -->
    <script>
        // Live TV 실시간 결과 JavaScript
        let liveTvUpdateInterval;
        let isLoading = false;
        
        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Live TV Results initialized');
            loadLiveTvResults();
            startAutoUpdate();
        });
        
        // 실시간 결과 로드 함수
        function loadLiveTvResults() {
            if (isLoading) return;
            
            isLoading = true;
            showLoading();
            
            const compId = '<?php echo str_replace("comp_", "", $comp_id); ?>';
            const eventNo = '30'; // 기본 이벤트 번호
            const apiUrl = `live_scoring_monitor.php?comp_id=${compId}&event_no=${eventNo}`;
            
            console.log('Loading live TV results from:', apiUrl);
            
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    console.log('Live TV API response:', data);
                    
                    if (data.success && data.live_tv) {
                        displayLiveTvResults(data.live_tv);
                        hideLoading();
                        hideError();
                    } else {
                        throw new Error(data.error || 'API returned error');
                    }
                })
                .catch(error => {
                    console.error('Error loading live TV results:', error);
                    showError();
                    hideLoading();
                })
                .finally(() => {
                    isLoading = false;
                });
        }
        
        // Live TV 결과 표시 함수
        function displayLiveTvResults(liveTvData) {
            console.log('Displaying live TV data:', liveTvData);
            
            // 헤더 정보 업데이트
            document.getElementById('event-title').textContent = liveTvData.event_title || '이벤트 정보 없음';
            document.getElementById('advancement-text').textContent = liveTvData.advancement_text || '';
            document.getElementById('recall-info').textContent = liveTvData.recall_info || '';
            
            // 테이블 데이터 업데이트
            const tbody = document.getElementById('results-tbody');
            tbody.innerHTML = '';
            
            if (liveTvData.participants && liveTvData.participants.length > 0) {
                liveTvData.participants.forEach((participant, index) => {
                    const row = document.createElement('tr');
                    if (participant.qualified) {
                        row.classList.add('qualified');
                    }
                    
                    row.innerHTML = `
                        <td>${participant.marks || 0}</td>
                        <td>(${participant.tag || ''})</td>
                        <td>${participant.name || ''} ${participant.qualified ? '<span class="qualified-icon">✅ 진출</span>' : ''}</td>
                        <td>${participant.from || ''}</td>
                    `;
                    
                    tbody.appendChild(row);
                });
            } else {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="4">경기 결과가 없습니다.</td>';
                tbody.appendChild(row);
            }
            
            // 업데이트 시간 표시
            if (liveTvData.file_info && liveTvData.file_info.timestamp) {
                document.getElementById('last-updated').textContent = 
                    `마지막 업데이트: ${liveTvData.file_info.timestamp}`;
            }
            
            // Live TV 컨텐츠 표시
            document.getElementById('live-tv-content').style.display = 'block';
        }
        
        // 로딩 표시
        function showLoading() {
            document.getElementById('live-loading').style.display = 'block';
            document.getElementById('live-tv-content').style.display = 'none';
            document.getElementById('error-message').style.display = 'none';
        }
        
        // 로딩 숨김
        function hideLoading() {
            document.getElementById('live-loading').style.display = 'none';
        }
        
        // 에러 표시
        function showError() {
            document.getElementById('error-message').style.display = 'block';
            document.getElementById('live-tv-content').style.display = 'none';
        }
        
        // 에러 숨김
        function hideError() {
            document.getElementById('error-message').style.display = 'none';
        }
        
        // 자동 업데이트 시작
        function startAutoUpdate() {
            // 기존 인터벌 클리어
            if (liveTvUpdateInterval) {
                clearInterval(liveTvUpdateInterval);
            }
            
            // 30초마다 업데이트
            liveTvUpdateInterval = setInterval(() => {
                console.log('Auto updating live TV results...');
                loadLiveTvResults();
            }, 30000);
            
            console.log('Auto update started (30 seconds interval)');
        }
        
        // 페이지 언로드 시 인터벌 클리어
        window.addEventListener('beforeunload', function() {
            if (liveTvUpdateInterval) {
                clearInterval(liveTvUpdateInterval);
            }
        });
    </script>
</body>
</html>
