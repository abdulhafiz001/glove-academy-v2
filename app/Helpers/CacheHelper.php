<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Cache TTL constants (in seconds)
     */
    const DASHBOARD_TTL = 300; // 5 minutes
    const STATIC_DATA_TTL = 3600; // 1 hour
    const SESSION_DATA_TTL = 1800; // 30 minutes
    const SCORES_TTL = 600; // 10 minutes
    const POSITIONS_TTL = 1800; // 30 minutes
    const CURRENT_SESSION_TTL = 300; // 5 minutes

    /**
     * Cache key prefixes
     */
    const DASHBOARD_PREFIX = 'dashboard_';
    const CLASSES_PREFIX = 'classes_';
    const SUBJECTS_PREFIX = 'subjects_';
    const SESSIONS_PREFIX = 'sessions_';
    const STATS_PREFIX = 'stats_';
    const TEACHER_ASSIGNMENTS_PREFIX = 'teacher_assignments_';
    const STUDENT_SCORES_PREFIX = 'student_scores_';
    const POSITIONS_PREFIX = 'positions_';
    const CURRENT_SESSION_KEY = 'current_session_term';
    const SESSION_STATUS_UPDATE_KEY = 'session_status_last_update';

    /**
     * Get cached dashboard data or compute and cache it
     */
    public static function getDashboardStats($role, $userId, callable $callback)
    {
        $cacheKey = self::DASHBOARD_PREFIX . $role . '_' . $userId;
        
        // Track dashboard keys for non-Redis invalidation
        if (config('cache.default') !== 'redis') {
            $keys = Cache::get('dashboard_cache_keys', []);
            if (!in_array($cacheKey, $keys)) {
                $keys[] = $cacheKey;
                Cache::put('dashboard_cache_keys', $keys, 86400); // Store for 24 hours
            }
        }
        
        // Use tags if Redis, otherwise regular cache
        if (config('cache.default') === 'redis') {
            return Cache::tags(['dashboard', 'user_' . $userId])
                ->remember($cacheKey, self::DASHBOARD_TTL, $callback);
        }
        
        return Cache::remember($cacheKey, self::DASHBOARD_TTL, $callback);
    }

    /**
     * Invalidate dashboard cache for a role/user
     */
    public static function invalidateDashboard($role = null, $userId = null)
    {
        if ($role && $userId) {
            $cacheKey = self::DASHBOARD_PREFIX . $role . '_' . $userId;
            Cache::forget($cacheKey);
            
            // If using Redis with tags, also invalidate by tag
            if (config('cache.default') === 'redis') {
                Cache::tags(['dashboard', 'user_' . $userId])->flush();
            }
        } else {
            // Invalidate all dashboard caches using tags if Redis, otherwise track keys
            if (config('cache.default') === 'redis') {
                Cache::tags(['dashboard'])->flush();
            } else {
                // For non-Redis, we need to track dashboard keys
                // Get all dashboard cache keys from a tracked list
                $dashboardKeys = Cache::get('dashboard_cache_keys', []);
                foreach ($dashboardKeys as $key) {
                    Cache::forget($key);
                }
                Cache::forget('dashboard_cache_keys');
            }
        }
    }

    /**
     * Get cached classes or compute and cache
     */
    public static function getClasses(callable $callback)
    {
        $cacheKey = self::CLASSES_PREFIX . 'all';
        return Cache::remember($cacheKey, self::STATIC_DATA_TTL, $callback);
    }

    /**
     * Invalidate classes cache
     */
    public static function invalidateClasses()
    {
        Cache::forget(self::CLASSES_PREFIX . 'all');
        // Also invalidate dashboard as it includes classes
        self::invalidateDashboard();
    }

    /**
     * Get cached subjects or compute and cache
     */
    public static function getSubjects(callable $callback)
    {
        $cacheKey = self::SUBJECTS_PREFIX . 'all';
        return Cache::remember($cacheKey, self::STATIC_DATA_TTL, $callback);
    }

    /**
     * Invalidate subjects cache
     */
    public static function invalidateSubjects()
    {
        Cache::forget(self::SUBJECTS_PREFIX . 'all');
        self::invalidateDashboard();
    }

    /**
     * Get cached sessions or compute and cache
     */
    public static function getSessions(callable $callback)
    {
        $cacheKey = self::SESSIONS_PREFIX . 'all';
        return Cache::remember($cacheKey, self::SESSION_DATA_TTL, $callback);
    }

    /**
     * Invalidate sessions cache
     */
    public static function invalidateSessions()
    {
        Cache::forget(self::SESSIONS_PREFIX . 'all');
        Cache::forget(self::SESSIONS_PREFIX . 'current');
        self::invalidateDashboard();
    }

    /**
     * Get cached teacher assignments or compute and cache
     */
    public static function getTeacherAssignments($teacherId, callable $callback)
    {
        $cacheKey = self::TEACHER_ASSIGNMENTS_PREFIX . $teacherId;
        
        if (config('cache.default') === 'redis') {
            return Cache::tags(['teacher_assignments', 'teacher_' . $teacherId])
                ->remember($cacheKey, self::STATIC_DATA_TTL, $callback);
        }
        
        return Cache::remember($cacheKey, self::STATIC_DATA_TTL, $callback);
    }

    /**
     * Invalidate teacher assignments cache
     */
    public static function invalidateTeacherAssignments($teacherId = null)
    {
        if ($teacherId) {
            $cacheKey = self::TEACHER_ASSIGNMENTS_PREFIX . $teacherId;
            Cache::forget($cacheKey);
            
            if (config('cache.default') === 'redis') {
                Cache::tags(['teacher_assignments', 'teacher_' . $teacherId])->flush();
            }
        } else {
            // Invalidate all teacher assignments
            if (config('cache.default') === 'redis') {
                Cache::tags(['teacher_assignments'])->flush();
            }
        }
    }

    /**
     * Get cached student scores
     */
    public static function getStudentScores($studentId, $academicSessionId, $subjectId = null, $term = null, callable $callback)
    {
        $cacheKey = self::STUDENT_SCORES_PREFIX . $studentId . '_' . $academicSessionId;
        if ($subjectId) {
            $cacheKey .= '_subject_' . $subjectId;
        }
        if ($term) {
            $cacheKey .= '_term_' . $term;
        }
        
        if (config('cache.default') === 'redis') {
            return Cache::tags(['student_scores', 'student_' . $studentId])
                ->remember($cacheKey, self::SCORES_TTL, $callback);
        }
        
        return Cache::remember($cacheKey, self::SCORES_TTL, $callback);
    }

    /**
     * Invalidate student scores cache
     */
    public static function invalidateStudentScores($studentId, $academicSessionId = null, $subjectId = null, $term = null)
    {
        if ($academicSessionId) {
            $baseKey = self::STUDENT_SCORES_PREFIX . $studentId . '_' . $academicSessionId;
            
            // Invalidate all variations
            Cache::forget($baseKey);
            if ($subjectId) {
                Cache::forget($baseKey . '_subject_' . $subjectId);
            }
            if ($term) {
                Cache::forget($baseKey . '_term_' . $term);
            }
            if ($subjectId && $term) {
                Cache::forget($baseKey . '_subject_' . $subjectId . '_term_' . $term);
            }
            
            if (config('cache.default') === 'redis') {
                Cache::tags(['student_scores', 'student_' . $studentId])->flush();
            }
        } else {
            // Invalidate all scores for this student
            if (config('cache.default') === 'redis') {
                Cache::tags(['student_scores', 'student_' . $studentId])->flush();
            }
        }
    }

    /**
     * Get cached positions
     */
    public static function getPositions($classId, $term, $academicSessionId, callable $callback)
    {
        $cacheKey = self::POSITIONS_PREFIX . $classId . '_' . $term . '_' . $academicSessionId;
        
        if (config('cache.default') === 'redis') {
            return Cache::tags(['positions', 'class_' . $classId])
                ->remember($cacheKey, self::POSITIONS_TTL, $callback);
        }
        
        return Cache::remember($cacheKey, self::POSITIONS_TTL, $callback);
    }

    /**
     * Invalidate positions cache
     */
    public static function invalidatePositions($classId, $term, $academicSessionId)
    {
        $cacheKey = self::POSITIONS_PREFIX . $classId . '_' . $term . '_' . $academicSessionId;
        Cache::forget($cacheKey);
        
        if (config('cache.default') === 'redis') {
            Cache::tags(['positions', 'class_' . $classId])->flush();
        }
    }

    /**
     * Get cached current session/term
     */
    public static function getCurrentSessionTerm(callable $callback)
    {
        if (config('cache.default') === 'redis') {
            return Cache::tags(['current_session'])
                ->remember(self::CURRENT_SESSION_KEY, self::CURRENT_SESSION_TTL, $callback);
        }
        
        return Cache::remember(self::CURRENT_SESSION_KEY, self::CURRENT_SESSION_TTL, $callback);
    }

    /**
     * Invalidate current session/term cache
     */
    public static function invalidateCurrentSessionTerm()
    {
        Cache::forget(self::CURRENT_SESSION_KEY);
        Cache::forget(self::SESSION_STATUS_UPDATE_KEY);
        
        if (config('cache.default') === 'redis') {
            Cache::tags(['current_session'])->flush();
        }
    }

    /**
     * Clear all application caches
     */
    public static function clearAll()
    {
        Cache::flush();
    }
}

