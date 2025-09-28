<?php
session_start();

// 다국어 시스템 로드
require_once __DIR__ . '/lang/Language.php';
$lang = Language::getInstance();

// 대회 스케줄러 로드
require_once __DIR__ . '/data/scheduler.php';
$scheduler = new CompetitionScheduler();

// URL에서 대회 ID 가져오기
$comp_id = $_GET['id'] ?? '';
$page = $_GET['page'] ?? 'main';

if (!$comp_id) {
    header('Location: /');
    exit;
}

// 대회 정보 가져오기
$competition = $scheduler->getCompetitionById($comp_id);
if (!$competition) {
    header('Location: /');
    exit;
}

// 대회별 기능 함수들
function getCompetitionNotices($comp_data_path) {
    if (!$comp_data_path) return [];
    
    $notices_file = $comp_data_path . '/notices.json';
    if (file_exists($notices_file)) {
        return json_decode(file_get_contents($notices_file), true) ?: [];
    }
    return [];
}

// RunOrder_Tablet.txt에서 시간표 순서대로 이벤트 목록 가져오기
function getEventsFromRunOrder($comp_data_path) {
    if (!$comp_data_path) return [];
    
    $runorder_file = $comp_data_path . '/RunOrder_Tablet.txt';
    if (!file_exists($runorder_file)) return [];
    
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $events = [];
    $processed_events = []; // 중복 방지 (이벤트번호-세부번호 조합)
    
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue; // 헤더 라인 스킵
        
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 14) {
            $event_no = $cols[0];
            $event_name = $cols[1];
            $round = $cols[2];
            $display_number = $cols[13]; // 세부번호 (1-1, 1-2, 3-1, 3-2...)
            
            if (!empty($event_no) && is_numeric($event_no)) {
                // 세부번호가 있는 경우 이벤트번호-세부번호 조합으로 중복 체크
                $unique_key = $event_no . ($display_number ? '-' . $display_number : '');
                
                if (!in_array($unique_key, $processed_events)) {
                    $processed_events[] = $unique_key;
                    
                    // 세부번호가 있는 경우 이벤트명에 세부번호 포함
                    $full_event_name = $event_name;
                    if ($display_number && $display_number !== $event_no) {
                        $full_event_name = $event_name . ' (' . $display_number . ')';
                    }
                    
                    $events[] = [
                        'event_no' => intval($event_no),
                        'display_number' => $display_number ?: $event_no,
                        'event_name' => $full_event_name,
                        'round' => $round,
                        'has_result' => false, // 결과 파일 존재 여부는 나중에 확인
                        'detail_no' => $display_number // 세부번호 별도 저장
                    ];
                }
            }
        }
    }
    
    // 이벤트 번호와 세부번호 순으로 정렬 (시간표 순서)
    usort($events, function($a, $b) {
        // 먼저 이벤트 번호로 정렬
        if ($a['event_no'] != $b['event_no']) {
            return $a['event_no'] - $b['event_no'];
        }
        // 같은 이벤트 번호면 세부번호로 정렬
        return strcmp($a['detail_no'] ?? '', $b['detail_no'] ?? '');
    });
    
    return $events;
}

function getCompetitionSchedule($comp_data_path, $comp_id) {
    // 먼저 푸시된 타임테이블 데이터 확인
    $timetable_file = __DIR__ . '/data/timetables/timetable_' . str_replace('comp_', '', $comp_id) . '.json';
    if (file_exists($timetable_file)) {
        $timetable_data = json_decode(file_get_contents($timetable_file), true);
        if ($timetable_data && isset($timetable_data['events'])) {
            return $timetable_data;
        }
    }
    
    // 기존 schedule.json 파일 확인 (호환성)
    if (!$comp_data_path) return [];
    
    $schedule_file = $comp_data_path . '/schedule.json';
    if (file_exists($schedule_file)) {
        return json_decode(file_get_contents($schedule_file), true) ?: [];
    }
    return [];
}

function getCompetitionResults($comp_data_path) {
    if (!$comp_data_path) return [];
    
    $results_file = $comp_data_path . '/results.json';
    if (file_exists($results_file)) {
        return json_decode(file_get_contents($results_file), true) ?: [];
    }
    return [];
}

// 대회 데이터 디렉토리 경로
$comp_data_path = isset($competition['comp_data_path']) ? $competition['comp_data_path'] : null;

// 페이지별 데이터 로드
$notices = getCompetitionNotices($comp_data_path);
$schedule = getCompetitionSchedule($comp_data_path, $comp_id);
$results = getCompetitionResults($comp_data_path);

?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($competition['title']) ?> | DanceOffice</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    
    <style>
        /* 인쇄용 헤더 스타일 */
        .competition-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        @media screen {
            .competition-header {
                display: none !important;
            }
        }
        
        /* 모바일 최적화 */
        @media (max-width: 768px) {
            .professional-timetable {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .professional-timetable .timetable-main {
                min-width: 600px !important;
                font-size: 0.75em !important;
                table-layout: auto !important;
            }
            
            .professional-timetable th {
                padding: 6px 3px !important;
                font-size: 0.75em !important;
                white-space: nowrap;
            }
            
            .professional-timetable th:nth-child(1) { /* 시간 */
                width: 50px !important;
                max-width: 50px !important;
            }
            
            .professional-timetable th:nth-child(2) { /* 번호 */
                width: 30px !important;
                max-width: 30px !important;
            }
            
            .professional-timetable th:nth-child(3) { /* 경기 종목 */
                min-width: 180px !important;
                max-width: 250px !important;
            }
            
            .professional-timetable th:nth-child(4) { /* 댄스 */
                min-width: 200px !important;
                max-width: 300px !important;
            }
            
            .professional-timetable th:nth-child(5) { /* 라운드 */
                width: 70px !important;
                max-width: 90px !important;
            }
            
            .professional-timetable td {
                padding: 6px 3px !important;
                font-size: 0.75em !important;
            }
            
            /* 시간 표시 최적화 */
            .professional-timetable td span {
                font-size: 0.9em !important;
            }
            
            /* 댄스 배지 최적화 */
            .professional-timetable td div span[style*="background: linear-gradient(135deg, #f59e0b"] {
                padding: 1px 2px !important;
                font-size: 0.6em !important;
                margin: 0px !important;
            }
            
            /* 이벤트 번호 배지 최적화 */
            .professional-timetable td span[style*="background: #3b82f6"] {
                padding: 3px 4px !important;
                font-size: 0.7em !important;
            }
            
            /* 라운드 아이콘 최적화 */
            .professional-timetable td div span[style*="font-size: 1em"] {
                font-size: 0.7em !important;
            }
            
            .professional-timetable td div span[style*="font-size: 0.85em"] {
                font-size: 0.65em !important;
            }
            
            /* 상세 번호 배지 최적화 */
            .professional-timetable td span[style*="background: linear-gradient(135deg, #64748b"] {
                padding: 1px 3px !important;
                font-size: 0.6em !important;
                min-width: 20px !important;
            }
            
            /* 시간 정보 최적화 */
            .professional-timetable td div[style*="flex-direction: column"] span:first-child {
                font-size: 0.8em !important;
            }
            
            .professional-timetable td div[style*="flex-direction: column"] span:last-child {
                font-size: 0.65em !important;
            }
            
            /* 경기 종목 텍스트 */
            .professional-timetable td div span[style*="color: #1f2937"] {
                font-size: 0.75em !important;
                line-height: 1.2 !important;
            }
        }
        
        @media print {
            .competition-header.print-only {
                display: block !important;
            }
            /* 인쇄 시 불필요한 요소 숨기기 */
            .comp-nav, .main-nav, button, .comp-nav-tabs, .back-btn {
                display: none !important;
            }
            
            /* 인쇄 시 전체 페이지 사용 */
            body, .container, .comp-content {
                background: white !important;
                color: black !important;
                font-size: 12px;
                margin: 0 !important;
                padding: 10px !important;
            }
            
            /* 대회 타이틀 컴팩트하게 */
            .competition-header {
                margin-bottom: 5px !important;
                padding: 5px 0 !important;
            }
            
            .competition-header h1 {
                font-size: 16px !important;
                margin: 0 0 5px 0 !important;
                line-height: 1.2 !important;
            }
            
            .competition-header .competition-meta {
                font-size: 10px !important;
                margin: 0 !important;
            }
            
            /* 화면에서 보이는 헤더들 숨기기 */
            .section-title, h2, .comp-header {
                display: none !important;
            }
            
            /* 타임테이블 정보 박스 숨기기 */
            .timetable-info {
                display: none !important;
            }
            
            /* 표 인쇄 스타일 - 컴팩트하게 */
            .professional-timetable table {
                background: white !important;
                font-size: 9px !important;
                border: 1px solid #000 !important;
                page-break-inside: auto;
                line-height: 1.2 !important;
            }
            
            /* 행별 페이지 나누기 최적화 */
            .professional-timetable tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            
            /* 표가 페이지를 넘어갈 때만 나누기 */
            .professional-timetable table {
                page-break-before: auto;
                page-break-after: auto;
            }
            
            .professional-timetable th {
                background: #f5f5f5 !important;
                color: black !important;
                border: 1px solid #000 !important;
                font-weight: bold !important;
                padding: 4px 3px !important;
                font-size: 10px !important;
                line-height: 1.1 !important;
            }
            
            .professional-timetable td {
                border: 1px solid #000 !important;
                color: black !important;
                padding: 3px 3px !important;
                font-size: 9px !important;
                line-height: 1.1 !important;
                vertical-align: top !important;
            }
            
            /* 인쇄 시 아이콘 숨기고 텍스트로 변경 */
            .professional-timetable .material-symbols-rounded {
                display: none !important;
            }
            
            /* 인쇄 시 번호 컬럼 아이콘만 숨김 */
            .professional-timetable th:nth-child(2) .material-symbols-rounded {
                display: none !important;
            }
            
            /* 인쇄 시 댄스 컬럼 아이콘 숨김 */
            .professional-timetable .dance-badges .material-symbols-rounded {
                display: none !important;
            }
            
            /* 댄스 컬럼 글씨 진하게 */
            .professional-timetable td:nth-child(4) {
                font-weight: bold !important;
                color: #000 !important;
            }
            
            /* 행 높이 최소화 */
            .professional-timetable tr {
                height: auto !important;
                min-height: 0 !important;
            }
            
            /* 배지와 아이콘 크기 최소화 */
            .professional-timetable span[style*="padding:"] {
                padding: 1px 2px !important;
                font-size: 7px !important;
                margin: 0 !important;
            }
            
            /* 시간 표시 최소화 */
            .professional-timetable td div[style*="flex-direction: column"] {
                line-height: 1 !important;
            }
            
            .professional-timetable td div[style*="flex-direction: column"] span {
                font-size: 8px !important;
                margin: 0 !important;
            }
            
            /* 인쇄 시 시간 정렬 개선 (첫 번째 컬럼) */
            .professional-timetable td:nth-child(1) {
                text-align: center !important;
                vertical-align: middle !important;
            }
            
            /* 배지 색상 조정 */
            .professional-timetable span[style*="background: #3b82f6"] {
                background: #000 !important;
                color: white !important;
            }
            
            .professional-timetable span[style*="background: #64748b"] {
                background: #666 !important;
                color: white !important;
            }
            
            /* 제목 색상 조정 */
            h2, h3 {
                color: black !important;
            }
        }
    </style>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* 상단 헤더 */
        .comp-header {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .comp-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .comp-header-content {
            position: relative;
            z-index: 2;
        }

        .comp-title {
            font-size: 32px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 16px;
        }

        .comp-info {
            display: flex;
            justify-content: center;
            gap: 32px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .comp-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
            font-size: 14px;
        }

        .comp-info-item .material-symbols-rounded {
            color: #3b82f6;
            font-size: 20px;
        }

        /* 네비게이션 탭 */
        .comp-nav {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 8px;
            margin-bottom: 32px;
            display: flex;
            gap: 8px;
            overflow-x: auto;
        }

        .comp-nav-item {
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            color: #94a3b8;
            font-weight: 500;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .comp-nav-item:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .comp-nav-item.active {
            background: #3b82f6;
            color: white;
        }

        /* 컨텐츠 섹션 */
        .comp-content {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 20px;
            padding: 32px;
            min-height: 400px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title .material-symbols-rounded {
            color: #3b82f6;
            font-size: 28px;
        }

        /* 대회 개요 스타일 */
        .comp-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .overview-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 16px;
            padding: 24px;
        }

        .overview-card h3 {
            color: #3b82f6;
            font-size: 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .overview-card p {
            color: #94a3b8;
            line-height: 1.6;
        }

        /* 공지사항/일정 목록 스타일 */
        .item-list {
            space-y: 16px;
        }

        .item-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .item-title {
            font-size: 16px;
            font-weight: 600;
            color: #f1f5f9;
        }

        .item-date {
            font-size: 12px;
            color: #64748b;
            background: rgba(59, 130, 246, 0.1);
            padding: 4px 12px;
            border-radius: 12px;
        }

        .item-content {
            color: #94a3b8;
            line-height: 1.6;
        }

        /* 빈 상태 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state .material-symbols-rounded {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* 백 버튼 */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        /* 모바일 대응 */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .comp-title {
                font-size: 24px;
            }

            .comp-info {
                flex-direction: column;
                gap: 16px;
                align-items: center;
            }

            .comp-nav {
                flex-direction: column;
                gap: 4px;
            }

            .comp-content {
                padding: 24px 20px;
            }

            .comp-overview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 백 버튼 -->
        <a href="/" class="back-btn">
            <span class="material-symbols-rounded">arrow_back</span>
            <?= t('nav_dashboard') ?>
        </a>

        <!-- 대회 헤더 -->
        <div class="comp-header">
            <div class="comp-header-content">
                <h1 class="comp-title"><?= htmlspecialchars($competition['title']) ?></h1>
                
                <div class="comp-info">
                    <div class="comp-info-item">
                        <span class="material-symbols-rounded">schedule</span>
                        <?= $lang->formatDate($competition['date']) ?>
                    </div>
                    <div class="comp-info-item">
                        <span class="material-symbols-rounded">location_on</span>
                        <?= htmlspecialchars($competition['location']) ?>
                    </div>
                    <div class="comp-info-item">
                        <span class="material-symbols-rounded">group</span>
                        <?= htmlspecialchars($competition['host']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 네비게이션 탭 -->
        <nav class="comp-nav">
            <a href="?id=<?= $comp_id ?>&page=main" class="comp-nav-item <?= $page === 'main' ? 'active' : '' ?>">
                <span class="material-symbols-rounded">home</span>
                개요
            </a>
            <a href="?id=<?= $comp_id ?>&page=schedule" class="comp-nav-item <?= $page === 'schedule' ? 'active' : '' ?>">
                <span class="material-symbols-rounded">schedule</span>
                시간표
            </a>
            <a href="?id=<?= $comp_id ?>&page=notices" class="comp-nav-item <?= $page === 'notices' ? 'active' : '' ?>">
                <span class="material-symbols-rounded">campaign</span>
                공지사항
            </a>
            <a href="?id=<?= $comp_id ?>&page=results" class="comp-nav-item <?= $page === 'results' ? 'active' : '' ?>">
                <span class="material-symbols-rounded">trophy</span>
                종합결과
            </a>
            <a href="?id=<?= $comp_id ?>&page=live" class="comp-nav-item <?= $page === 'live' ? 'active' : '' ?>">
                <span class="material-symbols-rounded">live_tv</span>
                실시간 결과
            </a>
        </nav>

        <!-- 컨텐츠 -->
        <div class="comp-content">
            <?php if ($page === 'main'): ?>
                <!-- 대회 개요 -->
                <h2 class="section-title">
                    <span class="material-symbols-rounded">info</span>
                    대회 개요
                </h2>
                
                <div class="comp-overview">
                    <div class="overview-card">
                        <h3>
                            <span class="material-symbols-rounded">event</span>
                            대회 정보
                        </h3>
                        <p><strong>대회명:</strong> <?= htmlspecialchars($competition['title']) ?></p>
                        <p><strong>개최일:</strong> <?= $lang->formatDate($competition['date']) ?></p>
                        <p><strong>장소:</strong> <?= htmlspecialchars($competition['location']) ?></p>
                        <p><strong>주최:</strong> <?= htmlspecialchars($competition['host']) ?></p>
                        <p><strong>상태:</strong> 
                            <span style="color: <?= $competition['status'] === 'upcoming' ? '#22c55e' : ($competition['status'] === 'ongoing' ? '#f59e0b' : '#64748b') ?>">
                                <?= t('status_' . $competition['status']) ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="overview-card">
                        <h3>
                            <span class="material-symbols-rounded">analytics</span>
                            대회 현황
                        </h3>
                        <p><strong>공지사항:</strong> <?= count($notices) ?>건</p>
                        <p><strong>경기일정:</strong> <?= isset($schedule['timetable_rows']) ? count($schedule['timetable_rows']) : (isset($schedule['events']) ? count($schedule['events']) : count($schedule)) ?>개 종목</p>
                        <p><strong>결과:</strong> <?= count($results) ?>개 종목 완료</p>
                        <p><strong>생성일:</strong> <?= isset($competition['created_at']) ? date('Y-m-d', strtotime($competition['created_at'])) : '정보없음' ?></p>
                    </div>
                </div>

            <?php elseif ($page === 'schedule'): ?>
                <!-- 인쇄용 대회 헤더 -->
                <div class="competition-header print-only" style="display: none;">
                    <h1><?= htmlspecialchars($competition['title']) ?></h1>
                    <div class="competition-meta">
                        <span>📅 <?= $lang->formatDate($competition['date']) ?></span>
                        <span style="margin-left: 20px;">📍 <?= htmlspecialchars($competition['location']) ?></span>
                        <span style="margin-left: 20px;">🏢 <?= htmlspecialchars($competition['host']) ?></span>
                    </div>
                </div>
                
                <!-- 시간표 -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 class="section-title" style="margin-bottom: 0;">
                        <span class="material-symbols-rounded">schedule</span>
                        대회 시간표
                    </h2>
                    <button onclick="printTimetable()" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-rounded" style="font-size: 18px;">print</span>
                        인쇄
                    </button>
                </div>
                
                <?php if (empty($schedule)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-rounded">schedule</div>
                        <h3>시간표가 아직 등록되지 않았습니다</h3>
                        <p>대회 시간표는 대회 관리자가 등록할 예정입니다.</p>
                    </div>
                <?php else: ?>
                    <?php if (isset($schedule['timetable_rows'])): ?>
                        <!-- 푸시된 타임테이블 데이터 표시 (시간 포함) -->
                        <div class="timetable-info" style="background: #334155; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #475569;">
                            <p style="margin: 0; color: #94a3b8; font-size: 14px;">
                                <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 16px;">schedule</span>
                                마지막 업데이트: <?= htmlspecialchars($schedule['generated_at'] ?? '') ?>
                            </p>
                        </div>
                        
                        <!-- 전문적인 표 형태 타임테이블 -->
                        <div class="professional-timetable" style="background: white; border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden; overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.9em; table-layout: fixed; background: white; min-width: 800px;" class="timetable-main">
                                <thead>
                                    <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                        <th style="padding: 8px 6px; text-align: center; font-weight: 700; color: white; width: 80px; font-size: 0.85em;">⏰ 시간</th>
                                        <th style="padding: 8px 6px; text-align: center; font-weight: 700; color: white; width: 50px; font-size: 0.85em;">No.</th>
                                        <th style="padding: 8px 6px; text-align: left; font-weight: 700; color: white; width: 200px; font-size: 0.85em;">🏆 경기 종목</th>
                                        <th style="padding: 8px 6px; text-align: center; font-weight: 700; color: white; width: 180px; font-size: 0.85em;">💃 댄스</th>
                                        <th style="padding: 8px 6px; text-align: center; font-weight: 700; color: white; width: 100px; font-size: 0.85em;">🎯 라운드</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // 시간순으로 정렬
                                    $all_rows = $schedule['timetable_rows'];
                                    usort($all_rows, function($a, $b) {
                                        return strcmp($a['start_time'] ?? '', $b['start_time'] ?? '');
                                    });
                                    
                                    // 같은 경기의 여러 라운드 그룹 찾기
                                    $multi_round_groups = [];
                                    $event_count = [];
                                    
                                    // 각 경기명별로 몇 개의 라운드가 있는지 계산
                                    foreach ($all_rows as $row) {
                                        $event_name = $row['desc'] ?? $row['title'] ?? '';
                                        if (!empty($event_name)) {
                                            if (!isset($event_count[$event_name])) {
                                                $event_count[$event_name] = 0;
                                            }
                                            $event_count[$event_name]++;
                                        }
                                    }
                                    
                                    // 모든 이벤트를 흰색 배경으로 통일
                                    $multi_round_groups = [];
                                    
                                    
                                    // 이벤트별 행 수 계산 (숫자 이벤트만)
                                    $event_rows = [];
                                    foreach ($all_rows as $row) {
                                        $event_no = $row['no'] ?? '';
                                        // 숫자 이벤트만 그룹화 (특별 이벤트 제외)
                                        if (!empty($event_no) && is_numeric($event_no)) {
                                            if (!isset($event_rows[$event_no])) {
                                                $event_rows[$event_no] = 0;
                                            }
                                            $event_rows[$event_no]++;
                                        }
                                    }
                                    
                                    // 각 이벤트의 첫 번째 행 인덱스 찾기
                                    $first_row_indices = [];
                                    $event_first_occurrence = [];
                                    
                                    foreach ($all_rows as $index => $row) {
                                        $event_no = $row['no'] ?? '';
                                        if (!empty($event_no) && is_numeric($event_no)) {
                                            if (!isset($event_first_occurrence[$event_no])) {
                                                $event_first_occurrence[$event_no] = $index;
                                                $first_row_indices[$index] = true;
                                            }
                                        } else {
                                            // 특별 이벤트는 모두 첫 번째 행
                                            $first_row_indices[$index] = true;
                                        }
                                    }
                                    
                                    foreach ($all_rows as $index => $row): 
                                        $event_no = $row['no'] ?? '';
                                        $is_numeric_event = !empty($event_no) && is_numeric($event_no);
                                        $is_first_in_event = isset($first_row_indices[$index]);
                                        
                                        if ($is_numeric_event) {
                                            $event_row_count = $event_rows[$event_no] ?? 1;
                                        } else {
                                            // 특별 이벤트는 개별 처리
                                            $event_row_count = 1;
                                        }
                                        
                                        // 모든 행을 흰색 배경으로 통일
                                        $bg_color = '#ffffff';
                                        $accent_color = '#3b82f6';
                                    ?>
                                        <tr style="background: <?= $bg_color ?>; border-bottom: 1px solid #e5e7eb;">
                                            
                                            <!-- 시간 -->
                                            <?php if ($is_first_in_event): ?>
                                            <td <?= $is_numeric_event && $event_row_count > 1 ? 'rowspan="' . $event_row_count . '"' : '' ?> style="padding: 4px 3px; text-align: center; color: #1e40af; font-weight: 700; font-size: 0.8em; border-right: 1px solid #e5e7eb; vertical-align: middle;">
                                                <div style="display: flex; flex-direction: column; align-items: center; line-height: 1;">
                                                    <span style="font-size: 0.9em; font-weight: 800; color: #1e40af;">
                                                        <?= htmlspecialchars($row['start_time'] ?? '') ?>
                                                    </span>
                                                    <?php if (!empty($row['end_time'])): ?>
                                                        <span style="font-size: 0.7em; color: #64748b; margin-top: 1px;">
                                                            ~ <?= htmlspecialchars($row['end_time']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- 이벤트 번호 -->
                                            <td <?= $is_numeric_event && $event_row_count > 1 ? 'rowspan="' . $event_row_count . '"' : '' ?> style="padding: 4px 3px; text-align: center; font-weight: 600; border-right: 1px solid #e5e7eb; vertical-align: middle;">
                                                <?php if (!empty($row['no'])): ?>
                                                    <span style="background: <?= $accent_color ?>; color: white; padding: 2px 4px; border-radius: 4px; font-size: 0.8em; font-weight: 700;">
                                                        <?= htmlspecialchars($row['no']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            
                                            <!-- 경기 종목 -->
                                            <td style="padding: 4px 3px; border-right: 1px solid #e5e7eb;">
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <?php if (!empty($row['detail_no'])): ?>
                                                        <span style="background: linear-gradient(135deg, #64748b, #475569); color: white; padding: 1px 3px; border-radius: 3px; font-size: 0.7em; font-weight: 600; min-width: 25px; text-align: center; flex-shrink: 0;">
                                                            <?= htmlspecialchars($row['detail_no']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span style="color: #1f2937; font-weight: 500; font-size: 0.8em; line-height: 1.1;">
                                                        <?= htmlspecialchars($row['title'] ?? $row['desc'] ?? '경기 종목') ?>
                                                    </span>
                                                </div>
                                            </td>
                                            
                                            <!-- 댄스 종목 -->
                                            <td style="padding: 4px 3px; text-align: center; white-space: nowrap; border-right: 1px solid #e5e7eb;">
                                                <?php if (!empty($row['dances']) && is_array($row['dances'])): ?>
                                                    <div style="display: flex; gap: 2px; justify-content: center; flex-wrap: nowrap;">
                                                        <?php
                                                        // 공용 댄스 매핑 데이터 사용
                                                        require_once __DIR__ . '/data/dance_mapping.php';
                                                        foreach ($row['dances'] as $dance):
                                                        ?>
                                                            <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 1px 3px; border-radius: 3px; font-size: 0.65em; font-weight: 600;">
                                                                <?= htmlspecialchars(getDanceName($dance)) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- 라운드 -->
                                            <td style="padding: 4px 3px; text-align: center; white-space: nowrap;">
                                                <?php if (!empty($row['roundtype'])): ?>
                                                    <?php 
                                                    $roundtype = $row['roundtype'];
                                                    $roundnum = $row['roundnum'] ?? '';
                                                    
                                                    // 라운드별 아이콘
                                                    $round_icon = '🏁';
                                                    $round_color = '#059669';
                                                    if (strpos(strtolower($roundtype), 'round') !== false) {
                                                        $round_icon = '1️⃣';
                                                        $round_color = '#1d4ed8';
                                                    } elseif (strpos(strtolower($roundtype), 'semi') !== false) {
                                                        $round_icon = '🥈';
                                                        $round_color = '#d97706';
                                                    } elseif (strpos(strtolower($roundtype), 'final') !== false) {
                                                        $round_icon = '🏆';
                                                        $round_color = '#059669';
                                                    }
                                                    
                                                    // roundtype에 이미 숫자가 포함되어 있으면 roundnum 추가하지 않음
                                                    $display_text = $roundtype;
                                                    if (!empty($roundnum) && $roundnum !== '' && !preg_match('/\d/', $roundtype)) {
                                                        $display_text = $roundtype . ' ' . $roundnum;
                                                    }
                                                    ?>
                                                    <div style="display: flex; align-items: center; justify-content: center; gap: 2px; flex-wrap: nowrap;">
                                                        <span style="font-size: 0.8em;"><?= $round_icon ?></span>
                                                        <span style="color: <?= $round_color ?>; font-weight: 700; font-size: 0.7em;">
                                                            <?= htmlspecialchars($display_text) ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif (isset($schedule['events'])): ?>
                        <!-- 기존 이벤트 데이터 표시 (호환성) -->
                        <div class="timetable-info" style="background: #334155; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #475569;">
                            <p style="margin: 0; color: #94a3b8; font-size: 14px;">
                                <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 16px;">schedule</span>
                                마지막 업데이트: <?= htmlspecialchars($schedule['generated_at'] ?? '') ?>
                            </p>
                        </div>
                        
                        <div class="item-list">
                            <?php foreach ($schedule['events'] as $event): ?>
                                <div class="item-card">
                                    <div class="item-header">
                                        <h3 class="item-title">
                                            <span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; margin-right: 8px;">
                                                <?= htmlspecialchars($event['no'] ?? '') ?>번
                                            </span>
                                            <?= htmlspecialchars($event['desc'] ?? '경기 종목') ?>
                                        </h3>
                                        <span class="item-date">
                                            <?= htmlspecialchars($event['roundtype'] ?? '') ?>
                                            <?php if (!empty($event['roundnum']) && $event['roundnum'] !== ''): ?>
                                                <?= htmlspecialchars($event['roundnum']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- 기존 schedule 데이터 표시 (호환성) -->
                        <div class="item-list">
                            <?php foreach ($schedule as $item): ?>
                                <div class="item-card">
                                    <div class="item-header">
                                        <h3 class="item-title"><?= htmlspecialchars($item['title'] ?? '경기 종목') ?></h3>
                                        <span class="item-date"><?= htmlspecialchars($item['time'] ?? '') ?></span>
                                    </div>
                                    <div class="item-content">
                                        <?= htmlspecialchars($item['description'] ?? '') ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php elseif ($page === 'notices'): ?>
                <!-- 공지사항 -->
                <h2 class="section-title">
                    <span class="material-symbols-rounded">campaign</span>
                    공지사항
                </h2>
                
                <?php if (empty($notices)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-rounded">campaign</div>
                        <h3>공지사항이 아직 등록되지 않았습니다</h3>
                        <p>중요한 공지사항이 있을 때 이곳에 표시됩니다.</p>
                    </div>
                <?php else: ?>
                    <div class="item-list">
                        <?php foreach ($notices as $notice): ?>
                            <div class="item-card">
                                <div class="item-header">
                                    <h3 class="item-title"><?= htmlspecialchars($notice['title'] ?? '공지') ?></h3>
                                    <span class="item-date"><?= isset($notice['date']) ? date('Y-m-d', strtotime($notice['date'])) : '' ?></span>
                                </div>
                                <div class="item-content">
                                    <?= nl2br(htmlspecialchars($notice['content'] ?? '')) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($page === 'results'): ?>
                <!-- 종합결과 -->
                <h2 class="section-title">
                    <span class="material-symbols-rounded">trophy</span>
                    종합결과
                </h2>
                
                <!-- 실시간 결과 표시 - Live TV 형식 -->
                <div id="live-results-container" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.2);">
                    <h3 style="color: white; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                        <span class="material-symbols-rounded">live_tv</span>
                        실시간 경기 결과
                    </h3>
                    
                    <!-- 로딩 표시 -->
                    <div id="live-loading" style="text-align: center; padding: 40px; color: white;">
                        <div class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.8; animation: pulse 2s infinite;">refresh</div>
                        <p>실시간 결과를 로딩 중입니다...</p>
                    </div>
                    
                    <!-- Live TV 결과 표시 영역 -->
                    <div id="live-tv-content" style="display: none; max-height: 600px; overflow-y: auto; padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.1);">
                        <div style="text-align: center; margin-bottom: 20px; color: white;">
                            <h4 id="event-title" style="font-size: 1.8em; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">이벤트 정보 로딩 중...</h4>
                            <p id="advancement-text" style="font-size: 1.3em; font-weight: bold; margin: 10px 0; color: #ffeb3b;"></p>
                            <p id="recall-info" style="font-size: 1.1em; margin: 10px 0; color: #e3f2fd;"></p>
                        </div>
                        
                        <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 3px 10px rgba(0,0,0,0.2);">
                            <table id="results-table" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white;">
                                        <th style="padding: 15px; text-align: center; font-weight: bold;">Marks</th>
                                        <th style="padding: 15px; text-align: center; font-weight: bold;">Tag</th>
                                        <th style="padding: 15px; text-align: center; font-weight: bold;">Competitor Name(s)</th>
                                        <th style="padding: 15px; text-align: center; font-weight: bold;">From</th>
                                    </tr>
                                </thead>
                                <tbody id="results-tbody">
                                    <!-- 결과 데이터가 여기에 동적으로 추가됩니다 -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div id="last-updated" style="text-align: center; margin-top: 15px; color: white; font-size: 0.9em; opacity: 0.8;"></div>
                    </div>
                    
                    <!-- 에러 메시지 영역 -->
                    <div id="error-message" style="display: none; background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; text-align: center; border: 1px solid #f5c6cb;">
                        <p>실시간 결과를 불러올 수 없습니다. 잠시 후 다시 시도해주세요.</p>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <button onclick="loadLiveTvResults()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 0.9em; margin-right: 10px;">새로고침</button>
                        <span style="font-size: 12px; color: rgba(255,255,255,0.8);">
                            <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 16px;">refresh</span>
                            30초마다 자동 갱신됩니다. 최신 결과가 아닐 경우 새로고침(F5) 해주세요.
                        </span>
                    </div>
                </div>
                
                <!-- 이벤트별 결과 목록 -->
                <div id="event-results-container">
                    <h3 style="color: #3b82f6; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded">list</span>
                        이벤트별 결과
                    </h3>
                    
                    <?php 
                    // RunOrder에서 시간표 순서대로 이벤트 목록 가져오기
                    $comp_data_dir = __DIR__ . '/comp/data/' . str_replace('comp_', '', $comp_id);
                    $events_list = getEventsFromRunOrder($comp_data_dir);
                    
                    // Results 폴더에서 실제 결과 파일이 있는 이벤트 확인
                    $results_dir = $comp_data_dir . '/Results';
                    foreach ($events_list as &$event) {
                        // 세부번호가 있는 경우와 없는 경우를 구분하여 결과 파일 경로 확인
                        if (!empty($event['detail_no']) && $event['detail_no'] !== $event['event_no']) {
                            // 세부번호가 있는 경우: Event_1-1, Event_1-2 등으로 폴더명 구성
                            $event_folder = 'Event_' . $event['event_no'] . '-' . $event['detail_no'];
                            $event_result_file = $results_dir . '/' . $event_folder . '/' . $event_folder . '_result.html';
                        } else {
                            // 세부번호가 없는 경우: 기존 방식
                            $event_folder = 'Event_' . $event['event_no'];
                            $event_result_file = $results_dir . '/' . $event_folder . '/' . $event_folder . '_result.html';
                        }
                        
                        $event['has_result'] = file_exists($event_result_file);
                        $event['result_file_path'] = $event['has_result'] ? $event_result_file : null;
                        $event['event_folder'] = $event_folder; // 폴더명 저장
                    }
                    ?>
                    
                    <!-- 이벤트 리스트 -->
                    <div class="events-list" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <?php if (empty($events_list)): ?>
                            <div style="padding: 40px; text-align: center; color: #6b7280;">
                                <span class="material-symbols-rounded" style="font-size: 48px; opacity: 0.5; margin-bottom: 16px;">event_note</span>
                                <p>등록된 이벤트가 없습니다.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($events_list as $index => $event): ?>
                                <?php 
                                $detail_no = !empty($event['detail_no']) && $event['detail_no'] !== $event['event_no'] ? $event['detail_no'] : null;
                                $onclick_function = $event['has_result'] ? "showEventResult({$event['event_no']}, '" . htmlspecialchars($event['event_name'], ENT_QUOTES) . "'" . ($detail_no ? ", '{$detail_no}'" : '') . ")" : '';
                                ?>
                                <div class="event-list-item" onclick="<?php echo $onclick_function; ?>" 
                                     style="padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; cursor: <?php echo $event['has_result'] ? 'pointer' : 'default'; ?>; transition: background-color 0.2s; <?php echo $index === 0 ? 'border-top: none;' : ''; ?> <?php echo $event['has_result'] ? '' : 'opacity: 0.6;'; ?>">
                                    
                                    <div style="display: flex; align-items: center; gap: 16px;">
                                        <div style="background: <?php echo $event['has_result'] ? '#3b82f6' : '#9ca3af'; ?>; color: white; padding: 8px 12px; border-radius: 6px; font-weight: 600; min-width: 60px; text-align: center;">
                                            <?php echo $event['display_number']; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #1f2937; margin-bottom: 4px;">
                                                <?php echo htmlspecialchars($event['event_name']); ?>
                                            </div>
                                            <div style="color: #6b7280; font-size: 14px;">
                                                <?php echo htmlspecialchars($event['round']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <?php if ($event['has_result']): ?>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span style="background: #10b981; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                                    결과 완료
                                                </span>
                                                <button onclick="event.stopPropagation(); showEventResult(<?php echo $event['event_no']; ?>, '<?php echo htmlspecialchars($event['event_name'], ENT_QUOTES); ?>'<?php echo $detail_no ? ", '{$detail_no}'" : ''; ?>)" 
                                                        style="background: #3b82f6; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 12px; cursor: pointer;">
                                                    결과보기
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="background: #f3f4f6; color: #6b7280; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                대기 중
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 구식 이벤트 결과들도 표시 (호환성) -->
                    <?php 
                    $legacy_event_results = [];
                    
                    if (is_dir($comp_data_dir)) {
                        // results_*.json 파일들 찾기 (기존 방식)
                        $result_files = glob($comp_data_dir . '/results_*.json');
                        foreach ($result_files as $file) {
                            $result_data = json_decode(file_get_contents($file), true);
                            if ($result_data) {
                                $legacy_event_results[] = $result_data;
                            }
                        }
                        
                        // players_hits_*.json 파일들도 결과로 간주
                        $hits_files = glob($comp_data_dir . '/players_hits_*.json');
                        foreach ($hits_files as $file) {
                            $hits_data = json_decode(file_get_contents($file), true);
                            if ($hits_data) {
                                // 파일명에서 이벤트 번호 추출
                                $filename = basename($file);
                                if (preg_match('/players_hits_(\d+)\.json/', $filename, $matches)) {
                                    $event_no = $matches[1];
                                    
                                    // RunOrder에 없는 이벤트만 추가
                                    $exists_in_runorder = false;
                                    foreach ($events_list as $existing) {
                                        if ($existing['event_no'] == $event_no) {
                                            $exists_in_runorder = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!$exists_in_runorder) {
                                        // RunOrder_Tablet.txt에서 이벤트 정보 찾기
                                        $event_name = "이벤트 {$event_no}";
                                        $round = "Final";
                                        
                                        $runorder_file = $comp_data_dir . '/RunOrder_Tablet.txt';
                                        if (file_exists($runorder_file)) {
                                            $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                                            foreach ($lines as $line) {
                                                if (preg_match('/^bom/', $line)) continue;
                                                $cols = array_map('trim', explode(',', $line));
                                                if (count($cols) >= 4 && $cols[0] == $event_no) {
                                                    $event_name = $cols[1];
                                                    $round = $cols[2];
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        $legacy_event_results[] = [
                                            'event_no' => $event_no,
                                            'event_name' => $event_name,
                                            'round' => $round,
                                            'source' => 'hits'
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    ?>
                    
                    <!-- 호환성을 위한 구식 결과 표시 (필요시) -->
                    <?php if (!empty($legacy_event_results)): ?>
                        <div style="margin-top: 20px; padding: 16px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #6c757d;">
                            <h4 style="color: #6c757d; margin-bottom: 12px; font-size: 14px;">이전 형식 결과</h4>
                            <div style="font-size: 12px; color: #6c757d;">
                                <?php foreach ($legacy_event_results as $result): ?>
                                    <span style="margin-right: 12px;">이벤트 <?php echo $result['event_no']; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 이벤트 결과 모달 -->
                <div id="eventResultModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 99999; overflow: auto;">
                    <div style="position: relative; background: white; margin: 2% auto; max-width: 95%; min-height: 90%; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="position: sticky; top: 0; background: white; padding: 20px; border-bottom: 1px solid #e5e7eb; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; z-index: 10;">
                            <h3 id="modalEventTitle" style="margin: 0; color: #1f2937; display: flex; align-items: center; gap: 12px;">
                                <span class="material-symbols-rounded" style="color: #3b82f6;">emoji_events</span>
                                이벤트 결과
                            </h3>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <button id="downloadResultBtn" onclick="downloadEventResult()" style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">
                                    <span class="material-symbols-rounded" style="font-size: 18px;">download</span>
                                    다운로드
                                </button>
                                <button onclick="closeEventModal()" style="background: #ef4444; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold;">
                                    ✕ 닫기
                                </button>
                            </div>
                        </div>
                        <div id="modalEventContent" style="padding: 20px; min-height: 400px; background: white; border-radius: 8px;">
                            <div style="text-align: center; padding: 40px; color: #6b7280;">
                                <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">hourglass_empty</span>
                                <p>결과를 불러오는 중...</p>
                            </div>
                        </div>
                    </div>
                </div>


            <?php elseif ($page === 'live'): ?>
                <!-- 실시간 결과 -->
                <h2 class="section-title">
                    <span class="material-symbols-rounded">live_tv</span>
                    실시간 결과
                </h2>
                
                <div class="empty-state">
                    <div class="material-symbols-rounded">live_tv</div>
                    <h3>실시간 결과 서비스 준비 중</h3>
                    <p>대회 진행 중 실시간으로 결과를 확인할 수 있습니다.</p>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <script>
        // 인쇄 기능
        function printTimetable() {
            // 타임테이블만 인쇄하도록 설정
            const originalTitle = document.title;
            document.title = "<?= htmlspecialchars($competition['title']) ?> - 타임테이블";
            
            // 잠시 후 인쇄 실행
            setTimeout(() => {
                window.print();
                document.title = originalTitle;
            }, 100);
        }
        
        // 실시간 결과 로드
        function loadLiveResults() {
            const compId = "<?= htmlspecialchars($comp_id) ?>";
            const resultsContainer = document.getElementById('live-results-content');
            
            if (!resultsContainer) return;
            
            // 실시간 결과 API 호출
            fetch(`/comp/live_aggregation_api.php?comp_id=${compId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.results) {
                        displayLiveResults(data.results);
                    } else {
                        resultsContainer.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #94a3b8;">
                                <div class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">schedule</div>
                                <p>현재 진행 중인 경기가 없습니다.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading live results:', error);
                    resultsContainer.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #ef4444;">
                            <div class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px;">error</div>
                            <p>실시간 결과를 불러올 수 없습니다.</p>
                        </div>
                    `;
                });
        }
        
        // 실시간 결과 표시
        function displayLiveResults(results) {
            const resultsContainer = document.getElementById('live-results-content');
            
            let html = '';
            
            results.forEach(result => {
                html += `
                    <div style="background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 12px; padding: 20px; margin-bottom: 16px;">
                        <h4 style="color: #3b82f6; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span class="material-symbols-rounded">emoji_events</span>
                            ${result.event_no} ${result.event_name || '경기 종목'} - ${result.round || 'Final'}
                        </h4>
                        
                        <div style="color: #94a3b8; font-size: 14px; margin-bottom: 16px;">
                            <div style="margin-bottom: 8px;">
                                <strong>총 심사위원:</strong> ${result.total_judges || 0}
                            </div>
                            <div style="margin-bottom: 8px;">
                                <strong>완료된 심사위원:</strong> ${result.completed_judges || 0}
                            </div>
                            <div style="margin-bottom: 16px;">
                                <strong>진행률:</strong> ${result.progress || 0}%
                            </div>
                        </div>
                        
                        <div style="background: rgba(59, 130, 246, 0.1); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <h5 style="color: #3b82f6; margin-bottom: 12px; font-size: 16px;">집계 결과</h5>
                            <div style="color: #94a3b8; font-size: 14px; margin-bottom: 12px;">
                                <strong>진출 기준:</strong> Recall ${result.recall_threshold || 0}개 이상 (상위 ${result.advancing_count || 0}팀)
                            </div>
                            
                            ${result.rankings && result.rankings.length > 0 ? `
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                        <thead>
                                            <tr style="background: rgba(59, 130, 246, 0.2);">
                                                <th style="padding: 8px; text-align: center; border: 1px solid rgba(59, 130, 246, 0.3);">순위</th>
                                                <th style="padding: 8px; text-align: center; border: 1px solid rgba(59, 130, 246, 0.3);">등번호</th>
                                                <th style="padding: 8px; text-align: left; border: 1px solid rgba(59, 130, 246, 0.3);">선수명</th>
                                                <th style="padding: 8px; text-align: center; border: 1px solid rgba(59, 130, 246, 0.3);">Recall 점수</th>
                                                <th style="padding: 8px; text-align: center; border: 1px solid rgba(59, 130, 246, 0.3);">상태</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${result.rankings.map((ranking, index) => `
                                                <tr style="border-bottom: 1px solid rgba(59, 130, 246, 0.1);">
                                                    <td style="padding: 8px; text-align: center; font-weight: 600;">${index + 1}</td>
                                                    <td style="padding: 8px; text-align: center;">${ranking.player_no || ''}</td>
                                                    <td style="padding: 8px; text-align: left;">
                                                        ${ranking.player_name || ''}
                                                        ${ranking.exempt ? ' ⭐' : ''}
                                                    </td>
                                                    <td style="padding: 8px; text-align: center;">
                                                        ${ranking.exempt ? '면제' : (ranking.recall_score || 0)}
                                                    </td>
                                                    <td style="padding: 8px; text-align: center;">
                                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;
                                                            ${ranking.status === '진출' ? 'background: rgba(34, 197, 94, 0.2); color: #16a34a;' : 
                                                              ranking.status === '면제' ? 'background: rgba(59, 130, 246, 0.2); color: #3b82f6;' :
                                                              'background: rgba(239, 68, 68, 0.2); color: #dc2626;'}">
                                                            ${ranking.status || (ranking.exempt ? '면제' : '진출')}
                                                        </span>
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div style="margin-top: 12px; padding: 8px; background: rgba(59, 130, 246, 0.1); border-radius: 6px; text-align: center; font-size: 14px; font-weight: 600;">
                                    진출 팀: ${result.advancing_count || 0}팀 | 탈락 팀: ${result.eliminated_count || 0}팀
                                </div>
                            ` : '<p style="color: #94a3b8; text-align: center; padding: 20px;">결과 처리 중...</p>'}
                        </div>
                    </div>
                `;
            });
            
            resultsContainer.innerHTML = html || `
                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                    <div class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">schedule</div>
                    <p>현재 진행 중인 경기가 없습니다.</p>
                </div>
            `;
        }
        
        // 이벤트 결과 상세 보기
        function viewEventResult(eventNo, eventName) {
            const compId = "<?= htmlspecialchars($comp_id) ?>";
            
            // 로딩 표시
            const loadingDiv = document.createElement('div');
            loadingDiv.style.cssText = `
                position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                background: rgba(0,0,0,0.8); z-index: 9999; display: flex; 
                align-items: center; justify-content: center; color: white; font-size: 18px;
            `;
            loadingDiv.innerHTML = `
                <div style="text-align: center;">
                    <div style="font-size: 48px; margin-bottom: 20px;">⏳</div>
                    <div>${eventName} 상세 결과를 생성 중입니다...</div>
                </div>
            `;
            document.body.appendChild(loadingDiv);
            
            // 컴바인 리포트 생성 요청
            fetch(`/comp/generate_combined_report.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comp_id: compId,
                    event_no: eventNo,
                    event_name: eventName
                })
            })
            .then(response => response.json())
            .then(data => {
                document.body.removeChild(loadingDiv);
                
                if (data.success) {
                    // 새 창에서 결과 표시
                    const reportUrl = data.report_url;
                    window.open(reportUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
                } else {
                    alert('상세 결과 생성에 실패했습니다: ' + (data.message || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                document.body.removeChild(loadingDiv);
                console.error('Error:', error);
                alert('상세 결과 생성 중 오류가 발생했습니다: ' + error.message);
            });
        }
        
        // 페이지 로드 시 실시간 결과 로드
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = "<?= htmlspecialchars($page) ?>";
            if (currentPage === 'results') {
                loadLiveResults();
                // 30초마다 자동 새로고침
                setInterval(loadLiveResults, 30000);
            }
        });
        
        // Live TV 실시간 결과 JavaScript
        let liveTvUpdateInterval;
        let isLoading = false;
        
        // CSS 스타일 추가
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.5; }
                100% { opacity: 1; }
            }

            .qualified {
                background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%) !important;
                color: white !important;
                font-weight: bold;
            }

            .qualified td {
                border-bottom: 1px solid rgba(255,255,255,0.3) !important;
            }

            #results-table tr:nth-child(even) {
                background: #f8f9fa;
            }

            #results-table tr:hover {
                background: #e3f2fd;
            }

            #results-table td {
                padding: 12px 15px;
                text-align: center;
                border-bottom: 1px solid #eee;
            }

            /* 스크롤바 스타일링 */
            #live-tv-content::-webkit-scrollbar {
                width: 8px;
            }

            #live-tv-content::-webkit-scrollbar-track {
                background: rgba(255,255,255,0.1);
                border-radius: 4px;
            }

            #live-tv-content::-webkit-scrollbar-thumb {
                background: rgba(255,255,255,0.3);
                border-radius: 4px;
            }

            #live-tv-content::-webkit-scrollbar-thumb:hover {
                background: rgba(255,255,255,0.5);
            }

            /* Firefox 스크롤바 스타일링 */
            #live-tv-content {
                scrollbar-width: thin;
                scrollbar-color: rgba(255,255,255,0.3) rgba(255,255,255,0.1);
            }
        `;
        document.head.appendChild(style);
        
        // 실시간 결과 로드 함수
        function loadLiveTvResults() {
            if (isLoading) return;
            
            isLoading = true;
            showLoading();
            
            const urlParams = new URLSearchParams(window.location.search);
            const compId = urlParams.get('id') ? urlParams.get('id').replace('comp_', '') : '';
            // 이벤트 번호를 지정하지 않으면 최신 스코어링 파일이 있는 이벤트를 자동으로 찾음
            const apiUrl = `comp/live_scoring_monitor.php?comp_id=${compId}`;
            
            console.log('Loading live TV results from:', apiUrl);
            
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    console.log('Live TV API response:', data);
                    console.log('Detected event number:', data.event_no);
                    
                    if (data.success && data.live_tv) {
                        displayLiveTvResults(data.live_tv, data.event_no);
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
        function displayLiveTvResults(liveTvData, eventNo) {
            console.log('Displaying live TV data:', liveTvData, 'Event No:', eventNo);
            
            // 헤더 정보 업데이트
            const eventTitle = document.getElementById('event-title');
            const advancementText = document.getElementById('advancement-text');
            const recallInfo = document.getElementById('recall-info');
            
            if (eventTitle) {
                const titleText = eventNo ? `${eventNo}번 ` : '';
                eventTitle.textContent = titleText + (liveTvData.event_title || '이벤트 정보 없음');
            }
            if (advancementText) advancementText.textContent = liveTvData.advancement_text || '';
            if (recallInfo) recallInfo.textContent = liveTvData.recall_info || '';
            
            // 테이블 데이터 업데이트
            const tbody = document.getElementById('results-tbody');
            if (tbody) {
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
                            <td>${participant.name || ''} ${participant.qualified ? '<span style="margin-left: 10px; font-size: 1.2em; color: #10b981;">✅</span>' : ''}</td>
                            <td>${participant.from || ''}</td>
                        `;
                        
                        tbody.appendChild(row);
                    });
                } else {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="4">경기 결과가 없습니다.</td>';
                    tbody.appendChild(row);
                }
            }
            
            // 업데이트 시간 표시
            const lastUpdated = document.getElementById('last-updated');
            if (lastUpdated && liveTvData.file_info && liveTvData.file_info.timestamp) {
                lastUpdated.textContent = `마지막 업데이트: ${liveTvData.file_info.timestamp}`;
            }
            
            // Live TV 컨텐츠 표시
            const liveTvContent = document.getElementById('live-tv-content');
            if (liveTvContent) {
                liveTvContent.style.display = 'block';
            }
        }
        
        // 로딩 표시
        function showLoading() {
            const loadingEl = document.getElementById('live-loading');
            const contentEl = document.getElementById('live-tv-content');
            const errorEl = document.getElementById('error-message');
            
            if (loadingEl) loadingEl.style.display = 'block';
            if (contentEl) contentEl.style.display = 'none';
            if (errorEl) errorEl.style.display = 'none';
        }
        
        // 로딩 숨김
        function hideLoading() {
            const loadingEl = document.getElementById('live-loading');
            if (loadingEl) loadingEl.style.display = 'none';
        }
        
        // 에러 표시
        function showError() {
            const errorEl = document.getElementById('error-message');
            const contentEl = document.getElementById('live-tv-content');
            
            if (errorEl) errorEl.style.display = 'block';
            if (contentEl) contentEl.style.display = 'none';
        }
        
        // 에러 숨김
        function hideError() {
            const errorEl = document.getElementById('error-message');
            if (errorEl) errorEl.style.display = 'none';
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
        
        // results 페이지에서 live TV 초기화  
        const currentPage = "<?= htmlspecialchars($page) ?>";
        if (currentPage === 'results') {
            loadLiveTvResults();
            startAutoUpdate();
        }
        
        // 페이지 언로드 시 인터벌 클리어
        window.addEventListener('beforeunload', function() {
            if (liveTvUpdateInterval) {
                clearInterval(liveTvUpdateInterval);
            }
        });
        
        // 현재 모달의 이벤트 정보 저장
        let currentModalEvent = { eventNo: null, eventName: null };
        
        // 이벤트 결과 모달 표시 함수
        function showEventResult(eventNo, eventName, detailNo = null) {
            // 현재 이벤트 정보 저장
            currentModalEvent = { eventNo, eventName, detailNo };
            const modal = document.getElementById('eventResultModal');
            const title = document.getElementById('modalEventTitle');
            const content = document.getElementById('modalEventContent');
            
            // 모달 제목 설정
            const displayEventNo = detailNo ? `${eventNo}-${detailNo}` : eventNo;
            title.innerHTML = `
                <span class="material-symbols-rounded" style="color: #3b82f6;">emoji_events</span>
                이벤트 ${displayEventNo} - ${eventName || '결과'}
            `;
            
            // 로딩 표시
            content.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #6b7280;">
                    <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5; animation: spin 2s linear infinite;">hourglass_empty</span>
                    <p>결과를 불러오는 중...</p>
                </div>
            `;
            
            // 모달 표시
            console.log('Showing modal for event:', eventNo, eventName, detailNo);
            console.log('Modal element:', modal);
            console.log('Modal current display:', modal.style.display);
            
            if (!modal) {
                console.error('Modal element not found!');
                return;
            }
            
            // 모달창을 강제로 표시 - CSS 속성을 직접 설정
            modal.setAttribute('style', 'display: block !important; position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; background: rgba(0,0,0,0.8) !important; z-index: 99999 !important; visibility: visible !important; opacity: 1 !important; overflow: auto !important;');
            
            document.body.style.overflow = 'hidden';
            
            // 모달창이 실제로 DOM에 있는지 확인
            const modalInDOM = document.getElementById('eventResultModal');
            console.log('Modal in DOM:', modalInDOM);
            console.log('Modal parent:', modalInDOM ? modalInDOM.parentElement : 'none');
            console.log('Modal is connected:', modalInDOM ? modalInDOM.isConnected : false);
            
            console.log('Modal displayed, new display:', modal.style.display);
            console.log('Modal computed style:', window.getComputedStyle(modal).display);
            console.log('Modal position:', window.getComputedStyle(modal).position);
            console.log('Modal z-index:', window.getComputedStyle(modal).zIndex);
            console.log('Modal visibility:', window.getComputedStyle(modal).visibility);
            console.log('Modal opacity:', window.getComputedStyle(modal).opacity);
            
            // 모달창이 실제로 보이는지 테스트
            const modalRect = modal.getBoundingClientRect();
            console.log('Modal bounding rect:', modalRect);
            console.log('Modal width:', modalRect.width);
            console.log('Modal height:', modalRect.height);
            console.log('Modal top:', modalRect.top);
            console.log('Modal left:', modalRect.left);
            
            // 모달창에 강제로 빨간색 테두리 추가하여 시각적으로 확인
            modal.style.border = '5px solid red !important';
            console.log('Added red border to modal for debugging');
            
            // 결과 HTML 파일 직접 불러오기
            const compId = "<?= htmlspecialchars(str_replace('comp_', '', $comp_id)) ?>";
            let resultUrl;
            if (detailNo && detailNo !== eventNo) {
                // 세부번호가 있는 경우
                resultUrl = `comp/data/${compId}/Results/Event_${eventNo}-${detailNo}/Event_${eventNo}-${detailNo}_result.html`;
            } else {
                // 세부번호가 없는 경우
                resultUrl = `comp/data/${compId}/Results/Event_${eventNo}/Event_${eventNo}_result.html`;
            }
            
            console.log('Loading result file:', resultUrl);
            fetch(resultUrl)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (response.ok) {
                        return response.text();
                    } else {
                        throw new Error(`File not found: ${response.status}`);
                    }
                })
                .then(html => {
                    console.log('HTML content length:', html.length);
                    console.log('HTML preview:', html.substring(0, 200));
                    if (html.trim()) {
                        console.log('Setting content.innerHTML with HTML');
                        // HTML을 iframe에 로드하여 완전한 페이지로 표시
                        content.innerHTML = `
                            <div style="width: 100%; height: 80vh; border: none;">
                                <iframe src="${resultUrl}" 
                                        style="width: 100%; height: 100%; border: none; border-radius: 8px;"
                                        onload="console.log('Iframe loaded successfully')"
                                        onerror="console.error('Iframe load error')">
                                </iframe>
                            </div>
                        `;
                        console.log('Content set successfully with iframe');
                    } else {
                        console.log('HTML is empty, showing no content message');
                        content.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #6b7280;">
                                <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">description</span>
                                <h3>결과를 찾을 수 없습니다</h3>
                                <p>이벤트 ${displayEventNo}의 결과 파일이 아직 생성되지 않았습니다.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading event result:', error);
                    content.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #ef4444;">
                            <span class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px;">error</span>
                            <h3>오류가 발생했습니다</h3>
                            <p>결과를 불러올 수 없습니다. 잠시 후 다시 시도해주세요.</p>
                        </div>
                    `;
                });
        }
        
        // 이벤트 결과 다운로드 함수
        function downloadEventResult() {
            if (!currentModalEvent.eventNo) {
                alert('다운로드할 이벤트가 선택되지 않았습니다.');
                return;
            }
            
            const compId = "<?= htmlspecialchars(str_replace('comp_', '', $comp_id)) ?>";
            let resultUrl;
            let fileName;
            
            if (currentModalEvent.detailNo && currentModalEvent.detailNo !== currentModalEvent.eventNo) {
                // 세부번호가 있는 경우
                resultUrl = `comp/data/${compId}/Results/Event_${currentModalEvent.eventNo}-${currentModalEvent.detailNo}/Event_${currentModalEvent.eventNo}-${currentModalEvent.detailNo}_result.html`;
                fileName = `Event_${currentModalEvent.eventNo}-${currentModalEvent.detailNo}_${currentModalEvent.eventName || 'result'}.html`;
            } else {
                // 세부번호가 없는 경우
                resultUrl = `comp/data/${compId}/Results/Event_${currentModalEvent.eventNo}/Event_${currentModalEvent.eventNo}_result.html`;
                fileName = `Event_${currentModalEvent.eventNo}_${currentModalEvent.eventName || 'result'}.html`;
            }
            
            // 파일 다운로드
            const link = document.createElement('a');
            link.href = resultUrl;
            link.download = fileName;
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // 모달 닫기 함수
        function closeEventModal() {
            console.log('Closing modal');
            const modal = document.getElementById('eventResultModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            // 이벤트 정보 초기화
            currentModalEvent = { eventNo: null, eventName: null };
        }

        
        // 모달 배경 클릭 시 닫기
        document.getElementById('eventResultModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEventModal();
            }
        });

        // ESC 키로 모달 닫기
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventModal();
            }
        });
        
        // 로딩 애니메이션 CSS 추가
        if (!document.getElementById('modal-styles')) {
            const style = document.createElement('style');
            style.id = 'modal-styles';
            style.textContent = `
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                
                .event-list-item:hover {
                    background-color: #f8fafc !important;
                }
            `;
            document.head.appendChild(style);
        }
    </script>
</body>
</html>