<?php
session_start();

$comp_id = $_GET['comp_id'] ?? '';
$comp_id = preg_replace('/\x{FEFF}/u', '', $comp_id);
$comp_id = preg_replace('/[^\d-]/', '', $comp_id);

// Language setting
$lang = $_GET['lang'] ?? 'ko';
if (!in_array($lang, ['ko', 'en', 'zh', 'ja'])) {
    $lang = 'ko';
}

// Language texts
$texts = [
    'ko' => [
        'title' => '심사위원 채점 대시보드',
        'judge_info' => '심사위원',
        'logout' => '로그아웃',
        'competition' => '대회',
        'no_events' => '등록된 이벤트가 없습니다',
        'contact_admin' => '대회 관리자에게 문의하세요.',
        'scoring_complete' => '채점 완료',
        'scoring_pending' => '채점 대기',
        'round' => '라운드',
        'dances' => '종목',
        'score' => '채점하기',
        'view_results' => '결과보기',
        'switch_lang' => 'English',
        'not_assigned' => '배정되지 않음',
        'panel_info' => '패널',
        'scoring_not_allowed' => '채점 불가'
    ],
    'en' => [
        'title' => 'Judge Scoring Dashboard',
        'judge_info' => 'Judge',
        'logout' => 'Logout',
        'competition' => 'Competition',
        'no_events' => 'No events registered',
        'contact_admin' => 'Please contact the competition administrator.',
        'scoring_complete' => 'Scoring Complete',
        'scoring_pending' => 'Pending',
        'round' => 'Round',
        'dances' => 'Dances',
        'score' => 'Score',
        'view_results' => 'View Results',
        'switch_lang' => '한국어',
        'not_assigned' => 'Not Assigned',
        'panel_info' => 'Panel',
        'scoring_not_allowed' => 'Not Allowed'
    ],
    'zh' => [
        'title' => '评委评分仪表板',
        'judge_info' => '评委',
        'logout' => '退出登录',
        'competition' => '比赛',
        'no_events' => '没有注册的赛事',
        'contact_admin' => '请联系比赛管理员。',
        'scoring_complete' => '评分完成',
        'scoring_pending' => '待评分',
        'round' => '轮次',
        'dances' => '舞种',
        'score' => '评分',
        'view_results' => '查看结果',
        'switch_lang' => 'English',
        'not_assigned' => '未分配',
        'panel_info' => '面板',
        'scoring_not_allowed' => '不允许评分'
    ],
    'ja' => [
        'title' => '審査員採点ダッシュボード',
        'judge_info' => '審査員',
        'logout' => 'ログアウト',
        'competition' => '大会',
        'no_events' => '登録されたイベントがありません',
        'contact_admin' => '大会管理者にお問い合わせください。',
        'scoring_complete' => '採点完了',
        'scoring_pending' => '採点待ち',
        'round' => 'ラウンド',
        'dances' => '種目',
        'score' => '採点',
        'view_results' => '結果表示',
        'switch_lang' => 'English',
        'not_assigned' => '未割り当て',
        'panel_info' => 'パネル',
        'scoring_not_allowed' => '採点不可'
    ]
];

$t = $texts[$lang];

// Check if judge is logged in
if (!isset($_SESSION['scoring_logged_in']) || !$_SESSION['scoring_logged_in'] || $_SESSION['scoring_comp_id'] !== $comp_id) {
    header("Location: scoring_login.php?comp_id=" . urlencode($comp_id));
    exit;
}

// Load competition info
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";

if (!file_exists($info_file)) {
    echo "<h1>대회 정보를 찾을 수 없습니다.</h1>";
    exit;
}

$info = json_decode(file_get_contents($info_file), true);

// Load events
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$events = [];
$multievent_groups = []; // 멀티 이벤트 그룹 관리

if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        
        // Check if it's a competition event (has dances)
        $has_dance = false;
        for ($i = 6; $i <= 10; $i++) {
            if (isset($cols[$i]) && $cols[$i] !== '') {
                $has_dance = true;
                break;
            }
        }
        
        if ($has_dance) {
            // Remove BOM from event number
            $base_no_raw = preg_replace('/\x{FEFF}/u', '', $cols[0]);
            $base_no_raw = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $base_no_raw);
            
            // 세부번호는 14번째 컬럼(인덱스 13)에서 가져오기
            $detail_no = trim($cols[13] ?? '');
            $event_no = !empty($detail_no) ? $detail_no : $base_no_raw;
            $base_event_no = preg_replace('/\D+/', '', $base_no_raw); // 숫자만 추출
            
            
            $event_data = [
                'no' => $event_no,
                'base_no' => $base_event_no,
                'detail_no' => $detail_no,
                'name' => $cols[1],
                'round_type' => $cols[2],
                'round_num' => $cols[3],
                'round_display' => $cols[2], // 원본 라운드 정보를 직접 사용
                'panel_code' => $cols[11] ?? '',
                'dances' => []
            ];
            
            for ($i = 6; $i <= 10; $i++) {
                if (!empty($cols[$i])) {
                    // 원본 숫자 코드를 그대로 사용 (파일명과 일치시키기 위해)
                    $event_data['dances'][] = $cols[$i];
                    
                }
            }
            
            // 멀티 이벤트 그룹에 추가
            if (!isset($multievent_groups[$base_event_no])) {
                $multievent_groups[$base_event_no] = [];
            }
            $multievent_groups[$base_event_no][] = $event_data;
            
            $events[] = $event_data;
        }
    }
}

// 멀티 이벤트 그룹 정리 (세부번호가 있는 경우만)
$multievent_events = [];
foreach ($multievent_groups as $base_no => $group) {
    if (count($group) > 1) {
        // 세부번호가 있는 멀티 이벤트
        $multievent_events[$base_no] = $group;
    }
}

// Load round info from RunOrder_Tablet.txt (primary source)
$round_info = [];

// Load dance name mapping
$dance_mapping = [];
$dance_name_file = "$data_dir/DanceName.txt";
if (file_exists($dance_name_file)) {
    $lines = file($dance_name_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = preg_replace('/\x{FEFF}/u', '', $line);
        $line = str_replace(["\xEF\xBB\xBF", "\xFEFF", "\uFEFF"], '', $line);
        $line = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $line);
        
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 3 && is_numeric($cols[0])) {
            $dance_mapping[$cols[0]] = $cols[1]; // 숫자 -> 댄스명
        }
    }
}

// 하드코딩된 댄스 매핑 (백업)
$dance_mapping = [
    '1' => 'Waltz',
    '2' => 'Tango', 
    '3' => 'Viennese Waltz',
    '4' => 'Slow Foxtrot',
    '5' => 'Quickstep',
    '6' => 'Cha Cha Cha',
    '7' => 'Samba',
    '8' => 'Rumba',
    '9' => 'Paso Doble',
    '10' => 'Jive',
    '11' => 'Freestyle',
    '12' => 'Swing',
    '13' => 'Argentine Tango',
    '14' => 'Handicap',
    '15' => 'Formation Team'
];

// 댄스 변환 함수
function convertDanceNumber($dance_number, $dance_mapping) {
    $dance_number = trim($dance_number);
    return $dance_mapping[$dance_number] ?? $dance_number;
}



// Load panel list and check judge's panels
$panel_list = [];
$panel_file = "$data_dir/panel_list.json";
if (file_exists($panel_file)) {
    $panel_data = json_decode(file_get_contents($panel_file), true);
    if ($panel_data) {
        $panel_list = $panel_data;
    }
}

// Get current judge's panels
$current_judge_id = $_SESSION['scoring_judge_id'] ?? '';
$judge_panels = [];
foreach ($panel_list as $panel) {
    // 심사위원 ID를 문자열과 숫자 모두로 비교
    if ($panel['adj_code'] === $current_judge_id || 
        $panel['adj_code'] === (string)$current_judge_id || 
        (string)$panel['adj_code'] === $current_judge_id) {
        $judge_panels[] = $panel['panel_code'];
    }
}


// Calculate round info from RunOrder_Tablet.txt data
function calculateRoundDisplay($event_data, $all_events) {
    $event_name = $event_data['name'];
    $event_no = $event_data['no'];
    
    // Find all events with same name
    $same_name_events = array_filter($all_events, function($e) use ($event_name) {
        return $e['name'] === $event_name;
    });
    
    $total_events = count($same_name_events);
    if ($total_events <= 1) {
        return 'Final';
    }
    
    // Sort by event number to get position
    $sorted_events = array_values($same_name_events);
    usort($sorted_events, function($a, $b) {
        return intval($a['no']) - intval($b['no']);
    });
    
    $position = 0;
    foreach ($sorted_events as $idx => $evt) {
        if ($evt['no'] === $event_no) {
            $position = $idx;
            break;
        }
    }
    
    // Calculate round display based on position and total
    if ($total_events === 2) {
        return $position === 0 ? 'Semi-Final' : 'Final';
    } else if ($total_events === 3) {
        if ($position === 0) return 'Round 1';
        else if ($position === 1) return 'Semi-Final';
        else return 'Final';
    } else if ($total_events === 4) {
        if ($position === 0) return 'Round 1';
        else if ($position === 1) return 'Round 2';
        else if ($position === 2) return 'Semi-Final';
        else return 'Final';
    } else if ($total_events === 5) {
        if ($position === 0) return 'Round 1';
        else if ($position === 1) return 'Round 2';
        else if ($position === 2) return 'Round 3';
        else if ($position === 3) return 'Semi-Final';
        else return 'Final';
    } else {
        return ($position + 1) . '/' . $total_events;
    }
}

// Merge round info into events and check scoring eligibility
foreach ($events as $idx => &$event) {
    // Use original round_display from RunOrder_Tablet.txt (already set during parsing)
    // Only calculate if round_display is empty
    if (empty($event['round_display'])) {
        $event['round_display'] = calculateRoundDisplay($event, $events);
    }
    
    // Check if judge can score this event (panel match)
    $panel_code = $event['panel_code'] ?? '';
    
    // 정확한 패널 매칭 로직
    if (empty($judge_panels)) {
        // 패널 정보가 없는 경우 모든 이벤트 채점 가능
        $event['can_score'] = true;
        $reason = "no_panel_info";
    } else {
        // 패널 코드가 비어있거나 'N/A'인 경우, 또는 심사위원 패널에 정확히 포함된 경우만 채점 가능
        $event['can_score'] = empty($panel_code) || $panel_code === 'N/A' || in_array($panel_code, $judge_panels);
        $reason = $event['can_score'] ? "panel_match" : "no_panel_match";
    }
    
    $event['panel_status'] = $event['can_score'] ? 'eligible' : 'not_eligible';
    
}
unset($event); // Break the reference

// Check which events have scores
$events_with_scores = [];
$judge_id = $_SESSION['scoring_judge_id'] ?? '';

foreach ($events as $event) {
    $event_no = $event['no'];
    $has_scores = false;
    
    // 1. 기본 점수 파일 확인
    $scores_file = "$data_dir/scores_{$event_no}.json";
    if (file_exists($scores_file)) {
        $has_scores = true;
    } else {
        // 2. 개별 .adj 파일 확인 (모든 라운드/이벤트에 적용)
        $all_dances_scored = true;
        
        foreach ($event['dances'] as $dance) {
            $adj_file = "$data_dir/{$event_no}_{$dance}_{$judge_id}.adj";
            if (!file_exists($adj_file)) {
                $all_dances_scored = false;
                break;
            }
        }
        
        $has_scores = $all_dances_scored;
        
    }
    
    $events_with_scores[$event_no] = $has_scores;
}

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="<?=h($lang)?>">
<head>
    <meta charset="UTF-8">
    <title><?=h($t['title'])?> | <?=h($info['title'])?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body { 
            background: #f5f7fa; 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            margin: 0; 
            padding: 10px;
            font-size: 16px;
            line-height: 1.4;
        }
        
        .container { 
            max-width: 100%; 
            margin: 0 auto; 
            background: #fff; 
            border-radius: 8px; 
            padding: 15px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
            margin-top: 50px;
        }
        
        h1 { 
            color: #333; 
            margin: 0 0 10px 0; 
            font-size: 1.4em;
            font-weight: 700;
        }
        
        .judge-info {
            background: #e3f2fd;
            padding: 8px 12px;
            border-radius: 6px;
            color: #1976d2;
            font-weight: 600;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .logout-btn {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            transition: all 0.2s;
        }
        
        .logout-btn:active {
            background: #c82333;
            transform: translateY(1px);
        }
        
        .filter-btn {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            transition: all 0.2s;
        }
        
        .filter-btn:active {
            background: #218838;
            transform: translateY(1px);
        }
        
        .filter-btn.active {
            background: #6c757d;
        }
        
        .filter-btn.active:active {
            background: #5a6268;
        }
        
        .comp-info {
            background: #f8f9fa; 
            padding: 12px; 
            border-radius: 6px; 
            margin-bottom: 20px; 
            text-align: center;
            font-size: 0.9em;
        }
        
        .events-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .event-card {
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        /* 멀티 이벤트 카드 스타일 */
        .multievent-card {
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .multievent-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .multievent-number {
            font-size: 1.8em;
            font-weight: 700;
            color: #667eea;
            margin-right: 15px;
            line-height: 1;
        }
        
        .multievent-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        .sub-events {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .sub-event {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        
        .sub-event-number {
            font-size: 1.2em;
            font-weight: 700;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .sub-event-name {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 8px;
        }
        
        .sub-event-dances {
            font-size: 0.8em;
            color: #868e96;
            font-style: italic;
        }
        
        .event-card:active {
            transform: translateY(1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .event-card.scored {
            border-color: #28a745;
            background: #f8fff9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-not-assigned {
            background: #f8d7da;
            color: #721c24;
        }
        
        .event-card.not-eligible {
            border-color: #dc3545;
            background: #fff5f5;
            opacity: 0.7;
        }
        
        .btn-disabled {
            background: #6c757d !important;
            color: #fff !important;
            cursor: not-allowed !important;
            opacity: 0.6;
        }
        
        .btn-disabled:active {
            transform: none !important;
        }
        
        .panel-info {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 8px;
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            display: inline-block;
        }
        
        .event-number {
            font-size: 1.8em;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 4px;
            line-height: 1;
        }
        
        .event-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .event-details {
            color: #666;
            font-size: 0.85em;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        
        .event-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .btn {
            padding: 12px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            text-align: center;
            display: block;
        }
        
        .btn:active {
            transform: translateY(1px);
        }
        
        .btn-primary {
            background: #667eea;
            color: #fff;
        }
        
        .btn-primary:active {
            background: #5a6fd8;
        }
        
        .btn-info {
            background: #17a2b8;
            color: #fff;
        }
        
        .btn-info:active {
            background: #138496;
        }
        
        
        .top-controls {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .lang-switch {
            background: #667eea;
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 12px;
            padding-right: 30px;
        }
        
        .lang-switch:active {
            background-color: #5a6fd8;
            transform: translateY(1px);
        }
        
        .lang-switch option {
            background: #667eea;
            color: #fff;
            padding: 8px;
        }
        
        .container {
            position: relative;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 1.2em;
        }
        
        /* 태블릿 환경 (768px 이상) */
        @media (min-width: 768px) {
            body {
                padding: 20px;
            }
            
            .container {
                padding: 25px;
                border-radius: 12px;
            }
            
            .header {
                margin-bottom: 25px;
                margin-top: 60px;
            }
            
            h1 {
                font-size: 1.6em;
                margin-bottom: 0;
            }
            
            .judge-info {
                margin-bottom: 0;
                font-size: 0.95em;
            }
            
            .top-controls {
                top: 25px;
                right: 25px;
            }
            
            .logout-btn {
                padding: 10px 18px;
                font-size: 13px;
            }
            
            .events-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 15px;
            }
            
            .event-actions {
                flex-direction: row;
                gap: 8px;
            }
            
            .btn {
                flex: 1;
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .lang-switch {
                top: 25px;
                right: 25px;
                padding: 10px 32px 10px 14px;
                font-size: 13px;
            }
        }
        
        /* 큰 태블릿/데스크톱 (1024px 이상) */
        @media (min-width: 1024px) {
            .container {
                max-width: 1200px;
                padding: 30px;
            }
            
            .events-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
            }
            
            .event-card {
                padding: 20px;
            }
            
            .event-number {
                font-size: 2em;
            }
            
            .event-title {
                font-size: 1.2em;
            }
            
            .event-details {
                font-size: 0.9em;
            }
            
            .top-controls {
                top: 30px;
                right: 30px;
            }
            
            .lang-switch {
                padding: 10px 36px 10px 16px;
                font-size: 14px;
            }
            
            .logout-btn {
                padding: 10px 20px;
                font-size: 14px;
            }
            
            .lang-switch:hover {
                background: #5a6fd8;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="top-controls">
        <button id="filterToggle" class="filter-btn" onclick="toggleEventFilter()">대기중만</button>
        <select class="lang-switch" onchange="window.location.href='?comp_id=<?=h($comp_id)?>&lang=' + this.value">
            <option value="ko" <?=$lang === 'ko' ? 'selected' : ''?>>한국어</option>
            <option value="en" <?=$lang === 'en' ? 'selected' : ''?>>English</option>
            <option value="zh" <?=$lang === 'zh' ? 'selected' : ''?>>中文</option>
            <option value="ja" <?=$lang === 'ja' ? 'selected' : ''?>>日本語</option>
        </select>
        <a href="scoring_logout.php?comp_id=<?=h($comp_id)?>" class="logout-btn"><?=h($t['logout'])?></a>
    </div>
    
    <div class="header">
        <div>
            <h1><?=h($t['title'])?></h1>
            <div class="judge-info">
                <?=h($_SESSION['scoring_judge_name'])?> (<?=h($_SESSION['scoring_judge_country'])?>) - <?=h($t['judge_info'])?> #<?=h($_SESSION['scoring_judge_id'])?>
            </div>
        </div>
    </div>
    
    <div class="comp-info" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 30px; text-align: center;">
        <strong><?=h($info['title'])?></strong><br>
        <?=h($info['date'])?> | <?=h($info['place'])?>
    </div>
    
    <?php if (empty($events)): ?>
        <div class="empty-state">
            <h3><?=h($t['no_events'])?></h3>
            <p><?=h($t['contact_admin'])?></p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php 
            // 모든 이벤트를 번호순으로 정렬하여 표시
            $all_events = [];
            
            // 멀티 이벤트들을 기본 번호로 그룹화
            foreach ($multievent_events as $base_no => $group) {
                $all_events[] = [
                    'type' => 'multievent',
                    'base_no' => $base_no,
                    'group' => $group,
                    'sort_key' => intval($base_no)
                ];
            }
            
            // 일반 이벤트들 추가 (멀티 이벤트가 아닌 것들)
            $displayed_events = [];
            foreach ($multievent_events as $group) {
                foreach ($group as $event) {
                    $displayed_events[] = $event['no'];
                }
            }
            
            foreach ($events as $event) {
                if (!in_array($event['no'], $displayed_events)) {
                    $all_events[] = [
                        'type' => 'single',
                        'event' => $event,
                        'sort_key' => intval($event['no'])
                    ];
                }
            }
            
            // 번호순으로 정렬
            usort($all_events, function($a, $b) {
                return $a['sort_key'] - $b['sort_key'];
            });
            
            // 이벤트들 렌더링
            foreach ($all_events as $event_item):
                if ($event_item['type'] === 'multievent'):
                    $base_no = $event_item['base_no'];
                    $group = $event_item['group'];
                    $first_event = $group[0];
                    // 멀티 이벤트의 패널 매칭 로직
                    $panel_code = $first_event['panel_code'] ?? '';
                    if (empty($judge_panels)) {
                        $can_score = true;
                    } else {
                        $can_score = empty($panel_code) || $panel_code === 'N/A' || in_array($panel_code, $judge_panels);
                    }
                    $has_scores = true;
                    foreach ($group as $event) {
                        if (!$events_with_scores[$event['no']]) {
                            $has_scores = false;
                            break;
                        }
                    }
            ?>
            <div class="multievent-card <?=$has_scores ? 'scored' : ''?> <?=!$can_score ? 'not-eligible' : ''?>" 
                 data-status="<?=$has_scores ? 'completed' : ($can_score ? 'pending' : 'not-assigned')?>">
                <div class="status-badge <?=$has_scores ? 'status-completed' : ($can_score ? 'status-pending' : 'status-not-assigned')?>">
                    <?php if ($has_scores): ?>
                        <?=h($t['scoring_complete'])?>
                    <?php elseif ($can_score): ?>
                        <?=h($t['scoring_pending'])?>
                    <?php else: ?>
                        <?=h($t['not_assigned'])?>
                    <?php endif; ?>
                </div>
                
                <div class="multievent-header">
                    <div class="multievent-number">#<?=h($base_no)?></div>
                </div>
                
                <div class="sub-events">
                    <?php foreach ($group as $event): ?>
                    <div class="sub-event">
                        <div class="sub-event-number">#<?=h($event['no'])?></div>
                        <div class="sub-event-name"><?=h($event['name'])?></div>
                        <div class="sub-event-dances"><?php 
                            $dance_names = [];
                            foreach ($event['dances'] as $dance) {
                                $dance_names[] = convertDanceNumber($dance, $dance_mapping);
                            }
                            echo h(implode(', ', $dance_names));
                        ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="panel-info">
                    <?=h($t['panel_info'])?>: <?=h($first_event['panel_code'] ?: 'N/A')?>
                </div>
                
                <div class="event-details">
                    <?=h($t['round'])?>: <?=h($first_event['round_display'] ?: $first_event['round_type'] . ' ' . $first_event['round_num'])?><br>
                    <strong>멀티 이벤트 결승전</strong>
                </div>
                
                <div class="event-actions">
                    <?php if ($can_score): ?>
                        <?php if ($has_scores): ?>
                            <button class="btn btn-disabled" disabled>
                                <?=h($t['scoring_complete'])?>
                            </button>
                        <?php else: ?>
                            <a href="multievent_scoring.php?comp_id=<?=h($comp_id)?>&event_group=<?=h($base_no)?>&lang=<?=h($lang)?>" 
                               class="btn btn-primary" 
                               target="_blank">
                                <?=h($t['score'])?>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn btn-disabled" disabled>
                            <?=h($t['scoring_not_allowed'])?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: // 단일 이벤트 ?>
            <?php 
                $event = $event_item['event'];
            ?>
            <div class="event-card <?=$events_with_scores[$event['no']] ? 'scored' : ''?> <?=!$event['can_score'] ? 'not-eligible' : ''?>" 
                 data-status="<?=$events_with_scores[$event['no']] ? 'completed' : ($event['can_score'] ? 'pending' : 'not-assigned')?>">
                <div class="status-badge <?=$events_with_scores[$event['no']] ? 'status-completed' : ($event['can_score'] ? 'status-pending' : 'status-not-assigned')?>">
                    <?php if ($events_with_scores[$event['no']]): ?>
                        <?=h($t['scoring_complete'])?>
                    <?php elseif ($event['can_score']): ?>
                        <?=h($t['scoring_pending'])?>
                    <?php else: ?>
                        <?=h($t['not_assigned'])?>
                    <?php endif; ?>
                </div>
                
                <div class="event-number">#<?=h($event['no'])?></div>
                <div class="event-title"><?=h($event['name'])?></div>
                
                <div class="panel-info">
                    <?=h($t['panel_info'])?>: <?=h($event['panel_code'] ?: 'N/A')?>
                </div>
                
                <div class="event-details">
                    <?=h($t['round'])?>: <?=h($event['round_display'] ?: $event['round_type'] . ' ' . $event['round_num'])?><br>
                    <?=h($t['dances'])?>: <?php 
                        $dance_names = [];
                        foreach ($event['dances'] as $dance) {
                            $dance_names[] = convertDanceNumber($dance, $dance_mapping);
                        }
                        echo h(implode(', ', $dance_names));
                    ?>
                </div>
                
                <div class="event-actions">
                    <?php if ($event['can_score']): ?>
                        <?php if ($events_with_scores[$event['no']]): ?>
                            <button class="btn btn-disabled" disabled>
                                <?=h($t['scoring_complete'])?>
                            </button>
                        <?php else: ?>
                            <a href="judge_scoring.php?comp_id=<?=h($comp_id)?>&event_no=<?=h($event['no'])?>&lang=<?=h($lang)?>" 
                               class="btn btn-primary" 
                               target="_blank">
                                <?=h($t['score'])?>
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn btn-disabled" disabled>
                            <?=h($t['scoring_not_allowed'])?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer style="text-align: center; margin-top: 30px; color: #666; font-size: 0.9em; padding: 20px;">
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>

<script>
let showAllEvents = true;

function toggleEventFilter() {
    const filterBtn = document.getElementById('filterToggle');
    const eventCards = document.querySelectorAll('.event-card, .multievent-card');
    
    showAllEvents = !showAllEvents;
    
    if (showAllEvents) {
        // Show all events
        filterBtn.textContent = '대기중만';
        filterBtn.classList.remove('active');
        eventCards.forEach(card => {
            card.style.display = 'block';
        });
    } else {
        // Hide completed and not-assigned events
        filterBtn.textContent = '전체보기';
        filterBtn.classList.add('active');
        eventCards.forEach(card => {
            const status = card.getAttribute('data-status');
            if (status === 'completed' || status === 'not-assigned') {
                card.style.display = 'none';
            } else {
                card.style.display = 'block';
            }
        });
    }
}
</script>
</body>
</html>
