<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $competition_info['title']; ?> - 개요</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.2em;
            font-weight: 300;
        }
        
        .header .subtitle {
            margin: 15px 0 0 0;
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .nav-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav-tab {
            padding: 15px 25px;
            text-decoration: none;
            color: #495057;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .nav-tab:hover {
            background: #e9ecef;
            color: #007bff;
        }
        
        .nav-tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
            background: white;
        }
        
        .content {
            padding: 30px;
        }
        
        .overview-section {
            margin-bottom: 40px;
        }
        
        .overview-section h2 {
            color: #2c3e50;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .info-card h3 {
            margin: 0 0 10px 0;
            color: #007bff;
            font-size: 1.2em;
        }
        
        .info-card p {
            margin: 0;
            color: #495057;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $competition_info['title']; ?></h1>
            <div class="subtitle">
                📅 <?php echo $competition_info['date']; ?> | 
                📍 <?php echo $competition_info['venue']; ?> | 
                👥 <?php echo $competition_info['organizer']; ?>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="?id=<?php echo $comp_id; ?>&page=overview" class="nav-tab active">개요</a>
            <a href="#" class="nav-tab">시간표</a>
            <a href="#" class="nav-tab">공지사항</a>
            <a href="?id=<?php echo $comp_id; ?>&page=results" class="nav-tab">종합결과</a>
            <a href="#" class="nav-tab">실시간 결과</a>
        </div>

        <div class="content">
            <div class="overview-section">
                <h2>📋 대회 개요</h2>
                
                <div class="info-grid">
                    <div class="info-card">
                        <h3>📅 대회 일정</h3>
                        <p><?php echo $competition_info['date']; ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h3>📍 대회 장소</h3>
                        <p><?php echo $competition_info['venue']; ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h3>👥 주최/주관</h3>
                        <p><?php echo $competition_info['organizer']; ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h3>🏆 종목</h3>
                        <p>스탠다드, 라틴, 프로페셔널, 아마추어 등</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
