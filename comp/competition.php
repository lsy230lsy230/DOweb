<?php
// 대회 결과 페이지
$comp_id = $_GET['id'] ?? '';
$page = $_GET['page'] ?? 'overview';

// 대회 정보 설정
$competition_info = [
    'title' => '제12회 서초구청장배 댄스스포츠 대회',
    'date' => '2025년 09월 28일',
    'venue' => '서초실내체육관',
    'organizer' => '서초구댄스스포츠연맹'
];

// 이벤트별 결과 데이터 (1번부터 차례대로)
$events = [
    [
        'id' => 1,
        'name' => '솔로 일반부 스탠다드 1종목 (T)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 2,
        'name' => '유치부 라틴 1종목 (C)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 3,
        'name' => '솔로 초등부 저학년 라틴 2종목 (R J)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 4,
        'name' => '솔로 초등부 저학년 라틴 2종목 (C J)',
        'round' => '결승',
        'status' => 'processing'
    ],
    [
        'id' => 5,
        'name' => '솔로 유치부 라틴 1종목 (C)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 6,
        'name' => '솔로 초등부 저학년 라틴 3종목 (C R S)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 7,
        'name' => '솔로 초등부 저학년 라틴 3종목 (C R J)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 8,
        'name' => '솔로 초등부 고학년 라틴 3종목 (C R S)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 9,
        'name' => '솔로 초등부 저학년 라틴 2종목 (C R)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 10,
        'name' => '솔로 중등부 라틴 3종목 (C R J)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 11,
        'name' => '솔로 초등부 고학년 라틴 4종목',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 12,
        'name' => '솔로 일반부 라틴 3종목 (C R S)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 13,
        'name' => '솔로 초등부 고학년 5종목',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 14,
        'name' => '솔로 일반부 라틴 2종목 (C R)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 15,
        'name' => '솔로 일반부 라틴 2종목 (S R)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 16,
        'name' => '솔로 고등부 라틴 3종목 (C R J)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 17,
        'name' => '솔로 대학부 스탠다드 5종목',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 18,
        'name' => '프로-암 클로즈 라틴 1종목 (R)',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 19,
        'name' => '프로-암 오픈 라틴 1종목 (C)',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 20,
        'name' => '프로-암 오픈 라틴 1종목 (R)',
        'round' => 'Semi-Final',
        'status' => 'completed',
        'results' => [
            ['rank' => 1, 'players' => ['선수 25', '선수 264']]
        ]
    ],
    [
        'id' => 21,
        'name' => '프로-암 클로즈 라틴 1종목 (J)',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 22,
        'name' => '프로-암 오픈 라틴 2종목 (C R)',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 23,
        'name' => '프로-암 오픈 스탠다드 5종목',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 24,
        'name' => '프로-암 오픈 라틴 3종목 (CRC)',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 25,
        'name' => '듀오 라틴 3종목',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 26,
        'name' => '트리오 라틴 2종목(S R)',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 27,
        'name' => '매니아 스탠다드 3종목 (W T SF)',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 28,
        'name' => '프로페셔널 라틴',
        'round' => 'Final',
        'status' => 'completed',
        'created' => '2025-09-24 23:50:27',
        'reports' => ['detail', 'recall', 'combined']
    ],
    [
        'id' => 29,
        'name' => '포메이션A조',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 30,
        'name' => '프로페셔널 라틴',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 31,
        'name' => '프로페셔널 스탠다드',
        'round' => 'Semi-Final',
        'status' => 'completed',
        'reports' => ['detail', 'recall', 'combined']
    ],
    [
        'id' => 32,
        'name' => '아마추어 라틴 S',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 33,
        'name' => '매니아 스탠다드 2종목 (W T)',
        'round' => 'Semi-Final',
        'status' => 'processing'
    ],
    [
        'id' => 34,
        'name' => '듀오 라틴 3종목',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 35,
        'name' => '일반부 스탠다드 2종목 (W T)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 36,
        'name' => '트리오 라틴 2종목(S R)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 37,
        'name' => '매니아 스탠다드 2종목 (W T)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 38,
        'name' => '프로-암 클로즈 라틴 1종목 (R)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 39,
        'name' => '아빠랑 딸이랑 (W)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 40,
        'name' => '프로-암 오픈 라틴 1종목 (C)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 41,
        'name' => '아빠랑 딸이랑 (C)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 42,
        'name' => '프로-암 오픈 라틴 1종목 (R)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 43,
        'name' => '프로-암 클로즈 라틴 1종목 (J)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 44,
        'name' => '프로-암 오픈 라틴 2종목 (C R)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 45,
        'name' => '프로-암 오픈 스탠다드 5종목',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 46,
        'name' => '프로-암 오픈 라틴 3종목 (CRC)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 47,
        'name' => '매니아 스탠다드 3종목 (W T SF)',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 48,
        'name' => '프로페셔널 스탠다드 마스터클래스',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 49,
        'name' => '그랜드시니어 스탠다드 3종목',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 50,
        'name' => '아마추어 라틴 S',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 51,
        'name' => '프로페셔널 스탠다드',
        'round' => 'Final',
        'status' => 'processing'
    ],
    [
        'id' => 52,
        'name' => '프로페셔널 라틴',
        'round' => 'Final',
        'status' => 'processing'
    ]
];

// 페이지별 처리
switch ($page) {
    case 'results':
        include 'competition_results.php';
        break;
    case 'overview':
    default:
        include 'competition_overview.php';
        break;
}
?>
