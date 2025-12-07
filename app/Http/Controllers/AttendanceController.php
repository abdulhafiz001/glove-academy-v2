<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Models\TeacherSubject;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Get classes that the teacher is assigned to teach
     */
    public function getTeacherClasses(Request $request)
    {
        $teacher = $request->user();

        $assignments = TeacherSubject::where('teacher_id', $teacher->id)
                                    ->where('is_active', true)
                                    ->with('schoolClass')
                                    ->get();

        $classes = $assignments->groupBy('class_id')
                              ->map(function ($group) {
                                  $class = $group->first()->schoolClass;
                                  return [
                                      'id' => $class->id,
                                      'name' => $class->name,
                                      'subjects' => $group->map(function ($assignment) {
                                          return [
                                              'id' => $assignment->subject->id,
                                              'name' => $assignment->subject->name,
                                          ];
                                      })->values(),
                                  ];
                              })
                              ->values();

        return response()->json(['data' => $classes]);
    }

    /**
     * Get students for a specific class and subject
     */
    public function getClassStudents(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);

        $teacher = $request->user();

        // Verify teacher is assigned to this class and subject
        $assignment = TeacherSubject::where('teacher_id', $teacher->id)
                                   ->where('class_id', $request->class_id)
                                   ->where('subject_id', $request->subject_id)
                                   ->where('is_active', true)
                                   ->first();

        if (!$assignment) {
            return response()->json(['message' => 'You are not assigned to teach this subject in this class'], 403);
        }

        // Get students in this class who are offering this subject
        $students = Student::where('class_id', $request->class_id)
                          ->where('is_active', true)
                          ->whereHas('studentSubjects', function ($query) use ($request) {
                              $query->where('subject_id', $request->subject_id)
                                    ->where('is_active', true);
                          })
                          ->with(['studentSubjects' => function ($query) use ($request) {
                              $query->where('subject_id', $request->subject_id);
                          }])
                          ->orderBy('first_name')
                          ->get();

        return response()->json(['data' => $students]);
    }

    /**
     * Mark attendance for students
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'week' => 'required|integer|min:1|max:14',
            'day' => 'required|string',
            'date' => 'required|date',
            'attendances' => 'required|array',
            'attendances.*.student_id' => 'required|exists:students,id',
            'attendances.*.status' => 'required|in:present,absent,late,excused',
            'attendances.*.remark' => 'nullable|string',
        ]);

        $teacher = $request->user();

        // Verify teacher is assigned
        $assignment = TeacherSubject::where('teacher_id', $teacher->id)
                                   ->where('class_id', $request->class_id)
                                   ->where('subject_id', $request->subject_id)
                                   ->where('is_active', true)
                                   ->first();

        if (!$assignment) {
            return response()->json(['message' => 'You are not assigned to teach this subject in this class'], 403);
        }

        $currentSession = AcademicSession::current();
        $currentTerm = Term::current();

        if (!$currentSession || !$currentTerm) {
            return response()->json(['message' => 'No current academic session or term set'], 422);
        }

        DB::beginTransaction();
        try {
            $created = 0;
            $updated = 0;

            foreach ($request->attendances as $attendanceData) {
                $attendance = Attendance::updateOrCreate(
                    [
                        'student_id' => $attendanceData['student_id'],
                        'class_id' => $request->class_id,
                        'subject_id' => $request->subject_id,
                        'academic_session_id' => $currentSession->id,
                        'term' => $currentTerm->name,
                        'week' => $request->week,
                        'day' => $request->day,
                        'date' => $request->date,
                    ],
                    [
                        'teacher_id' => $teacher->id,
                        'status' => $attendanceData['status'],
                        'remark' => $attendanceData['remark'] ?? null,
                    ]
                );

                if ($attendance->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Attendance marked successfully',
                'created' => $created,
                'updated' => $updated,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to mark attendance: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get attendance records for a class/subject/week
     */
    public function getAttendanceRecords(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'week' => 'nullable|integer|min:1|max:14',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'term' => 'nullable|string',
        ]);

        $currentSession = AcademicSession::current();
        $currentTerm = Term::current();

        $sessionId = $request->academic_session_id ?? $currentSession->id;
        $term = $request->term ?? $currentTerm->name;

        $query = Attendance::where('class_id', $request->class_id)
                          ->where('subject_id', $request->subject_id)
                          ->where('academic_session_id', $sessionId)
                          ->where('term', $term);

        if ($request->week) {
            $query->where('week', $request->week);
        }

        $attendances = $query->with(['student', 'teacher'])
                            ->orderBy('date')
                            ->orderBy('student_id')
                            ->get();

        // Group by student
        $grouped = $attendances->groupBy('student_id')->map(function ($records, $studentId) {
            $student = $records->first()->student;
            return [
                'student_id' => $studentId,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'admission_number' => $student->admission_number,
                ],
                'records' => $records->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'week' => $record->week,
                        'day' => $record->day,
                        'date' => $record->date->format('Y-m-d'),
                        'status' => $record->status,
                        'remark' => $record->remark,
                    ];
                }),
                'statistics' => [
                    'total' => $records->count(),
                    'present' => $records->where('status', 'present')->count(),
                    'absent' => $records->where('status', 'absent')->count(),
                    'late' => $records->where('status', 'late')->count(),
                    'excused' => $records->where('status', 'excused')->count(),
                ],
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    /**
     * Get attendance statistics for admin analysis
     */
    public function getAttendanceStatistics(Request $request)
    {
        $request->validate([
            'class_id' => 'nullable|exists:classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'week' => 'nullable|integer|min:1|max:14',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'term' => 'nullable|string',
        ]);

        $currentSession = AcademicSession::current();
        $currentTerm = Term::current();

        // Get session ID - use request value or fallback to current session
        if ($request->academic_session_id) {
            $sessionId = $request->academic_session_id;
        } elseif ($currentSession) {
            $sessionId = $currentSession->id;
        } else {
            return response()->json([
                'error' => 'No academic session specified and no current session set'
            ], 400);
        }

        // Get term - use request value or fallback to current term
        if ($request->term) {
            $term = $request->term;
        } elseif ($currentTerm) {
            $term = $currentTerm->name;
        } else {
            return response()->json([
                'error' => 'No term specified and no current term set'
            ], 400);
        }

        // Build base query
        $baseQuery = Attendance::where('academic_session_id', $sessionId)
                              ->where('term', $term);

        if ($request->class_id) {
            $baseQuery->where('class_id', $request->class_id);
        }

        if ($request->subject_id) {
            $baseQuery->where('subject_id', $request->subject_id);
        }

        if ($request->week) {
            $baseQuery->where('week', $request->week);
        }

        // Overall statistics - clone the query for each count
        $totalRecords = $baseQuery->count();
        $totalPresent = (clone $baseQuery)->where('status', 'present')->count();
        $totalAbsent = (clone $baseQuery)->where('status', 'absent')->count();
        $uniqueStudents = (clone $baseQuery)->distinct('student_id')->count('student_id');

        // Per-student statistics - only show students who have attendance records
        $studentQuery = Student::where('is_active', true);
        
        if ($request->class_id) {
            $studentQuery->where('class_id', $request->class_id);
        }
        
        // Only get students who have attendance records for this session/term
        $studentsWithAttendance = (clone $baseQuery)
            ->distinct('student_id')
            ->pluck('student_id');
        
        $studentStats = Student::whereIn('id', $studentsWithAttendance)
                          ->when($request->class_id, function ($q) use ($request) {
                              $q->where('class_id', $request->class_id);
                          })
                          ->where('is_active', true)
                          ->with(['attendances' => function ($q) use ($sessionId, $term, $request) {
                              $q->where('academic_session_id', $sessionId)
                                ->where('term', $term);
                              
                              if ($request->subject_id) {
                                  $q->where('subject_id', $request->subject_id);
                              }
                              
                              if ($request->week) {
                                  $q->where('week', $request->week);
                              }
                          }])
                          ->get()
                          ->map(function ($student) {
                              $attendances = $student->attendances;
                              $total = $attendances->count();
                              $present = $attendances->where('status', 'present')->count();
                              $absent = $attendances->where('status', 'absent')->count();
                              
                              return [
                                  'student_id' => $student->id,
                                  'student_name' => $student->full_name,
                                  'admission_number' => $student->admission_number,
                                  'total' => $total,
                                  'present' => $present,
                                  'absent' => $absent,
                                  'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
                              ];
                          })
                          ->filter(function ($stat) {
                              return $stat['total'] > 0; // Only include students with attendance records
                          })
                          ->sortByDesc('attendance_rate')
                          ->values();

        // Class-wise statistics if no class filter - only show classes with attendance
        $classStats = [];
        if (!$request->class_id) {
            // Get classes that have attendance records
            $classesWithAttendance = (clone $baseQuery)
                ->distinct('class_id')
                ->pluck('class_id');
            
            $classes = SchoolClass::whereIn('id', $classesWithAttendance)
                                ->where('is_active', true)
                                ->get();
            
            foreach ($classes as $class) {
                $classBaseQuery = Attendance::where('class_id', $class->id)
                                           ->where('academic_session_id', $sessionId)
                                           ->where('term', $term);
                
                if ($request->subject_id) {
                    $classBaseQuery->where('subject_id', $request->subject_id);
                }
                
                if ($request->week) {
                    $classBaseQuery->where('week', $request->week);
                }
                
                $classTotal = $classBaseQuery->count();
                $classPresent = (clone $classBaseQuery)->where('status', 'present')->count();
                $classStudents = (clone $classBaseQuery)->distinct('student_id')->count('student_id');
                
                // Only add classes that have attendance records
                if ($classTotal > 0) {
                    $classStats[] = [
                        'class_id' => $class->id,
                        'class_name' => $class->name,
                        'total_records' => $classTotal,
                        'total_present' => $classPresent,
                        'total_students' => $classStudents,
                        'average_attendance_rate' => $classTotal > 0 ? round(($classPresent / $classTotal) * 100, 2) : 0,
                    ];
                }
            }
        }

        return response()->json([
            'data' => [
                'overall' => [
                    'total_records' => $totalRecords,
                    'total_present' => $totalPresent,
                    'total_absent' => $totalAbsent,
                    'unique_students' => $uniqueStudents,
                    'average_attendance_rate' => $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 2) : 0,
                ],
                'by_student' => $studentStats,
                'by_class' => $classStats,
            ],
        ]);
    }
}

