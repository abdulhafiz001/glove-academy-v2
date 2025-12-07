<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Redirect to API test endpoint or return API info
    return response()->json([
        'message' => 'G-LOVE Academy API',
        'version' => '1.0',
        'endpoints' => [
            'test' => '/api/test',
            'login' => '/api/login',
            'student_login' => '/api/student/login',
        ]
    ]);
});
