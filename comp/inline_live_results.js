// 인라인 실시간 결과 JavaScript
console.log('Inline live results script loaded');

// DOM이 로드되면 실행
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, looking for live results section...');
    
    // 웹사이트의 실제 구조에 맞게 실시간 결과 섹션 찾기
    let liveResultsSection = null;
    
    // 여러 가능한 선택자로 시도
    const selectors = [
        'div:contains("실시간 경기 결과")',
        'div:contains("실시간 결과를 로딩 중입니다")',
        'div:contains("30초마다 자동 갱신됩니다")',
        '.live-results-section',
        '#live-results',
        '.live-results',
        '[class*="live"]',
        '[id*="live"]'
    ];
    
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
    
    // 모든 요소를 검사하여 "로딩 중입니다" 텍스트가 포함된 요소 찾기
    if (!liveResultsSection) {
        const allElements = document.querySelectorAll('*');
        for (const element of allElements) {
            if (element.textContent && element.textContent.includes('로딩 중입니다')) {
                console.log('Found element with "로딩 중입니다" text:', element);
                liveResultsSection = element;
                break;
            }
        }
    }
    
    if (!liveResultsSection) {
        console.error('실시간 경기결과 섹션을 찾을 수 없습니다.');
        console.log('All divs:', document.querySelectorAll('div'));
        return;
    }
    
    console.log('Live results section found:', liveResultsSection);
    
    // 실시간 결과 HTML 생성
    const liveResultsHTML = `
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; margin: 20px 0;">
            <h3 style="color: #007bff; margin: 0 0 10px 0; font-size: 1.4em;">프로페셔널 라틴 - Semi-Final</h3>
            <p style="margin: 5px 0; font-size: 1.1em;"><strong>6커플이 다음라운에 진출합니다</strong></p>
            <p style="margin: 5px 0; font-size: 0.9em; color: #6c757d;">리콜 정보: 파일 리콜 수: 6명 | 심사위원 수: 13명 | 리콜 기준: 6명 이상</p>
            
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
    
    // 실시간 결과 섹션의 내용을 교체
    liveResultsSection.innerHTML = liveResultsHTML;
    
    console.log('Live results displayed successfully');
});
