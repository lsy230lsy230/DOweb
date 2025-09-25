<?php
// 테스트 페이지 1: 기본 이벤트 블록 구조
$comp_id = '20250913-001';
$data_dir = __DIR__ . "/test_data";

// 이벤트 데이터 로드
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$events = [];
$event_groups = [];

if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $cols = explode(',', $line);
        if (count($cols) >= 14) {
            $event_no = trim($cols[0]);
            $desc = trim($cols[1]);
            $round = trim($cols[2]);
            $detail_no = trim($cols[13]);
            $panel = trim($cols[11]);
            
            $events[] = [
                'no' => $event_no,
                'desc' => $desc,
                'round' => $round,
                'detail_no' => $detail_no,
                'panel' => $panel
            ];
        }
    }
}

// 이벤트 그룹 생성 (같은 이벤트 번호끼리 그룹화)
foreach ($events as $event) {
    $group_key = $event['no'];
    if (!isset($event_groups[$group_key])) {
        $event_groups[$group_key] = [
            'group_no' => $group_key,
            'group_name' => '',
            'events' => []
        ];
    }
    
    // 그룹명 설정 (첫 번째 이벤트의 카테고리 추출)
    if (empty($event_groups[$group_key]['group_name'])) {
        $event_groups[$group_key]['group_name'] = extractCategory($event['desc']);
    }
    
    $event_groups[$group_key]['events'][] = $event;
}

function extractCategory($desc) {
    // 이벤트 설명에서 카테고리 추출
    if (strpos($desc, '솔로 일반부') !== false) return '솔로 일반부';
    if (strpos($desc, '솔로 초등부') !== false) return '솔로 초등부';
    if (strpos($desc, '솔로 중등부') !== false) return '솔로 중등부';
    if (strpos($desc, '솔로 유치부') !== false) return '솔로 유치부';
    if (strpos($desc, '초등부') !== false) return '초등부';
    if (strpos($desc, '유치부') !== false) return '유치부';
    return '기타';
}

function h($s) { return htmlspecialchars($s ?? ''); }
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>테스트 페이지 1 - 이벤트 블록 구조</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            display: flex;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .left-panel {
            width: 300px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .right-panel {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .panel-header {
            background: #2c3e50;
            color: white;
            padding: 15px;
            font-weight: bold;
            font-size: 16px;
        }
        
        .event-group {
            border-bottom: 1px solid #eee;
        }
        
        .event-group:last-child {
            border-bottom: none;
        }
        
        .group-header {
            background: #34495e;
            color: white;
            padding: 12px 15px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .group-header:hover {
            background: #2c3e50;
        }
        
        .group-header.selected {
            background: #e74c3c;
        }
        
        .group-toggle {
            font-size: 14px;
            transition: transform 0.3s;
        }
        
        .group-toggle.expanded {
            transform: rotate(90deg);
        }
        
        .event-list {
            display: none;
            background: #f8f9fa;
        }
        
        .event-list.expanded {
            display: block;
        }
        
        .event-item {
            padding: 10px 15px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.2s;
        }
        
        .event-item:hover {
            background: #e3f2fd;
        }
        
        .event-item.selected {
            background: #bbdefb;
            border-left: 4px solid #2196f3;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-info {
            flex: 1;
        }
        
        .event-number {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 2px;
        }
        
        .event-desc {
            font-size: 12px;
            color: #666;
            line-height: 1.3;
        }
        
        .event-status {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: bold;
        }
        
        .status-final {
            background: #d4edda;
            color: #155724;
        }
        
        .status-semi {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-prelim {
            background: #f8d7da;
            color: #721c24;
        }
        
        .right-content {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .right-content h2 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .event-count {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 왼쪽 이벤트 리스트 패널 -->
        <div class="left-panel">
            <div class="panel-header">
                📋 이벤트 리스트
            </div>
            
            <?php foreach ($event_groups as $group): ?>
            <div class="event-group" data-group="<?=h($group['group_no'])?>">
                <div class="group-header" onclick="toggleGroup('<?=h($group['group_no'])?>')">
                    <span>
                        통합이벤트 <?=h($group['group_no'])?> (<?=h($group['group_name'])?>)
                    </span>
                    <span class="group-toggle">▶</span>
                </div>
                
                <div class="event-list" id="group-<?=h($group['group_no'])?>">
                    <?php foreach ($group['events'] as $event): ?>
                    <div class="event-item" 
                         data-event="<?=h($event['detail_no'] ?: $event['no'])?>"
                         onclick="selectEvent('<?=h($event['detail_no'] ?: $event['no'])?>', this)">
                        <div class="event-info">
                            <div class="event-number">
                                <?=h($event['detail_no'] ?: $event['no'])?>
                            </div>
                            <div class="event-desc">
                                <?=h($event['desc'])?>
                            </div>
                        </div>
                        <div class="event-status status-<?=strtolower($event['round'])?>">
                            <?=h($event['round'])?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 오른쪽 메인 패널 -->
        <div class="right-panel">
            <div class="right-content">
                <h2>이벤트 선택</h2>
                <p>왼쪽에서 이벤트를 선택하면 여기에 상세 정보가 표시됩니다.</p>
                
                <div class="event-count">
                    <strong>총 이벤트 그룹:</strong> <?=count($event_groups)?>개<br>
                    <strong>총 이벤트:</strong> <?=count($events)?>개
                </div>
                
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number"><?=count(array_filter($events, fn($e) => $e['round'] === 'Final'))?></div>
                        <div class="stat-label">Final</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?=count(array_filter($events, fn($e) => strpos($e['round'], 'Semi') !== false))?></div>
                        <div class="stat-label">Semi-Final</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?=count(array_filter($events, fn($e) => count($event_groups[$e['no']]['events']) > 1))?></div>
                        <div class="stat-label">멀티 이벤트</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedEvent = null;
        let expandedGroups = new Set();
        
        function toggleGroup(groupNo) {
            const group = document.querySelector(`[data-group="${groupNo}"]`);
            const eventList = document.getElementById(`group-${groupNo}`);
            const toggle = group.querySelector('.group-toggle');
            
            if (expandedGroups.has(groupNo)) {
                eventList.classList.remove('expanded');
                toggle.classList.remove('expanded');
                expandedGroups.delete(groupNo);
            } else {
                eventList.classList.add('expanded');
                toggle.classList.add('expanded');
                expandedGroups.add(groupNo);
            }
        }
        
        function selectEvent(eventId, element) {
            // 이전 선택 해제
            document.querySelectorAll('.event-item.selected').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelectorAll('.group-header.selected').forEach(header => {
                header.classList.remove('selected');
            });
            
            // 현재 선택
            element.classList.add('selected');
            element.closest('.event-group').querySelector('.group-header').classList.add('selected');
            
            selectedEvent = eventId;
            
            // 오른쪽 패널 업데이트
            updateRightPanel(eventId);
        }
        
        function updateRightPanel(eventId) {
            const rightContent = document.querySelector('.right-content');
            rightContent.innerHTML = `
                <h2>선택된 이벤트: ${eventId}</h2>
                <p>이벤트 상세 정보가 여기에 표시됩니다.</p>
                <div class="event-count">
                    <strong>이벤트 ID:</strong> ${eventId}<br>
                    <strong>선택 시간:</strong> ${new Date().toLocaleTimeString()}
                </div>
            `;
        }
        
        // 페이지 로드 시 첫 번째 그룹 확장
        document.addEventListener('DOMContentLoaded', function() {
            const firstGroup = document.querySelector('.event-group');
            if (firstGroup) {
                const groupNo = firstGroup.dataset.group;
                toggleGroup(groupNo);
            }
        });
    </script>
</body>
</html>

