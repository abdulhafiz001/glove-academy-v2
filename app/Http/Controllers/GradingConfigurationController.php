<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GradingConfiguration;
use App\Models\SchoolClass;

class GradingConfigurationController extends Controller
{
    /**
     * Get all grading configurations
     */
    public function index()
    {
        $configurations = GradingConfiguration::orderBy('is_default', 'desc')
                                             ->orderBy('name')
                                             ->get();

        return response()->json(['data' => $configurations]);
    }

    /**
     * Get a specific grading configuration
     */
    public function show(GradingConfiguration $gradingConfiguration)
    {
        $gradingConfiguration->load('classes');
        return response()->json(['data' => $gradingConfiguration]);
    }

    /**
     * Create a new grading configuration
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'class_ids' => 'required|array',
            'class_ids.*' => 'exists:classes,id',
            'grades' => 'required|array',
            'grades.*.grade' => 'required|string|max:10',
            'grades.*.min' => 'required|numeric|min:0|max:100',
            'grades.*.max' => 'required|numeric|min:0|max:100',
            'grades.*.remark' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Validate grade ranges don't overlap
        $grades = $request->grades;
        for ($i = 0; $i < count($grades); $i++) {
            for ($j = $i + 1; $j < count($grades); $j++) {
                if (($grades[$i]['min'] <= $grades[$j]['max'] && $grades[$i]['max'] >= $grades[$j]['min'])) {
                    return response()->json([
                        'message' => 'Grade ranges cannot overlap. Please ensure each grade has a unique range.'
                    ], 422);
                }
            }
        }

        // If setting as default, unset other defaults
        if ($request->is_default) {
            GradingConfiguration::where('is_default', true)->update(['is_default' => false]);
        }

        $configuration = GradingConfiguration::create([
            'name' => $request->name,
            'class_ids' => $request->class_ids,
            'grades' => $request->grades,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'is_default' => $request->is_default ?? false,
        ]);

        return response()->json([
            'message' => 'Grading configuration created successfully',
            'data' => $configuration
        ], 201);
    }

    /**
     * Update a grading configuration
     */
    public function update(Request $request, GradingConfiguration $gradingConfiguration)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'class_ids' => 'sometimes|required|array',
            'class_ids.*' => 'exists:classes,id',
            'grades' => 'sometimes|required|array',
            'grades.*.grade' => 'required|string|max:10',
            'grades.*.min' => 'required|numeric|min:0|max:100',
            'grades.*.max' => 'required|numeric|min:0|max:100',
            'grades.*.remark' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // Validate grade ranges if grades are being updated
        if ($request->has('grades')) {
            $grades = $request->grades;
            for ($i = 0; $i < count($grades); $i++) {
                for ($j = $i + 1; $j < count($grades); $j++) {
                    if (($grades[$i]['min'] <= $grades[$j]['max'] && $grades[$i]['max'] >= $grades[$j]['min'])) {
                        return response()->json([
                            'message' => 'Grade ranges cannot overlap. Please ensure each grade has a unique range.'
                        ], 422);
                    }
                }
            }
        }

        // If setting as default, unset other defaults
        if ($request->is_default && !$gradingConfiguration->is_default) {
            GradingConfiguration::where('id', '!=', $gradingConfiguration->id)
                               ->where('is_default', true)
                               ->update(['is_default' => false]);
        }

        $gradingConfiguration->update($request->only([
            'name', 'class_ids', 'grades', 'description', 'is_active', 'is_default'
        ]));

        return response()->json([
            'message' => 'Grading configuration updated successfully',
            'data' => $gradingConfiguration->fresh()
        ]);
    }

    /**
     * Delete a grading configuration
     */
    public function destroy(GradingConfiguration $gradingConfiguration)
    {
        if ($gradingConfiguration->is_default) {
            return response()->json([
                'message' => 'Cannot delete the default grading configuration. Set another as default first.'
            ], 422);
        }

        $gradingConfiguration->delete();

        return response()->json(['message' => 'Grading configuration deleted successfully']);
    }

    /**
     * Set a grading configuration as default
     */
    public function setDefault(GradingConfiguration $gradingConfiguration)
    {
        if (!$gradingConfiguration->is_active) {
            return response()->json([
                'message' => 'Cannot set inactive configuration as default'
            ], 422);
        }

        // Unset other defaults
        GradingConfiguration::where('id', '!=', $gradingConfiguration->id)
                           ->where('is_default', true)
                           ->update(['is_default' => false]);

        $gradingConfiguration->update(['is_default' => true]);

        return response()->json([
            'message' => 'Grading configuration set as default',
            'data' => $gradingConfiguration->fresh()
        ]);
    }
}

