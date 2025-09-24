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
        'title' => '심사위원 채점 로그인',
        'password_label' => '심사위원 비밀번호',
        'password_placeholder' => '3-4자리 숫자 입력',
        'login' => '로그인',
        'password_required' => '비밀번호를 입력해주세요.',
        'invalid_password' => '잘못된 비밀번호입니다.',
        'switch_lang' => 'English'
    ],
    'en' => [
        'title' => 'Judge Scoring Login',
        'password_label' => 'Judge Password',
        'password_placeholder' => 'Enter 3-4 digit number',
        'login' => 'Login',
        'password_required' => 'Please enter password.',
        'invalid_password' => 'Invalid password.',
        'switch_lang' => '한국어'
    ],
    'zh' => [
        'title' => '评委评分登录',
        'password_label' => '评委密码',
        'password_placeholder' => '请输入3-4位数字',
        'login' => '登录',
        'password_required' => '请输入密码。',
        'invalid_password' => '密码错误。',
        'switch_lang' => 'English'
    ],
    'ja' => [
        'title' => '審査員採点ログイン',
        'password_label' => '審査員パスワード',
        'password_placeholder' => '3-4桁の数字を入力',
        'login' => 'ログイン',
        'password_required' => 'パスワードを入力してください。',
        'invalid_password' => 'パスワードが間違っています。',
        'switch_lang' => 'English'
    ]
];

$t = $texts[$lang];

if (!$comp_id) {
    echo "<h1>잘못된 대회 ID입니다.</h1>";
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

// Load adjudicators to get passwords
$adjudicators_file = "$data_dir/adjudicators.txt";
$adjudicators = [];

if (file_exists($adjudicators_file)) {
    $lines = file($adjudicators_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 4) {
            $adjudicators[] = [
                'id' => $cols[0],
                'name' => $cols[1],
                'country' => $cols[2],
                'password' => $cols[3]
            ];
        }
    }
}

$error_message = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_judge_id = $_POST['judge_id'] ?? '';
    $input_password = $_POST['password'] ?? '';
    $input_password = preg_replace('/\D+/', '', $input_password); // 숫자만 추출
    
    // AJAX 요청인지 확인 (Content-Type이 application/json이거나 X-Requested-With가 XMLHttpRequest)
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' ||
               (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
    
    if (empty($input_judge_id) || empty($input_password)) {
        $error_message = '심사위원 ID와 비밀번호를 입력해주세요.';
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    } else {
        // Check if judge_id and password match any adjudicator
        $found_adjudicator = null;
        foreach ($adjudicators as $adjudicator) {
            if ($adjudicator['id'] === $input_judge_id && $adjudicator['password'] === $input_password) {
                $found_adjudicator = $adjudicator;
                break;
            }
        }
        
        if ($found_adjudicator) {
            // Store adjudicator info in session
            $_SESSION['scoring_logged_in'] = true;
            $_SESSION['scoring_judge_id'] = $found_adjudicator['id'];
            $_SESSION['scoring_judge_name'] = $found_adjudicator['name'];
            $_SESSION['scoring_judge_country'] = $found_adjudicator['country'];
            $_SESSION['scoring_comp_id'] = $comp_id;
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => '로그인 성공']);
            exit;
        } else {
            $error_message = '잘못된 심사위원 ID 또는 비밀번호입니다.';
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }
    }
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            margin: 0; 
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .login-container { 
            background: #fff; 
            border-radius: 12px; 
            padding: 30px 25px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 420px;
            text-align: center;
            margin: 0 0 20px 0;
        }
        
        h1 { 
            color: #333; 
            margin-bottom: 8px; 
            font-size: 1.5em;
            font-weight: 700;
        }
        
        .comp-info {
            color: #666;
            margin-bottom: 25px;
            font-size: 0.85em;
            line-height: 1.4;
        }
        
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s;
            background: #fafafa;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
        }
        
        .login-btn {
            width: 100%;
            background: #667eea;
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 15px;
        }
        
        .login-btn:active {
            background: #5a6fd8;
            transform: translateY(1px);
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 18px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }
        
        
        .lang-switch {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #667eea;
            color: #fff;
            border: none;
            padding: 8px 30px 8px 12px;
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
        
        .login-container {
            position: relative;
        }
        
        /* 태블릿 환경 (768px 이상) */
        @media (min-width: 768px) {
            body {
                padding: 20px;
            }
            
            .login-container {
                padding: 35px 30px;
                border-radius: 15px;
                max-width: 450px;
                margin: 0 0 25px 0;
            }
            
            h1 {
                font-size: 1.8em;
                margin-bottom: 10px;
            }
            
            .comp-info {
                font-size: 0.9em;
                margin-bottom: 30px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            input[type="password"] {
                padding: 12px 15px;
                font-size: 16px;
            }
            
            .login-btn {
                padding: 12px;
                font-size: 16px;
            }
            
            .lang-switch {
                top: 20px;
                right: 20px;
                padding: 10px 32px 10px 14px;
                font-size: 13px;
            }
        }
        
        /* 큰 태블릿/데스크톱 (1024px 이상) */
        @media (min-width: 1024px) {
            .login-container {
                padding: 40px 35px;
                max-width: 480px;
                margin: 0 0 30px 0;
            }
            
            .login-btn:hover {
                background: #5a6fd8;
            }
            
            
            .lang-switch {
                top: 30px;
                right: 30px;
                padding: 10px 36px 10px 16px;
                font-size: 14px;
            }
            
            .lang-switch:hover {
                background: #5a6fd8;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <select class="lang-switch" onchange="window.location.href='?comp_id=<?=h($comp_id)?>&lang=' + this.value">
        <option value="ko" <?=$lang === 'ko' ? 'selected' : ''?>>한국어</option>
        <option value="en" <?=$lang === 'en' ? 'selected' : ''?>>English</option>
        <option value="zh" <?=$lang === 'zh' ? 'selected' : ''?>>中文</option>
        <option value="ja" <?=$lang === 'ja' ? 'selected' : ''?>>日本語</option>
    </select>
    
    <h1><?=h($t['title'])?></h1>
    <div class="comp-info">
        <strong><?=h($info['title'])?></strong><br>
        <?=h($info['date'])?> | <?=h($info['place'])?>
    </div>
    
    <?php if ($error_message): ?>
        <div class="error"><?=h($error_message)?></div>
    <?php endif; ?>
    
    <form method="post">
        <div class="form-group">
            <label for="password"><?=h($t['password_label'])?></label>
            <input type="password" 
                   id="password" 
                   name="password" 
                   placeholder="<?=h($t['password_placeholder'])?>" 
                   maxlength="4"
                   pattern="[0-9]{3,4}"
                   required>
        </div>
        <button type="submit" class="login-btn"><?=h($t['login'])?></button>
    </form>
    
</div>

<footer style="text-align: center; margin-top: 20px; color: rgba(255,255,255,0.8); font-size: 0.9em; width: 100%;">
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>

<script>
// Only allow numbers
document.getElementById('password').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Auto-focus
document.getElementById('password').focus();
</script>
</body>
</html>
