<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\StudentSubject;
use App\Models\Score;
use App\Models\TeacherSubject;

class StudentController extends Controller
{
    /**
     * Get all students (admin)
     */
    public function index(Request $request)
    {
        $query = Student::with(['schoolClass', 'studentSubjects.subject'])
                       ->where('is_active', true);

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

        $students = $query->paginate(20);

        return response()->json($students);
    }

    /**
     * Create a new student (admin or form teacher)
     */
    public function store(Request $request)
    {
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

        // Check if the authenticated user is a form teacher for this class
        if (auth()->user()->isTeacher()) {
            $isFormTeacher = TeacherSubject::where('teacher_id', auth()->id())
                                          ->where('class_id', $request->class_id)
                                          ->exists();
            
            if (!$isFormTeacher) {
                return response()->json(['message' => 'Only the form teacher can add students to this class'], 403);
            }
        }

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
        ]);

        // Assign subjects to student
        foreach ($request->subjects as $subjectId) {
            StudentSubject::create([
                'student_id' => $student->id,
                'subject_id' => $subjectId,
                'is_active' => true,
            ]);
        }

        return response()->json([
            'message' => 'Student created successfully',
            'student' => $student->load(['schoolClass', 'studentSubjects.subject']),
        ], 201);
    }

    /**
     * Get a specific student
     */
    public function show(Student $student)
    {
        return response()->json($student->load(['schoolClass', 'studentSubjects.subject']));
    }

    /**
     * Update a student
     */
    public function update(Request $request, Student $student)
    {
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
            'is_active' => 'boolean',
        ]);

        $student->update($request->all());

        return response()->json([
            'message' => 'Student updated successfully',
            'student' => $student->load(['schoolClass', 'studentSubjects.subject']),
        ]);
    }

    /**
     * Delete a student
     */
    public function destroy(Student $student)
    {
        $student->update(['is_active' => false]);
        
        return response()->json(['message' => 'Student deactivated successfully']);
    }

    /**
     * Student dashboard (for student access) with caching
     */
    public function dashboard(Request $request)
    {
        $student = $request->user();
        $studentId = $student->id;
        
        $data = \App\Helpers\CacheHelper::getDashboardStats('student', $studentId, function () use ($student) {
            $scores = Score::with(['subject', 'schoolClass'])
                          ->where('student_id', $student->id)
                          ->where('is_active', true)
                          ->latest('created_at')
                          ->get();

            $totalSubjects = $student->studentSubjects()->count();
            $completedSubjects = $scores->count();

            return [
                'student' => $student->load(['schoolClass.formTeacher']),
                'stats' => [
                    'total_subjects' => $totalSubjects,
                    'completed_subjects' => $completedSubjects,
                ],
                'recent_scores' => $scores->take(5),
            ];
        });

        return response()->json($data);
    }

    /**
     * Get student subjects (for student access)
     */
    public function getSubjects(Request $request)
    {
        $student = $request->user();
        
        // Get the student's subjects through the StudentSubject relationship
        $studentSubjects = StudentSubject::with(['subject'])
                                       ->where('student_id', $student->id)
                                       ->where('is_active', true)
                                       ->get();
        
        // Transform the data to include additional information
        $subjects = $studentSubjects->map(function ($studentSubject) {
            $subject = $studentSubject->subject;
            
            // Get the latest score for this subject to calculate progress and grade
            $latestScore = Score::where('student_id', $studentSubject->student_id)
                               ->where('subject_id', $subject->id)
                               ->where('is_active', true)
                               ->latest()
                               ->first();
            
            // Calculate progress based on completed assessments
            $progress = 0;
            if ($latestScore) {
                $completedAssessments = 0;
                if ($latestScore->first_ca !== null) $completedAssessments++;
                if ($latestScore->second_ca !== null) $completedAssessments++;
                if ($latestScore->exam_score !== null) $completedAssessments++;
                $progress = round(($completedAssessments / 3) * 100);
            }
            
            // Calculate grade if all scores are available
            $grade = 'N/A';
            if ($latestScore && $latestScore->first_ca !== null && 
                $latestScore->second_ca !== null && $latestScore->exam_score !== null) {
                $total = $latestScore->first_ca + $latestScore->second_ca + $latestScore->exam_score;
                
                if ($total >= 80) $grade = 'A';
                elseif ($total >= 70) $grade = 'B';
                elseif ($total >= 60) $grade = 'C';
                elseif ($total >= 50) $grade = 'D';
                elseif ($total >= 40) $grade = 'E';
                else $grade = 'F';
            }
            
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'description' => $subject->description || 'Subject description not available',
                'progress' => $progress,
                'grade' => $grade,
                'color' => $this->getSubjectColor($subject->name),
                'icon' => $this->getSubjectIcon($subject->name),

                'latest_score' => $latestScore ? [
                    'first_ca' => $latestScore->first_ca,
                    'second_ca' => $latestScore->second_ca,
                    'exam_score' => $latestScore->exam_score,
                    'total' => $latestScore->total_score,
                    'term' => $latestScore->term
                ] : null
            ];
        });
        
        return response()->json($subjects);
    }
    
    /**
     * Get subject color based on subject name
     */
    private function getSubjectColor($subjectName)
    {
        $colors = [
            'Mathematics' => 'from-blue-500 to-blue-600',
            'English' => 'from-red-500 to-red-600',
            'Physics' => 'from-purple-500 to-purple-600',
            'Chemistry' => 'from-green-500 to-green-600',
            'Biology' => 'from-emerald-500 to-emerald-600',
            'Computer Science' => 'from-indigo-500 to-indigo-600',
            'Literature' => 'from-pink-500 to-pink-600',
            'History' => 'from-yellow-500 to-yellow-600',
            'Geography' => 'from-orange-500 to-orange-600',
            'Economics' => 'from-teal-500 to-teal-600',
        ];
        
        return $colors[$subjectName] ?? 'from-gray-500 to-gray-600';
    }
    
    /**
     * Get subject icon based on subject name
     */
    private function getSubjectIcon($subjectName)
    {
        $icons = [
            'Mathematics' => '📐',
            'English' => '📚',
            'Physics' => '⚡',
            'Chemistry' => '🧪',
            'Biology' => '🔬',
            'Computer Science' => '💻',
            'Literature' => '📖',
            'History' => '🏛️',
            'Geography' => '🌍',
            'Economics' => '💰',
        ];
        
        return $icons[$subjectName] ?? '📚';
    }
    


    /**
     * Get student results (for student access)
     * Students can only see results from their admission session/term onwards
     */
    public function getResults(Request $request)
    {
        $student = $request->user();
        
        // Check if result access is restricted
        if ($student->result_access_restricted) {
            return response()->json([
                'restricted' => true,
                'message' => $student->result_restriction_message ?? 'Your result access has been restricted. Please complete your school fees to view your results.',
            ], 403);
        }
        
        // Load student with admission session
        $student->load('admissionAcademicSession');
        
        // Get academic session - default to current if not specified
        $requestedSessionId = $request->academic_session_id;
        $currentSession = \App\Models\AcademicSession::current();
        
        $query = Score::with(['subject', 'academicSession'])
                      ->where('student_id', $student->id)
                      ->where('is_active', true);
        
        // Filter: Students can only see results from their admission session/term onwards
        if ($student->admission_academic_session_id) {
            // Get admission session and term
            $admissionSession = \App\Models\AcademicSession::find($student->admission_academic_session_id);
            $admissionTerm = $student->admission_term;
            
            if ($admissionSession) {
                // Only show results from admission session onwards
                $query->where(function($q) use ($admissionSession, $admissionTerm) {
                    // Results from sessions after admission session
                    $q->whereHas('academicSession', function($sessionQuery) use ($admissionSession) {
                        $sessionQuery->where('start_date', '>', $admissionSession->start_date);
                    });
                    
                    // OR results from admission session but from admission term onwards
                    $q->orWhere(function($termQuery) use ($admissionSession, $admissionTerm) {
                        $termQuery->where('academic_session_id', $admissionSession->id);
                        
                        // Filter by term - first term = can see all, second = second and third, third = only third
                        if ($admissionTerm === 'first') {
                            // Can see all terms in admission session
                        } elseif ($admissionTerm === 'second') {
                            $termQuery->whereIn('term', ['second', 'third']);
                        } elseif ($admissionTerm === 'third') {
                            $termQuery->where('term', 'third');
                        }
                    });
                });
            }
        }
        
        // Filter by requested academic session if specified
        if ($requestedSessionId) {
            $query->where('academic_session_id', $requestedSessionId);
        }
        
        $scores = $query->get();

        // Group scores by academic session and term
        $resultsBySession = [];
        foreach ($scores as $score) {
            $sessionName = $score->academicSession->name ?? 'Unknown Session';
            $term = ucfirst($score->term ?? 'first') . ' Term';
            
            if (!isset($resultsBySession[$sessionName])) {
                $resultsBySession[$sessionName] = [];
            }
            if (!isset($resultsBySession[$sessionName][$term])) {
                $resultsBySession[$sessionName][$term] = [];
            }
            $resultsBySession[$sessionName][$term][] = $score;
        }

        // Get class history for each session to show correct class
        $classHistory = [];
        foreach ($resultsBySession as $sessionName => $terms) {
            // Find the academic session by name
            $session = \App\Models\AcademicSession::where('name', $sessionName)->first();
            if ($session) {
                $history = \App\Models\StudentClassHistory::where('student_id', $student->id)
                    ->where('academic_session_id', $session->id)
                    ->with('schoolClass')
                    ->first();
                if ($history) {
                    $classHistory[$sessionName] = $history->schoolClass;
                }
            }
        }
        
        return response()->json([
            'student' => $student->load(['schoolClass', 'studentSubjects.subject']),
            'results' => $resultsBySession,
            'class_history' => $classHistory, // Class for each session
            'current_session' => $currentSession,
            'admission_session' => $student->admissionAcademicSession,
            'admission_term' => $student->admission_term,
        ]);
    }

    /**
     * Get student profile (for student access)
     */
    public function getProfile(Request $request)
    {
        $student = $request->user();
        
        return response()->json($student->load(['schoolClass', 'studentSubjects.subject']));
    }

    /**
     * Update student profile (for student access)
     */
    public function updateProfile(Request $request)
    {
        $student = $request->user();
        
        $request->validate([
            'email' => 'nullable|email|unique:students,email,' . $student->id,
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'parent_phone' => 'nullable|string',
            'parent_email' => 'nullable|email',
        ]);

        $student->update($request->only([
            'email', 'phone', 'address', 'parent_phone', 'parent_email'
        ]));

        return response()->json([
            'message' => 'Profile updated successfully',
            'student' => $student->load(['schoolClass', 'studentSubjects.subject']),
        ]);
    }

    /**
     * Change student password
     */
    public function changePassword(Request $request)
    {
        $student = $request->user();
        
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);

        // Check current password
        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $student->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $student->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
} 