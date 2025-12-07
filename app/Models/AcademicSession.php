<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AcademicSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
        'is_manual',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'is_manual' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get all terms for this session
     */
    public function terms()
    {
        return $this->hasMany(Term::class);
    }

    /**
     * Get the current term for this session
     */
    public function currentTerm()
    {
        return $this->hasOne(Term::class)->where('is_current', true);
    }

    /**
     * Get all scores for this session
     */
    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    /**
     * Calculate session status based on dates and current flag
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

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // When creating a new session, automatically create 3 terms
        static::created(function ($session) {
            $terms = [
                ['name' => 'first', 'display_name' => 'First Term', 'start_date' => $session->start_date, 'end_date' => date('Y-12-31', strtotime($session->start_date))],
                ['name' => 'second', 'display_name' => 'Second Term', 'start_date' => date('Y-01-01', strtotime($session->start_date . ' +1 year')), 'end_date' => date('Y-04-30', strtotime($session->start_date . ' +1 year'))],
                ['name' => 'third', 'display_name' => 'Third Term', 'start_date' => date('Y-05-01', strtotime($session->start_date . ' +1 year')), 'end_date' => $session->end_date],
            ];

            foreach ($terms as $term) {
                $session->terms()->create($term);
            }
        });

        // When setting a session as current, unset all others and mark as manual
        static::updating(function ($session) {
            if ($session->isDirty('is_current') && $session->is_current) {
                static::where('id', '!=', $session->id)->update(['is_current' => false]);
                $session->is_manual = true;
            }
        });
    }

    /**
     * Get the current academic session
     */
    public static function current()
    {
        return static::where('is_current', true)->where('is_active', true)->first();
    }
}