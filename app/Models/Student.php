<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class Student extends Model
{
    use HasFactory, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'admission_number',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'parent_name',
        'parent_phone',
        'parent_email',
        'class_id',
        'is_active',
        'password',
        'admission_academic_session_id',
        'admission_term',
        'status',
        'promoted_this_session',
        'result_access_restricted',
        'result_restriction_message',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
        'promoted_this_session' => 'boolean',
        'result_access_restricted' => 'boolean',
    ];

    /**
     * Get the admission academic session
     */
    public function admissionAcademicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'admission_academic_session_id');
    }

    protected $hidden = [
        'password',
    ];

    /**
     * Get the student's full name
     */
    public function getFullNameAttribute()
    {
        $name = $this->first_name . ' ' . $this->last_name;
        if ($this->middle_name) {
            $name = $this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name;
        }
        return $name;
    }

    /**
     * Get the student's class
     */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the student's subjects
     */
    public function studentSubjects()
    {
        return $this->hasMany(StudentSubject::class);
    }

    /**
     * Get the student's scores
     */
    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    /**
     * Get the student's attendance records
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get scores for a specific subject
     */
    public function getScoresForSubject($subjectId)
    {
        return $this->scores()->where('subject_id', $subjectId)->first();
    }

    /**
     * Hash the password when setting it
     */
    public function setPasswordAttribute($value)
    {
        // Skip if value is null or empty
        if (empty($value)) {
            return;
        }
        
        // Only hash if the value is not already a bcrypt hash
        if (!$this->isBcryptHash($value)) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }
    
    /**
     * Check if a string is a valid bcrypt hash
     */
    private function isBcryptHash($password)
    {
        // Bcrypt hashes start with $2y$, $2a$, or $2b$ and are 60 characters long
        if (!is_string($password) || strlen($password) !== 60) {
            return false;
        }
        
        // Check for bcrypt hash prefixes
        return strpos($password, '$2y$') === 0 || 
               strpos($password, '$2a$') === 0 || 
               strpos($password, '$2b$') === 0;
    }

    /**
     * Verify the student's password
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password);
    }

    /**
     * Check if student is active
     */
    public function isActive()
    {
        return $this->is_active;
    }
} 