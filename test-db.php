<?php

// Simple test script to check database content
require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Bootstrap Laravel
$app = Application::configure(basePath: dirname(__FILE__))
    ->withRouting(
        web: __DIR__.'/routes/web.php',
        api: __DIR__.'/routes/api.php',
        commands: __DIR__.'/routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// Test database connection and content
try {
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', '127.0.0.1') . 
        ';dbname=' . env('DB_DATABASE', 'forge') . 
        ';port=' . env('DB_PORT', '3306'),
        env('DB_USERNAME', 'forge'),
        env('DB_PASSWORD', '')
    );
    echo "✅ Database connection successful\n";
    
    // Check if tables exist and have data
    $tables = ['users', 'classes', 'subjects', 'students', 'teacher_subjects', 'class_subjects'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📊 {$table}: {$result['count']} records\n";
    }
    
    // Check specific data
    echo "\n🔍 Checking specific data:\n";
    
    // Check teachers
    $stmt = $pdo->query("SELECT id, name, username, role FROM users WHERE role = 'teacher'");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "👨‍🏫 Teachers:\n";
    foreach ($teachers as $teacher) {
        echo "  - ID: {$teacher['id']}, Name: {$teacher['name']}, Username: {$teacher['username']}\n";
    }
    
    // Check classes with form teachers
    $stmt = $pdo->query("SELECT c.name, u.name as teacher_name FROM classes c LEFT JOIN users u ON c.form_teacher_id = u.id WHERE c.is_active = 1");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\n🏫 Classes with form teachers:\n";
    foreach ($classes as $class) {
        $teacher = $class['teacher_name'] ?: 'No form teacher';
        echo "  - {$class['name']}: {$teacher}\n";
    }
    
    // Check teacher subject assignments
    $stmt = $pdo->query("SELECT u.name as teacher_name, s.name as subject_name, c.name as class_name FROM teacher_subjects ts JOIN users u ON ts.teacher_id = u.id JOIN subjects s ON ts.subject_id = s.id JOIN classes c ON ts.class_id = c.id WHERE ts.is_active = 1");
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\n📚 Teacher subject assignments:\n";
    foreach ($assignments as $assignment) {
        echo "  - {$assignment['teacher_name']} teaches {$assignment['subject_name']} in {$assignment['class_name']}\n";
    }
    
    // Check students
    $stmt = $pdo->query("SELECT s.first_name, s.last_name, s.admission_number, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.is_active = 1");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\n👥 Students:\n";
    foreach ($students as $student) {
        $class = $student['class_name'] ?: 'No class';
        echo "  - {$student['first_name']} {$student['last_name']} ({$student['admission_number']}) in {$class}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Test completed!\n";
