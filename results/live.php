<?php
// 디버그/에러 출력을 임시로 켭니다 (배포시 끄세요)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 실시간 결과 파일 경로 (NAS에서 자동 복사된 최신 결과 파일)
$recall_php_path = '/volume1/web/Recall/index.php';

// 명시적 출력 인코딩
header('Content-Type: text/html; charset=UTF-8');

$result_html = "<div class='result-error'><span class='material-symbols-rounded'>error</span> 실시간 결과 파일을 찾을 수 없습니다.</div>";

if (file_exists($recall_php_path)) {
    $raw = file_get_contents($recall_php_path);
    if ($raw === false) {
        $result_html = "<div class='result-error'><span class='material-symbols-rounded'>error</span> 파일을 읽을 수 없습니다.</div>";
    } else {
        // BOM 검사(UTF-8 BOM, UTF-16 LE/BE)
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            // UTF-8 BOM 제거
            $result_html = substr($raw, 3);
        } elseif (substr($raw, 0, 2) === "\xFF\xFE") {
            // UTF-16 LE
            if (function_exists('iconv')) {
                $result_html = iconv("UTF-16LE", "UTF-8//IGNORE", $raw);
            } elseif (function_exists('mb_convert_encoding')) {
                $result_html = mb_convert_encoding($raw, "UTF-8", "UTF-16LE");
            } else {
                $result_html = $raw;
            }
        } elseif (substr($raw, 0, 2) === "\xFE\xFF") {
            // UTF-16 BE
            if (function_exists('iconv')) {
                $result_html = iconv("UTF-16BE", "UTF-8//IGNORE", $raw);
            } elseif (function_exists('mb_convert_encoding')) {
                $result_html = mb_convert_encoding($raw, "UTF-8", "UTF-16BE");
            } else {
                $result_html = $raw;
            }
        } else {
            // 인코딩 자동 감지 시도
            $enc = null;
            if (function_exists('mb_detect_encoding')) {
                $enc = mb_detect_encoding($raw, ['UTF-8','CP949','EUC-KR','ISO-8859-1','ASCII'], true);
            }
            if ($enc) {
                if ($enc === 'UTF-8') {
                    $result_html = $raw;
                } else {
                    if (function_exists('iconv')) {
                        $result_html = iconv($enc, "UTF-8//IGNORE", $raw);
                    } elseif (function_exists('mb_convert_encoding')) {
                        $result_html = mb_convert_encoding($raw, "UTF-8", $enc);
                    } else {
                        $result_html = $raw;
                    }
                }
            } else {
                // 최후의 수단: CP949->UTF-8, EUC-KR->UTF-8, 그냥 RAW 순으로 시도
                if (function_exists('iconv')) {
                    $try = @iconv("CP949", "UTF-8//IGNORE", $raw);
                    if ($try !== false && mb_detect_encoding($try, 'UTF-8', true)) {
                        $result_html = $try;
                    } else {
                        $try2 = @iconv("EUC-KR", "UTF-8//IGNORE", $raw);
                        if ($try2 !== false && mb_detect_encoding($try2, 'UTF-8', true)) {
                            $result_html = $try2;
                        } else {
                            $result_html = $raw;
                        }
                    }
                } elseif (function_exists('mb_convert_encoding')) {
                    $result_html = mb_convert_encoding($raw, "UTF-8", "CP949");
                } else {
                    $result_html = $raw;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>준결 예선 실시간 경기 결과 - danceoffice.net</title>
    <meta http-equiv="refresh" content="30">
    <link rel="stylesheet" href="/assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
</head>
<body>
    <header class="main-header" style="margin-bottom: 5px;">
        <div class="logo-nav">
            <img src="/assets/danceoffice-logo.png" alt="Danceoffice Logo" class="main-logo">
            <nav class="main-nav">
                <a href="/">홈</a>
                <a href="/results/">전체 결과</a>
                <a href="/results/live.php" class="active">실시간 결과</a>
                <a href="/notice/">공지사항</a>
                <a href="/manage/">관리자</a>
            </nav>
        </div>
    </header>
    <main style="padding: 5px 10px; max-width: 1000px; margin: 0 auto;">
        <section class="results-section" style="padding: 10px 15px; margin-bottom: 10px;">
            <h1 style="font-size: 20px; margin-bottom: 5px; color: #03C75A; text-align: center;">1. 싱글 맨 프리스타일 - Semi</h1>
            <h2 style="font-size: 16px; margin-bottom: 15px; color: #00BFAE; text-align: center;">7커플이 3번 이벤트로 진출합니다.</h2>
            <h3 style="font-size: 18px; margin-bottom: 10px; color: #03C75A;">
                <span class="material-symbols-rounded">update</span>
                다음 라운드 진출자 명단
            </h3>
            <div class="result-area" style="margin-bottom: 10px;">
                <?php echo $result_html; ?>
            </div>
            <div class="result-refresh" style="font-size: 12px; margin-bottom: 10px;">
                <span class="material-symbols-rounded" style="vertical-align:middle;">refresh</span>
                <span>30초마다 자동 갱신됩니다. 최신 결과가 아닐 경우 새로고침(F5) 해주세요.</span>
            </div>
        </section>
        <section style="text-align: center; margin-top: 20px;">
            <p class="result-desc" style="font-size: 12px; color: #00BFAE;">전체 결과는 <a href="/results/">danceoffice.net</a>에서 확인하세요</p>
        </section>
    </main>
    <footer class="main-footer">
        &copy; 2025 danceoffice.net | Powered by Seyoung Lee
    </footer>
</body>
</html>