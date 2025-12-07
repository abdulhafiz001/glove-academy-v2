<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grading_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "JSS1-JSS3 Grading", "SS1-SS3 Grading"
            $table->json('class_ids'); // Array of class IDs this grading applies to
            $table->json('grades'); // Array of grade configurations: [{"grade": "A", "min": 80, "max": 100, "remark": "Excellent"}, ...]
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Only one can be default
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_configurations');
    }
};

