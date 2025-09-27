<?php
// 실시간 경기결과 업데이트 API
header('Content-Type: application/json; charset=utf-8');

$event_id = $_GET['event_id'] ?? '30';
$comp_id = '20250913-001';

// watch_scoring_files.php를 직접 include하여 실행
ob_start();
$_GET['comp_id'] = $comp_id;
$_GET['event_id'] = $event_id;
include 'watch_scoring_files.php';
$result = ob_get_clean();

if (empty($result)) {
    echo json_encode([
        'success' => false,
        'error' => '실시간 데이터를 가져올 수 없습니다.',
        'status' => 'loading'
    ]);
    exit;
}

$data = json_decode($result, true);

if (!$data || !$data['success']) {
    echo json_encode([
        'success' => false,
        'error' => $data['error'] ?? '데이터 처리 중 오류가 발생했습니다.',
        'status' => 'loading'
    ]);
    exit;
}

// 실시간 결과 HTML 생성
$html = generateLiveResultsHTML($data);

echo json_encode([
    'success' => true,
    'html' => $html,
    'status' => 'completed',
    'event_info' => [
        'name' => $data['event_name'],
        'round' => $data['round'],
        'recall_count' => $data['recall_count'],
        'total_participants' => $data['total_participants'],
        'timestamp' => $data['timestamp']
    ],
    'advancing_players' => $data['advancing_players'] ?? [],
    'total_judges' => $data['total_judges'] ?? 13
]);

function generateLiveResultsHTML($data) {
    $html = '<div style="font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(20px); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">';

    // 이벤트 정보 헤더
    $html .= '<div style="background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(20px); border: 1px solid rgba(59, 130, 246, 0.2); padding: 24px; text-align: center;">';
    $html .= '<h3 style="color: #f1f5f9; font-size: 24px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; justify-content: center; gap: 12px;">';
    $html .= '<span class="material-symbols-rounded" style="color: #3b82f6; font-size: 28px;">live_tv</span>';
    $html .= htmlspecialchars($data['event_name']) . ' - ' . htmlspecialchars($data['round']);
    $html .= '</h3>';
    $html .= '<div style="color: #94a3b8; font-size: 16px; font-weight: 500;">' . $data['recall_count'] . '커플이 다음라운에 진출합니다</div>';
    $html .= '</div>';

    // 리콜 정보
    $html .= '<div style="background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(59, 130, 246, 0.1); padding: 16px 24px; border-top: 1px solid rgba(59, 130, 246, 0.2);">';
    $html .= '<div style="color: #94a3b8; font-size: 14px; line-height: 1.6;">';
    $html .= '<strong style="color: #3b82f6;">리콜 정보:</strong> ';
    $html .= '파일 리콜 수: <strong style="color: #f1f5f9;">' . $data['recall_count'] . '명</strong> | ';
    $html .= '심사위원 수: <strong style="color: #f1f5f9;">' . ($data['total_judges'] ?? 13) . '명</strong> | ';
    $html .= '리콜 기준: <strong style="color: #f1f5f9;">' . $data['recall_count'] . '명 이상</strong>';
    $html .= '</div>';
    $html .= '</div>';

    // 진출자 목록 테이블
    if (!empty($data['advancing_players'])) {
        $html .= '<div style="background: rgba(15, 23, 42, 0.4); padding: 0;">';
        $html .= '<table cellspacing="0" cellpadding="0" width="100%" style="font-family: \'Inter\', sans-serif; border-collapse: collapse; background: transparent;">';
        
        // 테이블 헤더
        $html .= '<thead>';
        $html .= '<tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">';
        $html .= '<th width="8%" align="center" style="padding: 16px 12px; font-weight: 700; font-size: 14px;">Marks</th>';
        $html .= '<th width="8%" align="center" style="padding: 16px 12px; font-weight: 700; font-size: 14px;">Tag</th>';
        $html .= '<th width="60%" align="left" style="padding: 16px 20px; font-weight: 700; font-size: 14px;">Competitor Name(s)</th>';
        $html .= '<th width="24%" align="left" style="padding: 16px 20px; font-weight: 700; font-size: 14px;">From</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        
        // 진출자 행들
        $html .= '<tbody>';
        foreach ($data['advancing_players'] as $index => $player) {
            $isEven = ($index % 2 == 0);
            $bgColor = $isEven ? 'rgba(15, 23, 42, 0.6)' : 'rgba(30, 41, 59, 0.4)';
            
            $html .= '<tr style="font-weight: 500; background-color: ' . $bgColor . '; transition: all 0.3s ease;">';
            $html .= '<td align="center" style="padding: 16px 12px; color: #f1f5f9; font-weight: 600; font-size: 16px;">' . ($index + 1) . '</td>';
            $html .= '<td align="center" style="padding: 16px 12px; color: #94a3b8; font-size: 14px;">(' . $player['recall_count'] . ')</td>';
            $html .= '<td align="left" style="padding: 16px 20px; color: #f1f5f9; font-size: 15px;">';
            $html .= '<span style="color: #3b82f6; font-weight: 600; margin-right: 8px;">' . htmlspecialchars($player['player_number']) . '</span>';
            $html .= htmlspecialchars($player['player_name']);
            $html .= ' <span style="font-size: 14px; color: #10b981; font-weight: 600; margin-left: 8px;">✅ 진출</span>';
            $html .= '</td>';
            $html .= '<td align="left" style="padding: 16px 20px; color: #94a3b8; font-size: 14px;">' . htmlspecialchars($data['event_name']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
    } else {
        $html .= '<div style="text-align: center; padding: 60px 40px; color: #94a3b8; background: rgba(15, 23, 42, 0.4);">';
        $html .= '<div class="material-symbols-rounded" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5; color: #3b82f6;">schedule</div>';
        $html .= '<h3 style="color: #f1f5f9; margin-bottom: 8px;">진행 중인 경기가 없습니다</h3>';
        $html .= '<p>실시간 결과는 대회 진행 중에 표시됩니다.</p>';
        $html .= '</div>';
    }

    // 업데이트 시간
    $html .= '<div style="text-align: center; padding: 16px 24px; background: rgba(15, 23, 42, 0.8); color: #64748b; font-size: 12px; border-top: 1px solid rgba(59, 130, 246, 0.2); display: flex; align-items: center; justify-content: center; gap: 8px;">';
    $html .= '<span class="material-symbols-rounded" style="font-size: 16px;">schedule</span>';
    $html .= '마지막 업데이트: ' . $data['timestamp'];
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}
?>
