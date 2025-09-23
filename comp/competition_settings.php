<?php
/**
 * 대회 설정 관리
 */

$settingsFile = __DIR__ . '/uploads/competition_settings.json';

// 기본 설정
$defaultSettings = [
    'competition_title' => '2025 경기도지사배 전국장애인댄스스포츠선수권대회',
    'competition_subtitle' => 'DanceOffice 댄스스포츠 관리 시스템'
];

// 설정 로드
$settings = $defaultSettings;
if (file_exists($settingsFile)) {
    $loadedSettings = json_decode(file_get_contents($settingsFile), true);
    if ($loadedSettings) {
        $settings = array_merge($defaultSettings, $loadedSettings);
    }
}

// 설정 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSettings = [
        'competition_title' => $_POST['competition_title'] ?? $defaultSettings['competition_title'],
        'competition_subtitle' => $_POST['competition_subtitle'] ?? $defaultSettings['competition_subtitle']
    ];
    
    if (file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT))) {
        $message = '대회 설정이 저장되었습니다.';
        $settings = $newSettings;
    } else {
        $error = '설정 저장에 실패했습니다.';
    }
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>대회 설정</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn.success {
            background: #28a745;
        }
        
        .btn.success:hover {
            background: #218838;
        }
        
        .btn.info {
            background: #17a2b8;
        }
        
        .btn.info:hover {
            background: #138496;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .preview {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            text-align: center;
        }
        
        .preview h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .preview p {
            color: #666;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏆 대회 설정</h1>
            <p>대회 제목과 부제목을 설정하세요</p>
        </div>
        
        <?php if (isset($message)): ?>
        <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="competition_title">대회 제목</label>
                <input type="text" id="competition_title" name="competition_title" 
                       value="<?php echo htmlspecialchars($settings['competition_title']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="competition_subtitle">대회 부제목</label>
                <input type="text" id="competition_subtitle" name="competition_subtitle" 
                       value="<?php echo htmlspecialchars($settings['competition_subtitle']); ?>">
            </div>
            
            <div style="display: flex; gap: 15px;">
                <button type="submit" class="btn success">💾 설정 저장</button>
                <button type="button" class="btn info" onclick="openEventMonitor()">🖥️ 전체화면 미리보기</button>
                <button type="button" class="btn" onclick="openEventUpload()">📁 이벤트 업로드</button>
            </div>
        </form>
        
        <div class="preview">
            <h2>미리보기</h2>
            <h3 id="previewTitle"><?php echo htmlspecialchars($settings['competition_title']); ?></h3>
            <p id="previewSubtitle"><?php echo htmlspecialchars($settings['competition_subtitle']); ?></p>
        </div>
    </div>

    <script>
        function openEventMonitor() {
            window.open('event_monitor_v2.php', '_blank');
        }
        
        function openEventUpload() {
            window.open('event_upload.php', '_blank');
        }
        
        // 실시간 미리보기
        document.getElementById('competition_title').addEventListener('input', function() {
            document.getElementById('previewTitle').textContent = this.value;
        });
        
        document.getElementById('competition_subtitle').addEventListener('input', function() {
            document.getElementById('previewSubtitle').textContent = this.value;
        });
    </script>
</body>
</html>
