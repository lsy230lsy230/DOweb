<?php
/**
 * Skating System for DanceSport Competition Scoring
 * Based on the official rules adopted by the Official Board of Ballroom Dancing
 */

class SkatingSystem {
    
    /**
     * Calculate majority threshold
     * @param int $numAdjudicators Number of adjudicators
     * @return int Majority threshold
     */
    public static function getMajority($numAdjudicators) {
        return floor($numAdjudicators / 2) + 1;
    }
    
    /**
     * Calculate results for a single dance
     * @param array $adjudicatorMarks Array of adjudicator marks [adjudicator_id => [couple_id => place]]
     * @return array Results with places and detailed calculations
     */
    public static function calculateSingleDance($adjudicatorMarks) {
        $couples = [];
        $numAdjudicators = count($adjudicatorMarks);
        $majority = self::getMajority($numAdjudicators);
        
        // Get all couples
        foreach ($adjudicatorMarks as $adjudicator => $marks) {
            foreach ($marks as $coupleId => $place) {
                if (!isset($couples[$coupleId])) {
                    $couples[$coupleId] = [
                        'id' => $coupleId,
                        'places' => [],
                        'place_counts' => [],
                        'total_marks' => 0
                    ];
                }
            }
        }
        
        // Collect all places for each couple
        foreach ($adjudicatorMarks as $adjudicator => $marks) {
            foreach ($marks as $coupleId => $place) {
                $couples[$coupleId]['places'][] = $place;
                $couples[$coupleId]['total_marks'] += $place;
            }
        }
        
        // Count places for each couple
        foreach ($couples as $coupleId => &$couple) {
            $placeCounts = array_count_values($couple['places']);
            for ($i = 1; $i <= count($couples); $i++) {
                $couple['place_counts'][$i] = isset($placeCounts[$i]) ? $placeCounts[$i] : 0;
            }
        }
        
        // Calculate places using Skating System rules
        $results = [];
        $remainingCouples = array_keys($couples);
        $currentPlace = 1;
        
        while (!empty($remainingCouples)) {
            $placeResult = self::determinePlace($remainingCouples, $couples, $majority, $currentPlace);
            
            if (count($placeResult['couples']) == 1) {
                // Single couple gets this place
                $results[$placeResult['couples'][0]] = $currentPlace;
                $remainingCouples = array_diff($remainingCouples, $placeResult['couples']);
                $currentPlace++;
            } else {
                // Tie - multiple couples share this place
                foreach ($placeResult['couples'] as $coupleId) {
                    $results[$coupleId] = $currentPlace;
                }
                $remainingCouples = array_diff($remainingCouples, $placeResult['couples']);
                $currentPlace += count($placeResult['couples']);
            }
        }
        
        return [
            'results' => $results,
            'couples' => $couples,
            'majority' => $majority,
            'num_adjudicators' => $numAdjudicators
        ];
    }
    
    /**
     * Determine which couples get a specific place
     * @param array $remainingCouples Couples still to be placed
     * @param array $couples Couple data
     * @param int $majority Majority threshold
     * @param int $targetPlace Place being determined
     * @return array Couples that get this place
     */
    private static function determinePlace($remainingCouples, $couples, $majority, $targetPlace) {
        $candidates = [];
        
        // Rule 1: Check for majority of 1st places
        if ($targetPlace == 1) {
            foreach ($remainingCouples as $coupleId) {
                if ($couples[$coupleId]['place_counts'][1] >= $majority) {
                    $candidates[] = $coupleId;
                }
            }
            if (!empty($candidates)) {
                return ['couples' => $candidates, 'rule' => 'majority_1st'];
            }
        }
        
        // Rule 2: Check for majority of 1st+2nd places
        if ($targetPlace <= 2) {
            foreach ($remainingCouples as $coupleId) {
                $firstSecond = $couples[$coupleId]['place_counts'][1] + $couples[$coupleId]['place_counts'][2];
                if ($firstSecond >= $majority) {
                    $candidates[] = $coupleId;
                }
            }
            if (!empty($candidates)) {
                // If multiple couples, use total marks to break tie
                usort($candidates, function($a, $b) use ($couples) {
                    return $couples[$a]['total_marks'] - $couples[$b]['total_marks'];
                });
                
                // Check if there's still a tie
                $lowestTotal = $couples[$candidates[0]]['total_marks'];
                $finalCandidates = [];
                foreach ($candidates as $coupleId) {
                    if ($couples[$coupleId]['total_marks'] == $lowestTotal) {
                        $finalCandidates[] = $coupleId;
                    }
                }
                
                return ['couples' => $finalCandidates, 'rule' => 'majority_1st_2nd'];
            }
        }
        
        // Rule 3: Continue checking 1st+2nd+3rd, etc.
        for ($place = 1; $place <= count($remainingCouples); $place++) {
            $totalCount = 0;
            foreach ($remainingCouples as $coupleId) {
                for ($i = 1; $i <= $place; $i++) {
                    $totalCount += $couples[$coupleId]['place_counts'][$i];
                }
            }
            
            if ($totalCount >= $majority) {
                foreach ($remainingCouples as $coupleId) {
                    $totalCount = 0;
                    for ($i = 1; $i <= $place; $i++) {
                        $totalCount += $couples[$coupleId]['place_counts'][$i];
                    }
                    if ($totalCount >= $majority) {
                        $candidates[] = $coupleId;
                    }
                }
                
                if (!empty($candidates)) {
                    // Use total marks to break tie
                    usort($candidates, function($a, $b) use ($couples) {
                        return $couples[$a]['total_marks'] - $couples[$b]['total_marks'];
                    });
                    
                    $lowestTotal = $couples[$candidates[0]]['total_marks'];
                    $finalCandidates = [];
                    foreach ($candidates as $coupleId) {
                        if ($couples[$coupleId]['total_marks'] == $lowestTotal) {
                            $finalCandidates[] = $coupleId;
                        }
                    }
                    
                    return ['couples' => $finalCandidates, 'rule' => "majority_1st_to_{$place}th"];
                }
            }
        }
        
        // Fallback: Use total marks
        usort($remainingCouples, function($a, $b) use ($couples) {
            return $couples[$a]['total_marks'] - $couples[$b]['total_marks'];
        });
        
        $lowestTotal = $couples[$remainingCouples[0]]['total_marks'];
        $finalCandidates = [];
        foreach ($remainingCouples as $coupleId) {
            if ($couples[$coupleId]['total_marks'] == $lowestTotal) {
                $finalCandidates[] = $coupleId;
            }
        }
        
        return ['couples' => $finalCandidates, 'rule' => 'total_marks'];
    }
    
    /**
     * Calculate results for multiple dance event (Rule 11)
     * @param array $danceResults Array of single dance results
     * @param array $adjudicatorMarks All adjudicator marks for all dances
     * @return array Final results
     */
    public static function calculateMultipleDance($danceResults, $adjudicatorMarks) {
        $couples = [];
        $numDances = count($danceResults);
        $numAdjudicators = count($adjudicatorMarks[0] ?? []);
        $totalMarks = $numDances * $numAdjudicators;
        $majority = floor($totalMarks / 2) + 1;
        
        // Calculate final summary (sum of places)
        foreach ($danceResults as $dance => $results) {
            foreach ($results['results'] as $coupleId => $place) {
                if (!isset($couples[$coupleId])) {
                    $couples[$coupleId] = [
                        'id' => $coupleId,
                        'final_summary' => 0,
                        'place_counts' => [],
                        'total_marks' => 0
                    ];
                }
                $couples[$coupleId]['final_summary'] += $place;
            }
        }
        
        // Sort by final summary
        uasort($couples, function($a, $b) {
            return $a['final_summary'] - $b['final_summary'];
        });
        
        // Check for ties in final summary
        $finalResults = [];
        $currentPlace = 1;
        $groupedCouples = [];
        
        $prevSummary = null;
        $currentGroup = [];
        
        foreach ($couples as $coupleId => $couple) {
            if ($prevSummary === null || $couple['final_summary'] == $prevSummary) {
                $currentGroup[] = $coupleId;
            } else {
                if (count($currentGroup) > 1) {
                    $groupedCouples[] = $currentGroup;
                } else {
                    $finalResults[$currentGroup[0]] = $currentPlace;
                    $currentPlace++;
                }
                $currentGroup = [$coupleId];
            }
            $prevSummary = $couple['final_summary'];
        }
        
        // Handle last group
        if (count($currentGroup) > 1) {
            $groupedCouples[] = $currentGroup;
        } else {
            $finalResults[$currentGroup[0]] = $currentPlace;
        }
        
        // Apply Rule 11 for tied couples
        foreach ($groupedCouples as $tiedCouples) {
            $rule11Result = self::applyRule11($tiedCouples, $adjudicatorMarks, $majority);
            foreach ($rule11Result as $coupleId => $place) {
                $finalResults[$coupleId] = $currentPlace + $place - 1;
            }
            $currentPlace += count($tiedCouples);
        }
        
        return [
            'results' => $finalResults,
            'couples' => $couples,
            'majority' => $majority,
            'total_marks' => $totalMarks
        ];
    }
    
    /**
     * Apply Rule 11 for tied couples
     * @param array $tiedCouples Couples that are tied
     * @param array $adjudicatorMarks All adjudicator marks
     * @param int $majority Majority threshold
     * @return array Rule 11 results
     */
    private static function applyRule11($tiedCouples, $adjudicatorMarks, $majority) {
        $couples = [];
        
        // Initialize couples
        foreach ($tiedCouples as $coupleId) {
            $couples[$coupleId] = [
                'id' => $coupleId,
                'place_counts' => [],
                'total_marks' => 0
            ];
        }
        
        // Collect all marks across all dances
        foreach ($adjudicatorMarks as $dance => $adjudicators) {
            foreach ($adjudicators as $adjudicator => $marks) {
                foreach ($marks as $coupleId => $place) {
                    if (in_array($coupleId, $tiedCouples)) {
                        $couples[$coupleId]['place_counts'][$place] = 
                            ($couples[$coupleId]['place_counts'][$place] ?? 0) + 1;
                        $couples[$coupleId]['total_marks'] += $place;
                    }
                }
            }
        }
        
        // Apply same logic as single dance
        $results = [];
        $remainingCouples = $tiedCouples;
        $currentPlace = 1;
        
        while (!empty($remainingCouples)) {
            $placeResult = self::determinePlace($remainingCouples, $couples, $majority, $currentPlace);
            
            if (count($placeResult['couples']) == 1) {
                $results[$placeResult['couples'][0]] = $currentPlace;
                $remainingCouples = array_diff($remainingCouples, $placeResult['couples']);
                $currentPlace++;
            } else {
                foreach ($placeResult['couples'] as $coupleId) {
                    $results[$coupleId] = $currentPlace;
                }
                $remainingCouples = array_diff($remainingCouples, $placeResult['couples']);
                $currentPlace += count($placeResult['couples']);
            }
        }
        
        return $results;
    }
}
?>
