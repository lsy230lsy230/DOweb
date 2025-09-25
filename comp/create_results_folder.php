<?php
header('Content-Type: application/json; charset=utf-8');

// POST 데이터 받기
$input = json_decode(file_get_contents('php://input'), true);
$comp_id = $input['comp_id'] ?? '';
$event_no = $input['event_no'] ?? '';
$event_name = $input['event_name'] ?? '';
$aggregation_data = $input['aggregation_data'] ?? [];

if (empty($comp_id) || empty($event_no)) {
    echo json_encode(['success' => false, 'message' => '필수 파라미터가 누락되었습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 대회 데이터 디렉토리
$data_dir = __DIR__ . "/data/$comp_id";

// 웹 접근 가능한 results_reports 폴더 생성
$web_root = dirname(__DIR__);
$results_reports_dir = "$web_root/results_reports/$comp_id";
if (!is_dir($results_reports_dir)) {
    if (!mkdir($results_reports_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Results reports 폴더 생성에 실패했습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 이벤트별 폴더 생성
$event_results_dir = "$results_reports_dir/Event_$event_no";
if (!is_dir($event_results_dir)) {
    if (!mkdir($event_results_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Event 폴더 생성에 실패했습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    // 1. 상세 리포트 생성
    $detailed_report = generateDetailedReport($comp_id, $event_no, $event_name, $aggregation_data, $data_dir);
    $detailed_report_path = "$event_results_dir/detailed_report_$event_no.html";
    file_put_contents($detailed_report_path, $detailed_report);
    
    // 2. 리콜 리포트 생성
    $recall_report = generateRecallReport($comp_id, $event_no, $event_name, $aggregation_data, $data_dir);
    $recall_report_path = "$event_results_dir/recall_report_$event_no.html";
    file_put_contents($recall_report_path, $recall_report);
    
    // 3. 컴바인 리포트 생성
    $combined_report = generateCombinedReport($comp_id, $event_no, $event_name, $aggregation_data, $data_dir);
    $combined_report_path = "$event_results_dir/combined_report_$event_no.html";
    file_put_contents($combined_report_path, $combined_report);
    
    // 4. 메타데이터 저장
    $metadata = [
        'event_no' => $event_no,
        'event_name' => $event_name,
        'generated_at' => date('Y-m-d H:i:s'),
        'files' => [
            'detailed_report' => "detailed_report_$event_no.html",
            'recall_report' => "recall_report_$event_no.html",
            'combined_report' => "combined_report_$event_no.html"
        ]
    ];
    $metadata_path = "$event_results_dir/metadata.json";
    file_put_contents($metadata_path, json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode([
        'success' => true,
        'message' => '결과 파일이 성공적으로 생성되었습니다.',
        'files' => $metadata['files'],
        'results_dir' => $event_results_dir
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '결과 파일 생성 중 오류가 발생했습니다: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

function generateDetailedReport($comp_id, $event_no, $event_name, $aggregation_data, $data_dir) {
    // 대회 정보 로드
    $comp_info = json_decode(file_get_contents("$data_dir/info.json"), true);
    
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
    
    // 실제 채점 데이터 로드 (.adj 파일들)
    $scoring_data = loadScoringData($data_dir, $event_no);
    
    // 댄스 이름 로드
    $dance_names = [];
    $dance_file = "$data_dir/DanceName.txt";
    if (file_exists($dance_file)) {
        $dance_lines = file($dance_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($dance_lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                $dance_names[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
    
    // 심사위원 정보 로드
    $adjudicators = [];
    $adj_file = "$data_dir/adjudicators.txt";
    if (file_exists($adj_file)) {
        $adj_lines = file($adj_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($adj_lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                $adjudicators[trim($parts[0])] = trim($parts[1]);
            }
        }
    }
    
    // 모든 선수 번호 수집 (면제자 제외)
    $all_player_numbers = [];
    foreach ($scoring_data as $dance => $players) {
        foreach ($players as $player_no => $scores) {
            if (!in_array($player_no, $all_player_numbers)) {
                // 면제자 확인
                $is_exempted = false;
                foreach ($final_rankings as $ranking) {
                    if ($ranking['player_no'] == $player_no && $ranking['rank'] == 1) {
                        $is_exempted = true;
                        break;
                    }
                }
                
                // 면제자가 아닌 경우만 추가
                if (!$is_exempted) {
                    $all_player_numbers[] = $player_no;
                }
            }
        }
    }
    sort($all_player_numbers);
    
    // HTML 생성
    $html = '<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($event_name) . ' - 상세 리포트</title>
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
            padding: 8px; text-align: center; border: 1px solid #e0e0e0; 
            font-size: 12px;
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
        .print-btn { 
            position: fixed; top: 20px; right: 20px; z-index: 1000;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; padding: 12px 25px; border-radius: 25px;
            cursor: pointer; font-size: 14px; font-weight: bold;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s ease;
        }
        .print-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        @media print {
            .print-btn { display: none; }
            body { background: white; }
            .header { background: #f8f9fa; color: #333; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ 인쇄</button>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($comp_info['name'] ?? '대회') . '</h1>
            <p>' . htmlspecialchars($event_name) . ' - 상세 리포트</p>
            <p>생성일: ' . date('Y-m-d H:i:s') . '</p>
        </div>';
    
    // 각 댄스별 채점 테이블 생성
    $dance_names_map = [
        '6' => 'Cha Cha Cha',
        '7' => 'Samba', 
        '8' => 'Rumba',
        '9' => 'Jive'
    ];
    
    foreach (['6', '7', '8', '9'] as $dance) {
        $dance_name = $dance_names_map[$dance];
        $html .= '
        <div class="report-section">
            <h2>' . $dance_name . '</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Competitor Name(s)</th>';
        
        // 심사위원 컬럼 생성 (12-24)
        for ($i = 12; $i <= 24; $i++) {
            $html .= '<th>' . $i . '</th>';
        }
        
        $html .= '
                        <th>Mark</th>
                    </tr>
                </thead>
                <tbody>';
        
        // 각 선수별 행 생성
        foreach ($all_player_numbers as $player_no) {
            $player_name = isset($all_players[$player_no]) ? 
                $all_players[$player_no]['male'] . ' / ' . $all_players[$player_no]['female'] : 
                "선수 $player_no";
            
            // 면제 선수 확인
            $is_exempted = false;
            foreach ($final_rankings as $ranking) {
                if ($ranking['player_no'] == $player_no && $ranking['rank'] == 1) {
                    $is_exempted = true;
                    break;
                }
            }
            
            $html .= '
                    <tr>
                        <td>' . $player_no . '</td>
                        <td class="player-name">' . htmlspecialchars($player_name) . ($is_exempted ? ' ⭐' : '') . '</td>';
            
            // 각 심사위원의 점수
            $total_recall = 0;
            for ($i = 12; $i <= 24; $i++) {
                $recall = isset($scoring_data[$dance][$player_no][$i]) ? 
                    $scoring_data[$dance][$player_no][$i] : '0';
                $html .= '<td>' . $recall . '</td>';
                if ($recall == '1') $total_recall++;
            }
            
            // Mark 컬럼 (면제 또는 총 리콜 수)
            $mark = $is_exempted ? '면제' : $total_recall;
            $html .= '<td>' . $mark . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
        </div>';
    }
    
    // 심사위원 정보 섹션
    $html .= '
        <div class="report-section">
            <h2>Adjudicators</h2>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">';
    
    // 심사위원 코드 매핑 (12-24 -> A-M)
    $judge_mapping = [
        12 => 'A', 13 => 'B', 14 => 'C', 15 => 'D', 16 => 'E',
        17 => 'F', 18 => 'G', 19 => 'H', 20 => 'I', 21 => 'J',
        22 => 'K', 23 => 'L', 24 => 'M'
    ];
    
    // 실제 심사위원 번호 매핑
    $actual_judge_nums = [
        12 => '5', 13 => '9', 14 => '7', 15 => '10', 16 => '2',
        17 => '4', 18 => '3', 19 => '6', 20 => '11', 21 => '8',
        22 => '12', 23 => '1', 24 => '13'
    ];
    
    for ($i = 12; $i <= 24; $i++) {
        $judge_code = $judge_mapping[$i];
        $actual_judge_num = $actual_judge_nums[$i];
        $html .= '<span style="background: #f0f0f0; padding: 5px 10px; border-radius: 5px; margin: 2px;">' . 
                 $judge_code . '. ' . $actual_judge_num . '</span>';
    }
    
    $html .= '
            </div>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

function generateRecallReport($comp_id, $event_no, $event_name, $aggregation_data, $data_dir) {
    return generateDetailedReport($comp_id, $event_no, $event_name, $aggregation_data, $data_dir);
}

function generateCombinedReport($comp_id, $event_no, $event_name, $aggregation_data, $data_dir) {
    return generateDetailedReport($comp_id, $event_no, $event_name, $aggregation_data, $data_dir);
}

// 실제 채점 데이터를 로드하는 함수
function loadScoringData($data_dir, $event_no) {
    $scoring_data = [];
    
    // 이벤트의 댄스들 확인 (6, 7, 8, 9 = Cha Cha Cha, Samba, Rumba, Jive)
    $dances = ['6', '7', '8', '9'];
    
    foreach ($dances as $dance) {
        $scoring_data[$dance] = [];
        
        // 각 심사위원의 채점 데이터 로드 (12-24)
        for ($judge_num = 12; $judge_num <= 24; $judge_num++) {
            $adj_file = "$data_dir/{$event_no}_{$dance}_{$judge_num}.adj";
            
            if (file_exists($adj_file)) {
                $content = file_get_contents($adj_file);
                $lines = array_filter(array_map('trim', explode("\n", $content)));
                
                foreach ($lines as $line) {
                    // .adj 파일 형식: 각 줄에 선수 번호만 있음 (리콜된 선수)
                    $player_number = trim($line, '"');
                    if (!empty($player_number)) {
                        $scoring_data[$dance][$player_number][$judge_num] = '1'; // 리콜됨
                    }
                }
            }
        }
    }
    
    return $scoring_data;
}
?>
