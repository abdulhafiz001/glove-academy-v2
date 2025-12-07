<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormTeacherMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user || !$user->isTeacher() || !$user->is_active) {
            return response()->json(['message' => 'Unauthorized. Teacher access required.'], 403);
        }
        
        if (!$user->isFormTeacher()) {
            return response()->json(['message' => 'Access denied. Only form teachers can access this resource.'], 403);
        }

        return $next($request);
    }
}

