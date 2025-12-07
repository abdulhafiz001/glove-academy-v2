<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subject;
use App\Models\SchoolClass;

class SubjectController extends Controller
{
    /**
     * Get all subjects with caching
     */
    public function index()
    {
        $subjects = \App\Helpers\CacheHelper::getSubjects(function () {
            return Subject::withCount(['classSubjects' => function ($query) {
                $query->where('is_active', true);
            }])->where('is_active', true)->get();
        });

        return response()->json($subjects);
    }

    /**
     * Create a new subject
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:subjects,name',
            'code' => 'required|string|unique:subjects,code',
            'description' => 'nullable|string',
        ]);

        $subject = Subject::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'is_active' => true,
        ]);

        // Invalidate cache
        \App\Helpers\CacheHelper::invalidateSubjects();

        return response()->json([
            'message' => 'Subject created successfully',
            'subject' => $subject,
        ], 201);
    }

    /**
     * Get a specific subject
     */
    public function show(Subject $subject)
    {
        return response()->json($subject->load([
            'classSubjects.schoolClass',
            'teacherSubjects.teacher',
            'teacherSubjects.schoolClass'
        ]));
    }

    /**
     * Update a subject
     */
    public function update(Request $request, Subject $subject)
    {
        $request->validate([
            'name' => 'required|string|unique:subjects,name,' . $subject->id,
            'code' => 'required|string|unique:subjects,code,' . $subject->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $subject->update($request->all());

        // Invalidate cache
        \App\Helpers\CacheHelper::invalidateSubjects();

        return response()->json([
            'message' => 'Subject updated successfully',
            'subject' => $subject,
        ]);
    }

    /**
     * Delete a subject
     */
    public function destroy(Subject $subject)
    {
        // Check if subject has any students taking it
        $studentCount = $subject->studentSubjects()
            ->where('is_active', true)
            ->count();
        
        // Check if subject has any teachers assigned to teach it
        $teacherCount = $subject->teacherSubjects()
            ->where('is_active', true)
            ->count();
        
        // Check if subject has any scores recorded
        $scoreCount = $subject->scores()
            ->where('is_active', true)
            ->count();
        
        // Only prevent deletion if there are actual students, teachers, or scores
        // Class assignments alone shouldn't prevent deletion
        if ($studentCount > 0 || $teacherCount > 0 || $scoreCount > 0) {
            $reasons = [];
            if ($studentCount > 0) {
                $reasons[] = "{$studentCount} student(s) are taking this subject";
            }
            if ($teacherCount > 0) {
                $reasons[] = "{$teacherCount} teacher(s) are assigned to teach this subject";
            }
            if ($scoreCount > 0) {
                $reasons[] = "{$scoreCount} score(s) have been recorded for this subject";
            }
            
            return response()->json([
                'message' => 'Cannot delete subject: ' . implode(', ', $reasons) . '. Please remove these assignments first.'
            ], 400);
        }

        // If no actual usage, allow deletion and also deactivate class assignments
        $subject->update(['is_active' => false]);
        
        // Also deactivate all class_subjects assignments for this subject
        $subject->classSubjects()->update(['is_active' => false]);
        
        // Invalidate cache
        \App\Helpers\CacheHelper::invalidateSubjects();
        
        return response()->json(['message' => 'Subject deactivated successfully']);
    }
} 