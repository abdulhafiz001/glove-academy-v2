<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Student;

class StudentMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // For student routes, we'll check if the token belongs to a student
        // This will be handled in the AuthController for student login
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthorized. Student access required.'], 403);
        }

        return $next($request);
    }
} 