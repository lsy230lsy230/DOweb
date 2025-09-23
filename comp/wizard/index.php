<?php
session_start();

// 단계 결정
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$total_steps = 6;

// 폼 데이터 저장 (실제 구현에서는 파일/DB로 저장 권장)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['wizard'][$step] = $_POST;
    // 마지막 단계까지 입력되면 완료 플래그 설정
    if ($step >= $total_steps) {
        $_SESSION['wizard_complete'] = true;
        header("Location: ../index.php");
        exit;
    } else {
        header("Location: index.php?step=" . ($step + 1));
        exit;
    }
}

// 입력했던 값 불러오기(수정시)
function get_prev($key, $step) {
    return isset($_SESSION['wizard'][$step][$key]) ? htmlspecialchars($_SESSION['wizard'][$step][$key]) : '';
}

// 단계별 안내 및 폼
function render_step($step) {
    switch ($step) {
        case 1: // 대회 기본정보
            ?>
            <div class="wizard-title">1. 대회 기본정보 입력</div>
            <form method="post">
                <div><input name="comp_title" placeholder="대회명" value="<?= get_prev('comp_title', 1) ?>" required></div>
                <div><input type="date" name="comp_date" value="<?= get_prev('comp_date', 1) ?>" required></div>
                <div><input name="comp_place" placeholder="장소" value="<?= get_prev('comp_place', 1) ?>"></div>
                <div><input name="comp_host" placeholder="주최/주관" value="<?= get_prev('comp_host', 1) ?>"></div>
                <button type="submit">다음 &gt;</button>
            </form>
            <?php
            break;
        case 2: // 심사위원 입력
            ?>
            <div class="wizard-title">2. 심사위원 등록</div>
            <form method="post">
                <div>
                    <textarea name="adjudicators" rows="5" placeholder="ID,이름 형식으로 줄마다 입력" required><?= get_prev('adjudicators', 2) ?></textarea>
                    <div class="small">예시: <br>J1,김심사<br>J2,이심사</div>
                </div>
                <button type="submit">다음 &gt;</button>
            </form>
            <?php
            break;
        case 3: // 이벤트(종목) 입력
            ?>
            <div class="wizard-title">3. 이벤트(종목) 등록</div>
            <form method="post">
                <div>
                    <textarea name="events" rows="5" placeholder="코드,이름 형식으로 줄마다 입력" required><?= get_prev('events', 3) ?></textarea>
                    <div class="small">예시:<br>S1,솔로라틴<br>D1,듀오프리스타일</div>
                </div>
                <button type="submit">다음 &gt;</button>
            </form>
            <?php
            break;
        case 4: // 선수명단 입력
            ?>
            <div class="wizard-title">4. 선수 명단 입력</div>
            <form method="post">
                <div>
                    <textarea name="players" rows="7" placeholder="등번호,이름,파트너(없으면 공란) 형식"><?= get_prev('players', 4) ?></textarea>
                    <div class="small">예시:<br>101,홍길동,김춘향<br>102,이몽룡,</div>
                </div>
                <button type="submit">다음 &gt;</button>
            </form>
            <?php
            break;
        case 5: // 시간표
            ?>
            <div class="wizard-title">5. 시간표(타임테이블) 입력</div>
            <form method="post">
                <div>
                    <textarea name="timetable" rows="5" placeholder="경기,시간,내용 등"><?= get_prev('timetable', 5) ?></textarea>
                    <div class="small">예시:<br>솔로라틴,10:00,1라운드<br>듀오프리스타일,11:00,본선</div>
                </div>
                <button type="submit">다음 &gt;</button>
            </form>
            <?php
            break;
        case 6: // 요약/최종확인
            ?>
            <div class="wizard-title">6. 입력정보 최종 확인</div>
            <form method="post">
                <div class="summary">
                    <b>대회명:</b> <?= get_prev('comp_title', 1) ?><br>
                    <b>일자:</b> <?= get_prev('comp_date', 1) ?><br>
                    <b>장소:</b> <?= get_prev('comp_place', 1) ?><br>
                    <b>주최/주관:</b> <?= get_prev('comp_host', 1) ?><br>
                    <b>심사위원:</b><br>
                    <pre><?= htmlspecialchars(get_prev('adjudicators', 2)) ?></pre>
                    <b>이벤트:</b><br>
                    <pre><?= htmlspecialchars(get_prev('events', 3)) ?></pre>
                    <b>선수명단:</b><br>
                    <pre><?= htmlspecialchars(get_prev('players', 4)) ?></pre>
                    <b>시간표:</b><br>
                    <pre><?= htmlspecialchars(get_prev('timetable', 5)) ?></pre>
                </div>
                <button type="submit" style="background:#03C75A;">대회 준비 완료</button>
            </form>
            <?php
            break;
        default:
            echo "잘못된 단계입니다.";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>대회 설정 마법사 | COMP</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#f0f3f8; font-family:sans-serif; margin:0;}
        .wizard-main { max-width:430px; margin:3vh auto 0 auto; background:#fff; border-radius:14px; box-shadow:0 4px 18px #00339914; padding:2.1em 1.2em 2.2em 1.2em;}
        .wizard-steps { margin-bottom:2em; text-align:center;}
        .wizard-step { display:inline-block; width:26px; height:26px; line-height:26px; border-radius:50%; background:#eee; color:#888; text-align:center; margin-right:7px;}
        .wizard-step.active { background:#03C75A; color:#fff; font-weight:700;}
        .wizard-title { font-weight:700; margin-bottom:1.2em; text-align:center; font-size:1.08em;}
        form > div { margin-bottom:1em;}
        input, textarea { width:100%; padding:0.55em; border-radius:7px; border:1px solid #bbb; font-size:1em;}
        textarea {resize:vertical;min-height:65px;}
        button { padding:0.5em 2em; border-radius:8px; border:none; background:#03C75A; color:#fff; font-weight:700; font-size:1em;}
        .small {font-size:0.93em; color:#888; margin:0.2em 0 0 0;}
        .summary { font-size:0.97em; color:#333; background:#f7fafd; border-radius:8px; padding:1.1em; margin-bottom:1.3em;}
        pre { background:#f0f3f8; padding:0.5em 0.7em; border-radius:7px; font-size:0.97em;}
        @media (max-width:600px) {
            .wizard-main { max-width:97vw; padding:1.1em 0.3em;}
            .wizard-title { font-size:0.99em;}
        }
        footer {margin-top:2.2em;text-align:center;font-size:0.92em;color:#aaa;}
    </style>
</head>
<body>
<div class="wizard-main">
    <div class="wizard-steps">
        <?php for ($i=1; $i<=$total_steps; $i++): ?>
            <span class="wizard-step<?= $step==$i?' active':'' ?>"><?= $i ?></span>
        <?php endfor; ?>
    </div>
    <?php render_step($step); ?>
</div>
<footer>
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>
</body>
</html>