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
                <!-- 시간표 -->
                <h2 class="section-title">
                    <span class="material-symbols-rounded">schedule</span>
                    대회 시간표
                </h2>
                
                <?php if (empty($schedule)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-rounded">schedule</div>
                        <h3>시간표가 아직 등록되지 않았습니다</h3>
                        <p>대회 시간표는 대회 관리자가 등록할 예정입니다.</p>
                    </div>
                <?php else: ?>
                    <?php if (isset($schedule['timetable_rows'])): ?>
                        <!-- 푸시된 타임테이블 데이터 표시 (시간 포함) -->
                        <div class="timetable-info" style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h3 style="margin: 0 0 10px 0; color: #3b82f6;">
                                <span class="material-symbols-rounded" style="vertical-align: middle;">info</span>
                                타임테이블 정보
                            </h3>
                            <p style="margin: 5px 0;"><strong>마지막 업데이트:</strong> <?= htmlspecialchars($schedule['generated_at'] ?? '') ?></p>
                            <p style="margin: 5px 0;"><strong>대회 시작:</strong> <?= htmlspecialchars($schedule['start_time'] ?? '09:00') ?></p>
                            <p style="margin: 5px 0;"><strong>개회식 시간:</strong> <?= htmlspecialchars($schedule['opening_time'] ?? '10:30') ?></p>
                            <p style="margin: 5px 0;"><strong>총 항목 수:</strong> <?= count($schedule['timetable_rows']) ?>개</p>
                        </div>
                        
                        <div class="item-list">
                            <?php 
                            $processed_groups = []; // 멀티이벤트 그룹 처리 추적
                            foreach ($schedule['timetable_rows'] as $index => $row): 
                                $is_multi = isset($row['is_multi_event']) && $row['is_multi_event'];
                                $is_first_in_group = isset($row['is_first_in_group']) && $row['is_first_in_group'];
                                
                                // 멀티이벤트 처리
                                if ($is_multi && isset($row['no'])) {
                                    $group_key = $row['no'] . '_' . $row['start_time'];
                                    if (in_array($group_key, $processed_groups)) {
                                        continue; // 이미 처리된 그룹은 건너뛰기
                                    }
                                    $processed_groups[] = $group_key;
                                    
                                    // 같은 그룹의 모든 이벤트 찾기
                                    $group_rows = array_filter($schedule['timetable_rows'], function($r) use ($row) {
                                        return isset($r['no']) && isset($row['no']) && 
                                               $r['no'] === $row['no'] && 
                                               $r['start_time'] === $row['start_time'] && 
                                               isset($r['is_multi_event']) && $r['is_multi_event'];
                                    });
                                }
                            ?>
                                <div class="item-card" style="<?= isset($row['is_opening']) && $row['is_opening'] ? 'border-left: 4px solid #f59e0b;' : (isset($row['is_special']) && $row['is_special'] ? 'border-left: 4px solid #10b981;' : 'border-left: 4px solid #3b82f6;') ?>">
                                    <div class="item-header" style="display: flex; align-items: center; gap: 15px;">
                                        <?php if ($is_multi && isset($row['no'])): ?>
                                            <!-- 멀티이벤트: 시간은 한 번만 표시 -->
                                            <div class="time-info" style="min-width: 120px; text-align: center;">
                                                <div style="color: #3b82f6; font-weight: bold; font-size: 1.1em;">
                                                    <?= htmlspecialchars($row['start_time'] ?? '') ?>
                                                </div>
                                                <div style="color: #64748b; font-size: 0.9em;">
                                                    <?= htmlspecialchars($row['end_time'] ?? '') ?>
                                                </div>
                                            </div>
                                            <div class="event-info" style="flex: 1;">
                                                <h3 class="item-title" style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                                    <span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em;">
                                                        <?= htmlspecialchars($row['no'] ?? '') ?>번
                                                    </span>
                                                    <span>멀티이벤트 (<?= count($group_rows) ?>개 세부 종목)</span>
                                                </h3>
                                                
                                                <!-- 세부 이벤트들 표시 -->
                                                <div style="margin-top: 10px;">
                                                    <?php foreach ($group_rows as $sub_row): ?>
                                                        <div style="padding: 8px; background: #f8fafc; border-radius: 4px; margin-bottom: 8px; border-left: 3px solid #3b82f6;">
                                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                                <span style="background: #64748b; color: white; padding: 1px 6px; border-radius: 3px; font-size: 0.75em;">
                                                                    <?= htmlspecialchars($sub_row['detail_no']) ?>
                                                                </span>
                                                                <strong><?= htmlspecialchars($sub_row['title']) ?></strong>
                                                            </div>
                                                            <?php if (!empty($sub_row['dances'])): ?>
                                                                <div style="margin-top: 4px; font-size: 0.9em; color: #64748b;">
                                                                    <strong>댄스:</strong> 
                                                                    <?php
                                                                    $dance_names = ['1' => 'W', '2' => 'T', '3' => 'V', '4' => 'F', '5' => 'Q', '6' => 'C', '7' => 'S', '8' => 'R', '9' => 'P', '10' => 'J'];
                                                                    $dances = array_map(function($d) use ($dance_names) {
                                                                        return $dance_names[$d] ?? $d;
                                                                    }, $sub_row['dances']);
                                                                    echo htmlspecialchars(implode(', ', $dances));
                                                                    ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                                <?php if (!empty($row['roundtype'])): ?>
                                                    <div style="font-size: 0.9em; color: #64748b; margin-top: 8px;">
                                                        <?= htmlspecialchars($row['roundtype']) ?>
                                                        <?php if (!empty($row['roundnum'])): ?>
                                                            <?= htmlspecialchars($row['roundnum']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <!-- 단일이벤트 또는 특별이벤트 -->
                                            <div class="time-info" style="min-width: 120px; text-align: center;">
                                                <div style="color: #3b82f6; font-weight: bold; font-size: 1.1em;">
                                                    <?= htmlspecialchars($row['start_time'] ?? '') ?>
                                                </div>
                                                <div style="color: #64748b; font-size: 0.9em;">
                                                    <?= htmlspecialchars($row['end_time'] ?? '') ?>
                                                </div>
                                            </div>
                                            <div class="event-info" style="flex: 1;">
                                                <h3 class="item-title" style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                                    <?php if (isset($row['is_opening']) && $row['is_opening']): ?>
                                                        <span style="background: #f59e0b; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em;">
                                                            개회식
                                                        </span>
                                                    <?php elseif (isset($row['is_special']) && $row['is_special']): ?>
                                                        <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em;">
                                                            특별
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8em;">
                                                            <?= htmlspecialchars($row['no'] ?? '') ?>번
                                                        </span>
                                                        <?php if (!empty($row['detail_no'])): ?>
                                                            <span style="background: #64748b; color: white; padding: 1px 6px; border-radius: 3px; font-size: 0.75em;">
                                                                <?= htmlspecialchars($row['detail_no']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <span><?= htmlspecialchars($row['title'] ?? '경기 종목') ?></span>
                                                </h3>
                                                <?php if (!empty($row['roundtype'])): ?>
                                                    <div style="font-size: 0.9em; color: #64748b; margin-top: 4px;">
                                                        <?= htmlspecialchars($row['roundtype']) ?>
                                                        <?php if (!empty($row['roundnum'])): ?>
                                                            <?= htmlspecialchars($row['roundnum']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-content">
                                        <?php if (!empty($row['dances'])): ?>
                                            <p><strong>댄스 종목:</strong> 
                                                <?php
                                                $dance_names = ['1' => 'W', '2' => 'T', '3' => 'V', '4' => 'F', '5' => 'Q', '6' => 'C', '7' => 'S', '8' => 'R', '9' => 'P', '10' => 'J'];
                                                $dances = array_map(function($d) use ($dance_names) {
                                                    return $dance_names[$d] ?? $d;
                                                }, $row['dances']);
                                                echo htmlspecialchars(implode(', ', $dances));
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (isset($row['duration'])): ?>
                                            <p><strong>예상 소요시간:</strong> <?= $row['duration'] ?>분
                                                <?php if (isset($row['extra_time']) && $row['extra_time'] > 0): ?>
                                                    (추가 <?= $row['extra_time'] ?>분)
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (isset($schedule['events'])): ?>
                        <!-- 기존 이벤트 데이터 표시 (호환성) -->
                        <div class="timetable-info" style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h3 style="margin: 0 0 10px 0; color: #3b82f6;">
                                <span class="material-symbols-rounded" style="vertical-align: middle;">info</span>
                                이벤트 목록
                            </h3>
                            <p style="margin: 5px 0;"><strong>마지막 업데이트:</strong> <?= htmlspecialchars($schedule['generated_at'] ?? '') ?></p>
                            <p style="margin: 5px 0;"><strong>총 이벤트 수:</strong> <?= count($schedule['events']) ?>개</p>
                            <p style="color: #f59e0b;"><strong>⚠️ 시간 정보가 없습니다. 다시 푸시해주세요.</strong></p>
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
                                            <?php if (!empty($event['roundnum'])): ?>
                                                <?= htmlspecialchars($event['roundnum']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="item-content">
                                        <?php if (!empty($event['dances'])): ?>
                                            <p><strong>댄스 종목:</strong> 
                                                <?php
                                                $dance_names = ['1' => 'W', '2' => 'T', '3' => 'V', '4' => 'F', '5' => 'Q', '6' => 'C', '7' => 'S', '8' => 'R', '9' => 'P', '10' => 'J'];
                                                $dances = array_map(function($d) use ($dance_names) {
                                                    return $dance_names[$d] ?? $d;
                                                }, $event['dances']);
                                                echo htmlspecialchars(implode(', ', $dances));
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (isset($event['extra_time']) && $event['extra_time'] > 0): ?>
                                            <p><strong>추가 시간:</strong> <?= $event['extra_time'] ?>분</p>
                                        <?php endif; ?>
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
                
                <?php if (empty($results)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-rounded">trophy</div>
                        <h3>결과가 아직 발표되지 않았습니다</h3>
                        <p>대회 종료 후 결과가 이곳에 표시됩니다.</p>
                    </div>
                <?php else: ?>
                    <div class="item-list">
                        <?php foreach ($results as $result): ?>
                            <div class="item-card">
                                <div class="item-header">
                                    <h3 class="item-title"><?= htmlspecialchars($result['category'] ?? '경기 종목') ?></h3>
                                    <span class="item-date">결과 발표</span>
                                </div>
                                <div class="item-content">
                                    <?= htmlspecialchars($result['summary'] ?? '') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

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
</body>
</html>