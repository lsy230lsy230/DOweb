<?php
$comp_id = $_GET['comp_id'] ?? '';
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";
$adjs_file = "$data_dir/adjudicators.txt";

if (!preg_match('/^\d{8}-\d+$/', $comp_id) || !is_file($info_file)) {
    echo "<h1>잘못된 대회 ID 또는 대회 정보가 없습니다.</h1>";
    exit;
}
$info = json_decode(file_get_contents($info_file), true);

// 심사위원 명단 불러오기
$adjudicators = [];
if (file_exists($adjs_file)) {
    foreach (file($adjs_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $parts = array_map('trim', explode(',', $line));
        $adjudicators[] = [
            'code' => $parts[0] ?? '',
            'name' => $parts[1] ?? '',
            'nation' => $parts[2] ?? '',
            'password' => $parts[3] ?? ''
        ];
    }
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 심사위원 명단 파일 업로드
    if (isset($_POST['upload']) && isset($_FILES['adjsfile']) && $_FILES['adjsfile']['error'] == UPLOAD_ERR_OK) {
        $lines = file($_FILES['adjsfile']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $added = 0; $skipped = 0;
        $existing_codes = array_column($adjudicators, 'code');
        foreach ($lines as $line) {
            $parts = array_map('trim', explode(',', $line));
            if (!$parts[0] || !$parts[1]) continue; // 코드, 성명 필수
            if (in_array($parts[0], $existing_codes)) { $skipped++; continue;}
            $adjudicators[] = [
                'code' => $parts[0],
                'name' => $parts[1],
                'nation' => $parts[2] ?? '',
                'password' => $parts[3] ?? ''
            ];
            $added++;
        }
        $msg = "업로드 완료! {$added}명 추가, {$skipped}명(중복) 건너뜀";
    }
    // 심사위원 추가
    if (isset($_POST['add']) && $_POST['code'] && $_POST['name']) {
        $adjudicators[] = [
            'code' => trim($_POST['code']),
            'name' => trim($_POST['name']),
            'nation' => trim($_POST['nation']),
            'password' => trim($_POST['password'])
        ];
        $msg = "심사위원이 추가되었습니다.";
    }
    // 삭제
    if (isset($_POST['delete']) && isset($_POST['idx'])) {
        unset($adjudicators[$_POST['idx']]);
        $msg = "삭제되었습니다.";
    }
    // adjudicators.txt 저장
    $lines = [];
    foreach ($adjudicators as $a) {
        $lines[] = implode(',', [$a['code'], $a['name'], $a['nation'], $a['password']]);
    }
    file_put_contents($adjs_file, implode("\n", $lines));
    header("Location: manage_adjudicators.php?comp_id=" . urlencode($comp_id) . "&msg=" . urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= h($info['title']) ?> 심사위원 명단 관리 | danceoffice.net COMP</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#f7fafd; font-family:sans-serif; margin:0;}
        .mainbox { max-width:950px; margin:3vh auto 0 auto; background:#fff; border-radius:18px; box-shadow:0 6px 32px #00339911; padding:2.2em 1.5em 2em 1.5em;}
        h1 { color:#003399; font-size:1.15em; margin-bottom:0.6em;}
        .desc {margin-bottom:1.3em; color:#333;}
        table { width:100%; border-collapse:collapse; background:#fff;}
        th, td { padding:0.6em 0.3em; text-align:center;}
        th { background:#f0f4fa; color:#003399; font-weight:700;}
        td { color:#333;}
        tr:not(:last-child) td { border-bottom:1px solid #eee;}
        .del-btn { background:#ec3b28; color:#fff; border:none; border-radius:8px; padding:0.3em 1em; font-weight:700; cursor:pointer;}
        .del-btn:hover { background:#b31e06;}
        .add-form { margin:1.5em 0 1em 0; display:flex; gap:0.6em; flex-wrap:wrap;}
        .add-form input { padding:0.4em 0.6em; border-radius:7px; border:1px solid #bbb; font-size:1em; min-width:100px;}
        .add-form button { background:#03C75A; color:#fff; border:none; border-radius:8px; padding:0.4em 1.5em; font-weight:700; cursor:pointer;}
        .add-form button:hover { background:#00BFAE;}
        .msg { color:#03C75A; margin-bottom:1em;}
        .goto-dash {display:inline-block; margin-bottom:1.1em; color:#888;}
        .goto-dash:hover {color:#003399;}
        .goto-data {color:#03C75A; margin-left:1em;}
        .goto-data:hover {color:#009c85;}
        .panel-link {
            display:inline-block; margin-left:1em; font-size:1em; color:#003399; background:#eaf3ff;
            padding:0.38em 1.2em; border-radius:7px; text-decoration:none; font-weight:600;
            transition:background 0.15s;
        }
        .panel-link:hover { background:#d3e9ff; color:#021b75;}
        .upload-form { margin:0 0 1.2em 0; display:flex; align-items:center; gap:0.7em; }
        .upload-form input[type="file"] { border-radius:7px; border:1px solid #bbb; padding:0.3em; }
        .upload-form button { background:#003399; color:#fff; border:none; border-radius:8px; padding:0.4em 1.6em; font-weight:700; cursor:pointer;}
        .upload-form button:hover { background:#2222aa;}
        .small {font-size:0.93em; color:#888; margin-left:0.8em;}
        .nation-th {min-width:80px;}
        .print-btn { background:#666; color:#fff; font-weight:600; border:none; border-radius:8px; padding:0.3em 1.5em; margin-left:1em; cursor:pointer;}
        .print-btn:hover { background:#003399; }
        @media (max-width:1000px) {
            .mainbox { max-width:99vw; padding:1.1em 0.3em;}
            .add-form input {min-width:70px;}
        }
        @media (max-width:730px) {
            .add-form {flex-direction:column;}
            .add-form input {width:100%;}
            .upload-form {flex-direction:column; align-items:flex-start;}
        }
        /* 인쇄시 명단 표만 */
        @media print {
            .print-btn, .add-form, .upload-form, .goto-dash, .goto-data, .msg, .panel-link, .desc, h1, footer { display:none !important; }
            table { margin-top:0 !important; }
            body { background:#fff !important; }
            .mainbox { box-shadow:none !important; padding:0; }
            table, th, td { font-size:1em; }
        }
        footer {
            position: relative;
            width: 100%;
            min-height: 45px;
            margin-top: 3em;
            background: none;
            text-align: center;
            font-size: 0.95em;
            color: #aaa;
            padding: 1em 0 1em 0;
            box-sizing: border-box;
            z-index: 2;
        }
    </style>
</head>
<body>
<div class="mainbox">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2em;">
        <div>
            <a href="dashboard.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-dash">&lt; 대시보드로</a>
            <a href="data_manage.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-data">데이터 관리로 &gt;</a>
        </div>
        <div>
            <a href="manage_adjudicator_panels.php?comp_id=<?= urlencode($comp_id) ?>" class="panel-link">패널 설정으로 이동 &gt;</a>
            <button type="button" onclick="window.print()" class="print-btn">명단 인쇄</button>
        </div>
    </div>
    <h1><?= h($info['title']) ?> 심사위원 명단 관리</h1>
    <div class="desc">
        심사위원은 코드, 성명, 국적 필수, 비밀번호는 선택입니다.<br>
        심사위원을 모두 등록한 후 <b>패널 설정으로 이동</b>하여 패널구성 및 이벤트 배정을 하세요.
    </div>
    <?php if ($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>
    <form class="upload-form" method="post" enctype="multipart/form-data" onsubmit="return confirm('업로드된 txt/csv 파일의 심사위원 정보가 추가됩니다. 진행할까요?');">
        <input type="file" name="adjsfile" accept=".txt,.csv" required>
        <button type="submit" name="upload" value="1">TXT 업로드</button>
        <span class="small">형식: 코드,이름,국적,비밀번호 (비밀번호 생략 가능, 기존 코드 무시)</span>
    </form>
    <form class="add-form" method="post" autocomplete="off">
        <input name="code" placeholder="코드" required>
        <input name="name" placeholder="심사위원명" required>
        <input name="nation" placeholder="국적">
        <input name="password" placeholder="비밀번호">
        <button type="submit" name="add" value="1">추가</button>
    </form>
    <table>
        <tr>
            <th>코드</th>
            <th>성명</th>
            <th class="nation-th">국적</th>
            <th>비밀번호</th>
            <th>관리</th>
        </tr>
        <?php foreach ($adjudicators as $i => $a): ?>
            <tr>
                <td><?= h($a['code']) ?></td>
                <td><?= h($a['name']) ?></td>
                <td><?= h($a['nation']) ?></td>
                <td><?= h($a['password']) ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="idx" value="<?= $i ?>">
                        <button type="submit" name="delete" value="1" class="del-btn" onclick="return confirm('정말 삭제할까요?');">삭제</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($adjudicators)): ?>
            <tr><td colspan="5" style="color:#aaa;">등록된 심사위원이 없습니다.</td></tr>
        <?php endif; ?>
    </table>
</div>
<footer>
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>
</body>
</html>