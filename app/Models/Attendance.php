<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'academic_session_id',
        'term',
        'week',
        'day',
        'date',
        'status',
        'remark',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Get the student
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the class
     */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the subject
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the teacher
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the academic session
     */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Get attendance statistics for a student in a session/term
     */
    public static function getStudentStatistics($studentId, $sessionId, $term, $subjectId = null)
    {
        $query = static::where('student_id', $studentId)
                      ->where('academic_session_id', $sessionId)
                      ->where('term', $term);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        $total = $query->count();
        $present = $query->where('status', 'present')->count();
        $absent = $query->where('status', 'absent')->count();
        $late = $query->where('status', 'late')->count();
        $excused = $query->where('status', 'excused')->count();

        $attendanceRate = $total > 0 ? ($present / $total) * 100 : 0;

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused,
            'attendance_rate' => round($attendanceRate, 2),
        ];
    }

    /**
     * Get attendance statistics for a class
     */
    public static function getClassStatistics($classId, $sessionId, $term, $subjectId = null, $week = null)
    {
        $query = static::where('class_id', $classId)
                      ->where('academic_session_id', $sessionId)
                      ->where('term', $term);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        if ($week) {
            $query->where('week', $week);
        }

        $totalRecords = $query->count();
        $totalPresent = $query->where('status', 'present')->count();
        
        // Get unique students count
        $uniqueStudents = $query->distinct('student_id')->count('student_id');
        
        // Average attendance rate
        $avgAttendanceRate = $uniqueStudents > 0 ? ($totalPresent / max($totalRecords, 1)) * 100 : 0;

        return [
            'total_records' => $totalRecords,
            'total_present' => $totalPresent,
            'total_absent' => $query->where('status', 'absent')->count(),
            'unique_students' => $uniqueStudents,
            'average_attendance_rate' => round($avgAttendanceRate, 2),
        ];
    }
}

