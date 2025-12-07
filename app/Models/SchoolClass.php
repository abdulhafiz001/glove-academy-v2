<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'description',
        'form_teacher_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get students in this class
     */
    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /**
     * Get subjects taught in this class
     */
    public function classSubjects()
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    /**
     * Get teachers assigned to this class through subjects
     */
    public function teachers()
    {
        return $this->hasManyThrough(User::class, TeacherSubject::class, 'class_id', 'id', 'id', 'teacher_id');
    }

    /**
     * Get the form teacher
     */
    public function formTeacher()
    {
        return $this->belongsTo(User::class, 'form_teacher_id');
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // When form_teacher_id is updated, update the user's is_form_teacher status
        static::updated(function ($class) {
            if ($class->isDirty('form_teacher_id')) {
                // Update the old form teacher's status
                if ($class->getOriginal('form_teacher_id')) {
                    $oldFormTeacher = User::find($class->getOriginal('form_teacher_id'));
                    if ($oldFormTeacher) {
                        $oldFormTeacher->update([
                            'is_form_teacher' => SchoolClass::where('form_teacher_id', $oldFormTeacher->id)
                                                           ->where('is_active', true)
                                                           ->exists()
                        ]);
                    }
                }

                // Update the new form teacher's status
                if ($class->form_teacher_id) {
                    $newFormTeacher = User::find($class->form_teacher_id);
                    if ($newFormTeacher) {
                        $newFormTeacher->update([
                            'is_form_teacher' => SchoolClass::where('form_teacher_id', $newFormTeacher->id)
                                                           ->where('is_active', true)
                                                           ->exists()
                        ]);
                    }
                }
            }
        });
    }
} 