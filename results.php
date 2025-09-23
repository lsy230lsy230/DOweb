<?php
session_start();

// 다국어 시스템 로드
require_once __DIR__ . '/lang/Language.php';
$lang = Language::getInstance();

// 대회 스케줄러 로드
require_once __DIR__ . '/data/scheduler.php';
$scheduler = new CompetitionScheduler();

// 국가 이름 매핑
function getCountryName($code) {
    $countries = [
        'KR' => '대한민국',
        'CN' => '중국',
        'JP' => '일본',
        'US' => '미국',
        'DE' => '독일',
        'UK' => '영국',
        'FR' => '프랑스',
        'IT' => '이탈리아',
        'RU' => '러시아',
        'AU' => '호주',
        'CA' => '캐나다',
        'SG' => '싱가포르',
        'HK' => '홍콩',
        'TW' => '대만',
        'TH' => '태국',
        'MY' => '말레이시아',
        'VN' => '베트남',
        'ID' => '인도네시아',
        'PH' => '필리핀',
        'IN' => '인도',
        'BR' => '브라질',
        'AR' => '아르헨티나',
        'MX' => '멕시코',
        'ES' => '스페인',
        'NL' => '네덜란드',
        'BE' => '벨기에',
        'CH' => '스위스',
        'AT' => '오스트리아',
        'SE' => '스웨덴',
        'NO' => '노르웨이',
        'DK' => '덴마크',
        'FI' => '핀란드',
        'PL' => '폴란드',
        'CZ' => '체코',
        'OTHER' => '기타'
    ];
    return $countries[$code] ?? $code;
}

// 완료된 대회들만 가져와서 최신순으로 정렬
$all_competitions = $scheduler->getAllCompetitions();
$completed_competitions = array_filter($all_competitions, function($comp) {
    return $comp['status'] === 'completed';
});

// 날짜 기준으로 최신순 정렬
usort($completed_competitions, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// 특정 대회의 결과 보기
$view_comp_id = $_GET['comp_id'] ?? '';
$selected_competition = null;
if ($view_comp_id) {
    foreach ($completed_competitions as $comp) {
        if ($comp['id'] === $view_comp_id) {
            $selected_competition = $comp;
            break;
        }
    }
}

// 결과 데이터 로드 함수
function getCompetitionResults($comp_data_path) {
    if (!$comp_data_path) return [];
    
    $results_file = $comp_data_path . '/results.json';
    if (file_exists($results_file)) {
        return json_decode(file_get_contents($results_file), true) ?: [];
    }
    return [];
}

$competition_results = [];
if ($selected_competition && isset($selected_competition['comp_data_path'])) {
    $competition_results = getCompetitionResults($selected_competition['comp_data_path']);
}

?>
<!DOCTYPE html>
<html lang="<?= $lang->getCurrentLang() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $selected_competition ? htmlspecialchars($selected_competition['title']) . ' - ' : '' ?><?= t('nav_results') ?> | DanceOffice</title>
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

        /* 대회 리스트 스타일 */
        .competitions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
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

        .card-date {
            color: #64748b;
            font-size: 14px;
            background: rgba(100, 116, 139, 0.2);
            padding: 4px 12px;
            border-radius: 12px;
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
            font-size: 14px;
        }

        .info-item .material-symbols-rounded {
            color: #3b82f6;
            font-size: 18px;
        }

        .country-flag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .country-flag .material-symbols-rounded {
            font-size: 16px;
        }

        /* 결과 상세 보기 */
        .results-detail {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 20px;
            padding: 32px;
        }

        .competition-info {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .competition-info h2 {
            color: #f1f5f9;
            font-size: 24px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .competition-info h2 .material-symbols-rounded {
            color: #3b82f6;
            font-size: 28px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .results-section {
            margin-top: 32px;
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

        .result-item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .result-title {
            font-size: 16px;
            font-weight: 600;
            color: #f1f5f9;
        }

        .result-content {
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

        .empty-state h3 {
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

            .results-detail {
                padding: 24px 20px;
            }

            .info-grid {
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

        <?php if ($selected_competition): ?>
        <!-- 특정 대회 결과 상세 보기 -->
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">
                    <span class="material-symbols-rounded">trophy</span>
                    <?= htmlspecialchars($selected_competition['title']) ?> 결과
                </h1>
                <p class="page-subtitle">
                    <?= $lang->formatDate($selected_competition['date']) ?> 개최
                </p>
            </div>
        </div>

        <div class="results-detail">
            <!-- 대회 정보 -->
            <div class="competition-info">
                <h2>
                    <span class="material-symbols-rounded">info</span>
                    대회 정보
                </h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="material-symbols-rounded">event</span>
                        <strong>대회명:</strong> <?= htmlspecialchars($selected_competition['title']) ?>
                    </div>
                    <div class="info-item">
                        <span class="material-symbols-rounded">schedule</span>
                        <strong>개최일:</strong> <?= $lang->formatDate($selected_competition['date']) ?>
                    </div>
                    <div class="info-item">
                        <span class="material-symbols-rounded">location_on</span>
                        <strong>장소:</strong> <?= htmlspecialchars($selected_competition['location']) ?>
                    </div>
                    <div class="info-item">
                        <span class="material-symbols-rounded">group</span>
                        <strong>주최:</strong> <?= htmlspecialchars($selected_competition['host']) ?>
                    </div>
                    <div class="info-item">
                        <span class="country-flag">
                            <span class="material-symbols-rounded">flag</span>
                            <?= getCountryName($selected_competition['country'] ?? 'KR') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- 종합 결과 -->
            <div class="results-section">
                <h2 class="section-title">
                    <span class="material-symbols-rounded">military_tech</span>
                    종합 결과
                </h2>

                <?php if (empty($competition_results)): ?>
                    <div class="empty-state">
                        <div class="material-symbols-rounded">trophy</div>
                        <h3>결과가 아직 발표되지 않았습니다</h3>
                        <p>대회 결과가 집계되면 이곳에 표시됩니다.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($competition_results as $result): ?>
                        <div class="result-item">
                            <div class="result-header">
                                <h3 class="result-title"><?= htmlspecialchars($result['category'] ?? '경기 종목') ?></h3>
                            </div>
                            <div class="result-content">
                                <?= nl2br(htmlspecialchars($result['summary'] ?? $result['content'] ?? '')) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 대회 목록으로 돌아가기 -->
            <div style="text-align: center; margin-top: 32px;">
                <a href="/results.php" class="back-btn">
                    <span class="material-symbols-rounded">list</span>
                    대회 목록으로 돌아가기
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- 대회 목록 보기 -->
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="page-title">
                    <span class="material-symbols-rounded">trophy</span>
                    <?= t('nav_results') ?>
                </h1>
                <p class="page-subtitle">
                    완료된 대회의 종합 결과를 확인하세요
                </p>
            </div>
        </div>

        <?php if (empty($completed_competitions)): ?>
            <div class="empty-state">
                <div class="material-symbols-rounded">trophy</div>
                <h3>완료된 대회가 없습니다</h3>
                <p>대회가 완료되면 이곳에 결과가 표시됩니다.</p>
            </div>
        <?php else: ?>
            <div class="competitions-grid">
                <?php foreach ($completed_competitions as $comp): ?>
                <a href="results.php?comp_id=<?= urlencode($comp['id']) ?>" class="competition-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><?= htmlspecialchars($comp['title'] ?? $comp['name']) ?></h3>
                        </div>
                        <span class="card-date"><?= date('Y.m.d', strtotime($comp['date'])) ?></span>
                    </div>
                    <div class="card-info">
                        <div class="info-item">
                            <span class="material-symbols-rounded">location_on</span>
                            <?= htmlspecialchars($comp['location'] ?? $comp['place']) ?>
                        </div>
                        <div class="info-item">
                            <span class="material-symbols-rounded">group</span>
                            <?= htmlspecialchars($comp['host']) ?>
                        </div>
                        <div class="info-item">
                            <span class="country-flag">
                                <span class="material-symbols-rounded">flag</span>
                                <?= getCountryName($comp['country'] ?? 'KR') ?>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>