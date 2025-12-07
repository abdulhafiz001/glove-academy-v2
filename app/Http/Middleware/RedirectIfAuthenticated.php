<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     * Redirect authenticated users away from login/guest pages
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via Sanctum
        if ($request->user()) {
            $user = $request->user();
            $role = $user->role ?? 'student';
            
            // Redirect to appropriate dashboard based on role
            switch ($role) {
                case 'admin':
                    return redirect('/admin/dashboard');
                case 'teacher':
                    return redirect('/teacher/dashboard');
                case 'student':
                    return redirect('/student/dashboard');
                default:
                    return redirect('/');
            }
        }

        return $next($request);
    }
}
