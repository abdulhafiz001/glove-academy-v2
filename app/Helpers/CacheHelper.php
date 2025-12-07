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

    /**
     * Cache key prefixes
     */
    const DASHBOARD_PREFIX = 'dashboard_';
    const CLASSES_PREFIX = 'classes_';
    const SUBJECTS_PREFIX = 'subjects_';
    const SESSIONS_PREFIX = 'sessions_';
    const STATS_PREFIX = 'stats_';

    /**
     * Get cached dashboard data or compute and cache it
     */
    public static function getDashboardStats($role, $userId, callable $callback)
    {
        $cacheKey = self::DASHBOARD_PREFIX . $role . '_' . $userId;
        
        return Cache::remember($cacheKey, self::DASHBOARD_TTL, $callback);
    }

    /**
     * Invalidate dashboard cache for a role/user
     */
    public static function invalidateDashboard($role = null, $userId = null)
    {
        if ($role && $userId) {
            Cache::forget(self::DASHBOARD_PREFIX . $role . '_' . $userId);
        } else {
            // Clear all dashboard caches
            Cache::flush(); // Or use tags if Redis supports it
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
     * Clear all application caches
     */
    public static function clearAll()
    {
        Cache::flush();
    }
}

