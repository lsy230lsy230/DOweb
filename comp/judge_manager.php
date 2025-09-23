<?php
// 심사위원 관리 시스템

function loadJudges() {
    $judges_file = __DIR__ . '/judges.txt';
    $judges = [];
    
    if (file_exists($judges_file)) {
        $lines = file($judges_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue; // 주석 무시
            $parts = explode(',', $line);
            if (count($parts) >= 10) {
                $judges[] = [
                    'id' => trim($parts[0]),
                    'name' => trim($parts[1]),
                    'organization' => trim($parts[2]),
                    'region' => trim($parts[3]),
                    'phone' => trim($parts[4]),
                    'email' => trim($parts[5]),
                    'photo' => trim($parts[6]),
                    'specialty' => trim($parts[7]),
                    'created_at' => trim($parts[8]),
                    'updated_at' => trim($parts[9])
                ];
            }
        }
    }
    return $judges;
}

function saveJudges($judges) {
    $judges_file = __DIR__ . '/judges.txt';
    $content = "# 심사위원 인명사전\n";
    $content .= "# 형식: id,name,organization,region,phone,email,photo,specialty,created_at,updated_at\n";
    $content .= "# id: 고유 식별자 (자동 생성)\n";
    $content .= "# photo: 증명사진 파일명 (judges_photos/ 폴더에 저장)\n\n";
    
    foreach ($judges as $judge) {
        $content .= implode(',', $judge) . "\n";
    }
    
    file_put_contents($judges_file, $content);
}

function generateJudgeId() {
    $judges = loadJudges();
    $max_id = 0;
    foreach ($judges as $judge) {
        if (preg_match('/judge(\d+)/', $judge['id'], $matches)) {
            $max_id = max($max_id, intval($matches[1]));
        }
    }
    return 'judge' . str_pad($max_id + 1, 3, '0', STR_PAD_LEFT);
}

function getJudgeById($id) {
    $judges = loadJudges();
    foreach ($judges as $judge) {
        if ($judge['id'] === $id) {
            return $judge;
        }
    }
    return null;
}

function deleteJudge($id) {
    $judges = loadJudges();
    $judges = array_filter($judges, function($judge) use ($id) {
        return $judge['id'] !== $id;
    });
    saveJudges($judges);
}

function addJudge($data) {
    $judges = loadJudges();
    $new_judge = [
        'id' => generateJudgeId(),
        'name' => $data['name'],
        'organization' => $data['organization'],
        'region' => $data['region'],
        'phone' => $data['phone'],
        'email' => $data['email'],
        'photo' => $data['photo'] ?? '',
        'specialty' => $data['specialty'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    $judges[] = $new_judge;
    saveJudges($judges);
    return $new_judge;
}

function updateJudge($id, $data) {
    $judges = loadJudges();
    foreach ($judges as &$judge) {
        if ($judge['id'] === $id) {
            $judge['name'] = $data['name'];
            $judge['organization'] = $data['organization'];
            $judge['region'] = $data['region'];
            $judge['phone'] = $data['phone'];
            $judge['email'] = $data['email'];
            $judge['photo'] = $data['photo'] ?? $judge['photo'];
            $judge['specialty'] = $data['specialty'];
            $judge['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    saveJudges($judges);
}

function getRegions() {
    return ['서울', '부산', '대구', '인천', '광주', '대전', '울산', '세종', '경기', '강원', '충북', '충남', '전북', '전남', '경북', '경남', '제주'];
}

function getSpecialties() {
    return ['라틴', '모던', '스탠다드', '라인댄스', '힙합', '재즈', '발레', '현대무용', '민속무용', '기타'];
}
?>






