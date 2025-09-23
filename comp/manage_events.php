<?php
$comp_id = $_GET['comp_id'] ?? '';
$data_dir = __DIR__ . "/data/$comp_id";
$info_file = "$data_dir/info.json";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";
$dance_file = "$data_dir/DanceName.txt";
$panel_file = "$data_dir/panel_list.json";

require_once 'detail_numbers_manager.php';

// ==== 1. 예제파일 다운로드용 샘플 데이터 생성 ====
$example_csv = <<<CSV
순번,이벤트명,라운드타입,라운드차수,진출자수,다음라운드,댄스1,댄스2,댄스3,댄스4,댄스5,패널코드,시간(분)
1,Under 21 Open Latin,예선전,1,5,2,CCC,Samba,Rumba,Paso,,PA,1.5
1,Under 21 Open Latin,예선전,2,3,3,CCC,Samba,Rumba,Paso,,PB,1.5
1,Under 21 Open Latin,준결승,,3,4,CCC,Samba,Rumba,Paso,,PC,1.5
1,Under 21 Open Latin,결승,,,,CCC,Samba,Rumba,Paso,,PD,1.5
2,Break Time,,,,,,,,,,0.5
CSV;

// ==== 2. 예제파일 다운로드 처리 ====
if (isset($_GET['download_example'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="event_example.csv"');
    echo $example_csv;
    exit;
}

// ==== 3. 파일 업로드 처리 ====
$msg = '';
if (isset($_POST['upload_event']) && isset($_FILES['eventfile']) && $_FILES['eventfile']['error'] == UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['eventfile']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv','txt'])) {
        $msg = "CSV 또는 TXT 파일만 업로드 가능합니다.";
    } else {
        move_uploaded_file($_FILES['eventfile']['tmp_name'], $runorder_file);
        $msg = "이벤트 데이터가 업로드 되었습니다.";
    }
    header("Location: manage_events.php?comp_id=" . urlencode($comp_id) . "&msg=" . urlencode($msg));
    exit;
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

// ==== 4. Dance 종류 데이터(축약명 => 정식명) 불러오기 ====
$dance_types = [];
if (file_exists($dance_file)) {
    $lines = file($dance_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        if (isset($cols[2]) && $cols[2] && isset($cols[1]) && $cols[1]) {
            $dance_types[$cols[2]] = $cols[1]; // 축약명 => 정식명
        }
    }
}

// ==== 5. 패널코드 목록 불러오기 ====
$panel_codes = [];
if (file_exists($panel_file)) {
    $panel_list = json_decode(file_get_contents($panel_file), true);
    foreach ($panel_list as $row) {
        if (!empty($row['panel_code'])) $panel_codes[$row['panel_code']] = true;
    }
    $panel_codes = array_keys($panel_codes); // 중복제거
}

// ==== 6. RunOrder 파일 불러오기 및 분류 ====
$events = [];
$non_competition_events = [];
if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^bom/', $line)) continue;
        $cols = array_map('trim', explode(',', $line));
        // 컬럼 mapping: 순번,이벤트명,라운드타입,라운드차수,진출자수,다음라운드,댄스1~5,패널코드,시간(분),세부번호
        $panel_code = $cols[11] ?? '';
        $music_time = isset($cols[12]) ? floatval($cols[12]) : 0.0;
        $detail_no = $cols[13] ?? ''; // 세부번호 컬럼 추가
        $dance_abbr = [];
        for ($i=6; $i<=10; $i++) {
            if (isset($cols[$i]) && trim($cols[$i])!=='') $dance_abbr[] = $cols[$i];
        }
        // 경기 외 이벤트: 댄스컬럼 모두 비었으면
        if (count($dance_abbr) === 0) {
            $non_competition_events[] = [
                'raw_no' => $cols[0] ?? '',
                'name'   => $cols[1] ?? '',
                'music_time' => $music_time,
            ];
        } else {
            // 댄스 종목 번호를 이름으로 변환
            $converted_dances = convert_dance_numbers_to_names($dance_abbr, $dance_types);
            // 댄스 종목을 번호 순으로 정렬
            $sorted_dances = sort_dances_by_number($converted_dances, $dance_types);
            
            $events[] = [
                'raw_no'      => $cols[0] ?? '',
                'name'        => $cols[1] ?? '',
                'round_type'  => $cols[2] ?? '',
                'round_num'   => $cols[3] ?? '',
                'next_qual'   => $cols[4] ?? '',
                'next_event'  => $cols[5] ?? '',
                'panel_code'  => $panel_code,
                'music_time'  => $music_time,
                'dances'      => $sorted_dances,
                'detail_no'   => $detail_no, // 세부번호 추가
            ];
        }
    }
}

// ==== 7. 순번 그룹화 ====
$grouped_events = [];
foreach ($events as $evt) {
    if (preg_match('/^(\d+)/', $evt['raw_no'], $m)) $grp = $m[1];
    else $grp = $evt['raw_no'];
    $grouped_events[$grp][] = $evt;
}

// ==== 7-1. 세부번호 자동 생성 ====
// 모든 이벤트에 대해 세부번호를 새로 계산
foreach ($events as $idx => &$event) {
    // 같은 raw_no를 가진 이벤트들 찾기
    $same_raw_no_events = array_filter($events, function($e) use ($event) {
        return $e['raw_no'] === $event['raw_no'];
    });
    $event_count = count($same_raw_no_events);
    
    // 이벤트가 2개 이상인 경우에만 세부번호 할당
    if ($event_count > 1) {
        // 같은 raw_no를 가진 이벤트들을 순서대로 정렬
        $sorted_events = $same_raw_no_events;
        usort($sorted_events, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        $event_index = array_search($event, $sorted_events);
        if ($event_index !== false) {
            $event['detail_no'] = $event['raw_no'] . '-' . ($event_index + 1);
        }
    } else {
        // 이벤트가 1개인 경우 세부번호를 빈 문자열로 설정
        $event['detail_no'] = '';
    }
}

// 세부번호를 RunOrder_Tablet.txt에 저장
$updated_lines = [];
if (file_exists($runorder_file)) {
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $event_counter = 0; // 실제 이벤트 카운터
    
    foreach ($lines as $line_idx => $line) {
        if (preg_match('/^bom/', $line)) {
            $updated_lines[] = $line;
            continue;
        }
        
        $cols = array_map('trim', explode(',', $line));
        
        // 해당 이벤트의 세부번호 찾기
        $detail_no = '';
        if (isset($events[$event_counter])) {
            $event = $events[$event_counter];
            $raw_no = $event['raw_no'];
            
            // 같은 순번의 이벤트 개수 확인
            $same_raw_no_events = array_filter($events, function($e) use ($raw_no) {
                return $e['raw_no'] === $raw_no;
            });
            $event_count = count($same_raw_no_events);
            
            // 이벤트가 2개 이상인 경우에만 세부번호 사용
            if ($event_count > 1) {
                $detail_no = $event['detail_no'];
            }
            // 이벤트가 1개인 경우 세부번호는 빈 문자열
        }
        
        // 세부번호 컬럼이 없으면 추가, 있으면 업데이트
        if (count($cols) < 14) {
            // 세부번호 컬럼이 없으면 추가
            $cols[] = $detail_no;
        } else {
            // 세부번호 컬럼이 있으면 업데이트
            $cols[13] = $detail_no;
        }
        
        // 다음 이벤트 번호 업데이트 (5번째 컬럼)
        if (isset($events[$event_counter])) {
            $next_event = $events[$event_counter]['next_event'] ?? '';
            $cols[5] = $next_event;
        }
        
        $updated_lines[] = implode(',', $cols);
        $event_counter++; // 실제 이벤트만 카운트
    }
    
    // 파일 저장
    file_put_contents($runorder_file, implode("\n", $updated_lines) . "\n");
}

// ==== 7-1. 라운드 자동 계산 함수 ====
function calculateRoundInfo($events) {
    // 이벤트명별로 그룹화하되, Raw 번호와 세부번호를 조합한 고유 키 사용
    $name_groups = [];
    foreach ($events as $idx => $evt) {
        $name = $evt['name'];
        $raw_no = $evt['raw_no'];
        $detail_no = $evt['detail_no'] ?? '';
        
        // 고유 키 생성: 이벤트명 + Raw번호 + 세부번호
        $unique_key = $name . '|' . $raw_no . '|' . $detail_no;
        
        if (!isset($name_groups[$name])) {
            $name_groups[$name] = [];
        }
        $name_groups[$name][] = ['idx' => $idx, 'event' => $evt, 'unique_key' => $unique_key];
        
        // 디버깅: 이벤트 그룹화 과정 출력
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo "<!-- DEBUG: Adding event to group '$name' - Raw: $raw_no, Detail: '$detail_no', Index: $idx, Unique: $unique_key -->\n";
        }
    }
    
    // 중복 제거: 같은 Raw 번호와 세부번호를 가진 이벤트를 하나로 합치기
    foreach ($name_groups as $name => &$group) {
        $unique_events = [];
        $seen_keys = [];
        
        foreach ($group as $item) {
            $unique_key = $item['unique_key'];
            if (!in_array($unique_key, $seen_keys)) {
                $unique_events[] = $item;
                $seen_keys[] = $unique_key;
            } else {
                // 디버깅: 중복 제거된 이벤트 출력
                if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                    echo "<!-- DEBUG: Duplicate removed - Raw: {$item['event']['raw_no']}, Detail: {$item['event']['detail_no']}, Index: {$item['idx']} -->\n";
                }
            }
        }
        
        $group = $unique_events;
    }
    
    // 디버깅: 최종 그룹 정보 출력
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        echo "<!-- DEBUG: Final groups after deduplication: -->\n";
        foreach ($name_groups as $name => $group) {
            echo "<!-- DEBUG: Group '$name' has " . count($group) . " events -->\n";
            foreach ($group as $item) {
                echo "<!-- DEBUG:   - Raw: {$item['event']['raw_no']}, Detail: {$item['event']['detail_no']}, Index: {$item['idx']} -->\n";
            }
        }
    }
    
    // 각 그룹별로 라운드 정보 계산
    $round_info = [];
    $next_event_info = []; // 다음 이벤트 번호 정보
    
    foreach ($name_groups as $name => $group) {
        $total_events = count($group);
        
        // 같은 이벤트명을 가진 이벤트들을 순번 순으로 정렬 (raw_no, detail_no 고려)
        usort($group, function($a, $b) {
            $raw_no_a = intval($a['event']['raw_no']);
            $raw_no_b = intval($b['event']['raw_no']);
            
            // 순번이 같으면 세부번호로 정렬
            if ($raw_no_a === $raw_no_b) {
                $detail_no_a = intval($a['event']['detail_no'] ?? 0);
                $detail_no_b = intval($b['event']['detail_no'] ?? 0);
                return $detail_no_a - $detail_no_b;
            }
            
            return $raw_no_a - $raw_no_b;
        });
        
        // 디버깅: 그룹 정보 출력
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            echo "<!-- DEBUG: Group '$name' has $total_events events -->\n";
            error_log("Group '$name' has $total_events events:");
            foreach ($group as $pos => $item) {
                echo "<!-- DEBUG: Position $pos: Raw={$item['event']['raw_no']}, Detail={$item['event']['detail_no']}, Index={$item['idx']} -->\n";
                error_log("  Position $pos: Raw={$item['event']['raw_no']}, Detail={$item['event']['detail_no']}, Index={$item['idx']}");
            }
        }
        
        foreach ($group as $pos => $item) {
            $idx = $item['idx'];
            $stage_text = '';
            
            // 디버깅: 라운드 계산 전 상태 출력
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                echo "<!-- DEBUG: Before calculation - pos=$pos, total_events=$total_events, idx=$idx -->\n";
            }
            
            if ($total_events === 1) {
                $stage_text = 'Final';
            } else if ($total_events === 2) {
                if ($pos === 0) $stage_text = 'Semi-Final';
                else $stage_text = 'Final';
            } else if ($total_events === 3) {
                if ($pos === 0) $stage_text = 'Round 1';
                else if ($pos === 1) $stage_text = 'Semi-Final';
                else $stage_text = 'Final';
                
                // 디버깅: 3개 이벤트 케이스 상세 출력
                if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                    echo "<!-- DEBUG: 3 events case - pos=$pos, condition check -->\n";
                    if ($pos === 0) echo "<!-- DEBUG: pos=0 → Round 1 -->\n";
                    else if ($pos === 1) echo "<!-- DEBUG: pos=1 → Semi-Final -->\n";
                    else echo "<!-- DEBUG: pos=$pos → Final (else case) -->\n";
                }
            } else if ($total_events === 4) {
                if ($pos === 0) $stage_text = 'Round 1';
                else if ($pos === 1) $stage_text = 'Round 2';
                else if ($pos === 2) $stage_text = 'Semi-Final';
                else $stage_text = 'Final';
            } else if ($total_events === 5) {
                if ($pos === 0) $stage_text = 'Round 1';
                else if ($pos === 1) $stage_text = 'Round 2';
                else if ($pos === 2) $stage_text = 'Round 3';
                else if ($pos === 3) $stage_text = 'Semi-Final';
                else $stage_text = 'Final';
            } else {
                $stage_text = ($pos + 1) . '/' . $total_events;
            }
            
            // 디버깅: 라운드 계산 후 상태 출력
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                echo "<!-- DEBUG: After calculation - pos=$pos, stage_text=$stage_text -->\n";
            }
            
            $round_info[$idx] = $stage_text;
            
            // 디버깅용 로그
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                echo "<!-- DEBUG: Calculated Round - Event: {$item['event']['name']}, Raw: {$item['event']['raw_no']}, Detail: {$item['event']['detail_no']}, Position: $pos, Total: $total_events, Round: $stage_text -->\n";
                error_log("Calculated Round - Event: {$item['event']['name']}, Raw: {$item['event']['raw_no']}, Detail: {$item['event']['detail_no']}, Position: $pos, Total: $total_events, Round: $stage_text");
                
                // 라운드 계산 조건 상세 출력
                echo "<!-- DEBUG: Round calculation details - total_events=$total_events, pos=$pos -->\n";
                if ($total_events === 3) {
                    echo "<!-- DEBUG: 3 events case - pos 0=Round 1, pos 1=Semi-Final, pos 2=Final -->\n";
                }
            }
            
            // 다음 이벤트 번호 자동 계산
            if ($pos < $total_events - 1) {
                // 다음 라운드가 있는 경우
                $next_item = $group[$pos + 1];
                $next_event_info[$idx] = $next_item['event']['raw_no'];
            } else {
                // 마지막 라운드인 경우
                $next_event_info[$idx] = '';
            }
        }
    }
    
    return ['round_info' => $round_info, 'next_event_info' => $next_event_info];
}

// 라운드 정보 계산
$round_calculation = calculateRoundInfo($events);
$round_info = $round_calculation['round_info'];
$next_event_info = $round_calculation['next_event_info'];

// 중복 제거된 이벤트 데이터를 전역 변수로 저장 (일관성 보장)
$unique_events = [];
$seen_keys = [];

foreach ($events as $evt) {
    $unique_key = $evt['name'] . '|' . $evt['raw_no'] . '|' . ($evt['detail_no'] ?? '');
    if (!in_array($unique_key, $seen_keys)) {
        $unique_events[] = $evt;
        $seen_keys[] = $unique_key;
    }
}

// 디버깅: 전역 중복 제거된 이벤트 정보 출력
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<!-- DEBUG: Global unique events: -->\n";
    foreach ($unique_events as $evt) {
        echo "<!-- DEBUG: Raw={$evt['raw_no']}, Name={$evt['name']}, Detail={$evt['detail_no']} -->\n";
    }
}

// 디버깅: 라운드 정보 출력
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<!-- DEBUG: Round info calculated -->\n";
    echo "<!-- DEBUG: Total events: " . count($events) . " -->\n";
    echo "<!-- DEBUG: Round info count: " . count($round_info) . " -->\n";
    foreach ($round_info as $idx => $round) {
        echo "<!-- DEBUG: Round info[$idx] = $round -->\n";
    }
}

// 다음 이벤트 번호를 이벤트에 적용
foreach ($events as $idx => &$event) {
    if (isset($next_event_info[$idx])) {
        $event['next_event'] = $next_event_info[$idx];
    }
}

// RunOrder_Tablet.txt에서 읽어온 라운드 정보를 사용
foreach ($events as $idx => &$event) {
    // RunOrder_Tablet.txt에서 읽어온 라운드 정보가 있으면 사용
    if (!empty($event['round_type'])) {
        $round_info[$idx] = $event['round_type'];
    }
    // RunOrder_Tablet.txt에서 읽어온 다음 이벤트 번호가 있으면 사용
    if (!empty($event['next_event'])) {
        $next_event_info[$idx] = $event['next_event'];
    }
}

// ==== 8. 직접 입력 처리 및 "다음라운드 자동 생성" ====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_event_inline'])) {
    $fields = [
        $_POST['raw_no'] ?? '',
        $_POST['name'] ?? '',
        $_POST['round_type'] ?? '',
        $_POST['round_num'] ?? '',
        $_POST['next_qual'] ?? '',
        $_POST['next_event'] ?? '',
        $_POST['dance1'] ?? '',
        $_POST['dance2'] ?? '',
        $_POST['dance3'] ?? '',
        $_POST['dance4'] ?? '',
        $_POST['dance5'] ?? '',
        $_POST['panel_code'] ?? '',
        $_POST['music_time'] ?? '',
    ];
    $line = implode(',', array_map(fn($s)=>str_replace([",","\n","\r"],['',' ',''],$s), $fields));
    $line .= "\n";
    file_put_contents($runorder_file, $line, FILE_APPEND);

    // 자동 다음라운드 생성
    $next_event = trim($_POST['next_event'] ?? '');
    $existing_nums = array_column($events, 'raw_no');
    if ($next_event !== "" && !in_array($next_event, $existing_nums)) {
        // 자동 라운드타입 증가
        $now_round_type = $_POST['round_type'];
        $next_round_type = $now_round_type;
        if ($now_round_type === "Round 1") {
            $next_round_type = "Round 2";
            // Round 1이 여러 번이면 Round 2부터는 Semi-Final, 그 이후 Final
            $exist_same = array_filter($events, fn($e) => $e['name']==$_POST['name'] && $e['round_type']=="Round 1");
            if (count($exist_same) >= 2) {
                $next_round_type = "Semi-Final";
            }
        } elseif ($now_round_type === "Round 2") {
            $next_round_type = "Semi-Final";
        } elseif ($now_round_type === "Round 3") {
            $next_round_type = "Semi-Final";
        } elseif ($now_round_type === "Semi-Final") {
            $next_round_type = "Final";
        }
        // 댄스 종목 번호를 이름으로 변환
        $dance_abbrs = array_filter([
            $_POST['dance1'] ?? '',
            $_POST['dance2'] ?? '',
            $_POST['dance3'] ?? '',
            $_POST['dance4'] ?? '',
            $_POST['dance5'] ?? ''
        ]);
        $converted_dances = convert_dance_numbers_to_names($dance_abbrs, $dance_types);
        $sorted_dances = sort_dances_by_number($converted_dances, $dance_types);
        
        $next_fields = [
            $next_event,
            $_POST['name'],
            $next_round_type,
            $next_round_num,
            "", // 진출자수
            "", // 다음라운드
            $sorted_dances[0] ?? '',
            $sorted_dances[1] ?? '',
            $sorted_dances[2] ?? '',
            $sorted_dances[3] ?? '',
            $sorted_dances[4] ?? '',
            $_POST['panel_code'] ?? '',
            $_POST['music_time'] ?? '',
        ];
        $next_line = implode(',', array_map(fn($s)=>str_replace([",","\n","\r"],['',' ',''],$s), $next_fields));
        $next_line .= "\n";
        file_put_contents($runorder_file, $next_line, FILE_APPEND);
    }
    header("Location: manage_events.php?comp_id=" . urlencode($comp_id) . "&msg=" . urlencode("한 줄이 추가되었습니다."));
    exit;
}

// ==== 9. 삭제 처리 ====
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_idx = intval($_GET['delete']);
    // RunOrder 파일 다시 읽기
    if (file_exists($runorder_file)) {
        $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (isset($lines[$delete_idx])) {
            unset($lines[$delete_idx]);
            file_put_contents($runorder_file, implode("\n", $lines) . "\n");
            
            // 세부번호도 재생성
            $detail_numbers = generateDetailNumbers($events);
            saveDetailNumbers($comp_id, $detail_numbers);
            
            header("Location: manage_events.php?comp_id=" . urlencode($comp_id) . "&msg=이벤트가 삭제되었습니다.");
            exit;
        }
    }
}

// ==== 9-1. 세부번호 수정 처리 ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_detail_numbers'])) {
    $detail_updates = $_POST['detail_numbers'] ?? [];
    $updated = false;
    
    foreach ($detail_updates as $key => $new_detail_no) {
        list($raw_no, $name) = explode('|', $key, 2);
        $raw_no = trim($raw_no); // 공백 문자 제거
        if (updateDetailNumber($comp_id, $raw_no, $name, $new_detail_no)) {
            $updated = true;
        }
    }
    
    if ($updated) {
        // RunOrder_Tablet.txt 파일 업데이트
        $updated_lines = [];
        if (file_exists($runorder_file)) {
            $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $event_counter = 0;
            
            foreach ($lines as $line_idx => $line) {
                if (preg_match('/^bom/', $line)) {
                    $updated_lines[] = $line;
                    continue;
                }
                
                $cols = array_map('trim', explode(',', $line));
                
                // 해당 이벤트의 세부번호 찾기
                $detail_no = '';
                if (isset($events[$event_counter])) {
                    $event = $events[$event_counter];
                    $raw_no = $event['raw_no'];
                    
                    // 같은 순번의 이벤트 개수 확인
                    $same_raw_no_events = array_filter($events, function($e) use ($raw_no) {
                        return $e['raw_no'] === $raw_no;
                    });
                    $event_count = count($same_raw_no_events);
                    
                    // 이벤트가 2개 이상인 경우에만 세부번호 사용
                    if ($event_count > 1) {
                        $detail_no = $event['detail_no'];
                    }
                }
                
                // 세부번호 컬럼이 없으면 추가, 있으면 업데이트
                if (count($cols) < 14) {
                    $cols[] = $detail_no;
                } else {
                    $cols[13] = $detail_no;
                }
                
                $updated_lines[] = implode(',', $cols);
                $event_counter++;
            }
            
            // 파일 저장
            file_put_contents($runorder_file, implode("\n", $updated_lines) . "\n");
        }
        
        $msg = "세부번호가 업데이트되었습니다.";
    } else {
        $msg = "세부번호 업데이트에 실패했습니다.";
    }
    
    header("Location: manage_events.php?comp_id=" . urlencode($comp_id) . "&msg=" . urlencode($msg));
    exit;
}

// ==== 10. 수정 폼 제출 처리 (수정시에도 다음라운드 자동생성 지원) ====
if (isset($_POST['edit_idx']) && is_numeric($_POST['edit_idx'])) {
    $edit_idx = intval($_POST['edit_idx']);
    if (file_exists($runorder_file)) {
        $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (isset($lines[$edit_idx])) {
            $fields = [
                $_POST['raw_no'] ?? '',
                $_POST['name'] ?? '',
                $_POST['round_type'] ?? '',
                $_POST['round_num'] ?? '',
                $_POST['next_qual'] ?? '',
                $_POST['next_event'] ?? '',
                $_POST['dance1'] ?? '',
                $_POST['dance2'] ?? '',
                $_POST['dance3'] ?? '',
                $_POST['dance4'] ?? '',
                $_POST['dance5'] ?? '',
                $_POST['panel_code'] ?? '',
                $_POST['music_time'] ?? '',
            ];
            $line = implode(',', array_map(fn($s)=>str_replace([",","\n","\r"],['',' ',''],$s), $fields));
            $lines[$edit_idx] = $line;

            // === 다음라운드 자동 생성 (수정시에도) ===
            $next_event = trim($_POST['next_event'] ?? '');
            // lines 전체에서 첫번째 컬럼(순번)만 추출
            $existing_nums = [];
            foreach ($lines as $l) {
                $cols = array_map('trim', explode(',', $l));
                $existing_nums[] = $cols[0] ?? '';
            }
            if ($next_event !== "" && !in_array($next_event, $existing_nums)) {
                // 라운드타입 증가 로직
                $now_round_type = $_POST['round_type'];
                $next_round_type = $now_round_type;
                if ($now_round_type === "Round 1") {
                    $next_round_type = "Round 2";
                    // Round 1 여러 번 있으면 Semi-Final 전환
                    $exist_same = 0;
                    foreach ($lines as $l) {
                        $cols = array_map('trim', explode(',', $l));
                        if (($cols[1]??'')==$_POST['name'] && ($cols[2]??'')=="Round 1") $exist_same++;
                    }
                    if ($exist_same >= 2) {
                        $next_round_type = "Semi-Final";
                    }
                } elseif ($now_round_type === "Round 2") {
                    $next_round_type = "Semi-Final";
                } elseif ($now_round_type === "Round 3") {
                    $next_round_type = "Semi-Final";
                } elseif ($now_round_type === "Semi-Final") {
                    $next_round_type = "Final";
                }
                // 댄스 종목 번호를 이름으로 변환
                $dance_abbrs = array_filter([
                    $_POST['dance1'] ?? '',
                    $_POST['dance2'] ?? '',
                    $_POST['dance3'] ?? '',
                    $_POST['dance4'] ?? '',
                    $_POST['dance5'] ?? ''
                ]);
                $converted_dances = convert_dance_numbers_to_names($dance_abbrs, $dance_types);
                $sorted_dances = sort_dances_by_number($converted_dances, $dance_types);
                
                $next_fields = [
                    $next_event,
                    $_POST['name'],
                    $next_round_type,
                    $next_round_num,
                    "", // 진출자수
                    "", // 다음라운드
                    $sorted_dances[0] ?? '',
                    $sorted_dances[1] ?? '',
                    $sorted_dances[2] ?? '',
                    $sorted_dances[3] ?? '',
                    $sorted_dances[4] ?? '',
                    $_POST['panel_code'] ?? '',
                    $_POST['music_time'] ?? '',
                ];
                $next_line = implode(',', array_map(fn($s)=>str_replace([",","\n","\r"],['',' ',''],$s), $next_fields));
                $lines[] = $next_line;
            }
            // 저장
            file_put_contents($runorder_file, implode("\n", $lines) . "\n");
            header("Location: manage_events.php?comp_id=" . urlencode($comp_id) . "&msg=이벤트가 수정되었습니다.");
            exit;
        }
    }
}

function h($s) { return htmlspecialchars($s ?? ''); }
function get_round_label($round_type, $round_num) {
    if ($round_type === "Round 1") return "Round 1";
    if ($round_type === "Round 2") return "Round 2";
    if ($round_type === "Round 3") return "Round 3";
    if ($round_type === "Semi-Final") return "Semi-Final";
    if ($round_type === "Final") return "Final";
    return h($round_type);
}
function render_dances($abbrs, $dance_types) {
    $out = [];
    foreach ($abbrs as $ab) {
        // None, 0, 빈 문자열, '?' 등 무효한 값 제외
        if (empty($ab) || $ab === '0' || $ab === 'None' || $ab === '?' || $ab === '-') {
            continue;
        }
        
        if (isset($dance_types[$ab])) {
            // 이름만 표시 (예: Waltz)
            $dance_info = get_dance_info_by_abbr($ab, $dance_types);
            $out[] = $dance_info['name'];
        } else {
            // 매칭되지 않는 경우 원본 표시 (유효한 경우만)
            if (!empty($ab) && $ab !== '0' && $ab !== 'None' && $ab !== '?' && $ab !== '-') {
                $out[] = $ab;
            }
        }
    }
    return implode(', ', $out);
}

function get_dance_info_by_abbr($abbr, $dance_types) {
    // DanceName.txt에서 번호 정보를 가져오기 위해 파일을 다시 읽음
    $dance_file = __DIR__ . "/data/{$_GET['comp_id']}/DanceName.txt";
    $dance_data = [];
    
    if (file_exists($dance_file)) {
        $lines = file($dance_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^bom/', $line)) continue;
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) >= 3 && is_numeric($cols[0])) {
                $dance_data[$cols[2]] = [
                    'number' => $cols[0],
                    'name' => $cols[1],
                    'abbr' => $cols[2]
                ];
            }
        }
    }
    
    return $dance_data[$abbr] ?? ['number' => '?', 'name' => $abbr, 'abbr' => $abbr];
}

function sort_dances_by_number($dance_abbrs, $dance_types) {
    $dance_file = __DIR__ . "/data/{$_GET['comp_id']}/DanceName.txt";
    $dance_data = [];
    
    if (file_exists($dance_file)) {
        $lines = file($dance_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^bom/', $line)) continue;
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) >= 3 && is_numeric($cols[0])) {
                $dance_data[$cols[2]] = intval($cols[0]);
            }
        }
    }
    
    // 번호 순으로 정렬
    usort($dance_abbrs, function($a, $b) use ($dance_data) {
        $num_a = $dance_data[$a] ?? 999; // 매칭되지 않는 경우 맨 뒤로
        $num_b = $dance_data[$b] ?? 999;
        return $num_a - $num_b;
    });
    
    return $dance_abbrs;
}

function convert_dance_numbers_to_names($dance_abbrs, $dance_types) {
    $dance_file = __DIR__ . "/data/{$_GET['comp_id']}/DanceName.txt";
    $dance_data = [];
    
    if (file_exists($dance_file)) {
        $lines = file($dance_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^bom/', $line)) continue;
            $cols = array_map('trim', explode(',', $line));
            if (count($cols) >= 3 && is_numeric($cols[0])) {
                $dance_data[intval($cols[0])] = [
                    'name' => $cols[1],
                    'abbr' => $cols[2]
                ];
            }
        }
    }
    
    $converted_dances = [];
    foreach ($dance_abbrs as $abbr) {
        // 숫자인 경우 번호로 변환
        if (is_numeric($abbr) && isset($dance_data[intval($abbr)])) {
            $converted_dances[] = $dance_data[intval($abbr)]['abbr'];
        } else {
            $converted_dances[] = $abbr;
        }
    }
    
    return $converted_dances;
}
function sum_minutes($list) {
    $total = 0.0;
    foreach ($list as $e) $total += floatval($e['music_time']);
    return $total;
}

// ==== 11. 수정 진입 시 데이터 준비 ====
$edit_mode = false;
$edit_fields = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_mode = true;
    $edit_idx = intval($_GET['edit']);
    if (file_exists($runorder_file)) {
        $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (isset($lines[$edit_idx])) {
            $edit_fields = array_map('trim', explode(',', $lines[$edit_idx]));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>이벤트 관리 | danceoffice.net COMP</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body { background:#f7fafd; font-family:sans-serif; margin:0;}
        .mainbox { max-width:1200px; margin:3vh auto 0 auto; background:#fff; border-radius:18px; box-shadow:0 6px 32px #00339911; padding:2.2em 1.3em 2em 1.3em;}
        h1 { color:#003399; font-size:1.15em; margin-bottom:0.6em;}
        .desc {margin-bottom:1.3em; color:#333;}
        table { width:100%; border-collapse:collapse; background:#fff; }
        th, td { padding:0.45em 0.3em; text-align:center; font-size:0.98em;}
        th { background:#f0f4fa; color:#003399; font-weight:700;}
        td { color:#333;}
        tr:not(:last-child) td { border-bottom:1px solid #eee;}
        .edit-btn, .del-btn {
            border:none; border-radius:8px; padding:0.28em 1em; font-weight:700; cursor:pointer;
        }
        .edit-btn { background:#03C75A; color:#fff;}
        .edit-btn:hover { background:#00BFAE;}
        .del-btn { background:#ec3b28; color:#fff;}
        .del-btn:hover { background:#b31e06;}
        .goto-dash {display:inline-block; margin-bottom:1.1em; color:#888;}
        .goto-dash:hover {color:#003399;}
        .event-group-row { background:#f8fafd; }
        .event-dances { font-size:0.96em; color:#225; }
        .panel-code-cell { font-weight:600; color:#155; }
        .time-table { margin-top:2.5em;}
        .noncomp-title { color:#003399; font-size:1.07em; margin:1em 0 0.5em 0; font-weight:700;}
        .addbox {margin:1.2em 0 1.5em 0;}
        @media (max-width:900px) {
            .mainbox { max-width:99vw; padding:1.1em 0.2em;}
            th, td { font-size:0.95em;}
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const danceMap = {
            <?php foreach ($dance_types as $abbr => $fullname): ?>
            "<?=strtoupper($abbr)?>": "<?=h($fullname)?> (<?=h($abbr)?>)",
            <?php endforeach; ?>
        };
        document.querySelectorAll('.dance-input').forEach(function(input){
            input.addEventListener('input', function(e) {
                const v = input.value.trim().toUpperCase();
                if(danceMap[v]) {
                    input.value = v;
                }
            });
        });
        
        // 댄스 입력 시 자동 정렬 기능
        function autoSortDances() {
            const danceInputs = document.querySelectorAll('.dance-input');
            const danceValues = Array.from(danceInputs).map(input => input.value.trim()).filter(v => v);
            
            // 번호를 약어로 변환
            const numberToAbbr = {
                '1': 'W', '2': 'T', '3': 'V', '4': 'S', '5': 'Q',
                '6': 'SA', '7': 'C', '8': 'R', '9': 'P', '10': 'J',
                '11': 'F', '12': 'SW', '13': 'AT', '14': 'HAND.', '15': 'FO'
            };
            
            // 숫자인 경우 약어로 변환
            const convertedValues = danceValues.map(val => {
                if (numberToAbbr[val]) {
                    return numberToAbbr[val];
                }
                return val.toUpperCase();
            });
            
            // DanceName.txt의 번호 순서대로 정렬
            const danceOrder = {
                'W': 1, 'T': 2, 'V': 3, 'S': 4, 'Q': 5,
                'SA': 6, 'C': 7, 'R': 8, 'P': 9, 'J': 10,
                'F': 11, 'SW': 12, 'AT': 13, 'HAND.': 14, 'FO': 15
            };
            
            convertedValues.sort((a, b) => {
                const orderA = danceOrder[a] || 999;
                const orderB = danceOrder[b] || 999;
                return orderA - orderB;
            });
            
            // 정렬된 값으로 입력 필드 업데이트
            danceInputs.forEach((input, index) => {
                input.value = convertedValues[index] || '';
            });
        }
        
        // 댄스 입력 필드에 정렬 버튼 추가
        document.addEventListener('DOMContentLoaded', function() {
            const addForm = document.querySelector('form[method="post"]');
            if (addForm) {
                const sortButton = document.createElement('button');
                sortButton.type = 'button';
                sortButton.textContent = '댄스 정렬';
                sortButton.style.cssText = 'background:#FF6B35; color:#fff; border:none; border-radius:8px; padding:0.4em 1em; font-weight:700; cursor:pointer; margin-left:0.5em;';
                sortButton.onclick = autoSortDances;
                
                const addButton = addForm.querySelector('button[type="submit"]');
                if (addButton) {
                    addButton.parentNode.insertBefore(sortButton, addButton.nextSibling);
                }
            }
        });
        
        // 삭제 확인
        window.deleteEvent = function(idx) {
            if(confirm('정말 삭제하시겠습니까?')) {
                location.href = "?comp_id=<?=h($comp_id)?>&delete=" + idx;
            }
        };
        // 수정 진입
        window.editEvent = function(idx) {
            location.href = "?comp_id=<?=h($comp_id)?>&edit=" + idx;
        };
        
        // 라운드 정보 저장
        window.saveRoundInfo = function() {
            if(confirm('라운드 정보를 데이터 파일에 저장하시겠습니까? 타임테이블에서도 바로 적용됩니다.')) {
                fetch(`save_round_info.php?comp_id=<?=h($comp_id)?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('라운드 정보가 저장되었습니다!');
                        } else {
                            alert('저장 실패: ' + (data.error || '알 수 없는 오류'));
                        }
                    })
                    .catch(error => {
                        alert('저장 중 오류가 발생했습니다: ' + error);
                    });
            }
        };
    });
    
    // 세부번호 자동 생성 기능 (전역 함수)
    window.autoGenerateDetailNumbers = function() {
        console.log('자동 생성 함수 시작');
        const inputs = document.querySelectorAll('input[name^="detail_numbers["]');
        console.log('찾은 입력 필드 수:', inputs.length);
        
        const groupCounters = {};
        
        inputs.forEach((input, index) => {
            const name = input.getAttribute('name');
            console.log(`입력 필드 ${index}:`, name);
            
            // 더 유연한 정규식 패턴 사용 (숫자가 아닌 raw_no도 처리)
            const match = name.match(/detail_numbers\[([^|]+)\|/);
            
            if (match && match.length > 1 && match[1]) {
                const rawNo = match[1].trim(); // 공백 문자 제거
                console.log(`매칭된 raw_no: "${rawNo}" (길이: ${rawNo.length})`);
                
                // raw_no가 "1"인 경우 특별히 디버깅
                if (rawNo === "1" || rawNo.includes("1")) {
                    console.log(`raw_no "1" 발견:`, {
                        original: match[1],
                        trimmed: rawNo,
                        charCodes: Array.from(rawNo).map(c => c.charCodeAt(0))
                    });
                }
                
                if (!groupCounters[rawNo]) {
                    groupCounters[rawNo] = 0;
                }
                groupCounters[rawNo]++;
                
                const newValue = rawNo + '-' + groupCounters[rawNo];
                input.value = newValue;
                console.log(`설정된 값: ${newValue}`);
            } else {
                console.warn('세부번호 입력 필드의 name 속성을 파싱할 수 없습니다:', name);
                console.log('Input element:', input);
            }
        });
        
        console.log('자동 생성 완료. 그룹별 카운터:', groupCounters);
        
        // 자동 생성 후 자동으로 저장
        setTimeout(() => {
            saveDetailNumbers();
        }, 100);
    };
    
    // 세부번호 저장 후 값 유지를 위한 함수
    window.saveDetailNumbers = function() {
        const inputs = document.querySelectorAll('input[name^="detail_numbers["]');
        const formData = new FormData();
        
        // 세부번호 데이터 수집
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            const value = input.value.trim();
            if (value) {
                formData.append(name, value);
            }
        });
        
        // 서버에 저장 요청
        fetch('manage_events.php?comp_id=<?= urlencode($comp_id) ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                // 성공 시 성공 메시지와 함께 페이지 새로고침
                alert('세부번호가 성공적으로 저장되었습니다.');
                // 약간의 지연 후 새로고침하여 서버 처리 완료 보장
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                alert('세부번호 저장에 실패했습니다.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('세부번호 저장 중 오류가 발생했습니다.');
        });
    };
    </script>
</head>
<body>
<div class="mainbox">

    <div style="margin-bottom:1.2em;">
        <a href="dashboard.php?comp_id=<?= urlencode($comp_id) ?>" class="goto-dash">&lt; 대시보드로</a>
    </div>
    <h1>이벤트 관리</h1>
    
    <?php if ($msg): ?>
        <div style="color:#03c75a; margin-bottom:1em;"><?= h($msg) ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
    <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <h3 style="color: #495057; margin: 0 0 10px 0;">🔍 디버깅 정보</h3>
        <div style="font-size: 12px; color: #6c757d;">
            <p><strong>총 이벤트 수:</strong> <?= count($events) ?></p>
            <p><strong>라운드 정보 수:</strong> <?= count($round_info) ?></p>
            <p><strong>라운드 정보:</strong></p>
            <ul style="margin: 5px 0; padding-left: 20px;">
                <?php foreach ($round_info as $idx => $round): ?>
                    <li>Index <?= $idx ?>: <?= $round ?></li>
                <?php endforeach; ?>
            </ul>
            
            <p><strong>이벤트 상세 정보:</strong></p>
            <ul style="margin: 5px 0; padding-left: 20px;">
                <?php foreach ($events as $idx => $event): ?>
                    <li>Index <?= $idx ?>: Raw=<?= $event['raw_no'] ?>, Name=<?= htmlspecialchars($event['name']) ?>, Detail=<?= $event['detail_no'] ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- 예제파일 다운로드 & 업로드 폼 -->
    <div style="margin-bottom:1.2em; display:flex; gap:1.2em; align-items:center; flex-wrap:wrap;">
        <a href="?comp_id=<?=urlencode($comp_id)?>&download_example=1" 
           style="display:inline-block; background:#003399; color:#fff; border-radius:8px; padding:0.4em 1.5em; font-weight:700; text-decoration:none;">
           이벤트 예제 파일 다운로드
        </a>
        <form method="post" enctype="multipart/form-data" style="display:inline-block;">
            <input type="file" name="eventfile" accept=".csv,.txt" required>
            <button type="submit" name="upload_event" value="1" style="background:#03C75A; color:#fff; border:none; border-radius:8px; padding:0.4em 1.5em; font-weight:700; cursor:pointer;">이벤트 파일 업로드</button>
        </form>
        <button onclick="saveRoundInfo()" style="background:#FF6B35; color:#fff; border:none; border-radius:8px; padding:0.4em 1.5em; font-weight:700; cursor:pointer;">
            라운드 정보 저장
        </button>
    </div>
    
    <!-- 웹 직접 입력: 한 줄 추가 폼 또는 수정 폼 -->
    <?php if ($edit_mode): ?>
    <form method="post" action="" style="margin-bottom:2em;">
        <fieldset class="addbox" style="border:1px solid #bbe; border-radius:8px; padding:0.8em 1em;">
            <legend style="color:#003399;font-weight:700;">이벤트 수정</legend>
            <input type="hidden" name="edit_idx" value="<?=h($edit_idx)?>">
            <input type="text" name="raw_no" placeholder="순번" style="width:4em;" value="<?=h($edit_fields[0]??'')?>">
            <input type="text" name="name" placeholder="이벤트명" style="width:12em;" value="<?=h($edit_fields[1]??'')?>">
            <select name="round_type" required>
                <option value="">라운드</option>
                <option value="Round 1" <?=($edit_fields[2]??'')=="Round 1"?'selected':''?>>Round 1</option>
                <option value="Round 2" <?=($edit_fields[2]??'')=="Round 2"?'selected':''?>>Round 2</option>
                <option value="Round 3" <?=($edit_fields[2]??'')=="Round 3"?'selected':''?>>Round 3</option>
                <option value="Semi-Final" <?=($edit_fields[2]??'')=="Semi-Final"?'selected':''?>>Semi-Final</option>
                <option value="Final" <?=($edit_fields[2]??'')=="Final"?'selected':''?>>Final</option>
            </select>
            <input type="number" step="1" name="next_qual" placeholder="진출자수" style="width:5em;" value="<?=h($edit_fields[4]??'')?>">
            <input type="text" name="next_event" placeholder="다음라운드" style="width:7em;" value="<?=h($edit_fields[5]??'')?>">
            <?php for($i=1;$i<=5;$i++): ?>
                <input class="dance-input" list="dance-list" name="dance<?=$i?>" placeholder="댄스<?=$i?> or 약어" value="<?=h($edit_fields[5+$i]??'')?>">
            <?php endfor; ?>
            <datalist id="dance-list">
                <?php foreach ($dance_types as $abbr => $fullname): ?>
                    <option value="<?=h($abbr)?>"><?=h($fullname)?> (<?=h($abbr)?>)</option>
                <?php endforeach; ?>
            </datalist>
            <input type="text" name="panel_code" placeholder="패널코드" style="width:6em;" value="<?=h($edit_fields[11]??'')?>">
            <input type="number" step="0.1" name="music_time" placeholder="시간(분)" style="width:5em;" value="<?=h($edit_fields[12]??'')?>">
            <button type="submit" style="background:#03C75A; color:#fff; border:none; border-radius:8px; padding:0.4em 1.3em; font-weight:700; cursor:pointer;">
                저장
            </button>
            <a href="?comp_id=<?=h($comp_id)?>" style="margin-left:0.7em;color:#888;">취소</a>
        </fieldset>
    </form>
    <?php else: ?>
    <form method="post" action="" style="margin-bottom:2em;">
        <fieldset class="addbox" style="border:1px solid #bbe; border-radius:8px; padding:0.8em 1em;">
            <legend style="color:#003399;font-weight:700;">새 이벤트 직접 입력</legend>
            <input type="text" name="raw_no" placeholder="순번" style="width:4em;">
            <input type="text" name="name" placeholder="이벤트명" style="width:12em;">
            <select name="round_type" required>
                <option value="">라운드</option>
                <option value="Round 1">Round 1</option>
                <option value="Round 2">Round 2</option>
                <option value="Round 3">Round 3</option>
                <option value="Semi-Final">Semi-Final</option>
                <option value="Final">Final</option>
            </select>
            <input type="number" step="1" name="next_qual" placeholder="진출자수" style="width:5em;">
            <input type="text" name="next_event" placeholder="다음라운드" style="width:7em;">
            <?php for($i=1;$i<=5;$i++): ?>
                <input class="dance-input" list="dance-list" name="dance<?=$i?>" placeholder="댄스<?=$i?> or 약어">
            <?php endfor; ?>
            <datalist id="dance-list">
                <?php foreach ($dance_types as $abbr => $fullname): ?>
                    <option value="<?=h($abbr)?>"><?=h($fullname)?> (<?=h($abbr)?>)</option>
                <?php endforeach; ?>
            </datalist>
            <input type="text" name="panel_code" placeholder="패널코드" style="width:6em;">
            <input type="number" step="0.1" name="music_time" placeholder="시간(분)" style="width:5em;">
            <button type="submit" name="add_event_inline" value="1" style="background:#003399; color:#fff; border:none; border-radius:8px; padding:0.4em 1.3em; font-weight:700; cursor:pointer;">
                추가
            </button>
            <span class="small" style="color:#888;">예: Round 1, Semi-Final, Final, 경기외(댄스 입력 X)도 가능. <b>다음라운드 번호 입력시 자동 생성</b><br>
            댄스 입력: 번호(1,2,3...) 또는 약어(W,T,V...) 모두 가능. <b>댄스 정렬 버튼으로 자동 정렬</b></span>
        </fieldset>
    </form>
    <?php endif; ?>

    <div class="desc">
        <b>라운드 컬럼은 같은 이벤트명을 가진 이벤트들을 자동으로 그룹화하여 Round 1, Round 2, Semi-Final, Final 순서로 계산됩니다.</b><br>
        <b>경기 외 이벤트(브레이크, 개회식, 시상 등)는 별도 표로 정리되어 시간표 계산에 사용됩니다.</b><br>
        각 경기 이벤트에는 음악 시간(분), 심사위원 패널 코드, 종목 등이 표시됩니다.<br>
        <span style="color:#03c75a;">동시진행 그룹</span>은 1-1, 1-2, ... 형식으로 묶어서 보여줍니다.<br>
        <b>세부번호는 멀티이벤트 결승전에서 사용되며, 자동 생성되지만 수정 가능합니다.</b>
    </div>
    
    <!-- 세부번호 수정 폼 -->
    <div style="margin-bottom:20px; background:#f8f9fa; padding:15px; border-radius:8px; border:1px solid #dee2e6;">
        <h4 style="color:#495057; margin:0 0 15px 0; display:flex; align-items:center; gap:10px;">
            <span class="material-symbols-rounded" style="font-size:20px;">edit</span>
            세부번호 수정
        </h4>
        <form method="post">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap:10px; max-height:300px; overflow-y:auto;">
                <?php 
                // 전역 변수 $unique_events 사용 (라운드 계산과 동일한 데이터)
                // 이렇게 하면 라운드 계산과 세부번호 수정이 동일한 데이터를 사용
                
                foreach ($unique_events as $evt): ?>
                    <div style="display:flex; align-items:center; gap:10px; padding:8px; background:white; border-radius:4px; border:1px solid #e9ecef;">
                        <label style="min-width:60px; font-size:12px; color:#495057; font-weight:600;">
                            <?= h($evt['raw_no']) ?>
                        </label>
                        <div style="flex:1; font-size:11px; color:#6c757d; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= h($evt['name']) ?>
                        </div>
                        <input type="text" 
                               name="detail_numbers[<?= h(trim($evt['raw_no'])) ?>|<?= h($evt['name']) ?>]" 
                               value="<?= h($evt['detail_no']) ?>" 
                               style="width:70px; padding:4px 6px; border:1px solid #ced4da; border-radius:4px; font-size:12px; text-align:center;"
                               placeholder="1-1">
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:15px; text-align:center; display:flex; gap:10px; justify-content:center;">
                <button type="button" onclick="autoGenerateDetailNumbers()" 
                        style="background:#28a745; color:white; border:none; padding:8px 15px; border-radius:4px; font-weight:600; cursor:pointer; font-size:12px;">
                    자동 생성
                </button>
                <button type="button" onclick="saveDetailNumbers()" 
                        style="background:#ff6b35; color:white; border:none; padding:8px 20px; border-radius:4px; font-weight:600; cursor:pointer; font-size:12px;">
                    세부번호 저장
                </button>
            </div>
        </form>
    </div>
    <table>
        <tr>
            <th>순번<br>(그룹)</th>
            <th>세부번호<br>(동시진행)</th>
            <th>이벤트명</th>
            <th>라운드<br>(자동계산)</th>
            <th>진출자수<br>(결승제외)</th>
            <th>다음라운드<br>순번(결승제외)</th>
            <th>댄스(종목)<br><small>번호.이름 순</small></th>
            <th>심사위원<br>패널코드</th>
            <th>시간<br>(분)</th>
            <th>관리</th>
        </tr>
        <?php
        // RunOrder 줄 번호 기록
        $row_idx = 0;
        foreach ($grouped_events as $grp_no => $evts):
            foreach ($evts as $k => $e):
                // 이벤트의 원본 인덱스 찾기 (raw_no, name, detail_no 모두 고려)
                $original_idx = null;
                foreach ($events as $orig_idx => $orig_evt) {
                    if ($orig_evt['raw_no'] === $e['raw_no'] && 
                        $orig_evt['name'] === $e['name'] && 
                        ($orig_evt['detail_no'] ?? '') === ($e['detail_no'] ?? '')) {
                        $original_idx = $orig_idx;
                        break;
                    }
                }
                $calculated_round = $original_idx !== null ? ($round_info[$original_idx] ?? '-') : '-';
                
                // 디버깅용 로그 (개발 시에만 사용)
                if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                    echo "<!-- DEBUG: Display Event: {$e['name']}, Raw: {$e['raw_no']}, Detail: {$e['detail_no']}, Original_idx: " . ($original_idx ?? 'null') . ", Round: $calculated_round -->\n";
                    error_log("Display Event: {$e['name']}, Raw: {$e['raw_no']}, Detail: {$e['detail_no']}, Original_idx: " . ($original_idx ?? 'null') . ", Round: $calculated_round");
                    
                    // 원본 이벤트 정보도 출력
                    if ($original_idx !== null) {
                        $orig_evt = $events[$original_idx];
                        echo "<!-- DEBUG: Original Event: Raw={$orig_evt['raw_no']}, Detail={$orig_evt['detail_no']}, Name={$orig_evt['name']} -->\n";
                        error_log("  Original Event: Raw={$orig_evt['raw_no']}, Detail={$orig_evt['detail_no']}, Name={$orig_evt['name']}");
                    }
                }
        ?>
            <tr<?=($k==0 && count($evts)>1?' class="event-group-row"':'')?>>
                <?php if ($k==0): ?>
                    <td rowspan="<?=count($evts)?>" style="font-weight:bold;"><?=h($grp_no)?></td>
                <?php endif; ?>
                <td><?= h($e['detail_no']) ?></td>
                <td><?= h($e['name']) ?></td>
                <td style="font-weight:600; color:#0d2c96;"><?= h($calculated_round) ?></td>
                <?php if (!($e['round_type']==="결승")): ?>
                    <td><?= h($e['next_qual']) ?></td>
                    <td><?= h($e['next_event']) ?></td>
                <?php else: ?>
                    <td>-</td>
                    <td>-</td>
                <?php endif; ?>
                <td class="event-dances">
                    <?= render_dances($e['dances'], $dance_types); ?>
                </td>
                <td class="panel-code-cell">
                    <?= h($e['panel_code']) ?>
                </td>
                <td>
                    <?= $e['music_time'] ? number_format($e['music_time'], 1) : '-' ?>
                </td>
                <td>
                    <button class="edit-btn" onclick="editEvent(<?=$row_idx?>)">수정</button>
                    <button class="del-btn" onclick="deleteEvent(<?=$row_idx?>)">삭제</button>
                </td>
            </tr>
        <?php
            $row_idx++;
            endforeach;
        endforeach;
        if ($row_idx === 0):
        ?>
            <tr><td colspan="10" style="color:#aaa;">이벤트가 없습니다.</td></tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($non_competition_events)): ?>
        <div class="noncomp-title">경기 외 이벤트(시간 계산용)</div>
        <table class="time-table">
            <tr>
                <th>순번</th>
                <th>이벤트명</th>
                <th>시간(분)</th>
            </tr>
            <?php foreach ($non_competition_events as $e): ?>
            <tr>
                <td><?= h($e['raw_no']) ?></td>
                <td><?= h($e['name']) ?></td>
                <td><?= $e['music_time'] ? number_format($e['music_time'],1) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="2" style="text-align:right;color:#003399;font-weight:700;">합계</td>
                <td style="font-weight:700;"><?= number_format(sum_minutes($non_competition_events),1) ?></td>
            </tr>
        </table>
    <?php endif; ?>
</div>
</body>
</html>