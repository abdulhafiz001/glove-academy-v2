<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;

class ClassController extends Controller
{
    /**
     * Get all classes with caching
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $cacheKey = $user->isAdmin() ? 'admin' : 'teacher_' . $user->id;
        
        $result = \App\Helpers\CacheHelper::getClasses(function () use ($user, $cacheKey) {
            if ($user->isAdmin()) {
                // Admin can see all classes
                $classes = SchoolClass::with(['formTeacher', 'students' => function ($query) {
                                         $query->where('is_active', true);
                                     }])
                                     ->where('is_active', true)
                                     ->get();
            } elseif ($user->isFormTeacher()) {
                // Form teachers can only see classes where they are form teacher
                $classes = SchoolClass::with(['formTeacher', 'students' => function ($query) {
                                         $query->where('is_active', true);
                                     }])
                                     ->where('form_teacher_id', $user->id)
                                     ->where('is_active', true)
                                     ->get();
            } else {
                // Regular teachers cannot access classes page
                return ['data' => [], 'total' => 0, 'message' => 'Access denied'];
            }
            
            // Manually add student count for each class
            $classes = $classes->map(function ($class) {
                $class->students_count = $class->students->count();
                return $class;
            });

            return [
                'data' => $classes,
                'total' => $classes->count(),
                'message' => 'Classes retrieved successfully'
            ];
        });

        if ($result['message'] === 'Access denied') {
            return response()->json(['message' => 'Access denied. Only admins and form teachers can view classes.'], 403);
        }

        return response()->json($result);
    }

    /**
     * Create a new class
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:classes,name',
            'description' => 'nullable|string',
            'form_teacher_id' => 'nullable|exists:users,id',
        ]);

        $class = SchoolClass::create([
            'name' => $request->name,
            'description' => $request->description,
            'form_teacher_id' => $request->form_teacher_id,
            'is_active' => true,
        ]);

        // Invalidate cache
        \App\Helpers\CacheHelper::invalidateClasses();

        return response()->json([
            'message' => 'Class created successfully',
            'class' => $class->load('formTeacher'),
        ], 201);
    }

    /**
     * Get a specific class
     */
    public function show(Request $request, SchoolClass $class)
    {
        $user = $request->user();
        
        // Check if user is admin or form teacher of this class
        if (!$user->isAdmin() && $class->form_teacher_id !== $user->id) {
            return response()->json(['message' => 'Access denied. You can only view classes where you are the form teacher.'], 403);
        }
        
        return response()->json($class->load([
            'students' => function ($query) {
                $query->where('is_active', true);
            },
            'students.studentSubjects.subject'
        ]));
    }

    /**
     * Update a class
     */
    public function update(Request $request, SchoolClass $class)
    {
        $request->validate([
            'name' => 'required|string|unique:classes,name,' . $class->id,
            'description' => 'nullable|string',
            'form_teacher_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $class->update($request->all());

        // Invalidate cache
        \App\Helpers\CacheHelper::invalidateClasses();

        return response()->json([
            'message' => 'Class updated successfully',
            'class' => $class->load('formTeacher'),
        ]);
    }

    /**
     * Delete a class
     */
    public function destroy(SchoolClass $class)
    {
        // Check if class has students
        $studentCount = $class->students()->where('is_active', true)->count();
        
        if ($studentCount > 0) {
            return response()->json([
                'message' => 'Cannot delete class with active students. Please transfer students first.'
            ], 400);
        }

        $class->update(['is_active' => false]);
        
        // Invalidate cache
        \App\Helpers\CacheHelper::invalidateClasses();
        
        return response()->json(['message' => 'Class deactivated successfully']);
    }

    /**
     * Debug method to test form teacher endpoint
     */
    public function debugFormTeacher(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'is_form_teacher' => $user->isFormTeacher(),
            'form_teacher_classes' => SchoolClass::where('form_teacher_id', $user->id)->pluck('id'),
            'message' => 'Debug info for form teacher endpoint'
        ]);
    }
} 