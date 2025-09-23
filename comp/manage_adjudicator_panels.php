<?php
$comp_id = $_GET['comp_id'] ?? '';
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";
$adjs_file = "$data_dir/adjudicators.txt";
$panel_file = "$data_dir/panel_list.json"; // 패널코드-심사위원 json

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

// 패널코드-심사위원 불러오기
$panel_list = [];
if (file_exists($panel_file)) {
    $panel_list = json_decode(file_get_contents($panel_file), true);
    if (!is_array($panel_list)) $panel_list = [];
}

// 패널코드순 정렬
usort($panel_list, fn($a, $b) => strcmp($a['panel_code'], $b['panel_code']));

// 메시지
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 패널코드-심사위원 처리
    if (isset($_POST['save_panel'])) {
        $panel_list = [];
        foreach ($_POST['panel'] as $row) {
            if (empty($row['panel_code']) || empty($row['adj_code'])) continue;
            $panel_list[] = [
                'panel_code' => trim($row['panel_code']),
                'adj_code'   => trim($row['adj_code'])
            ];
        }
        file_put_contents($panel_file, json_encode($panel_list, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $msg = "패널코드-심사위원 지정이 저장되었습니다.";
    }
    // 패널코드 파일 업로드 (txt/csv, panel_code,심사위원코드)
    if (isset($_POST['upload_panel_csv']) && isset($_FILES['panelfile']) && $_FILES['panelfile']['error'] == UPLOAD_ERR_OK) {
        $lines = file($_FILES['panelfile']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $new_panel_list = [];
        foreach ($lines as $line) {
            $parts = array_map('trim', explode(',', $line));
            if (count($parts)<2) continue;
            $panel_code = $parts[0];
            $adj_code = $parts[1];
            if ($panel_code && $adj_code) {
                $new_panel_list[] = ['panel_code'=>$panel_code, 'adj_code'=>$adj_code];
            }
        }
        $panel_list = $new_panel_list;
        file_put_contents($panel_file, json_encode($panel_list, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        $msg = "패널코드-심사위원 업로드가 완료되었습니다.";
    }
    header("Location: manage_adjudicator_panels.php?comp_id=" . urlencode($comp_id) . "&msg=" . urlencode($msg));
    exit;
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= h($info['title']) ?> 심사위원 패널코드 관리 | danceoffice.net COMP</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#f7fafd; font-family:sans-serif; margin:0;}
        .mainbox { max-width:950px; margin:3vh auto 0 auto; background:#fff; border-radius:18px; box-shadow:0 6px 32px #00339911; padding:2.2em 1.5em 2em 1.5em;}
        h1 { color:#003399; font-size:1.15em; margin-bottom:0.6em;}
        .desc {margin-bottom:1.3em; color:#333;}
        table { width:100%; border-collapse:collapse; background:#fff;}
        th, td { padding:0.3em 0.2em; text-align:center; font-size:0.96em;}
        th { background:#f0f4fa; color:#003399; font-weight:700;}
        td { color:#333;}
        tr:not(:last-child) td { border-bottom:1px solid #eee;}
        .panel-form-table input[type="text"], .panel-form-table select {
            padding: 0.2em 0.4em;
            font-size: 0.97em;
            height: 2em;
        }
        .panel-btns {
            display: flex;
            gap: 0.3em;
            justify-content: center;
            align-items: center;
        }
        .panel-add-btn, .panel-del-btn {
            padding: 0.15em 0.7em;
            font-size: 0.97em;
            height: 2em;
            min-width: 2em;
            border-radius: 7px;
        }
        .panel-add-btn { background:#03C75A; color:#fff; border:none; font-weight:700; cursor:pointer;}
        .panel-del-btn { background:#ec3b28; color:#fff; border:none; font-weight:700; cursor:pointer;}
        .panel-add-btn:hover { background:#00BFAE;}
        .panel-del-btn:hover { background:#b31e06;}
        .panel-upload-form { margin:0 0 1.2em 0; display:flex; align-items:center; gap:0.7em;}
        .panel-upload-form input[type="file"] { border-radius:7px; border:1px solid #bbb; padding:0.3em;}
        .panel-upload-form button { background:#003399; color:#fff; border:none; border-radius:8px; padding:0.4em 1.6em; font-weight:700; cursor:pointer;}
        .panel-upload-form button:hover { background:#2222aa;}
        .small {font-size:0.93em; color:#888; margin-left:0.8em;}
        .goto-dash {display:inline-block; margin-bottom:1.1em; color:#888;}
        .goto-dash:hover {color:#003399;}
        .goto-data {color:#03C75A; margin-left:1em;}
        .goto-data:hover {color:#009c85;}
        .goto-adj {
            display:inline-block; margin-left:1em; font-size:1em; color:#003399; background:#eaf3ff;
            padding:0.38em 1.2em; border-radius:7px; text-decoration:none; font-weight:600; transition:background 0.15s;
        }
        .goto-adj:hover { background:#d3e9ff; color:#021b75;}
        .print-btn { background:#666; color:#fff; font-weight:600; border:none; border-radius:8px; padding:0.3em 1.5em; margin-left:1em; cursor:pointer;}
        .print-btn:hover { background:#003399; }
        .print-adj-name { display:none; }
        @media (max-width:1000px) {
            .mainbox { max-width:99vw; padding:1.1em 0.3em;}
            .panel-form-table input[type="text"] {min-width:70px;}
        }
        @media print {
            .print-btn, .panel-btns, .panel-upload-form, .goto-dash, .goto-data, .goto-adj, .msg, .desc, h1, footer { display:none !important; }
            .mainbox { box-shadow:none !important; padding:0; }
            table, th, td { font-size:1em; }
            body { background:#fff !important; }
            select { display: none !important; }
            .print-adj-name { display: inline !important; }
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
    <script>
    // 동적으로 패널 행 추가/삭제
    function addPanelRow() {
        const tb = document.getElementById('panel-table-body');
        const row = tb.rows[0].cloneNode(true);
        row.querySelectorAll('input,select').forEach(el => {
            if (el.tagName === 'INPUT') el.value='';
            if (el.tagName === 'SELECT') el.selectedIndex=0;
        });
        row.querySelector('.print-adj-name').textContent = '';
        tb.appendChild(row);
    }
    function removePanelRow(btn) {
        const tb = document.getElementById('panel-table-body');
        if (tb.rows.length>1) btn.closest('tr').remove();
        else alert("최소 1행은 필요합니다.");
    }
    </script>
</head>
<body>
<div class="mainbox">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2em;">
        <div>
            <a href="dashboard.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-dash">&lt; 대시보드로</a>
            <a href="data_manage.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-data">데이터 관리로 &gt;</a>
        </div>
        <div>
            <a href="manage_adjudicators.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-adj">심사위원 관리로 이동 &gt;</a>
            <button type="button" onclick="window.print()" class="print-btn">명단 인쇄</button>
        </div>
    </div>
    <h1><?= h($info['title']) ?> 심사위원 패널코드 관리</h1>
    <div class="desc">
        패널코드와 심사위원을 조합하여 패널을 등록하세요.<br>
        (이벤트별 배정은 필요시 별도 구현)
    </div>
    <?php if ($msg): ?><div class="msg" style="color:#03C75A; margin-bottom:1em;"><?= h($msg) ?></div><?php endif; ?>

    <form class="panel-upload-form" method="post" enctype="multipart/form-data" onsubmit="return confirm('패널코드-심사위원코드 txt/csv 파일로 패널을 일괄 업로드합니다. 진행할까요?');">
        <input type="file" name="panelfile" accept=".txt,.csv" required>
        <button type="submit" name="upload_panel_csv" value="1">패널 TXT 업로드</button>
        <span class="small">형식: 패널코드,심사위원코드 (한 줄에 한 패널)</span>
    </form>

    <form method="post">
    <table class="panel-form-table" style="margin-bottom:1.8em;">
        <thead>
            <tr>
                <th style="width:110px;">패널코드</th>
                <th>심사위원</th>
                <th style="width:80px;">관리</th>
            </tr>
        </thead>
        <tbody id="panel-table-body">
        <?php if (empty($panel_list)): ?>
            <tr>
                <td><input type="text" name="panel[0][panel_code]" value="" placeholder="예: PA"></td>
                <td>
                    <select name="panel[0][adj_code]" style="min-width:120px;">
                        <option value="">-- 심사위원 선택 --</option>
                        <?php foreach ($adjudicators as $adj): ?>
                        <option value="<?=h($adj['code'])?>"><?=h($adj['name'])?> (<?=h($adj['code'])?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <span class="print-adj-name"></span>
                </td>
                <td>
                    <div class="panel-btns">
                        <button type="button" class="panel-add-btn" onclick="addPanelRow()">+</button>
                        <button type="button" class="panel-del-btn" onclick="removePanelRow(this)">-</button>
                    </div>
                </td>
            </tr>
        <?php else: foreach ($panel_list as $i=>$row): ?>
            <tr>
                <td>
                    <input type="text" name="panel[<?=$i?>][panel_code]" value="<?=h($row['panel_code'])?>" placeholder="예: PA">
                </td>
                <td>
                    <select name="panel[<?=$i?>][adj_code]" style="min-width:120px;">
                        <option value="">-- 심사위원 선택 --</option>
                        <?php foreach ($adjudicators as $adj): ?>
                        <option value="<?=h($adj['code'])?>"<?=($row['adj_code']==$adj['code']?' selected':'')?>>
                            <?=h($adj['name'])?> (<?=h($adj['code'])?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="print-adj-name" style="display:none;">
                        <?php
                        $found = array_filter($adjudicators, fn($a) => $a['code']==$row['adj_code']);
                        $adj = $found ? array_values($found)[0] : null;
                        echo $adj ? h($adj['name'].' ('.$adj['code'].')') : '';
                        ?>
                    </span>
                </td>
                <td>
                    <div class="panel-btns">
                        <button type="button" class="panel-add-btn" onclick="addPanelRow()">+</button>
                        <button type="button" class="panel-del-btn" onclick="removePanelRow(this)">-</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <button type="submit" name="save_panel" value="1" class="panel-add-btn" style="padding:0.5em 2em;">패널 저장</button>
    </form>
</div>
<footer>
    2025 danceoffice.net | Powered by Seyoung Lee
</footer>
</body>
</html>