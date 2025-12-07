<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromotionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'criteria',
        'is_active',
        'description',
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the active promotion rule
     */
    public static function active()
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Check if a student meets promotion criteria
     */
    public function checkPromotionEligibility(Student $student, $sessionResults)
    {
        switch ($this->type) {
            case 'all_promote':
                return true;
                
            case 'minimum_grades':
                $minA = $this->criteria['min_a_count'] ?? 0;
                $minB = $this->criteria['min_b_count'] ?? 0;
                $minC = $this->criteria['min_c_count'] ?? 0;
                
                $gradeCounts = $this->countGrades($sessionResults);
                
                return ($gradeCounts['A'] >= $minA) && 
                       ($gradeCounts['B'] >= $minB) && 
                       ($gradeCounts['C'] >= $minC);
                       
            case 'minimum_average':
                $minAverage = $this->criteria['min_average'] ?? 50;
                $average = $this->calculateAverage($sessionResults);
                return $average >= $minAverage;
                
            case 'minimum_subjects_passed':
                $minPassed = $this->criteria['min_passed'] ?? 5;
                $passedCount = $this->countPassedSubjects($sessionResults);
                return $passedCount >= $minPassed;
                
            default:
                return false;
        }
    }

    private function countGrades($sessionResults)
    {
        $counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
        foreach ($sessionResults as $result) {
            $grade = $result['grade'] ?? 'F';
            if (isset($counts[$grade])) {
                $counts[$grade]++;
            }
        }
        return $counts;
    }

    private function calculateAverage($sessionResults)
    {
        if (empty($sessionResults)) return 0;
        $total = 0;
        $count = 0;
        foreach ($sessionResults as $result) {
            $total += $result['total_score'] ?? 0;
            $count++;
        }
        return $count > 0 ? $total / $count : 0;
    }

    private function countPassedSubjects($sessionResults)
    {
        $passed = 0;
        foreach ($sessionResults as $result) {
            $grade = $result['grade'] ?? 'F';
            if (in_array($grade, ['A', 'B', 'C'])) {
                $passed++;
            }
        }
        return $passed;
    }
}

