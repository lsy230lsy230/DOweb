<?php
// 대회 목록 및 신규 대회 생성
$data_dir = __DIR__ . '/data';
function load_competitions($data_dir) {
    $comps = [];
    if (!is_dir($data_dir)) return $comps;
    foreach (glob($data_dir . "/*/info.json") as $info_file) {
        $comp_id = basename(dirname($info_file));
        $info = json_decode(file_get_contents($info_file), true);
        if ($info) {
            $info['id'] = $comp_id;
            $comps[] = $info;
        }
    }
    usort($comps, function($a, $b) {
        return ($b['created'] ?? 0) <=> ($a['created'] ?? 0);
    });
    return $comps;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comp'])) {
    $title = trim($_POST['title'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $place = trim($_POST['place'] ?? '');
    $host = trim($_POST['host'] ?? '');
    if ($title && $date && $place && $host) {
        $today = date('Ymd');
        $seq = 1;
        do {
            $comp_id = sprintf("%s-%03d", $today, $seq++);
            $comp_path = "$data_dir/$comp_id";
        } while (file_exists($comp_path));
        mkdir($comp_path, 0777, true);
        $info = [
            'title' => $title,
            'date' => $date,
            'place' => $place,
            'host' => $host,
            'created' => time()
        ];
        file_put_contents("$comp_path/info.json", json_encode($info, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        header("Location: dashboard.php?comp_id=$comp_id");
        exit;
    } else {
        $error = "모든 항목을 입력해주세요.";
    }
}
$comps = load_competitions($data_dir);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>대회 현황판 | danceoffice.net COMP</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#f7fafd; font-family:sans-serif; margin:0; }
        h1 { color:#003399; text-align:center; font-size:1.35em; margin:1.2em 0 0.8em 0;}
        .newcomp-wrap {background:#fff; max-width:420px; margin:1.5em auto 2em auto; border-radius:13px; box-shadow:0 4px 18px #00339914; padding:1.2em 1.2em 1.2em 1.2em;}
        .newcomp-wrap form { display:grid; gap:0.7em;}
        input, button { padding:0.55em 0.7em; border-radius:7px; border:1px solid #bbb; font-size:1em;}
        input {border:1px solid #bbb;}
        button { border:none; background:#03C75A; color:#fff; font-weight:700; font-size:1em; cursor:pointer;}
        button:hover { background:#00bfae;}
        .error { color:#c22; margin-bottom:0.5em;}
        .comps-list {max-width:760px; margin:0 auto 3em auto;}
        table { width:100%; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 3px 14px #00339910;}
        th, td { padding:0.7em 0.3em; text-align:center;}
        th { background:#f0f4fa; color:#003399; font-weight:700;}
        td { color:#333;}
        tr:not(:last-child) td { border-bottom:1px solid #eee;}
        .dash-btn { background:#003399; color:#fff; border-radius:8px; padding:0.4em 1.2em; font-size:0.98em; text-decoration:none; }
        .dash-btn:hover { background:#023175;}
        .none-msg {text-align:center; color:#aaa; margin:2.5em 0 2em 0;}
        footer {margin-top:2.2em;text-align:center;font-size:0.92em;color:#aaa; padding: 1em 0;}
        @media (max-width:700px) {
            .comps-list {max-width:99vw;}
            table {font-size:0.98em;}
            .newcomp-wrap {max-width:97vw;}
        }
    </style>
</head>
<body>
<h1>댄스스포츠 대회 현황판</h1>
<div class="newcomp-wrap">
    <form method="post">
        <div style="font-weight:600; margin-bottom:0.7em; color:#03C75A;">새 대회 만들기</div>
        <?php if (!empty($error)): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <input name="title" placeholder="대회명" required>
        <input type="date" name="date" required>
        <input name="place" placeholder="장소" required>
        <input name="host" placeholder="주최/주관" required>
        <button type="submit" name="add_comp" value="1">대회 생성</button>
    </form>
</div>
<div class="comps-list">
    <?php if (empty($comps)): ?>
        <div class="none-msg">등록된 대회가 없습니다.</div>
    <?php else: ?>
        <table>
            <tr>
                <th>대회명</th>
                <th>일자</th>
                <th>장소</th>
                <th>주최</th>
                <th>관리</th>
            </tr>
            <?php foreach ($comps as $comp): ?>
                <tr>
                    <td><?= htmlspecialchars($comp['title']) ?></td>
                    <td><?= htmlspecialchars($comp['date']) ?></td>
                    <td><?= htmlspecialchars($comp['place']) ?></td>
                    <td><?= htmlspecialchars($comp['host']) ?></td>
                    <td>
                        <a class="dash-btn" href="dashboard.php?comp_id=<?= urlencode($comp['id']) ?>">대시보드</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
<footer>
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>
</body>
</html>