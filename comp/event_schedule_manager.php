<?php
/**
 * 대회 순서 및 이벤트 관리 클래스
 */

class EventScheduleManager {
    private $scheduleFile;
    private $schedule;
    
    public function __construct($scheduleFile = __DIR__ . "/event_schedule.txt") {
        $this->scheduleFile = $scheduleFile;
        $this->loadSchedule();
    }
    
    /**
     * 스케줄 파일 로드
     */
    private function loadSchedule() {
        $this->schedule = [];
        
        if (file_exists($this->scheduleFile)) {
            $lines = file($this->scheduleFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                $parts = explode(',', $line, 3);
                if (count($parts) >= 3) {
                    $this->schedule[] = [
                        'event_id' => trim($parts[0]),
                        'event_name' => trim($parts[1]),
                        'show_result' => trim($parts[2]) === 'true'
                    ];
                }
            }
        }
    }
    
    /**
     * 전체 스케줄 반환
     */
    public function getSchedule() {
        return $this->schedule;
    }
    
    /**
     * 현재 이벤트 정보 반환
     */
    public function getCurrentEvent($eventId) {
        foreach ($this->schedule as $event) {
            if ($event['event_id'] === $eventId) {
                return $event;
            }
        }
        return null;
    }
    
    /**
     * 다음 이벤트 정보 반환
     */
    public function getNextEvent($currentEventId) {
        $currentIndex = -1;
        foreach ($this->schedule as $index => $event) {
            if ($event['event_id'] === $currentEventId) {
                $currentIndex = $index;
                break;
            }
        }
        
        if ($currentIndex >= 0 && $currentIndex < count($this->schedule) - 1) {
            return $this->schedule[$currentIndex + 1];
        }
        
        return null;
    }
    
    /**
     * 이전 이벤트 정보 반환
     */
    public function getPreviousEvent($currentEventId) {
        $currentIndex = -1;
        foreach ($this->schedule as $index => $event) {
            if ($event['event_id'] === $currentEventId) {
                $currentIndex = $index;
                break;
            }
        }
        
        if ($currentIndex > 0) {
            return $this->schedule[$currentIndex - 1];
        }
        
        return null;
    }
    
    /**
     * 이벤트 표시 여부 업데이트
     */
    public function updateEventVisibility($eventId, $showResult) {
        foreach ($this->schedule as &$event) {
            if ($event['event_id'] === $eventId) {
                $event['show_result'] = $showResult;
                break;
            }
        }
        
        $this->saveSchedule();
    }
    
    /**
     * 스케줄 파일 저장
     */
    private function saveSchedule() {
        $lines = [];
        foreach ($this->schedule as $event) {
            $lines[] = $event['event_id'] . ',' . $event['event_name'] . ',' . ($event['show_result'] ? 'true' : 'false');
        }
        
        file_put_contents($this->scheduleFile, implode("\n", $lines));
    }
    
    /**
     * 현재 진행 중인 이벤트 인덱스 반환
     */
    public function getCurrentEventIndex($eventId) {
        foreach ($this->schedule as $index => $event) {
            if ($event['event_id'] === $eventId) {
                return $index;
            }
        }
        return 0;
    }
    
    /**
     * 전체 이벤트 수 반환
     */
    public function getTotalEvents() {
        return count($this->schedule);
    }
}
?>




