<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\TeacherSubject;
use App\Models\StudentSubject;
use App\Models\AcademicSession;
use App\Models\Term;

class ScoreController extends Controller
{
    /**
     * Get scores for admin view
     */
    public function adminIndex(Request $request)
    {
        $user = $request->user();
        
        // Check if user is admin, form teacher, or assigned teacher
        if (!$user->isAdmin()) {
            // For teachers, check if they are form teacher of any class
            if ($user->isFormTeacher()) {
                // Form teacher can see results of students in their assigned classes
                $formTeacherClasses = SchoolClass::where('form_teacher_id', $user->id)
                                               ->where('is_active', true)
                                               ->pluck('id');
                
                if ($formTeacherClasses->isEmpty()) {
                    return response()->json(['message' => 'No classes assigned as form teacher'], 403);
                }
                
                $query = Score::with(['student', 'subject', 'schoolClass', 'teacher'])
                             ->whereIn('class_id', $formTeacherClasses)
                             ->where('is_active', true);
            } else {
                // Regular teachers cannot access results page
                return response()->json(['message' => 'Access denied. Only form teachers can view results.'], 403);
            }
        } else {
            // Admin can see all results
            $query = Score::with(['student', 'subject', 'schoolClass', 'teacher'])
                         ->where('is_active', true);
        }

        // Filter by class
        if ($request->has('class_id')) {
            if (!$user->isAdmin()) {
                // For teachers, ensure they can only filter by their form teacher classes
                $formTeacherClasses = SchoolClass::where('form_teacher_id', $user->id)
                                               ->where('is_active', true)
                                               ->pluck('id');
                if (!$formTeacherClasses->contains($request->class_id)) {
                    return response()->json(['message' => 'Access denied to this class'], 403);
                }
            }
            $query->where('class_id', $request->class_id);
        }

        // Filter by subject
        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by term
        if ($request->has('term')) {
            $query->where('term', $request->term);
        }

        $scores = $query->paginate(20);

        return response()->json($scores);
    }

    /**
     * Get scores for teacher view
     */
    public function teacherIndex(Request $request)
    {
        $teacher = $request->user();
        
        // Get teacher's assigned subjects
        $assignedSubjectIds = TeacherSubject::where('teacher_id', $teacher->id)
                                          ->where('is_active', true)
                                          ->pluck('subject_id');
        
        if ($assignedSubjectIds->isEmpty()) {
            return response()->json(['message' => 'You are not assigned to teach any subjects'], 403);
        }
        
        $query = Score::with(['student', 'subject', 'schoolClass'])
                     ->whereIn('subject_id', $assignedSubjectIds)
                     ->where('is_active', true);

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by subject
        if ($request->has('subject_id')) {
            // Ensure teacher can only filter by subjects they are assigned to teach
            if (!$assignedSubjectIds->contains($request->subject_id)) {
                return response()->json(['message' => 'You are not assigned to teach this subject'], 403);
            }
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by term
        if ($request->has('term')) {
            $query->where('term', $request->term);
        }

        $scores = $query->paginate(20);

        return response()->json($scores);
    }

    /**
     * Get teacher's assigned classes and subjects for score management
     */
    public function getTeacherAssignmentsForScores(Request $request)
    {
        $teacher = $request->user();
        
        $assignments = TeacherSubject::with(['schoolClass', 'subject'])
                                   ->where('teacher_id', $teacher->id)
                                   ->where('is_active', true)
                                   ->get()
                                   ->groupBy('class_id')
                                   ->map(function ($classAssignments) {
                                       $class = $classAssignments->first()->schoolClass;
                                       $class->subjects = $classAssignments->pluck('subject');
                                       return $class;
                                   })
                                   ->values();

        return response()->json($assignments);
    }

    /**
     * Get students for a specific class and subject combination
     */
    public function getStudentsForClassSubject(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $teacher = $request->user();
        
        // Check if teacher is assigned to this class and subject
        $assignment = TeacherSubject::where('teacher_id', $teacher->id)
                                   ->where('class_id', $request->class_id)
                                   ->where('subject_id', $request->subject_id)
                                   ->where('is_active', true)
                                   ->first();

        if (!$assignment) {
            return response()->json(['message' => 'You are not assigned to this class and subject'], 403);
        }

        // Get students in the class who are taking this subject
        $students = Student::where('class_id', $request->class_id)
                          ->where('is_active', true)
                          ->whereHas('studentSubjects', function ($query) use ($request) {
                              $query->where('subject_id', $request->subject_id)
                                    ->where('is_active', true);
                          })
                          ->get();

        return response()->json($students);
    }

    /**
     * Get existing scores for students in a class-subject combination
     */
    public function getExistingScores(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'term' => 'required|in:first,second,third',
        ]);

        $teacher = $request->user();
        
        // Check if teacher is assigned to this class and subject
        $assignment = TeacherSubject::where('teacher_id', $teacher->id)
                                   ->where('class_id', $request->class_id)
                                   ->where('subject_id', $request->subject_id)
                                   ->where('is_active', true)
                                   ->first();

        if (!$assignment) {
            return response()->json(['message' => 'You are not assigned to this class and subject'], 403);
        }

        // Get existing scores for students in this class-subject-term combination
        // Teachers can only see scores they recorded themselves OR scores for subjects they are assigned to teach
        $scores = Score::with(['student'])
                      ->where('class_id', $request->class_id)
                      ->where('subject_id', $request->subject_id)
                      ->where('term', $request->term)
                      ->where('is_active', true)
                      ->where(function ($query) use ($teacher) {
                          // Teacher can see scores they recorded
                          $query->where('teacher_id', $teacher->id)
                                // OR scores for subjects they are assigned to teach (even if recorded by another teacher)
                                ->orWhereHas('subject', function ($subQuery) use ($teacher) {
                                    $subQuery->whereHas('teacherSubjects', function ($tsQuery) use ($teacher) {
                                        $tsQuery->where('teacher_id', $teacher->id)
                                               ->where('is_active', true);
                                    });
                                });
                      })
                      ->get();

        return response()->json($scores);
    }

    /**
     * Get scores for a specific class (teacher view)
     */
    public function getClassScores(Request $request, SchoolClass $class)
    {
        $teacher = $request->user();
        
        // Check if teacher is assigned to this class
        $assignments = TeacherSubject::where('teacher_id', $teacher->id)
                                   ->where('class_id', $class->id)
                                   ->where('is_active', true)
                                   ->with('subject')
                                   ->get();

        if ($assignments->isEmpty()) {
            return response()->json(['message' => 'You are not assigned to this class'], 403);
        }

        // Get the subject IDs the teacher is assigned to teach in this class
        $assignedSubjectIds = $assignments->pluck('subject_id');

        $students = $class->students()
                         ->where('is_active', true)
                         ->with(['scores' => function ($query) use ($teacher, $assignedSubjectIds) {
                             $query->whereIn('subject_id', $assignedSubjectIds)
                                   ->where('is_active', true)
                                   ->with('subject');
                         }])
                         ->get();

        return response()->json([
            'class' => $class,
            'students' => $students,
            'assignments' => $assignments,
        ]);
    }

    /**
     * Store a new score
     */
    public function store(Request $request)
    {
        // Get current academic session - required
        $currentSession = AcademicSession::current();
        if (!$currentSession) {
            return response()->json([
                'message' => 'No current academic session set. Please set an academic session in settings.',
            ], 422);
        }

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'required|exists:classes,id',
            'term' => 'required|in:first,second,third',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'first_ca' => 'nullable|numeric|min:0|max:100',
            'second_ca' => 'nullable|numeric|min:0|max:100',
            'exam_score' => 'nullable|numeric|min:0|max:100',
            'remark' => 'nullable|string|max:255',
        ]);

        // At least one score field must be provided
        if (!$request->first_ca && !$request->second_ca && !$request->exam_score) {
            return response()->json([
                'message' => 'At least one score field must be provided'
            ], 422);
        }

        // Use provided academic_session_id or default to current session
        $academicSessionId = $request->academic_session_id ?? $currentSession->id;

        // Check permissions for non-admin users
        $user = $request->user();
        if (!$user->isAdmin()) {
            // Check if user is form teacher of this class or assigned to teach any subject in this class
            $class = \App\Models\SchoolClass::find($request->class_id);
            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }
            
            $isFormTeacher = $class->form_teacher_id === $user->id;
            $isSubjectTeacher = \App\Models\TeacherSubject::where('teacher_id', $user->id)
                                                          ->where('class_id', $request->class_id)
                                                          ->where('is_active', true)
                                                          ->exists();
            
            if (!$isFormTeacher && !$isSubjectTeacher) {
                return response()->json(['message' => 'You can only manage scores for students in your assigned classes'], 403);
            }
        }

        // Check if score already exists for this student, subject, class, term, and academic session
        $existingScore = Score::where([
            'student_id' => $request->student_id,
            'subject_id' => $request->subject_id,
            'class_id' => $request->class_id,
            'term' => $request->term,
            'academic_session_id' => $academicSessionId,
        ])->first();

        if ($existingScore) {
            // Update existing score instead of creating new one
            $existingScore->update([
                'first_ca' => $request->first_ca,
                'second_ca' => $request->second_ca,
                'exam_score' => $request->exam_score,
                'remark' => $request->remark,
            ]);
            
            return response()->json([
                'message' => 'Score updated successfully',
                'score' => $existingScore->load(['student', 'subject', 'schoolClass'])
            ], 200);
        }

        // Create new score if none exists
        $score = Score::create([
            'student_id' => $request->student_id,
            'subject_id' => $request->subject_id,
            'class_id' => $request->class_id,
            'teacher_id' => $request->user()->id,
            'academic_session_id' => $academicSessionId,
            'term' => $request->term,
            'first_ca' => $request->first_ca,
            'second_ca' => $request->second_ca,
            'exam_score' => $request->exam_score,
            'remark' => $request->remark,
        ]);

        return response()->json([
            'message' => 'Score created successfully',
            'score' => $score->load(['student', 'subject', 'schoolClass'])
        ], 201);
    }

    /**
     * Update a score
     */
    public function update(Request $request, Score $score)
    {
        // Check if the score belongs to a past/closed academic session
        $currentSession = AcademicSession::current();
        if ($score->academic_session_id && $currentSession) {
            $scoreSession = AcademicSession::find($score->academic_session_id);
            if ($scoreSession && $scoreSession->id !== $currentSession->id && !$scoreSession->is_current) {
                return response()->json([
                    'message' => 'Cannot edit scores from past academic sessions. Only current session scores can be edited.',
                ], 403);
            }
        }

        $request->validate([
            'first_ca' => 'nullable|numeric|min:0|max:100',
            'second_ca' => 'nullable|numeric|min:0|max:100',
            'exam_score' => 'nullable|numeric|min:0|max:100',
            'remark' => 'nullable|string|max:255',
        ]);

        // At least one score field must be provided
        if (!$request->first_ca && !$request->second_ca && !$request->exam_score) {
            return response()->json([
                'message' => 'At least one score field must be provided'
            ], 422);
        }

        // Check permissions for non-admin users
        $user = $request->user();
        if (!$user->isAdmin()) {
            // Check if user is form teacher of this class or assigned to teach any subject in this class
            $class = \App\Models\SchoolClass::find($score->class_id);
            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }
            
            $isFormTeacher = $class->form_teacher_id === $user->id;
            $isSubjectTeacher = \App\Models\TeacherSubject::where('teacher_id', $user->id)
                                                          ->where('class_id', $score->class_id)
                                                          ->where('is_active', true)
                                                          ->exists();
            
            if (!$isFormTeacher && !$isSubjectTeacher) {
                return response()->json(['message' => 'You can only manage scores for students in your assigned classes'], 403);
            }
        }

        $score->update([
            'first_ca' => $request->first_ca,
            'second_ca' => $request->second_ca,
            'exam_score' => $request->exam_score,
            'remark' => $request->remark,
        ]);

        return response()->json($score->load(['student', 'subject', 'schoolClass']));
    }

    /**
     * Get scores for a specific subject that teacher is assigned to teach
     */
    public function getSubjectScores(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'required|exists:classes,id',
            'term' => 'nullable|in:first,second,third',
        ]);

        $teacher = $request->user();
        
        // Check if teacher is assigned to teach this subject in this class
        $assignment = TeacherSubject::where('teacher_id', $teacher->id)
                                   ->where('subject_id', $request->subject_id)
                                   ->where('class_id', $request->class_id)
                                   ->where('is_active', true)
                                   ->first();

        if (!$assignment) {
            return response()->json(['message' => 'You are not assigned to teach this subject in this class'], 403);
        }

        $query = Score::with(['student', 'subject', 'schoolClass'])
                     ->where('subject_id', $request->subject_id)
                     ->where('class_id', $request->class_id)
                     ->where('is_active', true);

        // Filter by term if provided
        if ($request->has('term')) {
            $query->where('term', $request->term);
        }

        $scores = $query->orderBy('student_id')
                        ->orderBy('term')
                        ->get();

        return response()->json($scores);
    }

    /**
     * Get admin view of student results
     */
    public function adminStudentResults(Request $request, Student $student)
    {
        $user = $request->user();
        
        // Check if user is admin, form teacher of the student's class, or assigned to teach subjects in that class
        if (!$user->isAdmin() && $student->class_id) {
            $class = \App\Models\SchoolClass::find($student->class_id);
            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }
            
            // Check if user is form teacher of this class
            if ($class->form_teacher_id === $user->id) {
                // Form teacher can view all results
            } else {
                // Check if user is assigned to teach any subject in this class
                $teacherAssignment = \App\Models\TeacherSubject::where('teacher_id', $user->id)
                                                              ->where('class_id', $class->id)
                                                              ->where('is_active', true)
                                                              ->first();
                
                if (!$teacherAssignment) {
                    return response()->json(['message' => 'You can only view results for students in your assigned classes'], 403);
                }
            }
        }
        
        // Get academic session - default to current if not specified
        $academicSessionId = $request->academic_session_id ?? AcademicSession::current()?->id;
        
        $query = Score::with(['subject', 'schoolClass', 'teacher', 'academicSession'])
                     ->where('student_id', $student->id)
                     ->where('is_active', true);
        
        // Filter by academic session if available
        if ($academicSessionId) {
            $query->where('academic_session_id', $academicSessionId);
        }
        
        // If user is a teacher (not admin), filter by subjects they are assigned to teach
        if ($user->isTeacher() && !$user->isAdmin()) {
            $assignedSubjectIds = TeacherSubject::where('teacher_id', $user->id)
                                              ->where('is_active', true)
                                              ->pluck('subject_id');
            
            if ($assignedSubjectIds->isNotEmpty()) {
                $query->whereIn('subject_id', $assignedSubjectIds);
            }
        }
        
        $scores = $query->orderBy('term')
                        ->orderBy('subject_id')
                        ->get()
                        ->groupBy('term');

        return response()->json([
            'student' => $student->load('schoolClass'),
            'results' => $scores,
        ]);
    }

    /**
     * Get student scores for a specific student
     */
    public function getStudentScores(Request $request, Student $student)
    {
        $user = $request->user();
        
        // Check if user is admin, form teacher of the student's class, or assigned to teach subjects in that class
        if (!$user->isAdmin() && $student->class_id) {
            $class = \App\Models\SchoolClass::find($student->class_id);
            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }
            
            // Check if user is form teacher of this class
            if ($class->form_teacher_id === $user->id) {
                // Form teacher can view all scores
            } else {
                // Check if user is assigned to teach any subject in this class
                $teacherAssignment = \App\Models\TeacherSubject::where('teacher_id', $user->id)
                                                              ->where('class_id', $class->id)
                                                              ->where('is_active', true)
                                                              ->first();
                
                if (!$teacherAssignment) {
                    return response()->json(['message' => 'You can only view scores for students in your assigned classes'], 403);
                }
            }
        }
        
        // Get academic session - default to current if not specified
        $academicSessionId = $request->academic_session_id ?? AcademicSession::current()?->id;
        
        $query = Score::with(['subject', 'schoolClass', 'teacher', 'academicSession'])
                     ->where('student_id', $student->id)
                     ->where('is_active', true);
        
        // Filter by academic session if available
        if ($academicSessionId) {
            $query->where('academic_session_id', $academicSessionId);
        }
        
        // If user is a teacher (not admin), filter by subjects they are assigned to teach
        if ($user->isTeacher() && !$user->isAdmin()) {
            $assignedSubjectIds = TeacherSubject::where('teacher_id', $user->id)
                                              ->where('is_active', true)
                                              ->pluck('subject_id');
            
            if ($assignedSubjectIds->isNotEmpty()) {
                $query->whereIn('subject_id', $assignedSubjectIds);
            }
        }
        
        $scores = $query->orderBy('term')
                        ->orderBy('subject_id')
                        ->get();

        return response()->json($scores);
    }

    /**
     * Get teacher view of student results (for form teachers)
     */
    public function teacherStudentResults(Request $request, Student $student)
    {
        $teacher = $request->user();
        
        // Check if teacher is a form teacher of the student's class
        if (!$teacher->isFormTeacher()) {
            return response()->json(['message' => 'Access denied. Only form teachers can view student results.'], 403);
        }
        
        // Check if teacher is form teacher of this specific student's class
        $class = SchoolClass::where('id', $student->class_id)
                           ->where('form_teacher_id', $teacher->id)
                           ->where('is_active', true)
                           ->first();
        
        if (!$class) {
            return response()->json(['message' => 'Access denied. You can only view results for students in classes where you are the form teacher.'], 403);
        }
        
        // Get scores for subjects the teacher is assigned to teach in this class
        $assignedSubjectIds = TeacherSubject::where('teacher_id', $teacher->id)
                                          ->where('class_id', $student->class_id)
                                          ->where('is_active', true)
                                          ->pluck('subject_id');
        
        // Get academic session - default to current if not specified
        $academicSessionId = $request->academic_session_id ?? AcademicSession::current()?->id;
        
        $query = Score::with(['subject', 'schoolClass', 'teacher', 'academicSession'])
                     ->where('student_id', $student->id)
                     ->where('is_active', true);
        
        // Filter by academic session if available
        if ($academicSessionId) {
            $query->where('academic_session_id', $academicSessionId);
        }
        
        // Filter by subjects the teacher is assigned to teach
        if ($assignedSubjectIds->isNotEmpty()) {
            $query->whereIn('subject_id', $assignedSubjectIds);
        }
        
        $scores = $query->orderBy('term')
                        ->orderBy('subject_id')
                        ->get()
                        ->groupBy('term');

        return response()->json([
            'student' => $student->load('schoolClass'),
            'results' => $scores,
        ]);
    }

    /**
     * Get class results for admin or form teacher
     */
    public function getClassResults(Request $request, SchoolClass $class)
    {
        $user = $request->user();
        
        // Check if user is admin or form teacher of this class
        if (!$user->isAdmin() && $class->form_teacher_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        // Get academic session - default to current if not specified
        $academicSessionId = $request->academic_session_id ?? AcademicSession::current()?->id;

        $students = $class->students()
                         ->where('is_active', true)
                         ->with(['scores' => function ($query) use ($request, $academicSessionId) {
                             $query->where('is_active', true)
                                   ->with(['subject', 'teacher', 'academicSession']);
                             
                             // Filter by academic session if available
                             if ($academicSessionId) {
                                 $query->where('academic_session_id', $academicSessionId);
                             }
                             
                             // Filter by term if provided
                             if ($request->has('term') && $request->term !== 'current') {
                                 $query->where('term', $request->term);
                             }
                         }])
                      ->get();

        // Group scores by term
        $results = [];
        foreach ($students as $student) {
            $studentResults = $student->scores->groupBy('term');
            $results[] = [
                'student' => $student,
                'results' => $studentResults,
            ];
        }

        return response()->json([
            'class' => $class->load('formTeacher'),
            'results' => $results,
        ]);
    }
} 