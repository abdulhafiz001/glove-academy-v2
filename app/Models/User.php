<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\SchoolClass;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role', // 'admin' or 'teacher'
        'is_form_teacher',
        'phone',
        'address',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            // When deleting a teacher, remove all their assignments
            if ($user->isTeacher()) {
                $user->teacherSubjects()->delete();

                // Remove as form teacher from any classes
                \App\Models\SchoolClass::where('form_teacher_id', $user->id)->update(['form_teacher_id' => null]);
            }
        });
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is teacher
     */
    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    /**
     * Check if user is a form teacher
     */
    public function isFormTeacher(): bool
    {
        return $this->is_form_teacher || $this->hasFormTeacherClasses();
    }

    /**
     * Check if user has form teacher classes
     */
    public function hasFormTeacherClasses(): bool
    {
        return SchoolClass::where('form_teacher_id', $this->id)
                         ->where('is_active', true)
                         ->exists();
    }

    /**
     * Get teacher's assigned subjects
     */
    public function teacherSubjects()
    {
        return $this->hasMany(\App\Models\TeacherSubject::class, 'teacher_id');
    }

    /**
     * Get teacher's assigned classes through subjects
     */
    public function assignedClasses()
    {
        return $this->hasManyThrough(\App\Models\SchoolClass::class, \App\Models\TeacherSubject::class, 'teacher_id', 'id', 'id', 'class_id');
    }
}
