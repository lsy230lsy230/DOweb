<?php
/**
 * 5가지 심사 방식 테스트 페이지
 */

// HTML 이스케이프 함수
function h($s) { 
    return htmlspecialchars($s ?? ''); 
}

$comp_id = '20250907-001';
$lang = 'ko';

$scoring_types = [
    '8' => ['name' => '멀티이벤트 결승전 (기존)', 'type' => 'multievent_final'],
    '9' => ['name' => '멀티이벤트 준결승 (기존)', 'type' => 'multievent_preliminary'],
    '10' => ['name' => '프리스타일 심사', 'type' => 'freestyle'],
    '11' => ['name' => '포메이션 심사', 'type' => 'formation'],
    '12' => ['name' => '멀티이벤트 결승전 (준결승 없음)', 'type' => 'multievent_final_only']
];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>심사 방식 테스트</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .scoring-type {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            transition: all 0.3s ease;
        }
        .scoring-type:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .scoring-type h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .scoring-type p {
            margin: 0 0 15px 0;
            color: #666;
        }
        .test-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
        }
        .test-btn:hover {
            background: #5a6fd8;
        }
        .type-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎭 5가지 심사 방식 테스트</h1>
        
        <?php foreach ($scoring_types as $event_no => $info): ?>
        <div class="scoring-type">
            <h3>
                이벤트 #<?= $event_no ?>: <?= $info['name'] ?>
                <span class="type-badge"><?= $info['type'] ?></span>
            </h3>
            <p>
                <?php if ($info['type'] === 'multievent_final'): ?>
                    기존에 작업한 결승전 시스템 - 터치 기반 순위 시스템, 드래그 앤 드롭 지원
                <?php elseif ($info['type'] === 'multievent_preliminary'): ?>
                    기존에 작업한 준결승 시스템 - Instagram 스타일 댄스별 채점
                <?php elseif ($info['type'] === 'freestyle'): ?>
                    프리스타일 심사 - 창의성, 기술성, 표현력, 음악성 종합 평가
                <?php elseif ($info['type'] === 'formation'): ?>
                    포메이션 심사 - 팀 단위 포메이션 패턴 평가
                <?php elseif ($info['type'] === 'multievent_final_only'): ?>
                    멀티이벤트 결승전 (준결승 없음) - 바로 결승전으로 진행
                <?php endif; ?>
            </p>
            <a href="judge_scoring_router.php?comp_id=<?= h($comp_id) ?>&event_no=<?= $event_no ?>&lang=<?= h($lang) ?>" 
               class="test-btn" target="_blank">
                테스트하기
            </a>
        </div>
        <?php endforeach; ?>
        
        <div style="text-align: center; margin-top: 30px; padding: 20px; background: #e8f5e8; border-radius: 8px;">
            <h3>📝 테스트 방법</h3>
            <p>1. 각 심사 방식을 클릭하여 테스트 페이지로 이동</p>
            <p>2. 각 시스템의 기능을 확인하고 테스트</p>
            <p>3. 데이터 저장 및 복원 기능 확인</p>
            <p>4. 모바일/태블릿 반응형 디자인 확인</p>
        </div>
    </div>
</body>
</html>
