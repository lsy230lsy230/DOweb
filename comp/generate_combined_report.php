<?php
header('Content-Type: application/json; charset=utf-8');

// POST 데이터 받기
$input = json_decode(file_get_contents('php://input'), true);
$comp_id = $input['comp_id'] ?? '';
$event_no = $input['event_no'] ?? '';
$event_name = $input['event_name'] ?? '';

if (empty($comp_id) || empty($event_no)) {
    echo json_encode(['success' => false, 'message' => '필수 파라미터가 누락되었습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 대회 데이터 디렉토리
$data_dir = __DIR__ . "/data/$comp_id";

// 이벤트 정보 로드
$event_info_file = "$data_dir/event_info.json";
$event_info = [];
if (file_exists($event_info_file)) {
    $event_info = json_decode(file_get_contents($event_info_file), true) ?: [];
}

// 선수 정보 로드
$players_file = "$data_dir/players.txt";
$all_players = [];
if (file_exists($players_file)) {
    $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        if (count($cols) >= 3) {
            $all_players[$cols[0]] = [
                'male' => $cols[1],
                'female' => $cols[2]
            ];
        }
    }
}

// 이벤트별 선수 로드
$event_players_file = "$data_dir/players_$event_no.txt";
$event_players = [];
if (file_exists($event_players_file)) {
    $lines = file($event_players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $player_no = trim($line);
        if (!empty($player_no)) {
            $event_players[] = $player_no;
        }
    }
}

// 심사위원 정보 로드
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
                'country' => $cols[2] ?? '',
                'password' => $cols[3]
            ];
        }
    }
}

// 채점 파일들 로드
$scores = [];
$score_files = glob("$data_dir/{$event_no}_*.adj");
foreach ($score_files as $file) {
    $filename = basename($file);
    if (preg_match('/^' . preg_quote($event_no) . '_(\d+)_(\d+)\.adj$/', $filename, $matches)) {
        $judge_id = $matches[1];
        $round = $matches[2];
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rankings = [];
        foreach ($lines as $line) {
            $player_no = trim($line, '"');
            if (!empty($player_no)) {
                $rankings[] = $player_no;
            }
        }
        
        if (!isset($scores[$judge_id])) {
            $scores[$judge_id] = [];
        }
        $scores[$judge_id][$round] = $rankings;
    }
}

// 결과 파일 로드
$result_file = "$data_dir/players_hits_$event_no.json";
$final_rankings = [];
if (file_exists($result_file)) {
    $result_data = json_decode(file_get_contents($result_file), true);
    if ($result_data) {
        foreach ($result_data as $rank => $players) {
            foreach ($players as $player_no) {
                $final_rankings[] = [
                    'rank' => $rank,
                    'player_no' => $player_no,
                    'player_name' => isset($all_players[$player_no]) ? 
                        $all_players[$player_no]['male'] . ' / ' . $all_players[$player_no]['female'] : 
                        "선수 $player_no"
                ];
            }
        }
    }
}

// 컴바인 리포트 HTML 생성
$html = generateCombinedReportHTML($comp_id, $event_no, $event_name, $event_info, $all_players, $event_players, $adjudicators, $scores, $final_rankings);

// 리포트 파일 저장
$report_filename = "combined_result_{$event_no}.html";
$report_path = __DIR__ . "/reports/$report_filename";

// reports 디렉토리 생성
if (!is_dir(__DIR__ . "/reports")) {
    mkdir(__DIR__ . "/reports", 0755, true);
}

file_put_contents($report_path, $html);

echo json_encode([
    'success' => true,
    'report_url' => "/comp/reports/$report_filename"
], JSON_UNESCAPED_UNICODE);

function generateCombinedReportHTML($comp_id, $event_no, $event_name, $event_info, $all_players, $event_players, $adjudicators, $scores, $final_rankings) {
    $comp_info = json_decode(file_get_contents(__DIR__ . "/data/$comp_id/info.json"), true);
    
    $html = '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($event_name) . ' - 상세 결과 리포트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { 
            text-align: center; color: white; margin-bottom: 30px; 
            background: rgba(255,255,255,0.1); padding: 30px; border-radius: 15px;
        }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        .header p { font-size: 1.2em; opacity: 0.9; }
        .report-section { 
            background: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .report-section h2 { 
            color: #667eea; margin-bottom: 20px; font-size: 1.5em; 
            border-bottom: 3px solid #667eea; padding-bottom: 10px;
        }
        table { 
            width: 100%; border-collapse: collapse; margin-top: 15px; 
            background: white; border-radius: 8px; overflow: hidden;
        }
        th, td { 
            padding: 12px; text-align: center; border: 1px solid #e0e0e0; 
        }
        th { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; font-weight: bold;
        }
        tr:nth-child(even) { background: #f8f9fa; }
        tr:hover { background: #e3f2fd; }
        .player-name { text-align: left; font-weight: 500; }
        .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #333; font-weight: bold; }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #333; font-weight: bold; }
        .rank-3 { background: linear-gradient(135deg, #cd7f32, #daa520); color: white; font-weight: bold; }
        .judge-scores { font-size: 0.9em; }
        .final-rank { font-weight: bold; font-size: 1.1em; }
        .print-btn { 
            position: fixed; top: 20px; right: 20px; z-index: 1000;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; padding: 12px 25px; border-radius: 25px;
            cursor: pointer; font-size: 16px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .print-btn:hover { transform: translateY(-2px); }
        @media print {
            body { background: white !important; }
            .print-btn { display: none !important; }
            .report-section { box-shadow: none !important; margin-bottom: 10px !important; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ 인쇄</button>
    
    <div class="container">
        <div class="header">
            <h1>🏆 ' . htmlspecialchars($event_name) . '</h1>
            <p>이벤트 번호: ' . htmlspecialchars($event_no) . ' | ' . htmlspecialchars($comp_info['title'] ?? '') . '</p>
            <p>' . htmlspecialchars($comp_info['date'] ?? '') . ' | ' . htmlspecialchars($comp_info['place'] ?? '') . '</p>
        </div>';

    // 1. 최종 순위 섹션
    $html .= '
        <div class="report-section">
            <h2>🏆 최종 순위</h2>
            <table>
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>등번호</th>
                        <th>선수명</th>
                        <th>상태</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($final_rankings as $ranking) {
        $rank_class = '';
        if ($ranking['rank'] == 1) $rank_class = 'rank-1';
        elseif ($ranking['rank'] == 2) $rank_class = 'rank-2';
        elseif ($ranking['rank'] == 3) $rank_class = 'rank-3';
        
        $html .= '
                    <tr>
                        <td class="' . $rank_class . '">' . $ranking['rank'] . '위</td>
                        <td>' . $ranking['player_no'] . '</td>
                        <td class="player-name">' . htmlspecialchars($ranking['player_name']) . '</td>
                        <td>' . ($ranking['rank'] <= 3 ? '🏆' : '') . '</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>';

    // 2. 심사위원별 채점 결과 섹션
    $html .= '
        <div class="report-section">
            <h2>📊 심사위원별 채점 결과</h2>
            <table>
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>등번호</th>
                        <th>선수명</th>';
    
    foreach ($adjudicators as $adj) {
        $html .= '<th>심사위원 ' . $adj['id'] . '</th>';
    }
    
    $html .= '
                    </tr>
                </thead>
                <tbody>';
    
    // 각 선수별로 심사위원별 순위 표시
    foreach ($event_players as $player_no) {
        $player_name = isset($all_players[$player_no]) ? 
            $all_players[$player_no]['male'] . ' / ' . $all_players[$player_no]['female'] : 
            "선수 $player_no";
        
        $html .= '
                    <tr>
                        <td class="final-rank">' . (array_search($player_no, array_column($final_rankings, 'player_no')) + 1) . '위</td>
                        <td>' . $player_no . '</td>
                        <td class="player-name">' . htmlspecialchars($player_name) . '</td>';
        
        foreach ($adjudicators as $adj) {
            $judge_rank = '-';
            if (isset($scores[$adj['id']])) {
                foreach ($scores[$adj['id']] as $round => $rankings) {
                    $rank = array_search($player_no, $rankings);
                    if ($rank !== false) {
                        $judge_rank = $rank + 1;
                        break;
                    }
                }
            }
            $html .= '<td class="judge-scores">' . $judge_rank . '</td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>';

    // 3. 통계 정보 섹션
    $total_players = count($event_players);
    $total_judges = count($adjudicators);
    
    $html .= '
        <div class="report-section">
            <h2>📈 경기 통계</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold;">' . $total_players . '</div>
                    <div>참가 팀</div>
                </div>
                <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold;">' . $total_judges . '</div>
                    <div>심사위원</div>
                </div>
                <div style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 2em; font-weight: bold;">' . count($final_rankings) . '</div>
                    <div>최종 순위</div>
                </div>
            </div>
        </div>';

    $html .= '
    </div>
</body>
</html>';

    return $html;
}
?>
