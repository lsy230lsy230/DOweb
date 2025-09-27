<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST 요청만 허용됩니다.']);
    exit;
}

// POST 데이터 받기 (form-urlencoded 우선, JSON 대체)
$group_id = $_POST['group_id'] ?? '';
$recall_count = intval($_POST['recall_count'] ?? 0);

// JSON 데이터가 있으면 우선 사용
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $group_id = $input['group_id'] ?? $group_id;
    $recall_count = intval($input['recall_count'] ?? $recall_count);
}

if (!$group_id) {
    echo json_encode(['success' => false, 'error' => '그룹 ID가 필요합니다.']);
    exit;
}

$comp_id = $_GET['comp_id'] ?? '20250913-001';
$data_dir = __DIR__ . "/data/$comp_id";
$runorder_file = "$data_dir/RunOrder_Tablet.txt";

try {
    if (!file_exists($runorder_file)) {
        throw new Exception("RunOrder_Tablet.txt 파일을 찾을 수 없습니다.");
    }
    
    // 파일 읽기
    $lines = file($runorder_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $updated = false;
    
    // 해당 이벤트 찾아서 Recall 수 업데이트
    foreach ($lines as $index => $line) {
        $parts = explode(',', $line);
        if (count($parts) >= 12) {
            $event_no = trim($parts[0]);
            
            // 정확한 이벤트 번호와 매칭되는 이벤트만 찾기
            if ($event_no == $group_id) {
                // 5번째 컬럼(리콜 수) 업데이트 (인덱스 4)
                $parts[4] = $recall_count;
                $lines[$index] = implode(',', $parts);
                $updated = true;
                break;
            }
        }
    }
    
    if (!$updated) {
        throw new Exception("해당 그룹 ID를 찾을 수 없습니다: $group_id");
    }
    
    // 파일에 다시 쓰기
    $content = implode("\n", $lines) . "\n";
    if (file_put_contents($runorder_file, $content) === false) {
        throw new Exception("파일 저장에 실패했습니다.");
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Recall 수가 $recall_count명으로 업데이트되었습니다.",
        'group_id' => $group_id,
        'recall_count' => $recall_count
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
