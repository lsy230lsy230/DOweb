<?php
$comp_id = $_GET['comp_id'] ?? '';
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";
$players_file = "$data_dir/players.txt";

if (!preg_match('/^\d{8}-\d+$/', $comp_id) || !is_file($info_file)) {
    echo "<h1>잘못된 대회 ID 또는 대회 정보가 없습니다.</h1>";
    exit;
}
$info = json_decode(file_get_contents($info_file), true);

// 선수명단 불러오기
$players = [];
if (file_exists($players_file)) {
    foreach (file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $parts = array_map('trim', explode(',', $line));
        $players[] = [
            'number' => $parts[0] ?? '',
            'name' => $parts[1] ?? '',
            'partner' => $parts[2] ?? '',
            'club' => $parts[3] ?? '',
            'nation' => $parts[4] ?? '',
        ];
    }
}

$msg = '';

// 등록/삭제/업로드 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 업로드
    if (isset($_POST['upload']) && isset($_FILES['txtfile']) && $_FILES['txtfile']['error'] == UPLOAD_ERR_OK) {
        $lines = file($_FILES['txtfile']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = array_map('trim', explode(',', $line));
            if (!$parts[1]) continue; // 이름 필수
            $exists = false;
            foreach ($players as $p) {
                if ($p['number'] === ($parts[0] ?? '')) { $exists = true; break; }
            }
            if (!$exists) {
                $players[] = [
                    'number' => $parts[0] ?? '',
                    'name' => $parts[1] ?? '',
                    'partner' => $parts[2] ?? '',
                    'club' => $parts[3] ?? '',
                    'nation' => $parts[4] ?? '',
                ];
            }
        }
        $msg = "업로드 완료! (중복 등번호 제외)";
    }
    // 추가
    if (isset($_POST['add']) && $_POST['name']) {
        $players[] = [
            'number' => trim($_POST['number']),
            'name' => trim($_POST['name']),
            'partner' => trim($_POST['partner']),
            'club' => trim($_POST['club']),
            'nation' => trim($_POST['nation']),
        ];
        $msg = "선수가 추가되었습니다.";
    }
    // 삭제
    if (isset($_POST['delete']) && isset($_POST['idx'])) {
        unset($players[$_POST['idx']]);
        $msg = "삭제되었습니다.";
    }
    // 저장
    $lines = [];
    foreach ($players as $p) {
        $lines[] = implode(',', [
            $p['number'], $p['name'], $p['partner'], $p['club'], $p['nation']
        ]);
    }
    file_put_contents($players_file, implode("\n", $lines));
    header("Location: manage_competitors.php?comp_id=" . urlencode($comp_id) . "&msg=" . urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= h($info['title']) ?> 선수 명단 관리 | danceoffice.net COMP</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#f7fafd; font-family:sans-serif; margin:0;}
        .mainbox { max-width:900px; margin:3vh auto 0 auto; background:#fff; border-radius:18px; box-shadow:0 6px 32px #00339911; padding:2.2em 1.5em 2em 1.5em;}
        h1 { color:#003399; font-size:1.15em; margin-bottom:0.9em;}
        .desc {margin-bottom:1.3em; color:#333;}
        table { width:100%; border-collapse:collapse; background:#fff; font-size:0.97em;}
        th, td { padding:0.6em 0.3em; text-align:center;}
        th { background:#f0f4fa; color:#003399; font-weight:700;}
        td { color:#333;}
        tr:not(:last-child) td { border-bottom:1px solid #eee;}
        .del-btn { background:#ec3b28; color:#fff; border:none; border-radius:8px; padding:0.3em 1em; font-weight:700; cursor:pointer;}
        .del-btn:hover { background:#b31e06;}
        .add-form { margin:1.7em 0 1em 0; display:flex; gap:0.6em; flex-wrap:wrap;}
        .add-form input { padding:0.4em 0.6em; border-radius:7px; border:1px solid #bbb; font-size:1em; min-width:120px;}
        .add-form button { background:#03C75A; color:#fff; border:none; border-radius:8px; padding:0.4em 1.5em; font-weight:700; cursor:pointer;}
        .add-form button:hover { background:#00BFAE;}
        .upload-form { margin:0 0 1.3em 0; display:flex; align-items:center; gap:0.7em; }
        .upload-form input[type="file"] { border-radius:7px; border:1px solid #bbb; padding:0.3em; }
        .upload-form button { background:#003399; color:#fff; border:none; border-radius:8px; padding:0.4em 1.6em; font-weight:700; cursor:pointer;}
        .upload-form button:hover { background:#2222aa;}
        .msg { color:#03C75A; margin-bottom:1em;}
        .goto-dash {display:inline-block; margin-bottom:1.4em; color:#888;}
        .goto-dash:hover {color:#003399;}
        .small {font-size:0.93em; color:#888; margin-left:0.8em;}
        @media (max-width:1000px) {
            .mainbox { max-width:99vw; padding:1.1em 0.3em;}
            .add-form input {min-width:80px;}
        }
        @media (max-width:600px) {
            .add-form {flex-direction:column;}
            .add-form input {width:100%;}
            .upload-form {flex-direction:column; align-items:flex-start;}
        }
        .th-club {min-width:80px;}
        .th-nation {min-width:80px;}
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
        .top-links {
            display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2em;
        }
        .top-links a { color:#888; font-size:1em; text-decoration:none;}
        .top-links a.goto-dash:hover { color:#003399; }
        .top-links a.goto-data { color:#03C75A; }
        .top-links a.goto-data:hover { color:#009c85; }
    </style>
</head>
<body>
<div class="mainbox">
    <div class="top-links">
        <a href="dashboard.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-dash">&lt; 대시보드로</a>
        <a href="data_manage.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-data">데이터 관리로 &gt;</a>
    </div>
    <h1><?= h($info['title']) ?> 선수 명단 관리</h1>
    <div class="desc">
        싱글댄스의 경우 이름만 입력해도 되고, 파트너가 있으면 입력하세요. 국가 승인/국제대회용으로 소속, 국적은 선택 입력입니다.
    </div>
    <?php if ($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>
    <form class="upload-form" method="post" enctype="multipart/form-data" onsubmit="return confirm('업로드된 txt 파일의 선수정보가 추가됩니다. 진행할까요?');">
        <input type="file" name="txtfile" accept=".txt,.csv" required>
        <button type="submit" name="upload" value="1">TXT 업로드</button>
        <span class="small">형식: 등번호,이름,파트너,소속,국적 (파트너/소속/국적은 생략 가능)</span>
    </form>
    <form class="add-form" method="post" autocomplete="off">
        <input name="number" placeholder="등번호">
        <input name="name" placeholder="이름" required>
        <input name="partner" placeholder="파트너">
        <input name="club" placeholder="소속">
        <input name="nation" placeholder="국적">
        <button type="submit" name="add" value="1">추가</button>
    </form>
    <table>
        <tr>
            <th>등번호</th>
            <th>이름</th>
            <th>파트너</th>
            <th class="th-club">소속</th>
            <th class="th-nation">국적</th>
            <th>관리</th>
        </tr>
        <?php foreach ($players as $i => $p): ?>
            <tr>
                <td><?= h($p['number']) ?></td>
                <td><?= h($p['name']) ?></td>
                <td><?= h($p['partner']) ?></td>
                <td><?= h($p['club']) ?></td>
                <td><?= h($p['nation']) ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="idx" value="<?= $i ?>">
                        <button type="submit" name="delete" value="1" class="del-btn" onclick="return confirm('정말 삭제할까요?');">삭제</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($players)): ?>
            <tr><td colspan="6" style="color:#aaa;">등록된 선수가 없습니다.</td></tr>
        <?php endif; ?>
    </table>
</div>
<footer>
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>
</body>
</html>