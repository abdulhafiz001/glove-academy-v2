<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\AcademicSession;
use App\Models\Term;
use App\Models\Score;
use App\Models\PromotionRule;
use App\Models\StudentClassHistory;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    /**
     * Get all promotion rules
     */
    public function index()
    {
        $rules = PromotionRule::orderBy('created_at', 'desc')->get();
        return response()->json([
            'data' => $rules,
            'active_rule' => PromotionRule::active(),
        ]);
    }

    /**
     * Create or update promotion rule
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:all_promote,minimum_grades,minimum_average,minimum_subjects_passed',
            'criteria' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // If setting as active, deactivate all others
        if ($request->is_active) {
            PromotionRule::where('is_active', true)->update(['is_active' => false]);
        }

        $rule = PromotionRule::create($request->all());

        return response()->json([
            'message' => 'Promotion rule created successfully',
            'data' => $rule,
        ], 201);
    }

    /**
     * Update promotion rule
     */
    public function update(Request $request, PromotionRule $promotionRule)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:all_promote,minimum_grades,minimum_average,minimum_subjects_passed',
            'criteria' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // If setting as active, deactivate all others
        if ($request->is_active) {
            PromotionRule::where('id', '!=', $promotionRule->id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
        }

        $promotionRule->update($request->all());

        return response()->json([
            'message' => 'Promotion rule updated successfully',
            'data' => $promotionRule,
        ]);
    }

    /**
     * Delete promotion rule
     */
    public function destroy(PromotionRule $promotionRule)
    {
        $promotionRule->delete();
        return response()->json(['message' => 'Promotion rule deleted successfully']);
    }

    /**
     * Run promotion process for a class or all classes
     */
    public function promoteStudents(Request $request)
    {
        $request->validate([
            'class_id' => 'nullable|exists:classes,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
        ]);

        $activeRule = PromotionRule::active();
        if (!$activeRule) {
            return response()->json([
                'message' => 'No active promotion rule found. Please activate a promotion rule first.',
            ], 422);
        }

        $session = AcademicSession::find($request->academic_session_id);
        if (!$session) {
            return response()->json(['message' => 'Academic session not found'], 404);
        }

        // Get all terms for this session
        $terms = Term::where('academic_session_id', $session->id)->get();
        if ($terms->count() !== 3) {
            return response()->json([
                'message' => 'All three terms must be completed before promotion',
            ], 422);
        }

        // Check if third term is completed (all students have scores)
        // This is a simplified check - you may want to enhance it

        $query = Student::where('is_active', true);
        
        // Only filter by status if it's explicitly set to exclude graduated/repeated students
        // But allow all active students to be processed
        if ($request->class_id) {
            $query->where('class_id', $request->class_id);
        }

        $students = $query->get();
        $promoted = 0;
        $repeated = 0;
        $graduated = 0;

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                // Record the class the student was in during this academic session BEFORE promotion
                // This ensures historical accuracy when viewing results
                $originalClassId = $student->class_id;
                StudentClassHistory::updateHistory($student->id, $session->id, $originalClassId);
                
                // Get all scores for this session
                $scores = Score::where('student_id', $student->id)
                             ->where('academic_session_id', $session->id)
                             ->where('is_active', true)
                             ->get();

                // Group by subject and get best score across terms
                // Also ensure total_score and grade are calculated
                $subjectScores = [];
                foreach ($scores as $score) {
                    // Calculate total_score if not set (null check only, 0 is valid)
                    $totalScore = $score->total_score;
                    if ($totalScore === null) {
                        $totalScore = ($score->first_ca ?? 0) + ($score->second_ca ?? 0) + ($score->exam_score ?? 0);
                    }
                    
                    // Ensure grade is calculated
                    $grade = $score->grade;
                    if (empty($grade)) {
                        // Temporarily set total_score to calculate grade
                        $originalTotal = $score->total_score;
                        $score->total_score = $totalScore;
                        $score->calculateGrade();
                        $grade = $score->grade ?? 'F';
                        $score->total_score = $originalTotal; // Restore original
                    }
                    
                    $subjectId = $score->subject_id;
                    
                    // Get best score across terms for each subject
                    if (!isset($subjectScores[$subjectId]) || 
                        ($subjectScores[$subjectId]['total_score'] < $totalScore)) {
                        $subjectScores[$subjectId] = [
                            'total_score' => $totalScore,
                            'grade' => $grade,
                        ];
                    }
                }

                // Convert to array for rule checking
                $sessionResults = array_values($subjectScores);
                
                // Skip students with no scores
                if (empty($sessionResults)) {
                    continue;
                }

                // Get current class
                $currentClass = SchoolClass::find($student->class_id);
                $className = $currentClass->name ?? '';

                // Check if should graduate (JSS3, SS3, Primary 5, Nursery 2, etc.)
                $isGraduating = $this->shouldGraduate($className);

                if ($isGraduating) {
                    // Mark as graduated
                    $student->update(['status' => 'graduated']);
                    $graduated++;
                } else {
                    // Check promotion eligibility
                    $eligible = $activeRule->checkPromotionEligibility($student, $sessionResults);

                    if ($eligible) {
                        // Promote to next class
                        $nextClass = $this->getNextClass($className);
                        if ($nextClass) {
                            $student->update([
                                'class_id' => $nextClass->id,
                                'promoted_this_session' => true,
                                'status' => 'active', // Reset status to active for promoted students
                            ]);
                            $promoted++;
                        } else {
                            // No next class found, graduate
                            $student->update(['status' => 'graduated']);
                            $graduated++;
                        }
                    } else {
                        // Repeat same class
                        $student->update(['status' => 'repeated']);
                        $repeated++;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Promotion process completed',
                'promoted' => $promoted,
                'repeated' => $repeated,
                'graduated' => $graduated,
                'total' => $students->count(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Promotion process failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if class is graduating class (JSS3, SS3, Primary 5, Nursery 2, etc.)
     */
    private function shouldGraduate($className)
    {
        $classNameUpper = strtoupper(trim($className));
        
        // Standard graduating classes (hardcoded for common cases)
        $graduatingClasses = [
            'JSS 3', 'JSS3', 'SS 3', 'SS3', 'S.S.3',
        ];
        
        // Check exact match for hardcoded classes
        if (in_array($classNameUpper, array_map('strtoupper', $graduatingClasses))) {
            return true;
        }
        
        // Dynamic pattern matching for graduating classes
        // This automatically handles Primary 5, Primary 6, Nursery 2, etc.
        // Check if it's a known graduating pattern
        $graduatingPatterns = [
            '/^PRIMARY\s*[5-9]$/i',  // Primary 5, 6, 7, 8, 9 (or any high number)
            '/^P\.?\s*[5-9]$/i',     // P.5, P.6, etc.
            '/^NURSERY\s*[2-9]$/i',  // Nursery 2, 3, 4, etc.
            '/^NUR\.?\s*[2-9]$/i',   // NUR.2, NUR.3, etc.
        ];
        
        foreach ($graduatingPatterns as $pattern) {
            if (preg_match($pattern, $className)) {
                // Double-check: if there's a next class, don't graduate
                $nextClass = $this->getNextClass($className);
                if (!$nextClass) {
                    return true; // No next class found, so this is a graduating class
                }
            }
        }
        
        // Also check if it's the highest level class in its category
        // (e.g., if Primary 6 exists but Primary 7 doesn't, Primary 6 is graduating)
        if (preg_match('/^PRIMARY\s*(\d+)$/i', $className, $matches) || 
            preg_match('/^P\.?\s*(\d+)$/i', $className, $matches)) {
            $level = (int)$matches[1];
            // Check if there's a higher level class
            $higherLevelExists = SchoolClass::where(function($query) use ($level) {
                $query->whereRaw('UPPER(TRIM(name)) LIKE ?', ['PRIMARY ' . ($level + 1)])
                      ->orWhereRaw('UPPER(TRIM(name)) = ?', ['PRIMARY' . ($level + 1)])
                      ->orWhereRaw('UPPER(TRIM(name)) = ?', ['P.' . ($level + 1)])
                      ->orWhereRaw('UPPER(TRIM(name)) = ?', ['P' . ($level + 1)]);
            })->exists();
            
            if (!$higherLevelExists && $level >= 5) {
                return true; // This is the highest primary class, so it's graduating
            }
        }
        
        return false;
    }

    /**
     * Get next class for promotion
     */
    private function getNextClass($currentClassName)
    {
        $currentUpper = strtoupper(trim($currentClassName));
        
        // Map classes to next classes - expanded to include Primary and Nursery
        $classProgression = [
            // Junior Secondary
            'JSS 1' => 'JSS 2', 'JSS1' => 'JSS2',
            'JSS 2' => 'JSS 3', 'JSS2' => 'JSS3',
            // Senior Secondary
            'SS 1' => 'SS 2', 'SS1' => 'SS2',
            'SS 2' => 'SS 3', 'SS2' => 'SS3',
            // Primary
            'PRIMARY 1' => 'PRIMARY 2', 'PRIMARY1' => 'PRIMARY2', 'P.1' => 'P.2', 'P1' => 'P2',
            'PRIMARY 2' => 'PRIMARY 3', 'PRIMARY2' => 'PRIMARY3', 'P.2' => 'P.3', 'P2' => 'P3',
            'PRIMARY 3' => 'PRIMARY 4', 'PRIMARY3' => 'PRIMARY4', 'P.3' => 'P.4', 'P3' => 'P4',
            'PRIMARY 4' => 'PRIMARY 5', 'PRIMARY4' => 'PRIMARY5', 'P.4' => 'P.5', 'P4' => 'P5',
            // Nursery
            'NURSERY 1' => 'NURSERY 2', 'NURSERY1' => 'NURSERY2', 'NUR.1' => 'NUR.2', 'NUR1' => 'NUR2',
        ];

        // Try exact match first
        $nextClassName = $classProgression[$currentUpper] ?? null;
        
        // If no exact match, try pattern matching (dynamic - works for any level)
        if (!$nextClassName) {
            // Primary classes - dynamically handles Primary 1, 2, 3, 4, 5, 6, 7, etc.
            if (preg_match('/^PRIMARY\s*(\d+)$/i', $currentClassName, $matches)) {
                $level = (int)$matches[1];
                // Check if next class exists in database before promoting
                $nextLevel = $level + 1;
                $potentialNextClass = SchoolClass::where(function($query) use ($nextLevel) {
                    $query->whereRaw('UPPER(TRIM(name)) LIKE ?', ['PRIMARY ' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['PRIMARY' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['P.' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['P' . $nextLevel]);
                })->first();
                
                if ($potentialNextClass) {
                    $nextClassName = 'PRIMARY ' . $nextLevel;
                }
            } elseif (preg_match('/^P\.?\s*(\d+)$/i', $currentClassName, $matches)) {
                $level = (int)$matches[1];
                $nextLevel = $level + 1;
                $potentialNextClass = SchoolClass::where(function($query) use ($nextLevel) {
                    $query->whereRaw('UPPER(TRIM(name)) LIKE ?', ['PRIMARY ' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['PRIMARY' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['P.' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['P' . $nextLevel]);
                })->first();
                
                if ($potentialNextClass) {
                    $nextClassName = 'P.' . $nextLevel;
                }
            }
            // Nursery classes - dynamically handles Nursery 1, 2, 3, etc.
            elseif (preg_match('/^NURSERY\s*(\d+)$/i', $currentClassName, $matches)) {
                $level = (int)$matches[1];
                $nextLevel = $level + 1;
                $potentialNextClass = SchoolClass::where(function($query) use ($nextLevel) {
                    $query->whereRaw('UPPER(TRIM(name)) LIKE ?', ['NURSERY ' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['NURSERY' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['NUR.' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['NUR' . $nextLevel]);
                })->first();
                
                if ($potentialNextClass) {
                    $nextClassName = 'NURSERY ' . $nextLevel;
                }
            } elseif (preg_match('/^NUR\.?\s*(\d+)$/i', $currentClassName, $matches)) {
                $level = (int)$matches[1];
                $nextLevel = $level + 1;
                $potentialNextClass = SchoolClass::where(function($query) use ($nextLevel) {
                    $query->whereRaw('UPPER(TRIM(name)) LIKE ?', ['NURSERY ' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['NURSERY' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['NUR.' . $nextLevel])
                          ->orWhereRaw('UPPER(TRIM(name)) = ?', ['NUR' . $nextLevel]);
                })->first();
                
                if ($potentialNextClass) {
                    $nextClassName = 'NUR.' . $nextLevel;
                }
            }
        }
        
        if (!$nextClassName) {
            return null;
        }

        // Try to find class with exact name or similar variations
        $nextClass = SchoolClass::where(function($query) use ($nextClassName) {
            $query->where('name', $nextClassName)
                  ->orWhere('name', str_replace(' ', '', $nextClassName))
                  ->orWhere('name', str_replace('.', '', $nextClassName))
                  ->orWhereRaw('UPPER(TRIM(name)) = ?', [strtoupper(trim($nextClassName))]);
        })->first();

        return $nextClass;
    }
}

