<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentClassHistory extends Model
{
    use HasFactory;

    protected $table = 'student_class_history';

    protected $fillable = [
        'student_id',
        'academic_session_id',
        'class_id',
    ];

    /**
     * Get the student
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the academic session
     */
    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Get the class
     */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get or create class history for a student in an academic session
     */
    public static function getOrCreate($studentId, $academicSessionId, $classId)
    {
        return static::firstOrCreate(
            [
                'student_id' => $studentId,
                'academic_session_id' => $academicSessionId,
            ],
            [
                'class_id' => $classId,
            ]
        );
    }

    /**
     * Update class history for a student in an academic session
     */
    public static function updateHistory($studentId, $academicSessionId, $classId)
    {
        return static::updateOrCreate(
            [
                'student_id' => $studentId,
                'academic_session_id' => $academicSessionId,
            ],
            [
                'class_id' => $classId,
            ]
        );
    }
}
