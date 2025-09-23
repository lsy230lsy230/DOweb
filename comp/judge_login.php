<?php
session_start();
$data_dir = __DIR__ . '/data';
$adjudicator_file = $data_dir . '/Adjudicator.txt';

$judges = [];
if (file_exists($adjudicator_file)) {
    $lines = file($adjudicator_file);
    foreach ($lines as $line) {
        $cols = explode(',', trim($line));
        if (count($cols) >= 4 && !empty($cols[3]) && !empty($cols[1])) {
            $judges[] = [
                'id' => $cols[0],
                'name' => $cols[1],
                'password' => $cols[3]
            ];
        }
    }
}

$login_err = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['judge_password'])) {
    $pw = trim($_POST['judge_password']);
    if ($pw) {
        $found = false;
        foreach ($judges as $j) {
        if ($j['password'] === $pw) {
            $_SESSION['judge_id'] = $j['id'];
            $_SESSION['judge_name'] = $j['name'];
            $found = true;
            
            // 심사위원 대시보드로 이동 (이벤트 파라미터는 대시보드에서 처리)
            header("Location: judge_dashboard.php");
            exit;
        }
        }
        if (!$found) {
            $login_err = "<div class='login-msg error'>비밀번호가 올바르지 않습니다.</div>";
        }
    } else {
        $login_err = "<div class='login-msg error'>비밀번호를 입력하세요.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>심사위원 로그인 | danceoffice.net</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        html, body {
            background: #23262b !important;
        }
        main {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            background: none;
        }
        .login-section {
            background: #23262b;
            border-radius: 18px;
            padding: 3.2em 2.4em 2.8em 2.4em;
            max-width: 460px;
            width: 96vw;
            margin: 0 auto;
            box-shadow: 0 4px 32px rgba(0,0,0,0.18);
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        .login-title {
            font-size: 2.0em;
            font-weight: 700;
            color: #03C75A;
            margin-bottom: 1.7em;
            text-align: center;
            letter-spacing: -1px;
        }
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.3em;
        }
        .login-form input[type="password"] {
            font-size: 1.25em;
            padding: 1.2em 1.1em;
            border-radius: 12px;
            border: 2px solid #31343a;
            background: #181B20;
            color: #fff;
            outline: none;
        }
        .login-form input[type="password"]:focus {
            border: 2px solid #03C75A;
            background: #23262b;
        }
        .login-form button {
            background: linear-gradient(90deg,#03C75A 70%,#00BFAE 100%);
            color: #222;
            border: none;
            border-radius: 24px;
            padding: 1.1em 0;
            font-weight: 700;
            font-size: 1.25em;
            margin-top: 0.2em;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            width: 100%;
            box-shadow: 0 2px 10px rgba(3,199,90,0.13);
        }
        .login-form button:hover {
            background: linear-gradient(90deg,#00BFAE 60%,#03C75A 100%);
            color: #fff;
        }
        .login-msg {
            margin-top: 1.2em;
            padding: 1em 1.2em;
            border-radius: 9px;
            font-size: 1.1em;
            text-align: center;
        }
        .login-msg.error {
            background: #311;
            color: #ff5e5e;
        }
        .login-section .login-notice {
            margin-top: 2.1em;
            color: #bbb;
            font-size: 1.13em;
            line-height: 1.7;
            text-align: center;
        }

        /* 태블릿/모바일 개선 */
        @media (max-width: 900px) {
            .login-section {
                max-width: 98vw;
                width: 99vw;
                padding: 2.2em 0.6em 2.5em 0.6em;
                border-radius: 0;
                box-shadow: none;
            }
            .login-title {
                font-size: 1.45em;
                margin-bottom: 1.1em;
            }
            .login-form input[type="password"], .login-form button {
                font-size: 1.2em;
                padding: 1em 0.8em;
                border-radius: 10px;
            }
            .login-form button {
                border-radius: 15px;
            }
            .login-section .login-notice {
                font-size: 1.03em;
                margin-top: 1.3em;
            }
        }
        @media (max-width: 600px) {
            .login-section {
                max-width: 100vw;
                width: 100vw;
                padding: 1.5em 0.2em 2em 0.2em;
            }
            .login-title {
                font-size: 1.11em;
            }
            .login-form input[type="password"], .login-form button {
                font-size: 1em;
                padding: 0.8em 0.5em;
            }
            .login-section .login-notice {
                font-size: 0.97em;
            }
        }
    </style>
</head>
<body>
    <main>
        <section class="login-section">
            <div class="login-title">심사위원 로그인</div>
            <form class="login-form" method="post">
                <input type="password" name="judge_password" placeholder="비밀번호만 입력" required autofocus>
                <button type="submit">로그인</button>
            </form>
            <?= $login_err ?>
            <div class="login-notice">
                ※ 비밀번호만 입력하면 심사위원 인증이 됩니다.<br>
                ※ 비밀번호를 잊으셨으면 관리자에게 문의하세요.
            </div>
        </section>
    </main>
</body>
</html>