<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Term extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id',
        'name',
        'display_name',
        'start_date',
        'end_date',
        'is_current',
        'is_manual',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'is_manual' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    /**
     * Calculate term status based on dates and current flag
     */
    public function getStatusAttribute()
    {
        $today = Carbon::today();
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);

        if ($this->is_current) return 'current';
        if ($today->lt($start)) return 'upcoming';
        if ($today->gt($end)) return 'past';
        return 'active';
    }

    protected static function boot()
    {
        parent::boot();

        // When setting a term as current, mark it as manual
        static::updating(function ($term) {
            if ($term->isDirty('is_current') && $term->is_current) {
                $term->is_manual = true;
            }
        });
    }

    /**
     * Get the current term
     */
    public static function current()
    {
        return static::where('is_current', true)->where('is_active', true)->first();
    }
}