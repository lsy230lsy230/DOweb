<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST 요청만 허용됩니다.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$event_id = $input['event_id'] ?? '';
$html_content = $input['html_content'] ?? '';
$filename = $input['filename'] ?? '';
$competition_data = $input['competition_data'] ?? [];
$comp_id = $input['comp_id'] ?? '20250913-001'; // 기본값 설정

if (empty($event_id) || empty($html_content)) {
    echo json_encode(['success' => false, 'error' => 'Event ID와 HTML 내용이 필요합니다.']);
    exit;
}

// 대회별 디렉토리 구조 생성
$base_dir = "data/{$comp_id}";
$results_dir = "{$base_dir}/Results";
$event_dir = "{$results_dir}/Event_{$event_id}";

// 디렉토리 생성
if (!file_exists($base_dir)) {
    if (!mkdir($base_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => '대회 디렉토리 생성에 실패했습니다.']);
        exit;
    }
}

if (!file_exists($results_dir)) {
    if (!mkdir($results_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Results 디렉토리 생성에 실패했습니다.']);
        exit;
    }
}

if (!file_exists($event_dir)) {
    if (!mkdir($event_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => '이벤트 디렉토리 생성에 실패했습니다.']);
        exit;
    }
}

// 파일명이 제공되지 않으면 고정된 이름 사용 (덮어쓰기)
if (empty($filename)) {
    $filename = "Event_{$event_id}_result.html";
}

// HTML 제목에 이벤트 번호 추가
$title_pattern = '/<title>([^<]+)<\/title>/';
$replacement = '<title>집계 결과 - 이벤트 ' . $event_id . '</title>';

// 기존 제목이 있으면 교체, 없으면 추가
if (preg_match($title_pattern, $html_content)) {
    $full_html = preg_replace($title_pattern, $replacement, $html_content);
} else {
    // 제목이 없으면 head 태그 안에 추가
    $head_pattern = '/(<head[^>]*>)/i';
    $replacement_with_title = '$1' . "\n    " . $replacement;
    $full_html = preg_replace($head_pattern, $replacement_with_title, $html_content);
}

$filepath = $event_dir . '/' . $filename;

// HTML 파일 저장
if (file_put_contents($filepath, $full_html) === false) {
    echo json_encode(['success' => false, 'error' => 'HTML 파일 저장에 실패했습니다.']);
    exit;
}

echo json_encode([
    'success' => true, 
    'message' => 'HTML 리포트가 성공적으로 저장되었습니다.', 
    'filename' => $filename,
    'filepath' => $filepath
]);
?>
