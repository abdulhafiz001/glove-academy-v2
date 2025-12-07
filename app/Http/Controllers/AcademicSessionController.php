<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AcademicSession;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AcademicSessionController extends Controller
{
    /**
     * Get all academic sessions with calculated status
     */
    public function index()
    {
        // Automatically update session and term statuses based on dates
        $this->updateSessionStatuses();
        
        $sessions = AcademicSession::with(['terms' => function($query) {
            $query->orderBy('start_date');
        }])
        ->orderBy('start_date', 'desc')
        ->get();

        return response()->json([
            'data' => $sessions,
            'message' => 'Academic sessions retrieved successfully'
        ]);
    }

    /**
     * Automatically update session and term statuses based on dates
     */
    private function updateSessionStatuses()
    {
        $today = Carbon::today();

        // Update terms first
        $this->updateTermStatuses();

        // Find sessions that ended in the past and are not manually set
        $pastSessions = AcademicSession::where('is_current', true)
            ->where('end_date', '<', $today)
            ->where('is_manual', false)
            ->get();

        foreach ($pastSessions as $session) {
            $session->update(['is_current' => false]);
        }

        // Find sessions that should be current based on dates but aren't manually set
        $sessionsToActivate = AcademicSession::where('is_current', false)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->where('is_active', true)
            ->where('is_manual', false)
            ->get();

        // If we have sessions that should be current but aren't
        foreach ($sessionsToActivate as $session) {
            // Only auto-activate if no manual current session exists
            $manualCurrentExists = AcademicSession::where('is_current', true)
                ->where('is_manual', true)
                ->exists();

            if (!$manualCurrentExists) {
                // Unset all other auto-set current sessions
                AcademicSession::where('is_current', true)
                    ->where('is_manual', false)
                    ->update(['is_current' => false]);
                
                $session->update(['is_current' => true]);
            }
        }
    }

    /**
     * Update term statuses automatically
     */
    private function updateTermStatuses()
    {
        $today = Carbon::today();

        // Find terms that ended in the past and are not manually set
        $pastTerms = Term::where('is_current', true)
            ->where('end_date', '<', $today)
            ->where('is_manual', false)
            ->get();

        foreach ($pastTerms as $term) {
            $term->update(['is_current' => false]);
        }

        // Find terms that should be current based on dates
        $termsToActivate = Term::where('is_current', false)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->where('is_active', true)
            ->where('is_manual', false)
            ->get();

        foreach ($termsToActivate as $term) {
            // Check if there's already a manual current term in this session
            $manualCurrentInSession = Term::where('academic_session_id', $term->academic_session_id)
                ->where('is_current', true)
                ->where('is_manual', true)
                ->exists();

            // Only auto-activate if no manual current term in this session
            if (!$manualCurrentInSession) {
                // Unset all other auto-set current terms in the same session
                Term::where('academic_session_id', $term->academic_session_id)
                    ->where('is_current', true)
                    ->where('is_manual', false)
                    ->update(['is_current' => false]);
                
                $term->update(['is_current' => true]);
            }
        }
    }

    /**
     * Get current academic session and term
     */
    public function current()
    {
        $this->updateSessionStatuses();
        
        $currentSession = AcademicSession::current();
        $currentTerm = Term::current();

        return response()->json([
            'session' => $currentSession,
            'term' => $currentTerm,
            'has_session' => $currentSession !== null,
            'has_term' => $currentTerm !== null,
        ]);
    }

    /**
     * Get a specific academic session
     */
    public function show(AcademicSession $academicSession)
    {
        $academicSession->load('terms');
        return response()->json($academicSession);
    }

    /**
     * Create a new academic session with enhanced validation
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:academic_sessions,name',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'sometimes|boolean',
        ]);

        // Check for date conflicts with existing sessions
        $conflictingSession = AcademicSession::where(function($query) use ($request) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                  ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                  ->orWhere(function($q) use ($request) {
                      $q->where('start_date', '<=', $request->start_date)
                        ->where('end_date', '>=', $request->end_date);
                  });
        })->first();

        if ($conflictingSession) {
            return response()->json([
                'message' => 'Date conflict with existing session: ' . $conflictingSession->name,
                'conflicting_session' => $conflictingSession
            ], 422);
        }

        DB::beginTransaction();
        try {
            $sessionData = [
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_current' => $request->is_current ?? false,
                'is_manual' => $request->is_current ?? false, // If setting as current, mark as manual
                'is_active' => true,
            ];

            // If setting as current, unset other current sessions
            if ($request->is_current) {
                AcademicSession::where('is_current', true)->update(['is_current' => false]);
            }

            $session = AcademicSession::create($sessionData);

            DB::commit();
            $session->load('terms');
            
            return response()->json([
                'message' => 'Academic session created successfully',
                'session' => $session,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create academic session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an academic session
     */
    public function update(Request $request, AcademicSession $academicSession)
    {
        $request->validate([
            'name' => 'sometimes|string|unique:academic_sessions,name,' . $academicSession->id,
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'is_current' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            $updateData = $request->only([
                'name', 'start_date', 'end_date', 'is_current', 'is_active'
            ]);

            // If setting as current manually, mark it as manual and unset others
            if ($request->has('is_current') && $request->is_current) {
                $updateData['is_manual'] = true;
                AcademicSession::where('id', '!=', $academicSession->id)->update(['is_current' => false]);
            }

            $academicSession->update($updateData);

            // Update terms if provided
            if ($request->has('terms')) {
                foreach ($request->terms as $termData) {
                    $term = $academicSession->terms()->where('name', $termData['name'])->first();
                    if ($term) {
                        $term->update([
                            'start_date' => $termData['start_date'] ?? $term->start_date,
                            'end_date' => $termData['end_date'] ?? $term->end_date,
                            'is_current' => $termData['is_current'] ?? $term->is_current,
                            'is_active' => $termData['is_active'] ?? $term->is_active,
                        ]);
                    }
                }
            }

            DB::commit();
            $academicSession->load('terms');
            
            return response()->json([
                'message' => 'Academic session updated successfully',
                'session' => $academicSession,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update academic session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an academic session
     */
    public function destroy(AcademicSession $academicSession)
    {
        // Check if there are scores associated with this session
        $scoreCount = $academicSession->scores()->count();
        
        if ($scoreCount > 0) {
            return response()->json([
                'message' => 'Cannot delete academic session with associated scores. Consider deactivating it instead.',
            ], 422);
        }

        $academicSession->delete();
        
        return response()->json([
            'message' => 'Academic session deleted successfully',
        ]);
    }

    /**
     * Set current academic session with conflict resolution
     */
    public function setCurrent(Request $request, AcademicSession $academicSession)
    {
        DB::beginTransaction();
        try {
            $today = Carbon::today();
            $previousCurrent = AcademicSession::where('is_current', true)->first();
            
            // Unset all other sessions as current
            AcademicSession::where('id', '!=', $academicSession->id)->update(['is_current' => false]);
            
            // Set this session as current and mark as manually set
            $academicSession->update([
                'is_current' => true,
                'is_manual' => true // Mark as manually set to prevent auto-update
            ]);

            $academicSession->load('terms');
            
            DB::commit();

            // Prepare response message
            $message = 'Current academic session updated';
            if ($previousCurrent && $previousCurrent->end_date > $today) {
                $message .= ". Note: The previous session ({$previousCurrent->name}) was still active but has been deactivated.";
            }

            return response()->json([
                'message' => $message,
                'session' => $academicSession,
                'updated_sessions' => 1
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to set current session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set current term
     */
    public function setCurrentTerm(Request $request, Term $term)
    {
        $request->validate([
            'academic_session_id' => 'required|exists:academic_sessions,id',
        ]);

        // Ensure the term belongs to the specified session
        if ($term->academic_session_id != $request->academic_session_id) {
            return response()->json([
                'message' => 'Term does not belong to the specified academic session',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Unset all other terms as current
            Term::where('id', '!=', $term->id)->update(['is_current' => false]);
            
            // Set this term as current and mark as manually set
            $term->update([
                'is_current' => true,
                'is_manual' => true
            ]);
            
            $term->load('academicSession');
            
            DB::commit();
            
            return response()->json([
                'message' => 'Current term updated',
                'term' => $term,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to set current term',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a term
     */
    public function updateTerm(Request $request, Term $term)
    {
        $request->validate([
            'display_name' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'is_current' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            $termData = $request->only([
                'display_name', 'start_date', 'end_date', 'is_current', 'is_active'
            ]);
            
            // If setting as current, mark as manual
            if ($request->has('is_current') && $request->is_current) {
                $termData['is_manual'] = true;
                
                // Unset other current terms
                Term::where('id', '!=', $term->id)->update(['is_current' => false]);
            }
            
            $term->update($termData);
            $term->load('academicSession');
            
            DB::commit();
            
            return response()->json([
                'message' => 'Term updated successfully',
                'term' => $term,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update term',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}