<?php
session_start();

// 다국어 시스템 로드
require_once __DIR__ . '/lang/Language.php';
$lang = Language::getInstance();

// 대회 스케줄러 로드
require_once __DIR__ . '/data/scheduler.php';
$scheduler = new CompetitionScheduler();

// 모든 대회 가져오기
$all_competitions = $scheduler->getAllCompetitions();

// 상태별 분류
$upcoming = [];
$ongoing = [];
$completed = [];

foreach ($all_competitions as $comp) {
    switch ($comp['status']) {
        case 'upcoming':
            $upcoming[] = $comp;
            break;
        case 'ongoing':
            $ongoing[] = $comp;
            break;
        case 'completed':
            $completed[] = $comp;
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('nav_competitions') ?> | DanceOffice</title>
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
        .page-header {
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

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .page-header-content {
            position: relative;
            z-index: 2;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .page-title .material-symbols-rounded {
            color: #3b82f6;
            font-size: 36px;
        }

        .page-subtitle {
            color: #94a3b8;
            font-size: 16px;
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

        /* 대회 섹션 */
        .competition-section {
            margin-bottom: 40px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: #f1f5f9;
        }

        .section-title .material-symbols-rounded {
            color: #3b82f6;
            font-size: 28px;
        }

        .section-count {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        /* 대회 그리드 */
        .competitions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 24px;
        }

        .competition-card {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .competition-card:hover {
            border-color: rgba(59, 130, 246, 0.4);
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 8px;
        }

        .card-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .status-upcoming {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .status-ongoing {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .status-completed {
            background: rgba(100, 116, 139, 0.2);
            color: #64748b;
        }

        .card-info {
            space-y: 12px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .info-item .material-symbols-rounded {
            color: #3b82f6;
            font-size: 18px;
        }

        .card-description {
            color: #64748b;
            font-size: 14px;
            margin-top: 12px;
            line-height: 1.5;
        }

        /* 빈 상태 */
        .empty-section {
            text-align: center;
            padding: 60px 20px;
            background: rgba(30, 41, 59, 0.3);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 16px;
            color: #64748b;
        }

        .empty-section .material-symbols-rounded {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-section h3 {
            margin-bottom: 8px;
        }

        /* 모바일 대응 */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .page-title {
                font-size: 24px;
            }

            .competitions-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .competition-card {
                padding: 20px;
            }

            .section-title {
                font-size: 20px;
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

        <!-- 페이지 헤더 -->
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">
                    <span class="material-symbols-rounded">event</span>
                    <?= t('nav_competitions') ?>
                </h1>
                <p class="page-subtitle">
                    댄스스포츠 대회 일정과 결과를 확인하세요
                </p>
            </div>
        </div>

        <!-- 진행 예정 대회 -->
        <?php if (!empty($upcoming)): ?>
        <div class="competition-section">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="material-symbols-rounded">upcoming</span>
                    <?= t('status_upcoming') ?> <?= t('nav_competitions') ?>
                </h2>
                <span class="section-count"><?= count($upcoming) ?>개</span>
            </div>
            <div class="competitions-grid">
                <?php foreach ($upcoming as $comp): ?>
                <a href="/competition.php?id=<?= urlencode($comp['id']) ?>" class="competition-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><?= htmlspecialchars($comp['title'] ?? $comp['name']) ?></h3>
                        </div>
                        <span class="card-status status-upcoming"><?= t('status_upcoming') ?></span>
                    </div>
                    <div class="card-info">
                        <div class="info-item">
                            <span class="material-symbols-rounded">schedule</span>
                            <?= $lang->formatDate($comp['date']) ?>
                        </div>
                        <div class="info-item">
                            <span class="material-symbols-rounded">location_on</span>
                            <?= htmlspecialchars($comp['location'] ?? $comp['place']) ?>
                        </div>
                        <div class="info-item">
                            <span class="material-symbols-rounded">group</span>
                            <?= htmlspecialchars($comp['host']) ?>
                        </div>
                    </div>
                    <?php if (!empty($comp['description'])): ?>
                    <div class="card-description">
                        <?= htmlspecialchars($comp['description']) ?>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 진행 중인 대회 -->
        <?php if (!empty($ongoing)): ?>
        <div class="competition-section">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="material-symbols-rounded">live_tv</span>
                    <?= t('status_ongoing') ?> <?= t('nav_competitions') ?>
                </h2>
                <span class="section-count"><?= count($ongoing) ?>개</span>
            </div>
            <div class="competitions-grid">
                <?php foreach ($ongoing as $comp): ?>
                <a href="/competition.php?id=<?= urlencode($comp['id']) ?>" class="competition-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><?= htmlspecialchars($comp['title'] ?? $comp['name']) ?></h3>
                        </div>
                        <span class="card-status status-ongoing"><?= t('status_ongoing') ?></span>
                    </div>
                    <div class="card-info">
                        <div class="info-item">
                            <span class="material-symbols-rounded">schedule</span>
                            <?= $lang->formatDate($comp['date']) ?>
                        </div>
                        <div class="info-item">
                            <span class="material-symbols-rounded">location_on</span>
                            <?= htmlspecialchars($comp['location'] ?? $comp['place']) ?>
                        </div>
                        <div class="info-item">
                            <span class="material-symbols-rounded">group</span>
                            <?= htmlspecialchars($comp['host']) ?>
                        </div>
                    </div>
                    <?php if (!empty($comp['description'])): ?>
                    <div class="card-description">
                        <?= htmlspecialchars($comp['description']) ?>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 완료된 대회 -->
        <?php if (!empty($completed)): ?>
        <div class="competition-section">
            <div class="section-header">
                <h2 class="section-title">
                    <span class="material-symbols-rounded">trophy</span>
                    <?= t('status_completed') ?> <?= t('nav_competitions') ?>
                </h2>
                <span class="section-count"><?= count($completed) ?>개</span>
            </div>
            <div class="competitions-grid">
                <?php foreach ($completed as $comp): ?>
                <a href="/competition.php?id=<?= urlencode($comp['id']) ?>" class="competition-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><?= htmlspecialchars($comp['title'] ?? $comp['name']) ?></h3>
                        </div>
                        <span class="card-status status-completed"><?= t('status_completed') ?></span>
                    </div>
                    <div class="card-info">
                        <div class="info-item">
                            <span class="material-symbols-rounded">schedule</span>
                            <?= $lang->formatDate($comp['date']) ?>
                        </div>
                        <div class="info-item">
                            <span class="material-symbols-rounded">location_on</span>
                            <?= htmlspecialchars($comp['location'] ?? $comp['place']) ?>
                        </div>
                        <div class="info-item">
                            <span class="material-symbols-rounded">group</span>
                            <?= htmlspecialchars($comp['host']) ?>
                        </div>
                    </div>
                    <?php if (!empty($comp['description'])): ?>
                    <div class="card-description">
                        <?= htmlspecialchars($comp['description']) ?>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 대회가 없는 경우 -->
        <?php if (empty($upcoming) && empty($ongoing) && empty($completed)): ?>
        <div class="empty-section">
            <div class="material-symbols-rounded">event_busy</div>
            <h3>등록된 대회가 없습니다</h3>
            <p>아직 등록된 대회가 없습니다. 새로운 대회가 등록되면 이곳에 표시됩니다.</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>