<?php
/**
 * 대회 스케줄러 시스템
 * 댄스스코어와 자체 시스템을 통합한 대회 관리
 */

class CompetitionScheduler {
    private $scheduleFile;
    private $competitions;
    private $compDataDir;
    
    public function __construct() {
        $this->scheduleFile = __DIR__ . '/competitions.json';
        $this->compDataDir = __DIR__ . '/../comp/data';
        $this->loadCompetitions();
    }
    
    private function loadCompetitions() {
        // 기존 competitions.json에서 로드
        if (file_exists($this->scheduleFile)) {
            $this->competitions = json_decode(file_get_contents($this->scheduleFile), true) ?: [];
        } else {
            $this->competitions = [];
        }
        
        // comp/data에서 실제 대회 정보 추가
        $this->loadCompetitionsFromCompData();
    }
    
    private function loadCompetitionsFromCompData() {
        if (!is_dir($this->compDataDir)) return;
        
        foreach (glob($this->compDataDir . "/*/info.json") as $info_file) {
            $comp_id = basename(dirname($info_file));
            $info = json_decode(file_get_contents($info_file), true);
            
            if ($info) {
                // 이미 존재하는지 확인 (our_system_id로)
                $exists = false;
                foreach ($this->competitions as $existing) {
                    if (isset($existing['our_system_id']) && $existing['our_system_id'] === $comp_id) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    // comp/data의 정보를 스케줄러 형식으로 변환
                    $competition = [
                        'id' => 'comp_' . $comp_id,
                        'name' => $info['title'],
                        'title' => $info['title'],
                        'subtitle' => '',
                        'date' => $info['date'],
                        'location' => $info['place'],
                        'place' => $info['place'],
                        'host' => $info['host'],
                        'description' => $info['host'] . ' 주최',
                        'status' => $this->getCompetitionStatus($info['date']),
                        'our_system_id' => $comp_id,
                        'comp_data_path' => dirname($info_file),
                        'created_at' => date('Y-m-d H:i:s', $info['created']),
                        'updated_at' => date('Y-m-d H:i:s', $info['created'])
                    ];
                    
                    $this->competitions[] = $competition;
                }
            }
        }
    }
    
    public function saveCompetitions() {
        file_put_contents($this->scheduleFile, json_encode($this->competitions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    public function addCompetition($data) {
        $competition = [
            'id' => uniqid('comp_'),
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? '',
            'date' => $data['date'],
            'place' => $data['place'],
            'status' => $data['status'] ?? 'upcoming', // upcoming, ongoing, completed
            'dancescore_id' => $data['dancescore_id'] ?? null,
            'our_system_id' => $data['our_system_id'] ?? null,
            'results_url' => $data['results_url'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->competitions[] = $competition;
        $this->saveCompetitions();
        return $competition['id'];
    }
    
    public function updateCompetition($id, $data) {
        foreach ($this->competitions as &$comp) {
            if ($comp['id'] === $id) {
                $comp = array_merge($comp, $data);
                $comp['updated_at'] = date('Y-m-d H:i:s');
                $this->saveCompetitions();
                return true;
            }
        }
        return false;
    }
    
    public function getCompetitionsByDateRange($startDate, $endDate) {
        return array_filter($this->competitions, function($comp) use ($startDate, $endDate) {
            return $comp['date'] >= $startDate && $comp['date'] <= $endDate;
        });
    }
    
    public function getUpcomingCompetitions($limit = 3) {
        $today = date('Y-m-d');
        $upcoming = array_filter($this->competitions, function($comp) use ($today) {
            return $comp['date'] >= $today && $comp['status'] === 'upcoming';
        });
        
        usort($upcoming, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        return array_slice($upcoming, 0, $limit);
    }
    
    public function getRecentCompetitions($limit = 3) {
        $today = date('Y-m-d');
        $recent = array_filter($this->competitions, function($comp) use ($today) {
            return $comp['date'] < $today && $comp['status'] === 'completed';
        });
        
        usort($recent, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return array_slice($recent, 0, $limit);
    }
    
    public function getCompetitionsByYear($year) {
        return array_filter($this->competitions, function($comp) use ($year) {
            return date('Y', strtotime($comp['date'])) == $year;
        });
    }
    
    public function getCompetitionById($id) {
        foreach ($this->competitions as $comp) {
            if ($comp['id'] === $id) {
                return $comp;
            }
        }
        return null;
    }
    
    public function getAllCompetitions() {
        return $this->competitions;
    }
    
    public function getCompetitionStatus($date) {
        $today = date('Y-m-d');
        $compDate = date('Y-m-d', strtotime($date));
        
        if ($compDate < $today) {
            return 'completed';
        } elseif ($compDate == $today) {
            return 'ongoing';
        } else {
            return 'upcoming';
        }
    }
}

// 사용 예시
if (isset($_GET['action'])) {
    $scheduler = new CompetitionScheduler();
    
    switch ($_GET['action']) {
        case 'add':
            if ($_POST) {
                $id = $scheduler->addCompetition($_POST);
                echo json_encode(['success' => true, 'id' => $id]);
            }
            break;
            
        case 'update':
            if ($_POST && isset($_POST['id'])) {
                $success = $scheduler->updateCompetition($_POST['id'], $_POST);
                echo json_encode(['success' => $success]);
            }
            break;
            
        case 'upcoming':
            $competitions = $scheduler->getUpcomingCompetitions();
            echo json_encode($competitions);
            break;
            
        case 'recent':
            $competitions = $scheduler->getRecentCompetitions();
            echo json_encode($competitions);
            break;
            
        case 'year':
            $year = $_GET['year'] ?? date('Y');
            $competitions = $scheduler->getCompetitionsByYear($year);
            echo json_encode($competitions);
            break;
    }
}
?>
