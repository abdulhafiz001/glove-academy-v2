<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\Score;

class TeacherController extends Controller
{
    /**
     * Get teacher dashboard data with caching
     */
    public function dashboard(Request $request)
    {
        $teacher = $request->user();
        $userId = $teacher->id;
        
        $data = \App\Helpers\CacheHelper::getDashboardStats('teacher', $userId, function () use ($teacher) {
            $assignments = TeacherSubject::with(['subject', 'schoolClass'])
                                       ->where('teacher_id', $teacher->id)
                                       ->where('is_active', true)
                                       ->get();

            $totalClasses = $assignments->count();
            $totalSubjects = $assignments->unique('subject_id')->count();
            
            $totalStudents = 0;
            foreach ($assignments as $assignment) {
                $totalStudents += $assignment->schoolClass->students()->where('is_active', true)->count();
            }

            $recentScores = Score::with(['student', 'subject', 'schoolClass'])
                                ->where('teacher_id', $teacher->id)
                                ->where('is_active', true)
                                ->latest()
                                ->take(5)
                                ->get();

            return [
                'teacher' => $teacher,
                'stats' => [
                    'total_classes' => $totalClasses,
                    'total_subjects' => $totalSubjects,
                    'total_students' => $totalStudents,
                ],
                'assignments' => $assignments,
                'recent_scores' => $recentScores,
            ];
        });

        return response()->json($data);
    }

    /**
     * Get teacher's assignments
     */
    public function getAssignments(Request $request)
    {
        $teacher = $request->user();

        $assignments = TeacherSubject::with(['subject', 'schoolClass'])
                                   ->where('teacher_id', $teacher->id)
                                   ->where('is_active', true)
                                   ->get();

        return response()->json($assignments);
    }

    /**
     * Get teacher's assigned classes
     */
    public function getClasses(Request $request)
    {
        $teacher = $request->user();

        // Get class IDs where teacher teaches subjects
        $subjectClassIds = TeacherSubject::where('teacher_id', $teacher->id)
                                        ->where('is_active', true)
                                        ->pluck('class_id');

        // Get class IDs where teacher is a form teacher
        $formTeacherClassIds = SchoolClass::where('form_teacher_id', $teacher->id)
                                         ->where('is_active', true)
                                         ->pluck('id');

        // Combine both sets of class IDs
        $allClassIds = $subjectClassIds->merge($formTeacherClassIds)->unique();

        // Get the actual class objects with students
        $classes = SchoolClass::with(['formTeacher', 'students' => function ($query) {
                                    $query->where('is_active', true);
                                }])
                             ->whereIn('id', $allClassIds)
                             ->where('is_active', true)
                             ->get();
        
        // Manually add student count for each class
        $classes = $classes->map(function ($class) {
            $class->student_count = $class->students->count();
            return $class;
        });



        // Add permission flags for each class
        $classes = $classes->map(function ($class) use ($formTeacherClassIds) {
            $class->can_manage = in_array($class->id, $formTeacherClassIds->toArray());
            $class->is_form_teacher = in_array($class->id, $formTeacherClassIds->toArray());
            return $class;
        });

        return response()->json($classes);
    }

    /**
     * Get classes where teacher is assigned as form teacher
     */
    public function getFormTeacherClasses(Request $request)
    {
        $teacher = $request->user();

        $classes = SchoolClass::with(['formTeacher', 'students' => function ($query) {
                                    $query->where('is_active', true);
                                }])
                             ->where('form_teacher_id', $teacher->id)
                             ->where('is_active', true)
                             ->get();
        
        // Manually add student count for each class
        $classes = $classes->map(function ($class) {
            $class->student_count = $class->students->count();
            return $class;
        });

        return response()->json($classes);
    }

    /**
     * Check if teacher is a form teacher
     */
    public function checkFormTeacherStatus()
    {
        $teacher = request()->user();
        
        $isFormTeacher = SchoolClass::where('form_teacher_id', $teacher->id)
                                   ->where('is_active', true)
                                   ->exists();
        
        return response()->json([
            'is_form_teacher' => $isFormTeacher,
            'form_teacher_classes_count' => $isFormTeacher ? 
                SchoolClass::where('form_teacher_id', $teacher->id)->where('is_active', true)->count() : 0
        ]);
    }

    /**
     * Get teacher's assigned subjects
     */
    public function getSubjects(Request $request)
    {
        $teacher = $request->user();

        $subjects = TeacherSubject::with(['subject', 'schoolClass'])
                                 ->where('teacher_id', $teacher->id)
                                 ->where('is_active', true)
                                 ->get()
                                 ->map(function ($assignment) {
                                     $subject = $assignment->subject;
                                     $subject->class = $assignment->schoolClass;
                                     return $subject;
                                 });

        return response()->json($subjects);
    }

    /**
     * Get all subjects (for adding students)
     */
    public function getAllSubjects(Request $request)
    {
        $subjects = Subject::where('is_active', true)
                          ->orderBy('name')
                          ->get();

        return response()->json($subjects);
    }



    /**
     * Get students based on teacher's role and assignments
     */
    public function getStudents(Request $request)
    {
        $teacher = $request->user();

        // Get teacher's subject assignments (class_id and subject_id pairs)
        $teacherAssignments = TeacherSubject::where('teacher_id', $teacher->id)
                                          ->where('is_active', true)
                                          ->get(['class_id', 'subject_id']);

        // Get subject IDs the teacher teaches
        $teacherSubjectIds = $teacherAssignments->pluck('subject_id')->unique();

        // Get class IDs where teacher teaches subjects
        $subjectClassIds = $teacherAssignments->pluck('class_id')->unique();

        // Get class IDs where teacher is a form teacher
        $formTeacherClassIds = SchoolClass::where('form_teacher_id', $teacher->id)
                                         ->where('is_active', true)
                                         ->pluck('id');

        // Build query for students
        $query = Student::with(['schoolClass', 'studentSubjects.subject'])
                       ->where('is_active', true);

        // Filter students based on teacher role:
        // 1. Form teachers can see ALL students in their form teacher classes
        // 2. All teachers (including form teachers) can see students offering subjects they teach
        
        if ($formTeacherClassIds->count() > 0 && $subjectClassIds->count() > 0) {
            // Form teacher who also teaches subjects
            // Show: students in form teacher classes OR students offering subjects in classes they teach
            $query->where(function ($q) use ($formTeacherClassIds, $subjectClassIds, $teacherSubjectIds) {
                // Students in form teacher classes (all students)
                $q->whereIn('class_id', $formTeacherClassIds)
                  // OR students offering subjects the teacher teaches in classes they're assigned to
                  ->orWhere(function ($subQ) use ($subjectClassIds, $teacherSubjectIds) {
                      $subQ->whereIn('class_id', $subjectClassIds)
                           ->whereHas('studentSubjects', function ($studentSubjectQuery) use ($teacherSubjectIds) {
                               $studentSubjectQuery->whereIn('subject_id', $teacherSubjectIds)
                                                   ->where('is_active', true);
                           });
                  });
            });
        } elseif ($formTeacherClassIds->count() > 0) {
            // Form teacher only (no subject assignments)
            // Show all students in form teacher classes
            $query->whereIn('class_id', $formTeacherClassIds);
        } elseif ($subjectClassIds->count() > 0 && $teacherSubjectIds->count() > 0) {
            // Regular teacher (not form teacher)
            // Show only students offering subjects the teacher teaches in classes they're assigned to
            $query->whereIn('class_id', $subjectClassIds)
                  ->whereHas('studentSubjects', function ($studentSubjectQuery) use ($teacherSubjectIds) {
                      $studentSubjectQuery->whereIn('subject_id', $teacherSubjectIds)
                                          ->where('is_active', true);
                  });
        } else {
            // Teacher has no assignments - return empty
            $query->whereRaw('1 = 0'); // Force empty result
        }

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Search by name or admission number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('admission_number', 'like', "%{$search}%");
            });
        }

        $students = $query->get();

        // Add permission flags for each student
        $students = $students->map(function ($student) use ($formTeacherClassIds) {
            $student->can_manage = in_array($student->class_id, $formTeacherClassIds->toArray());
            $student->is_form_teacher = in_array($student->class_id, $formTeacherClassIds->toArray());
            return $student;
        });

        // Add form teacher status to the response
        $response = [
            'students' => $students,
            'is_form_teacher' => $formTeacherClassIds->count() > 0,
            'form_teacher_classes' => $formTeacherClassIds->toArray()
        ];

        return response()->json($response);
    }

    /**
     * Add student (form teacher only)
     */
    public function addStudent(Request $request)
    {
        $teacher = $request->user();
        
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'admission_number' => 'required|string|unique:students,admission_number',
            'email' => 'nullable|email|unique:students,email',
            'phone' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'address' => 'nullable|string',
            'parent_name' => 'nullable|string',
            'parent_phone' => 'nullable|string',
            'parent_email' => 'nullable|email',
            'class_id' => 'required|exists:classes,id',
            'subjects' => 'required|array',
            'subjects.*' => 'exists:subjects,id',
        ]);

        // Check if teacher is a form teacher of this class
        $isFormTeacher = SchoolClass::where('id', $request->class_id)
                                   ->where('form_teacher_id', $teacher->id)
                                   ->where('is_active', true)
                                   ->exists();

        if (!$isFormTeacher) {
            return response()->json(['message' => 'You are not a form teacher of this class'], 403);
        }

        // Get current academic session and term
        $currentSession = \App\Models\AcademicSession::current();
        $currentTerm = \App\Models\Term::current();

        $student = Student::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'admission_number' => $request->admission_number,
            'email' => $request->email,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'address' => $request->address,
            'parent_name' => $request->parent_name,
            'parent_phone' => $request->parent_phone,
            'parent_email' => $request->parent_email,
            'class_id' => $request->class_id,
            'password' => $request->password ?? 'password', // Will be hashed by mutator
            'is_active' => true,
            'admission_academic_session_id' => $currentSession?->id,
            'admission_term' => $currentTerm?->name,
            'status' => 'active',
        ]);

        // Assign subjects to student
        foreach ($request->subjects as $subjectId) {
            \App\Models\StudentSubject::create([
                'student_id' => $student->id,
                'subject_id' => $subjectId,
                'is_active' => true,
            ]);
        }

        // Record class history for the current academic session
        if ($currentSession) {
            \App\Models\StudentClassHistory::updateHistory(
                $student->id,
                $currentSession->id,
                $student->class_id
            );
        }

        return response()->json([
            'message' => 'Student added successfully',
            'student' => $student->load(['schoolClass', 'studentSubjects.subject']),
        ], 201);
    }

    /**
     * Update student (form teacher only)
     */
    public function updateStudent(Request $request, Student $student)
    {
        $teacher = $request->user();
        
        // Check if teacher is a form teacher of this student's class
        $isFormTeacher = SchoolClass::where('id', $student->class_id)
                                   ->where('form_teacher_id', $teacher->id)
                                   ->where('is_active', true)
                                   ->exists();

        if (!$isFormTeacher) {
            return response()->json(['message' => 'You are not a form teacher of this student\'s class'], 403);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'admission_number' => 'required|string|unique:students,admission_number,' . $student->id,
            'email' => 'nullable|email|unique:students,email,' . $student->id,
            'phone' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'address' => 'nullable|string',
            'parent_name' => 'nullable|string',
            'parent_phone' => 'nullable|string',
            'parent_email' => 'nullable|email',
            'class_id' => 'required|exists:classes,id',
            'subjects' => 'nullable|array',
            'subjects.*' => 'string',
            'is_active' => 'boolean',
        ]);

        // Check if new class is also under this teacher's form teacher responsibility
        if ($request->class_id != $student->class_id) {
            $isFormTeacherOfNewClass = SchoolClass::where('id', $request->class_id)
                                                 ->where('form_teacher_id', $teacher->id)
                                                 ->where('is_active', true)
                                                 ->exists();

            if (!$isFormTeacherOfNewClass) {
                return response()->json(['message' => 'You can only assign students to classes where you are the form teacher'], 403);
            }
        }

        $student->update($request->all());

        // Update student subjects if provided
        if ($request->has('subjects')) {
            // Delete existing subject relationships completely
            $student->studentSubjects()->delete();
            
            // Create new subject relationships
            if ($request->subjects && count($request->subjects) > 0) {
                foreach ($request->subjects as $subjectName) {
                    $subject = \App\Models\Subject::where('name', $subjectName)->first();
                    if ($subject) {
                        \App\Models\StudentSubject::create([
                            'student_id' => $student->id,
                            'subject_id' => $subject->id,
                            'is_active' => true,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Student updated successfully',
            'student' => $student->load(['schoolClass', 'studentSubjects.subject']),
        ]);
    }

    /**
     * Delete student (form teacher only) - soft delete
     */
    public function deleteStudent(Student $student)
    {
        $teacher = request()->user();
        
        // Check if teacher is a form teacher of this student's class
        $isFormTeacher = SchoolClass::where('id', $student->class_id)
                                   ->where('form_teacher_id', $teacher->id)
                                   ->where('is_active', true)
                                   ->exists();

        if (!$isFormTeacher) {
            return response()->json(['message' => 'You are not a form teacher of this student\'s class'], 403);
        }

        $student->delete(); // Soft delete using SoftDeletes trait
        
        return response()->json(['message' => 'Student deleted successfully']);
    }

    /**
     * Get teacher profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        
        // Get teacher assignments
        $assignments = TeacherSubject::with(['schoolClass', 'subject'])
                                   ->where('teacher_id', $user->id)
                                   ->where('is_active', true)
                                   ->get()
                                   ->groupBy('class_id')
                                   ->map(function ($classAssignments) {
                                       $class = $classAssignments->first()->schoolClass;
                                       $class->subjects = $classAssignments->pluck('subject');
                                       return $class;
                                   })
                                   ->values();

        // Get form teacher classes
        $formTeacherClasses = SchoolClass::where('form_teacher_id', $user->id)
                                        ->where('is_active', true)
                                        ->pluck('name');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'phone' => $user->phone,
            'address' => $user->address,
            'date_joined' => $user->created_at,
            'role' => $user->role,
            'is_form_teacher' => $user->is_form_teacher,
            'avatar' => $user->avatar,
            'assignments' => $assignments,
            'form_teacher_classes' => $formTeacherClasses,
        ]);
    }

    /**
     * Update teacher profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username,' . $user->id,
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        $updateData = [
            'name' => $request->name,
            'username' => $request->username,
        ];

        // Only update email if provided
        if ($request->has('email')) {
            $updateData['email'] = $request->email ?: null;
        }

        // Only update phone if provided
        if ($request->has('phone')) {
            $updateData['phone'] = $request->phone ?: null;
        }

        // Only update address if provided
        if ($request->has('address')) {
            $updateData['address'] = $request->address ?: null;
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Change teacher password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);

        // Check current password
        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
} 