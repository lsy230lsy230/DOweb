<?php
/**
 * 포메이션 심사 시스템
 * 팀 단위, 포메이션 패턴 평가 시스템
 */

// 공통 파일들 포함
require_once __DIR__ . '/../shared/functions.php';
require_once __DIR__ . '/../shared/styles.php';
require_once __DIR__ . '/../shared/components.php';
?>

<!-- 포메이션 전용 스타일 -->
<style>
    .formation-container {
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: 20px;
        margin-top: 10px;
    }
    
    .formation-header {
        text-align: center;
        margin-bottom: 20px;
        padding: 15px;
        background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
        color: white;
        border-radius: 8px;
    }
    
    .formation-header h2 {
        margin: 0 0 10px 0;
        font-size: 24px;
    }
    
    .formation-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 14px;
    }
    
    .team-selection {
        margin-bottom: 20px;
    }
    
    .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .team-card {
        background: #f8f9fa;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .team-card:hover {
        border-color: #FF6B6B;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .team-card.selected {
        border-color: #FF6B6B;
        background: linear-gradient(135deg, #ffe8e8, #f0f8ff);
        transform: scale(1.05);
    }
    
    .team-number {
        font-size: 20px;
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
    }
    
    .team-members {
        font-size: 12px;
        color: #666;
    }
    
    .pattern-selection {
        margin-bottom: 20px;
    }
    
    .pattern-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .pattern-card {
        background: #fff;
        border: 2px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .pattern-card:hover {
        border-color: #4ECDC4;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .pattern-card.selected {
        border-color: #4ECDC4;
        background: linear-gradient(135deg, #e8f8f5, #f0f8ff);
    }
    
    .pattern-icon {
        font-size: 24px;
        margin-bottom: 10px;
    }
    
    .pattern-name {
        font-weight: bold;
        color: #333;
        margin-bottom: 5px;
    }
    
    .pattern-description {
        font-size: 12px;
        color: #666;
    }
    
    .evaluation-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .evaluation-criteria {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .criterion-item {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 15px;
        text-align: center;
    }
    
    .criterion-title {
        font-weight: bold;
        color: #333;
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    .score-input {
        width: 60px;
        padding: 8px;
        border: 2px solid #ddd;
        border-radius: 4px;
        text-align: center;
        font-size: 16px;
        font-weight: bold;
    }
    
    .score-input:focus {
        border-color: #4ECDC4;
        outline: none;
    }
    
    .overall-formation-score {
        background: linear-gradient(135deg, #4ECDC4, #44A08D);
        color: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        margin: 20px 0;
    }
    
    .overall-formation-score h3 {
        margin: 0 0 10px 0;
        font-size: 20px;
    }
    
    .overall-formation-score-value {
        font-size: 36px;
        font-weight: bold;
        margin: 10px 0;
    }
    
    .formation-comments {
        margin-top: 20px;
    }
    
    .formation-comments textarea {
        width: 100%;
        min-height: 100px;
        padding: 15px;
        border: 2px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        resize: vertical;
        font-family: inherit;
    }
    
    .formation-comments textarea:focus {
        border-color: #4ECDC4;
        outline: none;
    }
    
    .submit-formation {
        text-align: center;
        margin-top: 20px;
    }
    
    .submit-formation-btn {
        background: linear-gradient(135deg, #FF6B6B, #4ECDC4);
        color: white;
        border: none;
        padding: 15px 30px;
        font-size: 16px;
        font-weight: bold;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .submit-formation-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .submit-formation-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    /* 모바일 반응형 */
    @media (max-width: 768px) {
        .team-grid, .pattern-grid {
            grid-template-columns: 1fr;
        }
        
        .evaluation-criteria {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- 포메이션 심사 인터페이스 -->
<div class="formation-container">
    <div class="formation-header">
        <h2>🎭 포메이션 심사</h2>
        <p>팀의 포메이션 패턴과 전체적인 완성도를 평가해주세요</p>
    </div>
    
    <!-- 팀 선택 -->
    <div class="team-selection">
        <h3>팀 선택</h3>
        <div class="team-grid" id="teamGrid">
            <?php 
            // 팀 데이터 생성 (실제로는 데이터베이스에서 로드)
            $teams = [];
            for ($i = 1; $i <= 6; $i++) {
                $teams[] = [
                    'number' => $i,
                    'members' => '팀원 ' . ($i * 4) . '명'
                ];
            }
            foreach ($teams as $team): 
            ?>
                <div class="team-card" data-team="<?= $team['number'] ?>" onclick="selectTeam(<?= $team['number'] ?>)">
                    <div class="team-number">팀 <?= $team['number'] ?></div>
                    <div class="team-members"><?= $team['members'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- 포메이션 패턴 선택 -->
    <div class="pattern-selection">
        <h3>포메이션 패턴</h3>
        <div class="pattern-grid" id="patternGrid">
            <div class="pattern-card" data-pattern="line" onclick="selectPattern('line')">
                <div class="pattern-icon">📏</div>
                <div class="pattern-name">라인 포메이션</div>
                <div class="pattern-description">일직선 배열</div>
            </div>
            
            <div class="pattern-card" data-pattern="circle" onclick="selectPattern('circle')">
                <div class="pattern-icon">⭕</div>
                <div class="pattern-name">서클 포메이션</div>
                <div class="pattern-description">원형 배열</div>
            </div>
            
            <div class="pattern-card" data-pattern="diamond" onclick="selectPattern('diamond')">
                <div class="pattern-icon">💎</div>
                <div class="pattern-name">다이아몬드</div>
                <div class="pattern-description">마름모 배열</div>
            </div>
            
            <div class="pattern-card" data-pattern="triangle" onclick="selectPattern('triangle')">
                <div class="pattern-icon">🔺</div>
                <div class="pattern-name">트라이앵글</div>
                <div class="pattern-description">삼각형 배열</div>
            </div>
            
            <div class="pattern-card" data-pattern="square" onclick="selectPattern('square')">
                <div class="pattern-icon">⬜</div>
                <div class="pattern-name">스퀘어</div>
                <div class="pattern-description">사각형 배열</div>
            </div>
            
            <div class="pattern-card" data-pattern="free" onclick="selectPattern('free')">
                <div class="pattern-icon">🎨</div>
                <div class="pattern-name">자유 포메이션</div>
                <div class="pattern-description">창의적 배열</div>
            </div>
        </div>
    </div>
    
    <!-- 평가 섹션 -->
    <div class="evaluation-section">
        <h3>세부 평가</h3>
        <div class="evaluation-criteria">
            <div class="criterion-item">
                <div class="criterion-title">정확성</div>
                <input type="number" class="score-input" id="accuracyScore" min="1" max="10" value="5" onchange="updateFormationScore()">
                <div style="font-size: 12px; color: #666;">1-10점</div>
            </div>
            
            <div class="criterion-item">
                <div class="criterion-title">동기화</div>
                <input type="number" class="score-input" id="synchronizationScore" min="1" max="10" value="5" onchange="updateFormationScore()">
                <div style="font-size: 12px; color: #666;">1-10점</div>
            </div>
            
            <div class="criterion-item">
                <div class="criterion-title">창의성</div>
                <input type="number" class="score-input" id="creativityScore" min="1" max="10" value="5" onchange="updateFormationScore()">
                <div style="font-size: 12px; color: #666;">1-10점</div>
            </div>
            
            <div class="criterion-item">
                <div class="criterion-title">완성도</div>
                <input type="number" class="score-input" id="completionScore" min="1" max="10" value="5" onchange="updateFormationScore()">
                <div style="font-size: 12px; color: #666;">1-10점</div>
            </div>
            
            <div class="criterion-item">
                <div class="criterion-title">표현력</div>
                <input type="number" class="score-input" id="expressionScore" min="1" max="10" value="5" onchange="updateFormationScore()">
                <div style="font-size: 12px; color: #666;">1-10점</div>
            </div>
            
            <div class="criterion-item">
                <div class="criterion-title">음악성</div>
                <input type="number" class="score-input" id="musicalityScore" min="1" max="10" value="5" onchange="updateFormationScore()">
                <div style="font-size: 12px; color: #666;">1-10점</div>
            </div>
        </div>
    </div>
    
    <!-- 전체 점수 -->
    <div class="overall-formation-score">
        <h3>종합 점수</h3>
        <div class="overall-formation-score-value" id="overallFormationScore">30</div>
        <div style="font-size: 14px; opacity: 0.9;">정확성 + 동기화 + 창의성 + 완성도 + 표현력 + 음악성</div>
    </div>
    
    <!-- 코멘트 -->
    <div class="formation-comments">
        <h3>심사 코멘트</h3>
        <textarea id="formationComments" placeholder="팀의 포메이션 패턴, 동기화, 창의성 등에 대한 평가를 자유롭게 작성해주세요..."></textarea>
    </div>
    
    <!-- 제출 버튼 -->
    <div class="submit-formation">
        <button type="button" class="submit-formation-btn" id="submitFormation" onclick="submitFormationScore()">
            포메이션 점수 제출
        </button>
    </div>
</div>

<!-- 포메이션 전용 JavaScript -->
<script>
let selectedTeam = null;
let selectedPattern = null;
let currentScores = {
    accuracy: 5,
    synchronization: 5,
    creativity: 5,
    completion: 5,
    expression: 5,
    musicality: 5
};

document.addEventListener('DOMContentLoaded', function() {
    initializeFormationSystem();
});

function initializeFormationSystem() {
    updateFormationScore();
}

function selectTeam(teamNumber) {
    // 이전 선택 해제
    document.querySelectorAll('.team-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // 새 선택
    selectedTeam = teamNumber;
    const teamCard = document.querySelector(`[data-team="${teamNumber}"]`);
    if (teamCard) {
        teamCard.classList.add('selected');
    }
    
    console.log('Selected team:', teamNumber);
}

function selectPattern(pattern) {
    // 이전 선택 해제
    document.querySelectorAll('.pattern-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // 새 선택
    selectedPattern = pattern;
    const patternCard = document.querySelector(`[data-pattern="${pattern}"]`);
    if (patternCard) {
        patternCard.classList.add('selected');
    }
    
    console.log('Selected pattern:', pattern);
}

function updateFormationScore() {
    // 점수 업데이트
    currentScores.accuracy = parseInt(document.getElementById('accuracyScore').value) || 0;
    currentScores.synchronization = parseInt(document.getElementById('synchronizationScore').value) || 0;
    currentScores.creativity = parseInt(document.getElementById('creativityScore').value) || 0;
    currentScores.completion = parseInt(document.getElementById('completionScore').value) || 0;
    currentScores.expression = parseInt(document.getElementById('expressionScore').value) || 0;
    currentScores.musicality = parseInt(document.getElementById('musicalityScore').value) || 0;
    
    // 전체 점수 계산
    const total = Object.values(currentScores).reduce((sum, score) => sum + score, 0);
    
    document.getElementById('overallFormationScore').textContent = total;
    
    // 점수에 따른 색상 변경
    const overallElement = document.querySelector('.overall-formation-score');
    if (total >= 50) {
        overallElement.style.background = 'linear-gradient(135deg, #4ECDC4, #44A08D)';
    } else if (total >= 35) {
        overallElement.style.background = 'linear-gradient(135deg, #FF9800, #F57C00)';
    } else {
        overallElement.style.background = 'linear-gradient(135deg, #F44336, #D32F2F)';
    }
}

function submitFormationScore() {
    if (!selectedTeam) {
        alert('팀을 선택해주세요.');
        return;
    }
    
    if (!selectedPattern) {
        alert('포메이션 패턴을 선택해주세요.');
        return;
    }
    
    const comments = document.getElementById('formationComments').value.trim();
    const totalScore = Object.values(currentScores).reduce((sum, score) => sum + score, 0);
    
    // 데이터 준비
    const formationData = {
        team: selectedTeam,
        pattern: selectedPattern,
        scores: currentScores,
        totalScore: totalScore,
        comments: comments,
        timestamp: new Date().toISOString()
    };
    
    // FormData 생성
    const formData = new FormData();
    formData.append('comp_id', '<?= h($comp_id) ?>');
    formData.append('event_no', '<?= h($event_no) ?>');
    formData.append('type', 'formation');
    formData.append('judge_id', '<?= h($judge_id) ?>');
    formData.append('formation_data', JSON.stringify(formationData));
    
    // 제출 버튼 비활성화
    const submitBtn = document.getElementById('submitFormation');
    submitBtn.disabled = true;
    submitBtn.textContent = '제출 중...';
    
    // 서버로 전송
    fetch('scoring/save_scores.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert(`팀 ${selectedTeam}의 포메이션 점수가 저장되었습니다.\n패턴: ${selectedPattern}\n종합 점수: ${totalScore}점`);
            
            // 다음 팀으로 이동하거나 완료 처리
            moveToNextTeam();
        } else {
            alert('제출 실패: ' + (result.error || '알 수 없는 오류'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('제출 중 오류가 발생했습니다: ' + error);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = '포메이션 점수 제출';
    });
}

function moveToNextTeam() {
    // 현재 선택된 팀 카드 숨기기
    if (selectedTeam) {
        const teamCard = document.querySelector(`[data-team="${selectedTeam}"]`);
        if (teamCard) {
            teamCard.style.display = 'none';
        }
    }
    
    // 선택 해제
    selectedTeam = null;
    selectedPattern = null;
    document.querySelectorAll('.team-card, .pattern-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // 점수 초기화
    resetFormationScores();
    
    // 코멘트 초기화
    document.getElementById('formationComments').value = '';
    
    // 남은 팀이 있는지 확인
    const remainingTeams = document.querySelectorAll('.team-card:not([style*="display: none"])');
    if (remainingTeams.length === 0) {
        alert('모든 팀의 포메이션 심사가 완료되었습니다!');
        // 대시보드로 리다이렉트
        setTimeout(() => {
            window.location.href = 'scoring_dashboard.php?comp_id=<?= h($comp_id) ?>&lang=<?= h($lang) ?>';
        }, 2000);
    }
}

function resetFormationScores() {
    currentScores = {
        accuracy: 5,
        synchronization: 5,
        creativity: 5,
        completion: 5,
        expression: 5,
        musicality: 5
    };
    
    // 점수 입력 초기화
    document.getElementById('accuracyScore').value = 5;
    document.getElementById('synchronizationScore').value = 5;
    document.getElementById('creativityScore').value = 5;
    document.getElementById('completionScore').value = 5;
    document.getElementById('expressionScore').value = 5;
    document.getElementById('musicalityScore').value = 5;
    
    updateFormationScore();
}
</script>






