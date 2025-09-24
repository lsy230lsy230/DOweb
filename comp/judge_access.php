<?php
// 심사위원 접속 페이지
$lang = $_GET['lang'] ?? 'ko';
if (!in_array($lang, ['ko', 'en', 'zh', 'ja', 'ru'])) {
    $lang = 'ko';
}

// 언어별 텍스트
$texts = [
    'ko' => [
        'title' => '심사위원 접속',
        'subtitle' => '채점할 대회를 선택하세요',
        'competition' => '대회',
        'place' => '장소',
        'date' => '날짜',
        'login' => '로그인',
        'judge_id' => '심사위원 ID',
        'password' => '비밀번호',
        'enter' => '접속',
        'cancel' => '취소',
        'no_competitions' => '오늘 예정된 대회가 없습니다',
        'contact_admin' => '대회 관리자에게 문의하세요',
        'switch_lang' => 'English'
    ],
    'en' => [
        'title' => 'Judge Access',
        'subtitle' => 'Select a competition to score',
        'competition' => 'Competition',
        'place' => 'Venue',
        'date' => 'Date',
        'login' => 'Login',
        'judge_id' => 'Judge ID',
        'password' => 'Password',
        'enter' => 'Enter',
        'cancel' => 'Cancel',
        'no_competitions' => 'No competitions scheduled for today',
        'contact_admin' => 'Please contact the competition administrator',
        'switch_lang' => '한국어'
    ],
    'zh' => [
        'title' => '评委登录',
        'subtitle' => '选择要评分的比赛',
        'competition' => '比赛',
        'place' => '地点',
        'date' => '日期',
        'login' => '登录',
        'judge_id' => '评委ID',
        'password' => '密码',
        'enter' => '进入',
        'cancel' => '取消',
        'no_competitions' => '今天没有预定的比赛',
        'contact_admin' => '请联系比赛管理员',
        'switch_lang' => 'English'
    ],
    'ja' => [
        'title' => '審査員アクセス',
        'subtitle' => '採点する大会を選択してください',
        'competition' => '大会',
        'place' => '会場',
        'date' => '日付',
        'login' => 'ログイン',
        'judge_id' => '審査員ID',
        'password' => 'パスワード',
        'enter' => '入場',
        'cancel' => 'キャンセル',
        'no_competitions' => '本日予定されている大会はありません',
        'contact_admin' => '大会管理者にお問い合わせください',
        'switch_lang' => 'English'
    ],
    'ru' => [
        'title' => 'Доступ судей',
        'subtitle' => 'Выберите соревнование для судейства',
        'competition' => 'Соревнование',
        'place' => 'Место',
        'date' => 'Дата',
        'login' => 'Вход',
        'judge_id' => 'ID судьи',
        'password' => 'Пароль',
        'enter' => 'Войти',
        'cancel' => 'Отмена',
        'no_competitions' => 'Сегодня нет запланированных соревнований',
        'contact_admin' => 'Обратитесь к администратору соревнований',
        'switch_lang' => 'English'
    ]
];

$t = $texts[$lang];

// 오늘 날짜
$today = date('Y-m-d');

// 대회 데이터 로드
$competitions = [];
$data_dir = __DIR__ . '/data';

if (is_dir($data_dir)) {
    $dirs = scandir($data_dir);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        
        $comp_dir = "$data_dir/$dir";
        if (is_dir($comp_dir)) {
            $info_file = "$comp_dir/info.json";
            if (file_exists($info_file)) {
                $info = json_decode(file_get_contents($info_file), true);
                if ($info && isset($info['date'])) {
                    // 오늘 날짜와 일치하는 대회만 표시
                    if ($info['date'] === $today) {
                        $competitions[] = [
                            'id' => $dir,
                            'title' => $info['title'] ?? '대회명 없음',
                            'place' => $info['place'] ?? '장소 미정',
                            'date' => $info['date'] ?? $today
                        ];
                    }
                }
            }
        }
    }
}

// 날짜순으로 정렬
usort($competitions, function($a, $b) {
    return strcmp($a['date'], $b['date']);
});

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="<?=h($lang)?>">
<head>
    <meta charset="UTF-8">
    <title><?=h($t['title'])?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 800px;
            width: 100%;
            text-align: center;
        }
        
        .header {
            margin-bottom: 40px;
        }
        
        h1 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 2.2em;
            font-weight: 700;
        }
        
        .subtitle {
            color: #666;
            font-size: 1.1em;
            margin: 0;
        }
        
        .lang-switch {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #667eea;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .lang-switch:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }
        
        .competitions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .competition-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .competition-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .competition-card:active {
            transform: translateY(-2px);
        }
        
        .competition-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .competition-info {
            color: #666;
            font-size: 0.95em;
            line-height: 1.5;
        }
        
        .competition-place {
            margin-bottom: 5px;
        }
        
        .competition-date {
            font-weight: 600;
            color: #667eea;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.4em;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 1em;
        }
        
        
        /* 모바일 대응 */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 25px;
                border-radius: 12px;
            }
            
            h1 {
                font-size: 1.8em;
            }
            
            .competitions-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .competition-card {
                padding: 20px;
            }
            
            .modal-content {
                margin: 10% auto;
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <select class="lang-switch" onchange="window.location.href='?lang=' + this.value">
        <option value="ko" <?=$lang === 'ko' ? 'selected' : ''?>>한국어</option>
        <option value="en" <?=$lang === 'en' ? 'selected' : ''?>>English</option>
        <option value="zh" <?=$lang === 'zh' ? 'selected' : ''?>>中文</option>
        <option value="ja" <?=$lang === 'ja' ? 'selected' : ''?>>日本語</option>
        <option value="ru" <?=$lang === 'ru' ? 'selected' : ''?>>Русский</option>
    </select>
    
    <div class="container">
        <div class="header">
            <h1><?=h($t['title'])?></h1>
            <p class="subtitle"><?=h($t['subtitle'])?></p>
        </div>
        
        <?php if (empty($competitions)): ?>
            <div class="empty-state">
                <h3><?=h($t['no_competitions'])?></h3>
                <p><?=h($t['contact_admin'])?></p>
            </div>
        <?php else: ?>
            <div class="competitions-grid">
                <?php foreach ($competitions as $comp): ?>
                <a href="scoring_login.php?comp_id=<?=h($comp['id'])?>&lang=<?=h($lang)?>" class="competition-card">
                    <div class="competition-title"><?=h($comp['title'])?></div>
                    <div class="competition-info">
                        <div class="competition-place">
                            <strong><?=h($t['place'])?>:</strong> <?=h($comp['place'])?>
                        </div>
                        <div class="competition-date">
                            <strong><?=h($t['date'])?>:</strong> <?=h($comp['date'])?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    
</body>
</html>
