<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradingConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'class_ids',
        'grades',
        'is_active',
        'is_default',
        'description',
    ];

    protected $casts = [
        'class_ids' => 'array',
        'grades' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the default grading configuration
     */
    public static function default()
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Get grading configuration for a specific class
     */
    public static function forClass($classId)
    {
        // First try to find a specific configuration for this class
        $config = static::where('is_active', true)
                       ->whereJsonContains('class_ids', (int)$classId)
                       ->first();

        // If not found, use default
        if (!$config) {
            $config = static::default();
        }

        return $config;
    }

    /**
     * Calculate grade based on score
     */
    public function calculateGrade($score)
    {
        if (!$this->grades || !is_array($this->grades)) {
            // Fallback to default grading if no grades configured
            return $this->defaultGrade($score);
        }

        foreach ($this->grades as $gradeConfig) {
            $min = $gradeConfig['min'] ?? 0;
            $max = $gradeConfig['max'] ?? 100;
            
            if ($score >= $min && $score <= $max) {
                return [
                    'grade' => $gradeConfig['grade'] ?? 'F',
                    'remark' => $gradeConfig['remark'] ?? '',
                ];
            }
        }

        // Fallback
        return $this->defaultGrade($score);
    }

    /**
     * Default grade calculation (fallback)
     */
    private function defaultGrade($score)
    {
        if ($score >= 80) {
            return ['grade' => 'A', 'remark' => 'Excellent'];
        } elseif ($score >= 70) {
            return ['grade' => 'B', 'remark' => 'Very Good'];
        } elseif ($score >= 60) {
            return ['grade' => 'C', 'remark' => 'Good'];
        } elseif ($score >= 50) {
            return ['grade' => 'D', 'remark' => 'Fair'];
        } elseif ($score >= 40) {
            return ['grade' => 'E', 'remark' => 'Pass'];
        } else {
            return ['grade' => 'F', 'remark' => 'Fail'];
        }
    }

    /**
     * Get grade color (for UI)
     */
    public static function getGradeColor($grade)
    {
        $colors = [
            'A' => 'text-green-800 bg-green-100',
            'B' => 'text-blue-700 bg-blue-50',
            'C' => 'text-yellow-700 bg-yellow-50',
            'D' => 'text-orange-700 bg-orange-50',
            'E' => 'text-purple-700 bg-purple-50',
            'F' => 'text-red-700 bg-red-50',
        ];

        return $colors[$grade] ?? 'text-gray-700 bg-gray-50';
    }
}

