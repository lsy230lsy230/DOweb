// 간단한 실시간 결과 JavaScript
console.log('Simple live results script loaded');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, looking for live results section...');
    
    // 모든 가능한 선택자로 실시간 결과 섹션 찾기
    const selectors = [
        '.live-results-section',
        '#live-results', 
        '.live-results',
        '[class*="live"]',
        '[id*="live"]',
        'div:contains("실시간")',
        'div:contains("경기")'
    ];
    
    let liveResultsSection = null;
    
    for (const selector of selectors) {
        try {
            const element = document.querySelector(selector);
            if (element) {
                console.log('Found element with selector:', selector, element);
                liveResultsSection = element;
                break;
            }
        } catch (e) {
            console.log('Selector failed:', selector, e.message);
        }
    }
    
    // 모든 div 요소를 검사하여 "실시간" 텍스트가 포함된 요소 찾기
    if (!liveResultsSection) {
        const allDivs = document.querySelectorAll('div');
        for (const div of allDivs) {
            if (div.textContent && div.textContent.includes('실시간')) {
                console.log('Found div with "실시간" text:', div);
                liveResultsSection = div;
                break;
            }
        }
    }
    
    if (!liveResultsSection) {
        console.error('실시간 경기결과 섹션을 찾을 수 없습니다.');
        console.log('Available divs:', document.querySelectorAll('div'));
        return;
    }
    
    console.log('Live results section found:', liveResultsSection);
    
    // 간단한 테스트 메시지 표시
    liveResultsSection.innerHTML = `
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6;">
            <h3>프로페셔널 라틴 - Semi-Final</h3>
            <p><strong>6커플이 다음라운에 진출합니다</strong></p>
            <p>리콜 정보: 파일 리콜 수: 6명 | 심사위원 수: 13명 | 리콜 기준: 6명 이상</p>
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <thead>
                    <tr style="background: #007bff; color: white;">
                        <th style="padding: 12px 8px; text-align: left; font-weight: bold; font-size: 0.9em;">Marks</th>
                        <th style="padding: 12px 8px; text-align: left; font-weight: bold; font-size: 0.9em;">Tag</th>
                        <th style="padding: 12px 8px; text-align: left; font-weight: bold; font-size: 0.9em;">Competitor Name(s)</th>
                        <th style="padding: 12px 8px; text-align: left; font-weight: bold; font-size: 0.9em;">From</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px 8px; font-size: 0.9em;">1</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">(48)</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">18 김재희 & 박건희 ✅ 진출</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">프로페셔널 라틴</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px 8px; font-size: 0.9em;">2</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">(45)</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">19 전상우 & 강민지 ✅ 진출</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">프로페셔널 라틴</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px 8px; font-size: 0.9em;">3</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">(44)</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">16 김민제 & 함혜빈 ✅ 진출</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">프로페셔널 라틴</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px 8px; font-size: 0.9em;">4</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">(39)</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">17 윤휘진 & 이소담 ✅ 진출</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">프로페셔널 라틴</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px 8px; font-size: 0.9em;">5</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">(34)</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">15 염태우 & 박서희 ✅ 진출</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">프로페셔널 라틴</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 8px; font-size: 0.9em;">6</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">(28)</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">13 남기용 & 신나라 ✅ 진출</td>
                        <td style="padding: 10px 8px; font-size: 0.9em;">프로페셔널 라틴</td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    console.log('Live results displayed successfully');
});
