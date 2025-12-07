<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\StudentsImport;
use App\Imports\ScoresImport;
use App\Models\Student;
use App\Models\Score;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\AcademicSession;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportController extends Controller
{
    /**
     * Import students from Excel/CSV file
     */
    public function importStudents(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // 10MB max
            'class_id' => 'nullable|exists:classes,id',
        ]);

        $user = $request->user();
        
        // For teachers, class_id is required
        if ($user->role === 'teacher' && !$request->has('class_id')) {
            return response()->json([
                'message' => 'Class ID is required for teachers.',
            ], 400);
        }
        
        // If class_id is provided, verify permissions
        if ($request->has('class_id')) {
            $classId = $request->class_id;
            
            // Check if admin or form teacher of this class
            if ($user->role === 'teacher') {
                $isFormTeacher = \App\Models\SchoolClass::where('id', $classId)
                    ->where('form_teacher_id', $user->id)
                    ->where('is_active', true)
                    ->exists();
                
                if (!$isFormTeacher) {
                    return response()->json([
                        'message' => 'Unauthorized. You can only import students to classes where you are a form teacher.',
                    ], 403);
                }
            }
        }

        try {
            $import = new StudentsImport($request->class_id);
            Excel::import($import, $request->file('file'));

            $successCount = $import->getSuccessCount();
            $errors = $import->getErrors();

            return response()->json([
                'message' => 'Import completed',
                'success_count' => $successCount,
                'error_count' => count($errors),
                'errors' => $errors,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import scores from Excel/CSV file
     */
    public function importScores(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
            'class_id' => 'nullable|exists:classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
        ]);

        $user = $request->user();
        $teacherId = $user->id;
        $classId = $request->class_id;
        $subjectId = $request->subject_id;

        // If class_id and subject_id are provided, validate teacher assignment
        if ($classId && $subjectId) {
            // Check if user is a teacher assigned to this class and subject
            if ($user->role === 'teacher') {
                $isAssigned = \App\Models\TeacherSubject::where('teacher_id', $teacherId)
                    ->where('class_id', $classId)
                    ->where('subject_id', $subjectId)
                    ->where('is_active', true)
                    ->exists();
                
                if (!$isAssigned) {
                    return response()->json([
                        'message' => 'Unauthorized. You are not assigned to teach this subject in this class.',
                    ], 403);
                }
            } elseif (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Unauthorized. Only teachers and admins can import scores.',
                ], 403);
            }
        }

        try {
            $import = new ScoresImport($teacherId, $classId, $subjectId);
            Excel::import($import, $request->file('file'));

            $successCount = $import->getSuccessCount();
            $errors = $import->getErrors();

            return response()->json([
                'message' => 'Import completed',
                'success_count' => $successCount,
                'error_count' => count($errors),
                'errors' => $errors,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export students to Excel/CSV
     */
    public function exportStudents(Request $request)
    {
        $students = Student::with('schoolClass')
            ->where('is_active', true)
            ->get();

        $data = $students->map(function ($student) {
            return [
                'Admission Number' => $student->admission_number,
                'First Name' => $student->first_name,
                'Middle Name' => $student->middle_name ?? '',
                'Last Name' => $student->last_name,
                'Class' => $student->schoolClass->name ?? '',
                'Email' => $student->email ?? '',
                'Phone' => $student->phone ?? '',
                'Date of Birth' => $student->date_of_birth ? $student->date_of_birth->format('Y-m-d') : '',
                'Gender' => $student->gender ?? '',
                'Parent Name' => $student->parent_name ?? '',
                'Parent Phone' => $student->parent_phone ?? '',
                'Parent Email' => $student->parent_email ?? '',
            ];
        });

        return Excel::download(new class($data) implements FromCollection, WithHeadings {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Admission Number',
                    'First Name',
                    'Middle Name',
                    'Last Name',
                    'Class',
                    'Email',
                    'Phone',
                    'Date of Birth',
                    'Gender',
                    'Parent Name',
                    'Parent Phone',
                    'Parent Email',
                ];
            }
        }, 'students_export_' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Export scores/results to Excel/CSV
     */
    public function exportScores(Request $request)
    {
        $request->validate([
            'class_id' => 'nullable|exists:classes,id',
            'term' => 'nullable|in:first,second,third',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
        ]);

        $query = Score::with(['student', 'subject', 'schoolClass'])
            ->where('is_active', true);

        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->has('term')) {
            $query->where('term', $request->term);
        }

        $academicSessionId = $request->academic_session_id ?? AcademicSession::current()?->id;
        if ($academicSessionId) {
            $query->where('academic_session_id', $academicSessionId);
        }

        $scores = $query->get();

        $data = $scores->map(function ($score) {
            return [
                'Admission Number' => $score->student->admission_number ?? '',
                'Student Name' => ($score->student->first_name ?? '') . ' ' . ($score->student->last_name ?? ''),
                'Class' => $score->schoolClass->name ?? '',
                'Subject' => $score->subject->name ?? '',
                'Term' => ucfirst($score->term),
                'First CA' => $score->first_ca ?? '',
                'Second CA' => $score->second_ca ?? '',
                'Exam Score' => $score->exam_score ?? '',
                'Total Score' => $score->total_score ?? '',
                'Grade' => $score->grade ?? '',
                'Remark' => $score->remark ?? '',
            ];
        });

        return Excel::download(new class($data) implements FromCollection, WithHeadings {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Admission Number',
                    'Student Name',
                    'Class',
                    'Subject',
                    'Term',
                    'First CA',
                    'Second CA',
                    'Exam Score',
                    'Total Score',
                    'Grade',
                    'Remark',
                ];
            }
        }, 'scores_export_' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Download template for student import
     */
    public function downloadStudentTemplate(Request $request)
    {
        // Ensure user is authenticated and is admin or teacher
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Allow admins and teachers (form teachers can import students)
        if (!$user->isAdmin() && $user->role !== 'teacher') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $headers = [
            'Admission Number',
            'First Name',
            'Middle Name',
            'Last Name',
            'Email',
            'Phone',
            'Date of Birth',
            'Gender',
            'Parent Name',
            'Parent Phone',
            'Parent Email',
        ];

        $data = collect([$headers]);

        return Excel::download(new class($data) implements FromCollection {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return $this->data;
            }
        }, 'student_import_template.xlsx');
    }

    /**
     * Download template for score import
     */
    public function downloadScoreTemplate(Request $request)
    {
        $user = $request->user();
        
        // Check if class_id and subject_id are provided (for teacher imports)
        $classId = $request->query('class_id');
        $subjectId = $request->query('subject_id');
        
        // If class_id and subject_id are provided, validate teacher assignment and generate template with students
        if ($classId && $subjectId) {
            // Validate teacher assignment if user is a teacher
            if ($user->role === 'teacher') {
                $isAssigned = \App\Models\TeacherSubject::where('teacher_id', $user->id)
                    ->where('class_id', $classId)
                    ->where('subject_id', $subjectId)
                    ->where('is_active', true)
                    ->exists();
                
                if (!$isAssigned) {
                    return response()->json([
                        'message' => 'Unauthorized. You are not assigned to teach this subject in this class.',
                    ], 403);
                }
            } elseif (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'Unauthorized. Only teachers and admins can download score templates.',
                ], 403);
            }
            
            // Get students in the class who are assigned to this subject
            $class = SchoolClass::find($classId);
            $subject = Subject::find($subjectId);
            
            if (!$class || !$subject) {
                return response()->json([
                    'message' => 'Class or subject not found.',
                ], 404);
            }
            
            // Get students in the class who are taking this subject
            $students = Student::where('class_id', $classId)
                ->where('is_active', true)
                ->whereHas('studentSubjects', function ($query) use ($subjectId) {
                    $query->where('subject_id', $subjectId)
                          ->where('is_active', true);
                })
                ->orderBy('admission_number')
                ->get();
            
            // Build template data with student rows
            $headers = [
                'Admission Number',
                'Student Name',
                'Term',
                'First CA',
                'Second CA',
                'Exam Score',
                'Remark',
            ];
            
            $data = collect([$headers]);
            
            // Add a row for each student with pre-filled admission number and name
            foreach ($students as $student) {
                $fullName = trim(($student->first_name ?? '') . ' ' . ($student->middle_name ?? '') . ' ' . ($student->last_name ?? ''));
                $data->push([
                    $student->admission_number,
                    $fullName,
                    '', // Term - to be filled by user
                    '', // First CA
                    '', // Second CA
                    '', // Exam Score
                    '', // Remark
                ]);
            }
        } else {
            // Generate generic template (for admin imports without class/subject selection)
            $headers = [
                'Admission Number',
                'Subject',
                'Class',
                'Term',
                'First CA',
                'Second CA',
                'Exam Score',
                'Remark',
            ];
            
            $data = collect([$headers]);
        }

        return Excel::download(new class($data) implements FromCollection {
            protected $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return $this->data;
            }
        }, 'score_import_template.xlsx');
    }
}

