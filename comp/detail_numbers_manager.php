<?php
// 세부번호 관리 시스템

function loadDetailNumbers($comp_id) {
    $data_dir = __DIR__ . "/data/$comp_id";
    $detail_file = "$data_dir/detail_numbers.json";
    
    if (file_exists($detail_file)) {
        $data = json_decode(file_get_contents($detail_file), true);
        return $data ?: [];
    }
    
    return [];
}

function saveDetailNumbers($comp_id, $detail_numbers) {
    $data_dir = __DIR__ . "/data/$comp_id";
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0777, true);
    }
    
    $detail_file = "$data_dir/detail_numbers.json";
    file_put_contents($detail_file, json_encode($detail_numbers, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function generateDetailNumbers($events) {
    $detail_numbers = [];
    $group_counters = [];
    
    foreach ($events as $idx => $event) {
        $raw_no = $event['raw_no'];
        $group_no = preg_replace('/[^0-9]/', '', $raw_no); // 숫자만 추출
        
        if (!isset($group_counters[$group_no])) {
            $group_counters[$group_no] = 0;
        }
        $group_counters[$group_no]++;
        
        $detail_numbers[] = [
            'event_idx' => $idx,
            'raw_no' => $raw_no,
            'group_no' => $group_no,
            'detail_no' => $group_no . '-' . $group_counters[$group_no],
            'name' => $event['name'],
            'dances' => $event['dances']
        ];
    }
    
    return $detail_numbers;
}

function getDetailNumber($comp_id, $raw_no, $name = '') {
    $detail_numbers = loadDetailNumbers($comp_id);
    
    foreach ($detail_numbers as $detail) {
        if ($detail['raw_no'] === $raw_no && ($name === '' || $detail['name'] === $name)) {
            return $detail['detail_no'];
        }
    }
    
    return $raw_no; // 세부번호가 없으면 원본 번호 반환
}

function updateDetailNumber($comp_id, $raw_no, $name, $new_detail_no) {
    $detail_numbers = loadDetailNumbers($comp_id);
    
    foreach ($detail_numbers as &$detail) {
        if ($detail['raw_no'] === $raw_no && $detail['name'] === $name) {
            $detail['detail_no'] = $new_detail_no;
            saveDetailNumbers($comp_id, $detail_numbers);
            return true;
        }
    }
    
    return false;
}

function getEventByDetailNumber($comp_id, $detail_no) {
    $detail_numbers = loadDetailNumbers($comp_id);
    
    foreach ($detail_numbers as $detail) {
        if ($detail['detail_no'] === $detail_no) {
            return $detail;
        }
    }
    
    return null;
}

function getPlayersByDetailNumber($comp_id, $detail_no) {
    $players_file = __DIR__ . "/data/$comp_id/players_{$detail_no}.txt";
    if (file_exists($players_file)) {
        $lines = file($players_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_filter(array_map('trim', $lines));
    }
    
    return [];
}

function savePlayersByDetailNumber($comp_id, $detail_no, $players) {
    $data_dir = __DIR__ . "/data/$comp_id";
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0777, true);
    }
    
    $players_file = "$data_dir/players_{$detail_no}.txt";
    $content = implode("\n", $players);
    
    return file_put_contents($players_file, $content) !== false;
}
?>
