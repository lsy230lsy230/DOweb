<?php
$comp_id = $_GET['comp_id'] ?? '';
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$dance_file = "$data_dir/DanceName.txt";

// 대회 정보 로드
if (!preg_match('/^\d{8}-\d+$/', $comp_id) || !is_file($info_file)) {
    echo "<h1>잘못된 대회 ID 또는 대회 정보가 없습니다.</h1>";
    exit;
}
$info = json_decode(file_get_contents($info_file), true);

// 댄스 약어 => 풀네임 매핑
$dance_types = [];
if (file_exists($dance_file)) {
    $lines = file($dance_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        if (isset($cols[2]) && $cols[2] && isset($cols[1]) && $cols[1]) {
            $dance_types[strtoupper($cols[2])] = $cols[1]; // 약자(대문자) => 풀네임
        }
    }
}

// 시간 입력 처리
function padzero($n) { return str_pad($n, 2, "0", STR_PAD_LEFT); }
function to_time($s) {
    if (strpos($s, ':') !== false) {
        [$h, $m] = explode(':', $s);
        return intval($h) * 60 + intval($m);
    }
    return intval($s);
}
function to_hm($m) {
    $h = floor($m / 60);
    $m = $m % 60;
    return padzero($h) . ':' . padzero($m);
}
$start_time_str = $_POST['start_time'] ?? '09:00';
$opening_time_str = $_POST['opening_time'] ?? '10:30';
$start_time_min = to_time($start_time_str);
$opening_time_min = to_time($opening_time_str);

// 추가 시간 저장 처리
if (isset($_POST['save_extra_times']) && isset($_POST['extra_times'])) {
    $extra_times = $_POST['extra_times'];
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES);
    $new_lines = [];
    
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) {
            $new_lines[] = $line;
            continue;
        }
        
        $cols = explode(",", $line);
        if (count($cols) >= 14) {
            $event_no = $cols[0];
            $extra_time = isset($extra_times[$event_no]) ? intval($extra_times[$event_no]) : 0;
            
            // 15번째 컬럼이 없으면 추가
            while (count($cols) < 15) {
                $cols[] = '';
            }
            $cols[14] = $extra_time; // 15번째 컬럼에 추가 시간 저장
            $new_lines[] = implode(",", $cols);
        } else {
            $new_lines[] = $line;
        }
    }
    
    file_put_contents($runorder_file, implode("\n", $new_lines));
    header("Location: manage_timetable.php?comp_id=" . urlencode($comp_id));
    exit;
}

// 특별 이벤트 저장 처리
if (isset($_POST['save_special_events'])) {
    $special_events = $_POST['special_events'] ?? [];
    $special_events_file = "$data_dir/special_events.json";
    file_put_contents($special_events_file, json_encode($special_events, JSON_UNESCAPED_UNICODE));
    header("Location: manage_timetable.php?comp_id=" . urlencode($comp_id));
    exit;
}

// 특별 이벤트 추가 처리
if (isset($_POST['add_special_event'])) {
    $name = trim($_POST['special_event_name'] ?? '');
    $after_event = intval($_POST['special_event_after'] ?? 1);
    $duration = intval($_POST['special_event_duration'] ?? 10);
    
    if (!empty($name) && $duration > 0) {
        $special_events_file = "$data_dir/special_events.json";
        $special_events = [];
        if (file_exists($special_events_file)) {
            $special_events = json_decode(file_get_contents($special_events_file), true) ?? [];
        }
        
        $event_id = uniqid();
        $special_events[$event_id] = [
            'name' => $name,
            'after_event' => $after_event,
            'duration' => $duration
        ];
        
        file_put_contents($special_events_file, json_encode($special_events, JSON_UNESCAPED_UNICODE));
    }
    header("Location: manage_timetable.php?comp_id=" . urlencode($comp_id));
    exit;
}

// 특별 이벤트 삭제 처리
if (isset($_POST['delete_special_event'])) {
    $event_id = $_POST['event_id'];
    $special_events_file = "$data_dir/special_events.json";
    if (file_exists($special_events_file)) {
        $special_events = json_decode(file_get_contents($special_events_file), true) ?? [];
        unset($special_events[$event_id]);
        file_put_contents($special_events_file, json_encode($special_events, JSON_UNESCAPED_UNICODE));
    }
    header("Location: manage_timetable.php?comp_id=" . urlencode($comp_id));
    exit;
}

// 저장된 추가 시간 불러오기
$extra_times = [];
if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = explode(",", $line);
        if (count($cols) >= 15) {
            $event_no = $cols[0];
            $extra_time = !empty($cols[14]) ? intval($cols[14]) : 0;
            $extra_times[$event_no] = $extra_time;
            if ($event_no == '1') {
                echo "<!-- 디버깅 extra_times 읽기: 순번 1, cols[14]=" . $cols[14] . ", extra_time=$extra_time -->";
            }
        }
    }
}

// 저장된 특별 이벤트 불러오기
$special_events = [];
$special_events_file = "$data_dir/special_events.json";
if (file_exists($special_events_file)) {
    $special_events = json_decode(file_get_contents($special_events_file), true) ?? [];
}

// 저장된 라운드 정보 불러오기
$round_info = [];
$round_info_file = "$data_dir/round_info.json";
if (file_exists($round_info_file)) {
    $round_data = json_decode(file_get_contents($round_info_file), true);
    if ($round_data && isset($round_data['round_info'])) {
        $round_info = $round_data['round_info'];
    }
}

// RunOrder에서 이벤트 불러오기
$events = [];
$raw_no_groups = []; // raw_no별로 그룹화

if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        // 순번,이벤트명,라운드타입,라운드차수,진출자수,다음라운드,댄스1~5,패널코드,시간(분),세부번호
        $no = $cols[0] ?? '';
        $desc = $cols[1] ?? '';
        $roundtype = $cols[2] ?? '';
        $roundnum = $cols[3] ?? '';
        $detail_no = $cols[13] ?? ''; // 세부번호 추가
        $dances = [];
        for ($i = 6; $i <= 10; $i++) {
            if (!empty($cols[$i])) $dances[] = $cols[$i];
        }
        if (count($dances) === 0) continue; // 경기 외 이벤트는 타임테이블에서 제외
        
        $dances_full = [];
        foreach ($dances as $abbr) {
            // 숫자로 된 댄스 코드를 처리
            if (is_numeric($abbr)) {
                $dance_num = intval($abbr);
                // DanceName.txt에서 해당 번호의 댄스 찾기
                $found_dance = '';
                if (file_exists($dance_file)) {
                    $lines = file($dance_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        if (preg_match('/^bom/', $line)) continue;
                        $cols = array_map('trim', explode(',', $line));
                        if (isset($cols[0]) && intval($cols[0]) === $dance_num) {
                            $found_dance = $cols[1] ?? '';
                            break;
                        }
                    }
                }
                $dances_full[] = $found_dance ?: $abbr;
            } else {
                // 약어로 된 댄스 코드를 처리
                $abbr_up = strtoupper($abbr);
                $dances_full[] = isset($dance_types[$abbr_up]) ? $dance_types[$abbr_up] : $abbr;
            }
        }
        
        // raw_no별로 그룹화
        if (!isset($raw_no_groups[$no])) {
            $raw_no_groups[$no] = [];
        }
        $extra_time = isset($cols[14]) && !empty($cols[14]) ? intval($cols[14]) : 0;
        if ($no == '1') {
            echo "<!-- 디버깅 순번 1: cols[14]=" . (isset($cols[14]) ? $cols[14] : '없음') . ", extra_time=$extra_time -->";
        }
        $raw_no_groups[$no][] = [
            'no' => $no,
            'desc' => $desc,
            'roundtype' => $roundtype,
            'roundnum' => $roundnum,
            'detail_no' => $detail_no,
            'dances' => $dances_full,
            'dance_count' => count($dances_full),
            'extra_time' => $extra_time
        ];
    }
}

// 각 raw_no 그룹에서 댄스 수가 가장 많은 이벤트를 찾아 시간 계산용으로 사용
foreach ($raw_no_groups as $raw_no => $group) {
    // 댄스 수가 가장 많은 이벤트 찾기 (시간 계산용)
    $max_dance_count = 0;
    $selected_event = null;
    
    foreach ($group as $event) {
        if ($event['dance_count'] > $max_dance_count) {
            $max_dance_count = $event['dance_count'];
            $selected_event = $event;
        }
    }
    
    if ($selected_event) {
        $base_time = 1.5; // 기본 시간 (분)
        $duration = $base_time * $max_dance_count; // 종목수만큼 곱하기!
        
        // 저장된 라운드 정보가 있으면 사용, 없으면 기존 방식 사용
        $event_idx = count($events);
        $calculated_round = isset($round_info[$event_idx]) ? $round_info[$event_idx] : 
                           ($selected_event['roundtype'] . ($selected_event['roundtype'] === '예선전' && $selected_event['roundnum'] ? " $selected_event[roundnum]차" : ''));
        
        // 멀티이벤트의 경우 raw_no 기준으로 추가 시간 사용
        $extra_time = isset($extra_times[$raw_no]) ? $extra_times[$raw_no] : 0;
        
        $events[] = [
            'no' => $selected_event['no'],
            'desc' => $selected_event['desc'],
            'round' => $calculated_round,
            'dances' => $selected_event['dances'],
            'duration' => $duration,
            'detail_no' => $selected_event['detail_no'],
            'extra_time' => $extra_time, // raw_no 기준으로 추가 시간 설정
            'group_events' => $group, // 멀티이벤트 정보 저장 (모든 이벤트 포함)
        ];
        
        // 디버깅: 순번 1의 추가 시간 확인
        if ($selected_event['no'] == '1') {
            echo "<!-- 디버깅 이벤트 배열: 순번 1, extra_time=" . $extra_time . " -->";
        }
    }
}

// 타임테이블 계산: 각 이벤트의 시작/종료 시간 구하기
$rows = [];
$cur_min = $start_time_min;
$opening_row_idx = null;

for ($i = 0; $i < count($events); $i++) {
    // 개회식 시각 도달 전이면 계속 진행
    if ($cur_min < $opening_time_min && $cur_min + $events[$i]['duration'] >= $opening_time_min && $opening_row_idx === null) {
        // 개회식 삽입
        $rows[] = [
            'no' => '',
            'desc' => '개회식',
            'round' => '',
            'dances' => [],
            'start' => $opening_time_min,
            'end' => $opening_time_min + 20, // 기본 20분 예시
            'is_opening' => true
        ];
        $cur_min = $opening_time_min + 20;
        $opening_row_idx = count($rows) - 1;
    }
    
    // 특별 이벤트 확인 (현재 이벤트 번호 후에 삽입할 특별 이벤트)
    $event_no = $events[$i]['no'];
    foreach ($special_events as $special_event) {
        if ($special_event['after_event'] == $event_no) {
            $special_duration = intval($special_event['duration']);
            $rows[] = [
                'no' => '',
                'desc' => $special_event['name'],
                'round' => '',
                'dances' => [],
                'start' => $cur_min,
                'end' => $cur_min + $special_duration,
                'is_special' => true,
                'special_type' => $special_event['name']
            ];
            $cur_min += $special_duration;
        }
    }
    
    // 추가 시간 적용
    $extra_time = $events[$i]['extra_time'] ?? 0;
    $total_duration = $events[$i]['duration'] + $extra_time;
    
    $rows[] = [
        'no' => $events[$i]['no'],
        'desc' => $events[$i]['desc'],
        'round' => $events[$i]['round'],
        'dances' => $events[$i]['dances'],
        'detail_no' => $events[$i]['detail_no'], // 세부번호 추가
        'start' => $cur_min,
        'end' => $cur_min + $total_duration,
        'is_opening' => false,
        'group_events' => $events[$i]['group_events'] ?? [], // 멀티이벤트 정보 추가
        'extra_time' => $extra_time, // 추가 시간 정보
    ];
    $cur_min += $total_duration;
}

// 개회식 전 이벤트 시간 부족/초과 체크
$total_pre_opening = 0;
foreach ($rows as $r) {
    if (isset($r['is_opening']) && $r['is_opening']) break;
    $total_pre_opening += ($r['end'] - $r['start']);
}
$warning = '';
if ($total_pre_opening < ($opening_time_min - $start_time_min)) {
    $lack = ($opening_time_min - $start_time_min) - $total_pre_opening;
    $warning = "개회식 전 이벤트 시간이 " . intval($lack) . "분 부족합니다.";
} elseif ($total_pre_opening > ($opening_time_min - $start_time_min)) {
    $over = $total_pre_opening - ($opening_time_min - $start_time_min);
    $warning = "개회식 전 이벤트 시간이 " . intval($over) . "분 초과합니다.";
}

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?= h($info['title']) ?> 타임테이블 | danceoffice.net COMP</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#1a1a1a; font-family:sans-serif; margin:0;}
        .ttbox { max-width:1200px; margin:3vh auto 0 auto; background:#222; border-radius:18px; box-shadow:0 6px 32px #00339922; padding:2.7em 2em 2.2em 2em;}
        h1 { color:#03C75A; font-size:1.25em; margin-bottom:0.6em;}
        table { width:100%; border-collapse:collapse; background:#222; }
        th, td { padding:0.45em 0.3em; text-align:center; font-size:0.98em;}
        th { background:#202433; color:#03C75A; font-weight:700;}
        td { color:#eee;}
        tr:not(:last-child) td { border-bottom:1px solid #393a4a;}
        .opening-row { background:#e4f7ff !important; color:#0079b8 !important;}
        .warn { color:#ec3b28; font-weight:700; margin-bottom:1em;}
        .ttform {margin-bottom:1.5em;}
        input[type="time"] {padding:0.3em 0.9em; font-size:1em;}
        .ttform label {margin-right:1.1em;}
        .ttform button {background:#03C75A;color:#fff;border:none;border-radius:8px;padding:0.5em 1.5em;font-weight:700;font-size:1.07em;cursor:pointer;}
        .ttform button:hover {background:#009f5d;}
        .goto-dash {display:inline-block;margin-bottom:1.2em;color:#bbb;}
        .goto-dash:hover {color:#03C75A;}
        @media (max-width:1050px) {
            .ttbox {max-width:99vw;}
        }
        @media (max-width:700px) {
            .ttbox {padding:1.3em 0.2em;}
        }
    </style>
    <script>
        function saveExtraTime(eventNo, value) {
            console.log('saveExtraTime 호출됨:', eventNo, value);
            
            // 폼 생성
            const form = document.createElement('form');
            form.method = 'post';
            form.style.display = 'none';
            
            // 추가 시간 데이터 추가
            const extraTimeInput = document.createElement('input');
            extraTimeInput.type = 'hidden';
            extraTimeInput.name = 'extra_times[' + eventNo + ']';
            extraTimeInput.value = value;
            form.appendChild(extraTimeInput);
            
            // 저장 플래그 추가
            const saveFlag = document.createElement('input');
            saveFlag.type = 'hidden';
            saveFlag.name = 'save_extra_times';
            saveFlag.value = '1';
            form.appendChild(saveFlag);
            
            console.log('폼 데이터:', {
                eventNo: eventNo,
                value: value,
                formHTML: form.outerHTML
            });
            
            // 폼을 body에 추가하고 제출
            document.body.appendChild(form);
            console.log('폼 제출 전');
            form.submit();
        }
        
        // 페이지 로드 시 디버깅 정보 출력
        window.addEventListener('load', function() {
            console.log('페이지 로드 완료 - 버튼 방식으로 변경됨');
        });
    </script>
</head>
<body>
<div class="ttbox">
    <a href="dashboard.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-dash">&lt; 대시보드로</a>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1><?= h($info['title']) ?> <span style="font-size:0.85em;color:#03c75a;">타임테이블</span></h1>
        <div style="display: flex; gap: 10px;">
            <a href="export_timetable.php?comp=<?=h($comp_id)?>" class="btn" style="background: #e74c3c; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-size: 14px;" target="_blank">
                📄 PDF 내보내기
            </a>
            <a href="export_excel.php?comp=<?=h($comp_id)?>" class="btn" style="background: #27ae60; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-size: 14px;">
                📊 엑셀 내보내기
            </a>
        </div>
    </div>
    <div class="compinfo" style="color:#bbb; margin-bottom:1.2em;">
        <b>일자:</b> <?= h($info['date']) ?> &nbsp; <b>장소:</b> <?= h($info['place']) ?> &nbsp; <b>주최/주관:</b> <?= h($info['host']) ?>
    </div>
    <form method="post" class="ttform">
        <div style="margin-bottom: 15px;">
            <label>
                대회 시작 시간: <input type="time" name="start_time" value="<?=h($start_time_str)?>">
            </label>
            <label>
                개회식 시간: <input type="time" name="opening_time" value="<?=h($opening_time_str)?>">
            </label>
        </div>
        
        <button type="submit">타임테이블 생성</button>
    </form>
    
    
    <!-- 특별 이벤트 설정 -->
    <div style="margin: 20px 0; padding: 15px; background: #333; border-radius: 5px;">
        <h3 style="color: #03C75A; margin: 0 0 15px 0;">특별 이벤트 설정</h3>
        <form method="post" id="special-event-form">
            <div style="margin-bottom: 15px;">
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                    <input type="text" name="special_event_name" placeholder="이벤트명 (예: 브레이크 타임, 시상식 등)" style="flex: 1; padding: 8px;">
                    <span>순번</span>
                    <input type="number" name="special_event_after" value="1" min="1" style="width: 80px; padding: 8px;">
                    <span>후</span>
                    <input type="number" name="special_event_duration" value="10" min="1" style="width: 80px; padding: 8px;">
                    <span>분</span>
                    <button type="submit" name="add_special_event" style="background: #03C75A; color: white; border: none; padding: 8px 15px; border-radius: 3px;">추가</button>
                </div>
            </div>
        </form>
        
        <!-- 저장된 특별 이벤트 목록 -->
        <?php if (!empty($special_events)): ?>
            <div style="margin-top: 15px;">
                <h4 style="color: #90EE90; margin: 0 0 10px 0;">등록된 특별 이벤트</h4>
                <?php foreach ($special_events as $id => $event): ?>
                    <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: #444; border-radius: 3px; margin-bottom: 5px;">
                        <span style="color: #eee;"><?=h($event['name'])?></span>
                        <span style="color: #ccc;">순번 <?=h($event['after_event'])?> 후</span>
                        <span style="color: #ccc;"><?=h($event['duration'])?>분</span>
                        <form method="post" style="margin: 0;">
                            <input type="hidden" name="event_id" value="<?=h($id)?>">
                            <button type="submit" name="delete_special_event" style="background: #ff4444; color: white; border: none; padding: 5px 10px; border-radius: 3px; font-size: 12px;">삭제</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if($warning): ?>
        <div class="warn"><?=h($warning)?></div>
    <?php endif; ?>
    <table>
        <tr>
            <th>Ext. Time</th>
            <th>Start</th>
            <th>End</th>
            <th>No.</th>
            <th>Detail No.</th>
            <th>Description</th>
            <th>Round</th>
            <th colspan="6">Dances</th>
        </tr>
        <?php foreach ($rows as $row): ?>
            <?php if (!empty($row['is_opening'])): ?>
                <tr class="opening-row">
                    <td></td>
                    <td><?=to_hm($row['start'])?></td>
                    <td><?=to_hm($row['end'])?></td>
                    <td colspan="10"><b>개회식</b></td>
                </tr>
            <?php elseif (!empty($row['is_special'])): ?>
                <tr style="background: #2a4d3a; color: #90EE90;">
                    <td></td>
                    <td><?=to_hm($row['start'])?></td>
                    <td><?=to_hm($row['end'])?></td>
                    <td colspan="10"><b><?=h($row['special_type'])?></b></td>
                </tr>
            <?php else: ?>
                <?php if (isset($row['group_events']) && count($row['group_events']) > 1): ?>
                    <!-- 멀티이벤트: 첫 번째 행 -->
                    <tr>
                        <td rowspan="<?=count($row['group_events'])?>">
                            <input type="number" id="extra_<?=h($row['no'])?>" value="<?=h($row['extra_time'])?>" min="0" style="width: 50px; padding: 2px; text-align: center;">
                            <button type="button" onclick="saveExtraTime('<?=h($row['no'])?>', document.getElementById('extra_<?=h($row['no'])?>').value)" style="padding: 2px 5px; font-size: 10px;">저장</button>
                        </td>
                        <td rowspan="<?=count($row['group_events'])?>"><?=to_hm($row['start'])?></td>
                        <td rowspan="<?=count($row['group_events'])?>"><?=to_hm($row['end'])?></td>
                        <td rowspan="<?=count($row['group_events'])?>"><?=h($row['no'])?></td>
                        <td><?=h($row['group_events'][0]['detail_no'])?></td>
                        <td><?=h($row['group_events'][0]['desc'])?></td>
                        <td rowspan="<?=count($row['group_events'])?>"><?=h($row['round'])?></td>
                        <?php for($i=0;$i<6;$i++): ?>
                            <td><?=isset($row['group_events'][0]['dances'][$i]) ? h($row['group_events'][0]['dances'][$i]) : ''?></td>
                        <?php endfor; ?>
                    </tr>
                    <!-- 멀티이벤트: 나머지 행들 -->
                    <?php for($j=1; $j<count($row['group_events']); $j++): ?>
                        <tr>
                            <td><?=h($row['group_events'][$j]['detail_no'])?></td>
                            <td><?=h($row['group_events'][$j]['desc'])?></td>
                            <?php for($i=0;$i<6;$i++): ?>
                                <td><?=isset($row['group_events'][$j]['dances'][$i]) ? h($row['group_events'][$j]['dances'][$i]) : ''?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                <?php else: ?>
                    <!-- 단일 이벤트 -->
                    <tr>
                        <td>
                            <input type="number" id="extra_<?=h($row['no'])?>" value="<?=h($row['extra_time'])?>" min="0" style="width: 50px; padding: 2px; text-align: center;">
                            <button type="button" onclick="saveExtraTime('<?=h($row['no'])?>', document.getElementById('extra_<?=h($row['no'])?>').value)" style="padding: 2px 5px; font-size: 10px;">저장</button>
                        </td>
                        <td><?=to_hm($row['start'])?></td>
                        <td><?=to_hm($row['end'])?></td>
                        <td><?=h($row['no'])?></td>
                        <td><?=h($row['detail_no'])?></td>
                        <td><?=h($row['desc'])?></td>
                        <td><?=h($row['round'])?></td>
                        <?php for($i=0;$i<6;$i++): ?>
                            <td><?=isset($row['dances'][$i]) ? h($row['dances'][$i]) : ''?></td>
                        <?php endfor; ?>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>