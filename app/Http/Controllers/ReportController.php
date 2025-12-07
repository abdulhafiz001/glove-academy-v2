<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\Student;
use App\Models\Score;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Models\StudentClassHistory;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Generate PDF report card for a student
     */
    public function generateStudentReport(Request $request, $studentId = null)
    {
        $request->validate([
            'term' => 'required|in:first,second,third',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
        ]);

        // If studentId is not provided, get from authenticated student
        if (!$studentId) {
            $user = $request->user();
            
            // Check if user is a Student instance
            if ($user instanceof Student) {
                $studentId = $user->id;
            } elseif ($user && method_exists($user, 'isStudent')) {
                // This is for authenticated student access
                // Find student by user email or create a mapping
                $student = Student::where('email', $user->email)->first();
                if (!$student) {
                    // Try by admission number if that's how they login
                    $student = Student::where('admission_number', $user->username ?? '')->first();
                }
                if (!$student) {
                    return response()->json(['message' => 'Student record not found'], 404);
                }
                $studentId = $student->id;
            } else {
                return response()->json(['message' => 'Student ID required'], 422);
            }
        }

        $student = Student::with('schoolClass')->findOrFail($studentId);
        
        // Get academic session
        $academicSessionId = $request->academic_session_id ?? AcademicSession::current()?->id;
        if (!$academicSessionId) {
            return response()->json([
                'message' => 'No academic session specified or available'
            ], 422);
        }

        $academicSession = AcademicSession::findOrFail($academicSessionId);
        $term = $request->term;
        
        // Get the class the student was in during this specific academic session
        // This ensures we show the correct class even if the student was promoted later
        $classHistory = StudentClassHistory::where('student_id', $studentId)
            ->where('academic_session_id', $academicSessionId)
            ->with('schoolClass')
            ->first();
        
        // Use the class from history if available, otherwise use current class
        $sessionClass = $classHistory ? $classHistory->schoolClass : $student->schoolClass;

        // Get scores for this student, term, and session
        $scores = Score::with(['subject', 'teacher'])
            ->where('student_id', $studentId)
            ->where('term', $term)
            ->where('academic_session_id', $academicSessionId)
            ->where('is_active', true)
            ->get();

        // Calculate totals
        $totalScore = $scores->sum('total_score');
        $averageScore = $scores->count() > 0 ? round($totalScore / $scores->count(), 2) : 0;

        // Determine overall grade
        $overallGrade = $this->calculateGrade($averageScore);
        
        // Get promotion status for third term
        $promotionStatus = null;
        $isThirdTerm = strtolower($term) === 'third';
        
        // Calculate third term final average (Nigerian school system)
        // Final Average = (First Term Average + Second Term Average + Third Term Average) / 3
        $thirdTermFinalAverage = null;
        if ($isThirdTerm) {
            // Get scores for all three terms in this academic session
            $firstTermScores = Score::with(['subject', 'teacher'])
                ->where('student_id', $studentId)
                ->where('term', 'first')
                ->where('academic_session_id', $academicSessionId)
                ->where('is_active', true)
                ->get();
            
            $secondTermScores = Score::with(['subject', 'teacher'])
                ->where('student_id', $studentId)
                ->where('term', 'second')
                ->where('academic_session_id', $academicSessionId)
                ->where('is_active', true)
                ->get();
            
            // Calculate averages for each term
            $firstTermTotal = $firstTermScores->sum('total_score');
            $firstTermAverage = $firstTermScores->count() > 0 ? round($firstTermTotal / $firstTermScores->count(), 2) : null;
            
            $secondTermTotal = $secondTermScores->sum('total_score');
            $secondTermAverage = $secondTermScores->count() > 0 ? round($secondTermTotal / $secondTermScores->count(), 2) : null;
            
            $thirdTermAverage = $averageScore; // Current term average
            
            // Calculate final average if we have all three term averages
            if ($firstTermAverage !== null && $secondTermAverage !== null && $thirdTermAverage !== null) {
                $finalAverage = round(($firstTermAverage + $secondTermAverage + $thirdTermAverage) / 3, 2);
                $thirdTermFinalAverage = [
                    'first_term_average' => $firstTermAverage,
                    'second_term_average' => $secondTermAverage,
                    'third_term_average' => $thirdTermAverage,
                    'final_average' => $finalAverage
                ];
            }
            
            // Refresh student to get latest status
            $student->refresh();
            
            // Check status in order of priority
            if ($student->status === 'graduated') {
                $promotionStatus = 'graduated';
            } elseif ($student->status === 'repeated') {
                $promotionStatus = 'repeated';
            } elseif ($student->promoted_this_session) {
                // Student was promoted this session
                $promotionStatus = 'promoted';
            } elseif ($student->status === 'active') {
                // If still active and not marked as promoted, check if they should be
                // This handles cases where promotion hasn't been run yet
                // We'll show no status in this case
                $promotionStatus = null;
            }
        }

        // School information (you can make this configurable later)
        $schoolInfo = [
            'name' => 'G-LOVE ACADEMY',
            'address' => 'BESIDE ASSEMBLIES OF GOD CHURCH ZONE 9 LUGBE ABUJA',
            'phone' => '08125275999',
            'email' => '',
            'logo_path' => public_path('images/G-LOVE ACADEMY.jpeg'),
        ];

        // Create a student object with the session-specific class for display
        $studentForDisplay = clone $student;
        $studentForDisplay->setRelation('schoolClass', $sessionClass);
        
        $data = [
            'student' => $studentForDisplay,
            'scores' => $scores,
            'term' => ucfirst($term) . ' Term',
            'academicSession' => $academicSession,
            'totalScore' => $totalScore,
            'averageScore' => $averageScore,
            'overallGrade' => $overallGrade,
            'schoolInfo' => $schoolInfo,
            'promotionStatus' => $promotionStatus,
            'isThirdTerm' => $isThirdTerm,
            'thirdTermFinalAverage' => $thirdTermFinalAverage,
        ];

        $pdf = Pdf::loadView('reports.student-report-card', $data);
        
        // Sanitize filename - remove invalid characters (/, \, and other special chars)
        // Sanitize all parts of the filename
        $admissionNumber = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $student->admission_number);
        $sessionName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $academicSession->name);
        $termName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $term);
        $filename = "{$admissionNumber}_{$termName}_term_{$sessionName}.pdf";
        
        // Use streamDownload to avoid filename validation issues
        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Calculate grade based on score
     */
    private function calculateGrade($score)
    {
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        if ($score >= 40) return 'E';
        return 'F';
    }

    /**
     * Generate class result summary PDF
     */
    public function generateClassReport(Request $request, $classId)
    {
        $request->validate([
            'term' => 'required|in:first,second,third',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
        ]);

        $class = \App\Models\SchoolClass::findOrFail($classId);
        $academicSessionId = $request->academic_session_id ?? AcademicSession::current()?->id;
        
        if (!$academicSessionId) {
            return response()->json([
                'message' => 'No academic session specified or available'
            ], 422);
        }

        $academicSession = AcademicSession::findOrFail($academicSessionId);
        $term = $request->term;

        // Get all students in class with their scores
        $students = Student::where('class_id', $classId)
            ->where('is_active', true)
            ->with(['scores' => function($query) use ($term, $academicSessionId) {
                $query->where('term', $term)
                      ->where('academic_session_id', $academicSessionId)
                      ->where('is_active', true)
                      ->with('subject');
            }])
            ->get();

        // Process student results
        $studentResults = $students->map(function($student) {
            $scores = $student->scores;
            $totalScore = $scores->sum('total_score');
            $averageScore = $scores->count() > 0 ? round($totalScore / $scores->count(), 2) : 0;
            
            return [
                'student' => $student,
                'scores' => $scores,
                'totalScore' => $totalScore,
                'averageScore' => $averageScore,
                'overallGrade' => $this->calculateGrade($averageScore),
            ];
        });

        $data = [
            'class' => $class,
            'studentResults' => $studentResults,
            'term' => ucfirst($term) . ' Term',
            'academicSession' => $academicSession,
        ];

        $pdf = Pdf::loadView('reports.class-report', $data);
        
        // Sanitize filename - remove invalid characters (/, \, and other special chars)
        $sessionName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $academicSession->name);
        $className = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $class->name);
        $termName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $term);
        $filename = "{$className}_{$termName}_term_{$sessionName}.pdf";
        
        // Use streamDownload to avoid filename validation issues
        return Response::streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}

