<?php
// 심사위원 접속 페이지
$lang = $_GET['lang'] ?? 'ko';
if (!in_array($lang, ['ko', 'en', 'zh', 'ja'])) {
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
        
        /* 로그인 모달 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .modal-title {
            font-size: 1.4em;
            font-weight: 700;
            color: #333;
            margin: 0 0 5px 0;
        }
        
        .modal-subtitle {
            color: #666;
            font-size: 0.9em;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        
        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #667eea;
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: #fff;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #333;
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
    <a href="?lang=<?=$lang === 'ko' ? 'en' : 'ko'?>" class="lang-switch">
        <?=h($t['switch_lang'])?>
    </a>
    
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
                <div class="competition-card" onclick="openLoginModal('<?=h($comp['id'])?>', '<?=h($comp['title'])?>')">
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
    
    <!-- 로그인 모달 -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeLoginModal()">&times;</span>
            <div class="modal-header">
                <div class="modal-title"><?=h($t['login'])?></div>
                <div class="modal-subtitle" id="modalSubtitle"></div>
            </div>
            
            <form id="loginForm" onsubmit="submitLogin(event)">
                <div class="form-group">
                    <label class="form-label" for="judgeId"><?=h($t['judge_id'])?></label>
                    <input type="text" id="judgeId" name="judge_id" class="form-input" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password"><?=h($t['password'])?></label>
                    <input type="password" id="password" name="password" class="form-input" required autocomplete="current-password">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeLoginModal()">
                        <?=h($t['cancel'])?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?=h($t['enter'])?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentCompId = '';
        
        function openLoginModal(compId, compTitle) {
            currentCompId = compId;
            document.getElementById('modalSubtitle').textContent = compTitle;
            document.getElementById('loginModal').style.display = 'block';
            document.getElementById('judgeId').focus();
        }
        
        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
            document.getElementById('loginForm').reset();
            currentCompId = '';
        }
        
        function submitLogin(event) {
            event.preventDefault();
            
            const judgeId = document.getElementById('judgeId').value.trim();
            const password = document.getElementById('password').value;
            
            if (!judgeId || !password) {
                alert('심사위원 ID와 비밀번호를 입력해주세요.');
                return;
            }
            
            // 로그인 처리
            const formData = new FormData();
            formData.append('comp_id', currentCompId);
            formData.append('judge_id', judgeId);
            formData.append('password', password);
            
            fetch('scoring_login.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 로그인 성공 시 대시보드로 이동
                    window.location.href = `scoring_dashboard.php?comp_id=${currentCompId}&lang=<?=h($lang)?>`;
                } else {
                    alert(data.message || '로그인에 실패했습니다.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('로그인 중 오류가 발생했습니다.');
            });
        }
        
        // 모달 외부 클릭 시 닫기
        window.onclick = function(event) {
            const modal = document.getElementById('loginModal');
            if (event.target === modal) {
                closeLoginModal();
            }
        }
        
        // ESC 키로 모달 닫기
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeLoginModal();
            }
        });
    </script>
</body>
</html>
