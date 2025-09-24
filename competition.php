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
                
                <!-- 실시간 결과 표시 -->
                <div id="live-results-container" style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(59, 130, 246, 0.1); border-radius: 16px; padding: 24px; margin-bottom: 24px;">
                    <h3 style="color: #3b82f6; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded">live_tv</span>
                        실시간 경기 결과
                    </h3>
                    <div id="live-results-content">
                        <div style="text-align: center; padding: 40px; color: #94a3b8;">
                            <div class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">refresh</div>
                            <p>실시간 결과를 로딩 중입니다...</p>
                        </div>
                    </div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 16px; text-align: center;">
                        <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 16px;">refresh</span>
                        <span>30초마다 자동 갱신됩니다. 최신 결과가 아닐 경우 새로고침(F5) 해주세요.</span>
                    </div>
                </div>
                
                <!-- 이벤트별 결과 목록 -->
                <div id="event-results-container">
                    <h3 style="color: #3b82f6; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-rounded">list</span>
                        이벤트별 결과
                    </h3>
                    
                    <?php 
                    // 대회 데이터에서 결과 파일들 로드
                    $comp_data_dir = __DIR__ . '/comp/data/' . str_replace('comp_', '', $comp_id);
                    $event_results = [];
                    
                    if (is_dir($comp_data_dir)) {
                        // 1. results_*.json 파일들 찾기
                        $result_files = glob($comp_data_dir . '/results_*.json');
                        foreach ($result_files as $file) {
                            $result_data = json_decode(file_get_contents($file), true);
                            if ($result_data) {
                                $event_results[] = $result_data;
                            }
                        }
                        
                        // 2. players_hits_*.json 파일들도 결과로 간주
                        $hits_files = glob($comp_data_dir . '/players_hits_*.json');
                        foreach ($hits_files as $file) {
                            $hits_data = json_decode(file_get_contents($file), true);
                            if ($hits_data) {
                                // 파일명에서 이벤트 번호 추출
                                $filename = basename($file);
                                if (preg_match('/players_hits_(\d+)\.json/', $filename, $matches)) {
                                    $event_no = $matches[1];
                                    
                                    // RunOrder에서 해당 이벤트 정보 찾기
                                    $event_name = "이벤트 {$event_no}";
                                    $round = "Final";
                                    
                                    // RunOrder_Tablet.txt에서 이벤트 정보 찾기
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
                                    
                                    // 순위 데이터 변환
                                    $rankings = [];
                                    foreach ($hits_data as $rank => $players) {
                                        foreach ($players as $player_no) {
                                            $rankings[] = [
                                                'rank' => $rank,
                                                'player_no' => $player_no,
                                                'player_name' => "선수 {$player_no}"
                                            ];
                                        }
                                    }
                                    
                                    $event_results[] = [
                                        'event_no' => $event_no,
                                        'event_name' => $event_name,
                                        'round' => $round,
                                        'final_rankings' => $rankings,
                                        'source' => 'hits'
                                    ];
                                }
                            }
                        }
                        
                        // 3. RunOrder_Tablet.txt에서 완료된 이벤트들 찾기
                        $runorder_file = $comp_data_dir . '/RunOrder_Tablet.txt';
                        if (file_exists($runorder_file)) {
                            $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            foreach ($lines as $line) {
                                if (preg_match('/^bom/', $line)) continue;
                                $cols = array_map('trim', explode(',', $line));
                                if (count($cols) >= 4) {
                                    $event_no = $cols[0];
                                    $event_name = $cols[1];
                                    $round = $cols[2];
                                    
                                    // 이미 결과에 없는 이벤트만 추가
                                    $exists = false;
                                    foreach ($event_results as $existing) {
                                        if ($existing['event_no'] == $event_no) {
                                            $exists = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!$exists && !empty($event_no) && is_numeric($event_no)) {
                                        $event_results[] = [
                                            'event_no' => $event_no,
                                            'event_name' => $event_name,
                                            'round' => $round,
                                            'final_rankings' => [],
                                            'source' => 'runorder'
                                        ];
                                    }
                                }
                            }
                        }
                    }
                    ?>
                    
                    <?php if (empty($event_results)): ?>
                        <div class="empty-state">
                            <div class="material-symbols-rounded">trophy</div>
                            <h3>결과가 아직 발표되지 않았습니다</h3>
                            <p>대회 진행 중 결과가 이곳에 표시됩니다.</p>
                        </div>
                    <?php else: ?>
                        <div class="item-list">
                            <?php foreach ($event_results as $result): ?>
                                <div class="item-card">
                                    <div class="item-header">
                                        <h3 class="item-title">
                                            <span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; margin-right: 8px;">
                                                <?= htmlspecialchars($result['event_no'] ?? '') ?>번
                                            </span>
                                            <?= htmlspecialchars($result['event_name'] ?? '경기 종목') ?>
                                        </h3>
                                        <span class="item-date">
                                            <?= htmlspecialchars($result['round'] ?? '') ?>
                                        </span>
                                    </div>
                                    <div class="item-content">
                                        <?php if (isset($result['final_rankings']) && !empty($result['final_rankings'])): ?>
                                            <div style="margin-top: 12px;">
                                                <h4 style="color: #3b82f6; margin-bottom: 8px;">최종 순위</h4>
                                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px;">
                                                    <?php 
                                                    // 순위별로 그룹화
                                                    $rankings_by_rank = [];
                                                    foreach ($result['final_rankings'] as $ranking) {
                                                        $rank = $ranking['rank'];
                                                        if (!isset($rankings_by_rank[$rank])) {
                                                            $rankings_by_rank[$rank] = [];
                                                        }
                                                        $rankings_by_rank[$rank][] = $ranking;
                                                    }
                                                    ksort($rankings_by_rank);
                                                    
                                                    foreach ($rankings_by_rank as $rank => $players): 
                                                    ?>
                                                        <div style="background: rgba(59, 130, 246, 0.1); padding: 8px; border-radius: 6px; text-align: center;">
                                                            <strong style="color: #3b82f6; font-size: 14px;"><?= $rank ?>위</strong>
                                                            <div style="font-size: 0.9em; color: #94a3b8; margin-top: 4px;">
                                                                <?php foreach ($players as $player): ?>
                                                                    <div style="margin: 2px 0;"><?= htmlspecialchars($player['player_name'] ?? "선수 {$player['player_no']}") ?></div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p style="color: #94a3b8; text-align: center; padding: 20px;">
                                                <span class="material-symbols-rounded" style="vertical-align: middle; font-size: 18px;">schedule</span>
                                                결과 데이터를 처리 중입니다...
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
        
        // 페이지 로드 시 실시간 결과 로드
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = "<?= htmlspecialchars($page) ?>";
            if (currentPage === 'results') {
                loadLiveResults();
                // 30초마다 자동 새로고침
                setInterval(loadLiveResults, 30000);
            }
        });
    </script>
</body>
</html>