<?php
$comp_id = $_GET['comp_id'] ?? '';
$event_no = $_GET['event_no'] ?? '';
$filename = $_GET['filename'] ?? '';

if (empty($comp_id) || empty($event_no) || empty($filename)) {
    echo "Missing parameters";
    exit;
}

$data_dir = __DIR__ . "/data/{$comp_id}";
$scoring_dir = $data_dir . "/scoring_files/Event_{$event_no}";
$file_path = $scoring_dir . "/" . $filename;

if (!file_exists($file_path)) {
    echo "File not found";
    exit;
}

$json_content = file_get_contents($file_path);
$data = json_decode($json_content, true);

if (!$data) {
    echo "Invalid JSON file";
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>채점파일 보기 - <?= htmlspecialchars($filename) ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #3b82f6;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .file-info {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
            color: #6c757d;
        }
        .content {
            padding: 20px;
        }
        .json-viewer {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 600px;
            overflow-y: auto;
        }
        .download-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 15px;
        }
        .download-btn:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>채점파일 보기</h1>
        </div>
        
        <div class="file-info">
            <strong>파일명:</strong> <?= htmlspecialchars($filename) ?><br>
            <strong>이벤트:</strong> <?= htmlspecialchars($event_no) ?>번<br>
            <strong>생성일시:</strong> <?= date('Y-m-d H:i:s', filemtime($file_path)) ?><br>
            <strong>파일크기:</strong> <?= number_format(filesize($file_path)) ?> bytes
        </div>
        
        <div class="content">
            <h3>JSON 데이터</h3>
            <div class="json-viewer"><?= htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></div>
            
            <button class="download-btn" onclick="downloadFile()">파일 다운로드</button>
        </div>
    </div>

    <script>
        function downloadFile() {
            const url = new URL(window.location);
            url.searchParams.set('action', 'download');
            window.open(url.toString(), '_blank');
        }
    </script>
</body>
</html>
