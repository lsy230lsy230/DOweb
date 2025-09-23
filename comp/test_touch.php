<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>터치 테스트</title>
    <style>
        .player-card {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px;
            display: inline-block;
            min-width: 80px;
            user-select: none;
        }
        .player-card.selected {
            border-color: #4CAF50;
            background: #e8f5e8;
        }
        .player-card:hover {
            border-color: #4CAF50;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <h1>터치 테스트</h1>
    <p>선수 번호를 클릭하거나 터치해보세요:</p>
    
    <div class="player-card" data-player="11" onclick="toggleCard(this)">
        <div class="player-number">11</div>
        <input type="checkbox" class="recall-checkbox" style="display: none;">
    </div>
    
    <div class="player-card" data-player="12" onclick="toggleCard(this)">
        <div class="player-number">12</div>
        <input type="checkbox" class="recall-checkbox" style="display: none;">
    </div>
    
    <div class="player-card" data-player="13" onclick="toggleCard(this)">
        <div class="player-number">13</div>
        <input type="checkbox" class="recall-checkbox" style="display: none;">
    </div>
    
    <div class="player-card" data-player="14" onclick="toggleCard(this)">
        <div class="player-number">14</div>
        <input type="checkbox" class="recall-checkbox" style="display: none;">
    </div>
    
    <div class="player-card" data-player="15" onclick="toggleCard(this)">
        <div class="player-number">15</div>
        <input type="checkbox" class="recall-checkbox" style="display: none;">
    </div>
    
    <div id="status">선택된 선수: 0명</div>
    
    <script>
        function toggleCard(card) {
            console.log('Card clicked:', card);
            const checkbox = card.querySelector('.recall-checkbox');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
            
            updateStatus();
        }
        
        function updateStatus() {
            const selected = document.querySelectorAll('.recall-checkbox:checked').length;
            document.getElementById('status').textContent = `선택된 선수: ${selected}명`;
        }
        
        // 터치 이벤트도 추가
        document.querySelectorAll('.player-card').forEach(card => {
            card.addEventListener('touchstart', function(e) {
                e.preventDefault();
                this.style.transform = 'scale(0.95)';
            });
            
            card.addEventListener('touchend', function(e) {
                e.preventDefault();
                this.style.transform = '';
                toggleCard(this);
            });
        });
    </script>
</body>
</html>






