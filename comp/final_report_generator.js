// 결승전 결과 리포트 생성 함수들

function generateFinalReportHtml(data, eventInfo) {
    const compInfo = window.compInfo || { title: '대회', date: '2025.09.13', place: '장소' };
    
    return `
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>${eventInfo.desc} - Final Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 10px; }
        .subtitle { font-size: 18px; color: #666; margin-bottom: 5px; }
        .date { font-size: 14px; color: #888; }
        .results-section { margin-bottom: 40px; }
        .section-title { font-size: 20px; font-weight: bold; color: #333; margin-bottom: 15px; border-left: 4px solid #007bff; padding-left: 10px; }
        .final-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .final-table th, .final-table td { padding: 12px; text-align: center; border: 1px solid #ddd; }
        .final-table th { background: #333; color: white; font-weight: bold; }
        .final-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .final-table tbody tr:hover { background: #e9ecef; }
        .final-table th:first-child, .final-table td:first-child { background: #e9ecef; font-weight: bold; }
        .final-table th:nth-child(2), .final-table td:nth-child(2) { text-align: left; min-width: 200px; }
        .final-table th:last-child, .final-table td:last-child { background: #ffd700; font-weight: bold; color: #333; }
        .dance-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .dance-tab { background: #f8f9fa; border: 1px solid #ddd; padding: 8px 16px; cursor: pointer; border-radius: 4px; }
        .dance-tab.active { background: #007bff; color: white; }
        .dance-details { display: none; }
        .dance-details.active { display: block; }
        .skating-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .skating-table th, .skating-table td { padding: 6px; text-align: center; border: 1px solid #ddd; }
        .skating-table th { background: #f8f9fa; font-weight: bold; }
        .skating-table tbody tr:nth-child(even) { background: #f8f9fa; }
        .skating-table th:first-child, .skating-table td:first-child { background: #e9ecef; font-weight: bold; }
        .skating-table th:nth-child(2), .skating-table td:nth-child(2) { text-align: left; min-width: 150px; }
        .skating-table th:last-child, .skating-table td:last-child { background: #ffd700; font-weight: bold; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
        @media print { body { background: white; } .container { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">${compInfo.title}</div>
            <div class="subtitle">${eventInfo.desc} - Final Results</div>
            <div class="date">${compInfo.date} | ${compInfo.place}</div>
        </div>
        
        <div class="results-section">
            <div class="section-title">Final Rankings (Skating System)</div>
            <table class="final-table">
                <thead>
                    <tr>
                        <th>Place</th>
                        <th>Competitor Name(s)</th>
                        <th>SUM of Places</th>
                        ${Object.keys(data.dance_results).map(danceCode => {
                            const dance = data.dance_results[danceCode];
                            return `<th>${dance.name}</th>`;
                        }).join('')}
                    </tr>
                </thead>
                <tbody>
                    ${data.final_rankings.map(ranking => {
                        const player = data.players.find(p => p.number === ranking.player_no);
                        const playerName = player ? `${player.male} / ${player.female}` : `선수 ${ranking.player_no}`;
                        const danceRankings = Object.keys(data.dance_results).map(danceCode => {
                            const dance = data.dance_results[danceCode];
                            return dance.final_rankings[ranking.player_no] || '-';
                        });
                        
                        return `
                            <tr>
                                <td>${ranking.final_rank}</td>
                                <td>${playerName}</td>
                                <td>${ranking.sum_of_places}</td>
                                ${danceRankings.map(rank => `<td>${rank}</td>`).join('')}
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        </div>
        
        <div class="results-section">
            <div class="section-title">Detailed Results by Dance</div>
            <div class="dance-tabs">
                ${Object.keys(data.dance_results).map((danceCode, index) => {
                    const dance = data.dance_results[danceCode];
                    return `<div class="dance-tab ${index === 0 ? 'active' : ''}" onclick="showDanceDetails('${danceCode}')">${dance.name}</div>`;
                }).join('')}
            </div>
            
            ${Object.keys(data.dance_results).map((danceCode, index) => {
                const dance = data.dance_results[danceCode];
                return `
                    <div class="dance-details ${index === 0 ? 'active' : ''}" id="dance-${danceCode}">
                        <h4>${dance.name} - Skating System Results</h4>
                        <table class="skating-table">
                            <thead>
                                <tr>
                                    <th>Cpl. No.</th>
                                    <th>Competitor Name(s)</th>
                                    ${data.adjudicators.map(adj => `<th>${adj.code}</th>`).join('')}
                                    <th>1</th>
                                    <th>1&2</th>
                                    <th>1to3</th>
                                    <th>1to4</th>
                                    <th>1to5</th>
                                    <th>1to6</th>
                                    <th>Place</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.players.map(player => {
                                    const playerNo = player.number;
                                    const playerName = `${player.male} / ${player.female}`;
                                    const judgeRanks = data.adjudicators.map(adj => {
                                        const scores = dance.judge_scores[adj.code];
                                        return scores && scores[playerNo] ? scores[playerNo] : '-';
                                    });
                                    const skatingData = calculateSkatingDataForPlayer(dance.judge_scores, playerNo);
                                    const finalRank = dance.final_rankings[playerNo] || '-';
                                    
                                    return `
                                        <tr>
                                            <td>${playerNo}</td>
                                            <td>${playerName}</td>
                                            ${judgeRanks.map(rank => `<td>${rank}</td>`).join('')}
                                            <td>${skatingData.place_1}</td>
                                            <td>${skatingData.place_1_2}</td>
                                            <td>${skatingData.place_1to3} (${skatingData.sum_1to3})</td>
                                            <td>${skatingData.place_1to4} (${skatingData.sum_1to4})</td>
                                            <td>${skatingData.place_1to5} (${skatingData.sum_1to5})</td>
                                            <td>${skatingData.place_1to6} (${skatingData.sum_1to6})</td>
                                            <td><strong>${finalRank}</strong></td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }).join('')}
        </div>
        
        <div class="footer">
            <p>&copy; 2025 DanceOffice - Powered by Seyoung Lee</p>
        </div>
    </div>
    
    <script>
        function showDanceDetails(danceCode) {
            // 모든 탭과 내용 비활성화
            document.querySelectorAll('.dance-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.dance-details').forEach(details => details.classList.remove('active'));
            
            // 선택된 탭과 내용 활성화
            document.querySelector('[onclick="showDanceDetails(\\'' + danceCode + '\\')"]').classList.add('active');
            document.getElementById('dance-' + danceCode).classList.add('active');
        }
        
        function calculateSkatingDataForPlayer(judgeScores, playerNo) {
            const rankings = [];
            Object.values(judgeScores).forEach(scores => {
                if (scores[playerNo]) {
                    rankings.push(scores[playerNo]);
                }
            });
            
            if (rankings.length === 0) {
                return { place_1: 0, place_1_2: 0, place_1to3: 0, place_1to4: 0, place_1to5: 0, place_1to6: 0, sum_1to3: 0, sum_1to4: 0, sum_1to5: 0, sum_1to6: 0 };
            }
            
            let place_1 = 0, place_1_2 = 0, place_1to3 = 0, place_1to4 = 0, place_1to5 = 0, place_1to6 = 0;
            let sum_1to3 = 0, sum_1to4 = 0, sum_1to5 = 0, sum_1to6 = 0;
            
            rankings.forEach(rank => {
                if (rank === 1) place_1++;
                if (rank <= 2) place_1_2++;
                if (rank <= 3) { place_1to3++; sum_1to3 += rank; }
                if (rank <= 4) { place_1to4++; sum_1to4 += rank; }
                if (rank <= 5) { place_1to5++; sum_1to5 += rank; }
                if (rank <= 6) { place_1to6++; sum_1to6 += rank; }
            });
            
            return { place_1, place_1_2, place_1to3, place_1to4, place_1to5, place_1to6, sum_1to3, sum_1to4, sum_1to5, sum_1to6 };
        }
    </script>
</body>
</html>
    `;
}





