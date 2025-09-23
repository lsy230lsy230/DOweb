<?php
/**
 * 이벤트 그룹 관리 클래스
 */

class EventGroupManager {
    
    /**
     * 이벤트를 그룹별로 분류
     */
    public static function groupEvents($events) {
        $groups = [];
        
        foreach ($events as $event) {
            // 이벤트 ID에서 숫자 부분 추출 (예: 1A -> 1, 2B -> 2)
            $eventNumber = preg_replace('/[^0-9]/', '', $event['id']);
            
            if (!isset($groups[$eventNumber])) {
                $groups[$eventNumber] = [];
            }
            
            $groups[$eventNumber][] = $event;
        }
        
        // 숫자 순으로 정렬
        ksort($groups);
        
        return $groups;
    }
    
    /**
     * 특정 그룹의 이벤트들 반환
     */
    public static function getGroupEvents($events, $groupNumber) {
        $groupEvents = [];
        
        foreach ($events as $event) {
            $eventNumber = preg_replace('/[^0-9]/', '', $event['id']);
            if ($eventNumber == $groupNumber) {
                $groupEvents[] = $event;
            }
        }
        
        return $groupEvents;
    }
    
    /**
     * 그룹별 이벤트 수 반환
     */
    public static function getGroupCount($events) {
        $groups = self::groupEvents($events);
        return count($groups);
    }
    
    /**
     * 현재 그룹 인덱스 계산
     */
    public static function getCurrentGroupIndex($events, $currentEventId) {
        $currentEventNumber = preg_replace('/[^0-9]/', '', $currentEventId);
        $groups = self::groupEvents($events);
        $groupNumbers = array_keys($groups);
        
        return array_search($currentEventNumber, $groupNumbers);
    }
}
?>




