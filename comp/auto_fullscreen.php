<?php
/**
 * 자동 전체화면 진입 페이지
 * F11 키를 자동으로 눌러서 전체화면으로 진입
 */

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'results';
$event = isset($_GET['event']) ? $_GET['event'] : '';
$auto = isset($_GET['auto']) ? $_GET['auto'] : 'true';

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>자동 전체화면</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #000;
            color: #fff;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        
        .loading {
            text-align: center;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner"></div>
        <h2>전체화면으로 전환 중...</h2>
        <p>잠시만 기다려주세요.</p>
    </div>

    <script>
        // 페이지 로드 후 자동으로 전체화면 모니터로 리다이렉트
        window.addEventListener('load', function() {
            setTimeout(() => {
                const url = `fullscreen_monitor.php?mode=${encodeURIComponent('<?php echo $mode; ?>')}&event=${encodeURIComponent('<?php echo $event; ?>')}&auto=${encodeURIComponent('<?php echo $auto; ?>')}`;
                window.location.href = url;
            }, 1000);
        });
        
        // F11 키 자동 실행 (사용자 상호작용 후에만 가능)
        document.addEventListener('click', function() {
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen();
            } else if (document.documentElement.webkitRequestFullscreen) {
                document.documentElement.webkitRequestFullscreen();
            } else if (document.documentElement.mozRequestFullScreen) {
                document.documentElement.mozRequestFullScreen();
            } else if (document.documentElement.msRequestFullscreen) {
                document.documentElement.msRequestFullscreen();
            }
        });
    </script>
</body>
</html>




