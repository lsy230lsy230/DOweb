# DanceOffice - 댄스스포츠 대회 관리 시스템

댄스스코어와 자체 개발 시스템을 통합한 댄스스포츠 대회 관리 및 결과 제공 웹사이트입니다.

## 🎯 주요 기능

### 📅 통합 스케줄러 시스템
- 댄스스코어와 자체 시스템 결과 통합 관리
- 대회 상태 자동 관리 (upcoming, ongoing, completed)
- JSON 기반 유연한 대회 정보 저장

### 🏠 메인페이지 카드 뉴스
- 다가오는 대회 카드 (최대 3개)
- 최근 완료된 대회 결과 카드 (최대 2개)
- 직관적인 네비게이션으로 바로 결과 확인

### 📊 종합결과 페이지
- **주간 현황**: 지난주, 이번주, 다음주 대회 구분 표시
- **연도별 목록**: 과거 대회 내역을 연도별로 확인
- **타임라인 형태**: 시간순 정렬로 대회 이력 관리

### 🎨 반응형 UI/UX
- **PC 버전**: 좌측 네비게이션 + 메인 콘텐츠 + 우측 광고
- **모바일 버전**: 상단 헤더 + 메인 콘텐츠 + 하단 네비게이션
- **일관된 디자인**: 모든 페이지에서 통일된 사용자 경험

## 🛠️ 기술 스택

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, CSS3, JavaScript
- **Database**: JSON 파일 기반
- **Integration**: 댄스스코어 시스템 연동
- **Responsive**: CSS Grid & Flexbox

## 📁 프로젝트 구조

```
Y:/
├── index.php              # 메인 페이지
├── assets/                # CSS, 이미지, 로고
├── data/                  # 데이터 파일
│   ├── scheduler.php      # 스케줄러 시스템
│   ├── competitions.json  # 대회 정보 (예시)
│   ├── notice.txt         # 공지사항
│   └── schedule.txt       # 일정
├── results/               # 경기 결과 (공개)
│   ├── index.php          # 결과 메인 페이지
│   └── results/           # 결과 파일들
├── comprehensive/         # 종합결과 페이지
│   └── index.php          # 종합결과 메인
├── notice/                # 공지사항 (공개)
├── manage/                # 관리자 페이지
├── comp/                  # 채점 시스템 (비공개)
├── recall/                # 리콜 시스템
├── 04_scripts/            # 유틸리티 스크립트
├── 05_documents/          # 문서 파일
├── robots.txt             # 검색엔진 설정
└── sitemap.xml            # 사이트맵
```

## 🚀 설치 및 설정

### 1. 웹서버 설정
- PHP 7.4 이상
- 웹 루트를 프로젝트 폴더로 설정

### 2. 권한 설정
```bash
chmod 755 data/
chmod 644 data/*.json
chmod 755 comp/
```

### 3. 대회 데이터 설정
`data/competitions.json` 파일에 대회 정보를 추가하세요:

```json
{
    "id": "comp_2025_001",
    "title": "대회명",
    "subtitle": "부제목",
    "date": "2025-09-14",
    "place": "장소",
    "status": "upcoming",
    "dancescore_id": "ds_2025_001",
    "our_system_id": "comp_2025_001",
    "results_url": "/results/results/"
}
```

## 📱 사용법

### 메인페이지
- 다가오는 대회 카드에서 "대회 정보" 클릭
- 최근 대회 카드에서 "결과 보기" 클릭

### 종합결과 페이지
- 주간 현황에서 각 주별 대회 확인
- 연도별 목록에서 과거 대회 내역 검색

### 관리자 기능
- `/manage/` 경로에서 대회 관리
- 스케줄러 시스템을 통한 대회 추가/수정

## 🔧 개발 정보

### 댄스스코어 연동
- 기존 결과 파일 형식 그대로 유지
- `results/results/` 폴더의 HTML/PDF 파일 활용
- 댄스스코어 결과 형식에 맞춰 표시

### 자체 시스템 통합
- `comp/` 폴더의 채점 시스템 보호
- 새로운 스케줄러로 대회 관리
- 두 시스템의 결과를 통합 인터페이스로 제공

## 📄 라이선스

이 프로젝트는 댄스스포츠 대회 관리 목적으로 개발되었습니다.

## 👨‍💻 개발자

- **개발**: Seyoung Lee
- **시스템**: DanceOffice
- **연동**: 댄스스코어 + 자체 개발 시스템

## 🔗 관련 링크

- [댄스스코어](http://www.dancescorelive.net)
- [DanceOffice](https://www.danceoffice.net)

---

**© 2025 DanceOffice | Powered by Seyoung Lee**
