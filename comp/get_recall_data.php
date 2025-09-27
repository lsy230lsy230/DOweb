<?php
header('Content-Type: application/json; charset=utf-8');

$comp_id = $_GET['comp_id'] ?? '20250913-001';
$event_no = $_GET['event_no'] ?? $_GET['event_id'] ?? ''; // event_id 또는 event_no 파라미터 지원

if (!$event_no) {
    echo json_encode(['success' => false, 'error' => '이벤트 번호가 필요합니다.']);
    exit;
}

$data_dir = __DIR__ . "/data/$comp_id";

try {
    // 대회 정보 로드
    $info_file = "$data_dir/info.json";
    $competition_info = [];
    if (file_exists($info_file)) {
        $competition_info = json_decode(file_get_contents($info_file), true);
    }
    
    // 이벤트 정보 로드
    $runorder_file = "$data_dir/RunOrder_Tablet.txt";
    if (!file_exists($runorder_file)) {
        throw new Exception("RunOrder_Tablet.txt 파일을 찾을 수 없습니다.");
    }
    
    $events = [];
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 12) {
            $event = [
                'no' => trim($parts[0]),
                'desc' => trim($parts[1]),
                'round' => trim($parts[2]) ?: 'Final',
                'recall_count' => intval(trim($parts[4]) ?: 0), // 5번째 컬럼이 리콜 수 (인덱스 4)
                'detail_no' => trim($parts[11]) ?: trim($parts[0]),
                'event_no' => trim($parts[0]),
                'dances' => array_slice($parts, 3, 8),
                'panel' => trim($parts[11]) ?: 'A'
            ];
            $events[] = $event;
        }
    }
    
    // 현재 이벤트 찾기
    $current_event = null;
    error_log("찾고 있는 이벤트 번호: $event_no");
    error_log("사용 가능한 이벤트들: " . implode(', ', array_column($events, 'no')));
    
    foreach ($events as $event) {
        error_log("확인 중인 이벤트: {$event['no']}, detail_no: {$event['detail_no']}, event_no: {$event['event_no']}");
        if ($event['detail_no'] == $event_no || $event['no'] == $event_no || $event['event_no'] == $event_no) {
            $current_event = $event;
            error_log("이벤트 찾음: {$event['no']}");
            break;
        }
    }
    
    if (!$current_event) {
        throw new Exception("이벤트를 찾을 수 없습니다: $event_no");
    }
    
    // 패널 정보 로드
    $panel_file = "$data_dir/panel_list.json";
    $panel_data = [];
    if (file_exists($panel_file)) {
        $panel_data = json_decode(file_get_contents($panel_file), true);
    }
    
    // 현재 이벤트의 패널에 해당하는 심사위원 찾기
    $current_panel = $current_event['panel'];
    $judges = [];
    foreach ($panel_data as $panel) {
        if ($panel['panel_code'] === $current_panel) {
            $judges[] = $panel['adj_code'];
        }
    }
    
    // 선수 정보 로드
    $players_file = "$data_dir/players_$event_no.txt";
    $players = [];
    if (file_exists($players_file)) {
        $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $players[] = trim($line);
        }
    }
    
    // 선수 이름 매핑 로드 (커플 이름 생성)
    $player_names_file = "$data_dir/players.txt";
    $player_names = [];
    if (file_exists($player_names_file)) {
        $lines = file($player_names_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 3) {
                $number = trim($parts[0]);
                $male_name = trim($parts[1]);
                $female_name = trim($parts[2]);
                $player_names[$number] = $male_name . ' & ' . $female_name;
            }
        }
    }
    
    // 댄스별 리콜 데이터 분석
    $dance_recalls = [];
    $dance_names = [
        '6' => 'Cha Cha Cha',
        '7' => 'Samba', 
        '8' => 'Rumba',
        '9' => 'Paso Doble',
        '10' => 'Jive'
    ];
    
    // 각 댄스별로 리콜 데이터 분석
    foreach ($dance_names as $dance_code => $dance_name) {
        $dance_recall_files = glob("$data_dir/{$event_no}_{$dance_code}_*.adj");
        $dance_player_recalls = [];
        
        foreach ($players as $player_num) {
            $recall_count = 0;
            $recalling_judges = [];
            
            foreach ($dance_recall_files as $file) {
                $filename = basename($file);
                // 파일명에서 심사위원 번호 추출 (예: 28_6_12.adj -> 12)
                if (preg_match('/_(\d+)\.adj$/', $filename, $matches)) {
                    $judge_num = $matches[1];
                    
                    // 현재 패널의 심사위원인지 확인
                    if (in_array($judge_num, $judges)) {
                        $content = file_get_contents($file);
                        $lines = explode("\n", trim($content));
                        
                        foreach ($lines as $line) {
                            $line = trim($line, '"');
                            if ($line === $player_num) {
                                $recall_count++;
                                $recalling_judges[] = $judge_num;
                                break;
                            }
                        }
                    }
                }
            }
            
            $player_name = $player_names[$player_num] ?? "선수 $player_num";
            $dance_player_recalls[] = [
                'player_number' => $player_num,
                'player_name' => $player_name,
                'recall_count' => $recall_count,
                'judges' => $recalling_judges
            ];
        }
        
        // 리콜 횟수로 정렬 (내림차순)
        usort($dance_player_recalls, function($a, $b) {
            return $b['recall_count'] - $a['recall_count'];
        });
        
        $dance_recalls[$dance_code] = [
            'dance_name' => $dance_name,
            'player_recalls' => $dance_player_recalls
        ];
    }
    
    // 전체 리콜 데이터 (기존 로직 유지)
    $player_recalls = [];
    $recall_files = glob("$data_dir/{$event_no}_*_*.adj");
    
    foreach ($players as $player_num) {
        $recall_count = 0;
        $recalling_judges = [];
        
        foreach ($recall_files as $file) {
            $filename = basename($file);
            // 파일명에서 심사위원 번호 추출 (예: 28_6_12.adj -> 12)
            if (preg_match('/_(\d+)\.adj$/', $filename, $matches)) {
                $judge_num = $matches[1];
                
                // 현재 패널의 심사위원인지 확인
                if (in_array($judge_num, $judges)) {
                    $content = file_get_contents($file);
                    $lines = explode("\n", trim($content));
                    
                    foreach ($lines as $line) {
                        $line = trim($line, '"');
                        if ($line === $player_num) {
                            $recall_count++;
                            $recalling_judges[] = $judge_num;
                            break;
                        }
                    }
                }
            }
        }
        
        $player_name = $player_names[$player_num] ?? "선수 $player_num";
        $player_recalls[] = [
            'player_number' => $player_num,
            'player_name' => $player_name,
            'recall_count' => $recall_count,
            'judges' => $recalling_judges
        ];
    }
    
    // 리콜 횟수로 정렬 (내림차순)
    usort($player_recalls, function($a, $b) {
        return $b['recall_count'] - $a['recall_count'];
    });
    
    // RunOrder_Tablet.txt에서 리콜 수 가져오기
    $recall_count = 0;
    foreach ($events as $event) {
        if ($event['detail_no'] == $event_no || $event['no'] == $event_no || $event['event_no'] == $event_no) {
            // RunOrder_Tablet.txt의 4번째 컬럼이 리콜 수
            $recall_count = intval($event['recall_count'] ?? 0);
            break;
        }
    }
    
    // 리콜 기준 계산: 표시용 임계치(심사위원 수의 절반)와 파일 지정 리콜 수를 분리
    // - recall_threshold: 표기/참고용 (절반 이상)
    // - advancing_players: 파일에 지정된 리콜 수 상위 N 커플로 산정
    $total_judges = count($judges);
    $recall_threshold = $recall_count > 0 ? $recall_count : ceil($total_judges / 2);

    // 상위 N 커플 산정 (파일 리콜 수 우선, 없으면 절반 이상 기준으로 대체)
    $advancing_count = $recall_count > 0 ? $recall_count : $recall_threshold;
    // player_recalls는 이미 리콜 횟수 내림차순 정렬 완료
    $advancing_players = array_slice($player_recalls, 0, max(0, (int)$advancing_count));
    
    // 심사위원 이름 매핑 로드
    $adjudicators_file = "$data_dir/adjudicators.txt";
    $adjudicator_names = [];
    if (file_exists($adjudicators_file)) {
        $lines = file($adjudicators_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 2) {
                $judge_code = trim($parts[0]);
                $judge_name = trim($parts[1]); // 2번째 컬럼이 이름
                if (!empty($judge_name)) {
                    $adjudicator_names[$judge_code] = $judge_name;
                }
            }
        }
    }
    
    // 심사위원 이름 배열 생성
    $judge_names = [];
    foreach ($judges as $judge) {
        $judge_names[] = $adjudicator_names[$judge] ?? "Judge $judge";
    }
    
    // 전체 참가자 수 계산 (모든 선수 파일에서)
    $total_participants = 0;
    $players_file = "$data_dir/players_{$event_no}.txt";
    error_log("=== 참가자 파일 계산 시작 ===");
    error_log("event_no: $event_no");
    error_log("data_dir: $data_dir");
    error_log("참가자 파일 경로: $players_file");
    error_log("파일 존재 여부: " . (file_exists($players_file) ? 'YES' : 'NO'));
    
    if (file_exists($players_file)) {
        $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total_participants = count($lines);
        error_log("참가자 파일에서 읽은 라인 수: " . count($lines));
        error_log("계산된 전체 참가자 수: $total_participants");
    } else {
        error_log("참가자 파일이 존재하지 않음: $players_file");
        // 디렉토리 내용 확인
        $files = scandir($data_dir);
        error_log("data_dir의 파일들: " . implode(', ', $files));
    }
    
    // 결과 반환
    $result = [
        'success' => true,
        'competition_info' => $competition_info,
        'event_info' => $current_event,
        'total_judges' => $total_judges,
        'total_participants' => $total_participants,
        'recall_count_from_file' => $recall_count,
        'recall_threshold' => $recall_threshold,
        'player_recalls' => $player_recalls,
        'advancing_players' => array_values($advancing_players),
        'judges' => $judges,
        'judge_names' => $judge_names,
        'dance_recalls' => $dance_recalls
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
