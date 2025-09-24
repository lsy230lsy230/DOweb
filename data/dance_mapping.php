<?php
/**
 * 공용 댄스 매핑 데이터
 * manage_events.php와 competition.php에서 공통으로 사용
 */

// 댄스 번호별 매핑 (DanceName.txt 기준)
$dance_mapping = [
    // 스탠다드 댄스
    '1' => ['name' => 'Waltz', 'abbr' => 'W', 'category' => 'Standard'],
    '2' => ['name' => 'Tango', 'abbr' => 'T', 'category' => 'Standard'],
    '3' => ['name' => 'Viennese Waltz', 'abbr' => 'V', 'category' => 'Standard'],
    '4' => ['name' => 'Slow Foxtrot', 'abbr' => 'S', 'category' => 'Standard'],
    '5' => ['name' => 'Quickstep', 'abbr' => 'Q', 'category' => 'Standard'],
    
    // 라틴 댄스
    '6' => ['name' => 'Cha Cha Cha', 'abbr' => 'C', 'category' => 'Latin'],
    '7' => ['name' => 'Samba', 'abbr' => 'SA', 'category' => 'Latin'],
    '8' => ['name' => 'Rumba', 'abbr' => 'R', 'category' => 'Latin'],
    '9' => ['name' => 'Paso Doble', 'abbr' => 'P', 'category' => 'Latin'],
    '10' => ['name' => 'Jive', 'abbr' => 'J', 'category' => 'Latin'],
    
    // 기타 댄스
    '11' => ['name' => 'Freestyle', 'abbr' => 'F', 'category' => 'Other'],
    '12' => ['name' => 'Swing', 'abbr' => 'SW', 'category' => 'Other'],
    '13' => ['name' => 'Argentine Tango', 'abbr' => 'AT', 'category' => 'Other'],
    '14' => ['name' => 'Handicap', 'abbr' => 'Hand.', 'category' => 'Other'],
    '15' => ['name' => 'Formation Team', 'abbr' => 'FO', 'category' => 'Other']
];

// 약어별 매핑 (역방향)
$dance_abbr_mapping = [];
foreach ($dance_mapping as $number => $info) {
    $dance_abbr_mapping[$info['abbr']] = $info;
}

/**
 * 댄스 번호나 약어를 받아서 풀네임을 반환
 */
function getDanceName($input) {
    global $dance_mapping, $dance_abbr_mapping;
    
    $input = trim($input);
    
    // 숫자인 경우
    if (is_numeric($input) && isset($dance_mapping[$input])) {
        return $dance_mapping[$input]['name'];
    }
    
    // 약어인 경우
    if (isset($dance_abbr_mapping[$input])) {
        return $dance_abbr_mapping[$input]['name'];
    }
    
    // 매칭되지 않는 경우 원본 반환
    return $input;
}

/**
 * 댄스 번호나 약어를 받아서 약어를 반환
 */
function getDanceAbbr($input) {
    global $dance_mapping, $dance_abbr_mapping;
    
    $input = trim($input);
    
    // 숫자인 경우
    if (is_numeric($input) && isset($dance_mapping[$input])) {
        return $dance_mapping[$input]['abbr'];
    }
    
    // 약어인 경우
    if (isset($dance_abbr_mapping[$input])) {
        return $dance_abbr_mapping[$input]['abbr'];
    }
    
    // 매칭되지 않는 경우 원본 반환
    return $input;
}

/**
 * 댄스 배열을 번호 순으로 정렬
 */
function sortDancesByNumber($dances) {
    global $dance_mapping, $dance_abbr_mapping;
    
    $dance_order = [];
    foreach ($dance_mapping as $number => $info) {
        $dance_order[$info['abbr']] = intval($number);
    }
    
    usort($dances, function($a, $b) use ($dance_order) {
        $a_abbr = getDanceAbbr($a);
        $b_abbr = getDanceAbbr($b);
        
        $order_a = $dance_order[$a_abbr] ?? 999;
        $order_b = $dance_order[$b_abbr] ?? 999;
        
        return $order_a - $order_b;
    });
    
    return $dances;
}

/**
 * 댄스 배열을 이름으로 변환하여 반환
 */
function convertDancesToNames($dances) {
    $result = [];
    foreach ($dances as $dance) {
        $result[] = getDanceName($dance);
    }
    return $result;
}

/**
 * 댄스 배열을 약어로 변환하여 반환
 */
function convertDancesToAbbrs($dances) {
    $result = [];
    foreach ($dances as $dance) {
        $result[] = getDanceAbbr($dance);
    }
    return $result;
}
?>
