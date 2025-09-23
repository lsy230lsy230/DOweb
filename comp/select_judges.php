<?php
session_start();
require_once 'auth.php';
require_once 'judge_manager.php';

// 오너 권한만 접근 가능
requirePermission('manage_users');

$user = $_SESSION['user'];
$comp_id = $_GET['comp_id'] ?? '';

if (!$comp_id) {
    die("대회 ID가 필요합니다.");
}

// 대회 정보 로드
$data_dir = __DIR__ . '/data';
$comp_path = "$data_dir/$comp_id";
$info_file = "$comp_path/info.json";

if (!file_exists($info_file)) {
    die("대회 정보를 찾을 수 없습니다.");
}

$comp_info = json_decode(file_get_contents($info_file), true);

// 심사위원 선택 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_judges'])) {
    $selected_judges = $_POST['judges'] ?? [];
    $comp_info['selected_judges'] = $selected_judges;
    file_put_contents($info_file, json_encode($comp_info, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    $message = "심사위원이 선택되었습니다.";
}

$judges = loadJudges();
$selected_judges = $comp_info['selected_judges'] ?? [];

function h($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>심사위원 선택 | danceoffice.net</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <style>
        body {
            background: #181B20;
            min-height: 100vh;
            font-size: 14px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            font-family: 'Noto Sans KR', sans-serif;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #1a1d21 0%, #181B20 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(3, 199, 90, 0.15);
            border: 2px solid #03C75A;
            text-align: center;
        }
        
        .header h1 {
            color: #03C75A;
            font-size: 24px;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .comp-info {
            background: #181B20;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #31343a;
        }
        
        .comp-info h4 {
            color: #03C75A;
            margin: 0 0 10px 0;
        }
        
        .judges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .judge-card {
            background: #222;
            border-radius: 12px;
            padding: 15px;
            border: 2px solid #31343a;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .judge-card:hover {
            border-color: #03C75A;
        }
        
        .judge-card.selected {
            border-color: #03C75A;
            background: rgba(3, 199, 90, 0.1);
        }
        
        .judge-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 10px auto;
            display: block;
            border: 2px solid #03C75A;
        }
        
        .judge-name {
            color: #03C75A;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 8px;
        }
        
        .judge-info {
            color: #F5F7FA;
            font-size: 11px;
            margin-bottom: 5px;
            text-align: center;
        }
        
        .btn {
            background: linear-gradient(90deg, #03C75A 70%, #00BFAE 100%);
            color: #222;
            border: none;
            padding: 12px 24px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .btn:hover {
            background: linear-gradient(90deg, #00BFAE 60%, #03C75A 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .message {
            color: #03C75A;
            background: rgba(3, 199, 90, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .selected-count {
            background: #03C75A;
            color: #222;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>
                <span class="material-symbols-rounded">gavel</span>
                심사위원 선택
            </h1>
            <div class="comp-info">
                <h4><?= h($comp_info['title']) ?></h4>
                <p><?= h($comp_info['date']) ?> | <?= h($comp_info['place']) ?></p>
            </div>
        </header>
        
        <?php if (isset($message)): ?>
            <div class="message"><?= h($message) ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="selected-count" id="selectedCount">
                선택된 심사위원: 0명
            </div>
            
            <div class="judges-grid">
                <?php foreach ($judges as $judge): ?>
                    <div class="judge-card" onclick="toggleJudge('<?= h($judge['id']) ?>')">
                        <input type="checkbox" 
                               name="judges[]" 
                               value="<?= h($judge['id']) ?>" 
                               id="judge_<?= h($judge['id']) ?>"
                               <?= in_array($judge['id'], $selected_judges) ? 'checked' : '' ?>
                               style="display: none;">
                        
                        <?php if ($judge['photo'] && file_exists(__DIR__ . '/judges_photos/' . $judge['photo'])): ?>
                            <img src="judges_photos/<?= h($judge['photo']) ?>" class="judge-photo" alt="<?= h($judge['name']) ?>">
                        <?php else: ?>
                            <div class="judge-photo" style="background: #31343a; display: flex; align-items: center; justify-content: center; color: #8A8D93;">
                                <span class="material-symbols-rounded">person</span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="judge-name"><?= h($judge['name']) ?></div>
                        <div class="judge-info"><?= h($judge['organization']) ?></div>
                        <div class="judge-info"><?= h($judge['region']) ?> | <?= h($judge['specialty']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center;">
                <button type="submit" name="select_judges" class="btn">
                    <span class="material-symbols-rounded">save</span>
                    심사위원 선택 완료
                </button>
            </div>
        </form>
    </div>
    
    <script>
        function toggleJudge(judgeId) {
            const checkbox = document.getElementById('judge_' + judgeId);
            const card = checkbox.closest('.judge-card');
            
            checkbox.checked = !checkbox.checked;
            card.classList.toggle('selected', checkbox.checked);
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('input[name="judges[]"]:checked');
            const countElement = document.getElementById('selectedCount');
            countElement.textContent = '선택된 심사위원: ' + checkboxes.length + '명';
        }
        
        // 초기 선택 상태 설정
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="judges[]"]');
            checkboxes.forEach(checkbox => {
                const card = checkbox.closest('.judge-card');
                if (checkbox.checked) {
                    card.classList.add('selected');
                }
            });
            updateSelectedCount();
        });
    </script>
</body>
</html>






