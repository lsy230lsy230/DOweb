<?php
// 다국어 지원 테스트 페이지
session_start();
require_once __DIR__ . '/includes/language.php';

// 테스트용 대회 데이터
$test_competitions = [
    [
        'id' => 'test_001',
        'title' => '제12회 서초구청장배 댄스스포츠 대회',
        'date' => '2025-09-14',
        'place' => '서초구민회관',
        'status' => 'completed'
    ],
    [
        'id' => 'test_002', 
        'title' => '제3회 경기도지사배 전국장애인댄스스포츠선수권대회',
        'date' => '2025-09-21',
        'place' => '경기도청',
        'status' => 'upcoming'
    ]
];
?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('main_title') ?> - 다국어 테스트</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            color: #e2e8f0;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px;
            background: rgba(30, 41, 59, 0.6);
            border-radius: 20px;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .language-selector {
            margin-bottom: 30px;
            text-align: center;
        }
        .language-selector select {
            padding: 10px 20px;
            border-radius: 10px;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #e2e8f0;
            font-size: 16px;
        }
        .test-section {
            background: rgba(30, 41, 59, 0.6);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .test-section h2 {
            color: #3b82f6;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .competition-card {
            background: rgba(15, 23, 42, 0.8);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(59, 130, 246, 0.1);
        }
        .competition-card h3 {
            color: #10b981;
            margin-bottom: 10px;
        }
        .competition-meta {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 14px;
            color: #94a3b8;
        }
        .btn {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: transform 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
    </style>
    <script>
        function changeLanguage(langCode) {
            const url = new URL(window.location);
            url.searchParams.set('lang', langCode);
            window.location.href = url.toString();
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= __('main_title') ?></h1>
            <p><?= __('main_subtitle') ?></p>
            
            <div class="language-selector">
                <label for="lang-select">
                    <span class="material-symbols-rounded">language</span>
                    <?= __('language_select') ?>:
                </label>
                <select id="lang-select" onchange="changeLanguage(this.value)">
                    <option value="ko" <?= $lang->getCurrentLanguage() === 'ko' ? 'selected' : '' ?>>한국어</option>
                    <option value="ja" <?= $lang->getCurrentLanguage() === 'ja' ? 'selected' : '' ?>>日本語</option>
                    <option value="zh" <?= $lang->getCurrentLanguage() === 'zh' ? 'selected' : '' ?>>中文</option>
                    <option value="ru" <?= $lang->getCurrentLanguage() === 'ru' ? 'selected' : '' ?>>Русский</option>
                </select>
            </div>
        </div>

        <div class="test-section">
            <h2>
                <span class="material-symbols-rounded">translate</span>
                다국어 지원 테스트
            </h2>
            <p>현재 언어: <strong><?= $lang->getLanguageName($lang->getCurrentLanguage()) ?></strong></p>
            
            <h3>네비게이션 테스트:</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin: 20px 0;">
                <a href="#" class="btn"><?= __('nav_home') ?></a>
                <a href="#" class="btn"><?= __('nav_results') ?></a>
                <a href="#" class="btn"><?= __('nav_comprehensive') ?></a>
                <a href="#" class="btn"><?= __('nav_notice') ?></a>
                <a href="#" class="btn"><?= __('nav_manage') ?></a>
            </div>
        </div>

        <div class="test-section">
            <h2>
                <span class="material-symbols-rounded">event</span>
                <?= __('upcoming_competitions') ?>
            </h2>
            <?php foreach ($test_competitions as $comp): ?>
                <div class="competition-card">
                    <h3><?= htmlspecialchars($comp['title']) ?></h3>
                    <div class="competition-meta">
                        <span>
                            <span class="material-symbols-rounded">schedule</span>
                            <?= __('competition_date') ?>: <?= $comp['date'] ?>
                        </span>
                        <span>
                            <span class="material-symbols-rounded">location_on</span>
                            <?= __('competition_place') ?>: <?= htmlspecialchars($comp['place']) ?>
                        </span>
                        <span>
                            <span class="material-symbols-rounded">info</span>
                            <?= __('competition_status') ?>: <?= __('status_' . $comp['status']) ?>
                        </span>
                    </div>
                    <div style="margin-top: 15px;">
                        <a href="#" class="btn"><?= __('btn_view_results') ?></a>
                        <a href="#" class="btn"><?= __('btn_view_details') ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="test-section">
            <h2>
                <span class="material-symbols-rounded">check_circle</span>
                기능 테스트
            </h2>
            <p>✅ 언어 감지: <?= $lang->getCurrentLanguage() ?></p>
            <p>✅ 번역 로드: <?= count($lang->getSupportedLanguages()) ?>개 언어 지원</p>
            <p>✅ 세션 저장: <?= isset($_SESSION['language']) ? '활성화' : '비활성화' ?></p>
            <p>✅ URL 파라미터: <?= isset($_GET['lang']) ? '감지됨' : '없음' ?></p>
        </div>
    </div>
</body>
</html>
