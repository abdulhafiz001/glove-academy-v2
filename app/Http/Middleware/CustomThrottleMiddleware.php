<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CustomThrottleMiddleware
{
    /**
     * Handle an incoming request.
     * Custom rate limiting with better error messages
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 5, $decayMinutes = 15): Response
    {
        // Ensure parameters are integers (route parameters come as strings)
        $maxAttempts = (int) $maxAttempts;
        $decayMinutes = (int) $decayMinutes;
        
        // Use IP address for rate limiting
        $ip = $request->ip() ?: $request->server('REMOTE_ADDR');
        $key = 'login_attempts_' . $ip;
        
        // Get current attempts from cache
        try {
            $attempts = (int) Cache::get($key, 0);
        } catch (\Exception $e) {
            // If cache fails, log error but allow request (fail open for availability)
            \Log::warning('Rate limiting cache error: ' . $e->getMessage());
            $attempts = 0;
        }
        
        // Log for debugging (remove in production)
        \Log::info('Rate limit check', [
            'ip' => $ip,
            'key' => $key,
            'attempts' => $attempts,
            'max' => $maxAttempts
        ]);
        
        // Check if rate limit exceeded BEFORE processing request
        if ($attempts >= $maxAttempts) {
            try {
                $secondsRemaining = (int) Cache::get($key . '_reset', $decayMinutes * 60);
            } catch (\Exception $e) {
                $secondsRemaining = $decayMinutes * 60;
            }
            
            \Log::warning('Rate limit exceeded', [
                'ip' => $ip,
                'attempts' => $attempts,
                'max' => $maxAttempts
            ]);
            
            return response()->json([
                'message' => 'Too many login attempts. Please try again in ' . ceil($secondsRemaining / 60) . ' minute(s).',
                'errors' => [
                    'rate_limit' => ['Too many login attempts. Please wait before trying again.']
                ]
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => $secondsRemaining,
            ]);
        }
        
        // Process the request first
        $response = $next($request);
        
        // Increment attempts AFTER request is processed
        // Increment on failed login (non-200 status codes like 422 for validation errors)
        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200 && $statusCode !== 201) {
            // Failed login attempt - increment counter
            try {
                $newAttempts = $attempts + 1;
                $ttl = now()->addMinutes($decayMinutes);
                Cache::put($key, $newAttempts, $ttl);
                Cache::put($key . '_reset', $decayMinutes * 60, $ttl);
                
                \Log::info('Rate limit incremented', [
                    'ip' => $ip,
                    'attempts' => $newAttempts,
                    'status_code' => $statusCode
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to increment rate limit: ' . $e->getMessage());
            }
        } else {
            // Successful login - reset attempts
            try {
                Cache::forget($key);
                Cache::forget($key . '_reset');
                \Log::info('Rate limit reset on successful login', ['ip' => $ip]);
            } catch (\Exception $e) {
                \Log::error('Failed to reset rate limit: ' . $e->getMessage());
            }
        }
        
        // Add rate limit headers to response
        try {
            $remaining = max(0, $maxAttempts - ($statusCode !== 200 && $statusCode !== 201 ? $attempts + 1 : $attempts));
            $response->header('X-RateLimit-Limit', $maxAttempts);
            $response->header('X-RateLimit-Remaining', $remaining);
        } catch (\Exception $e) {
            // Ignore header errors
        }
        
        return $response;
    }
}

