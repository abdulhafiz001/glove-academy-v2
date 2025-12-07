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
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Standard Promotion", "Strict Promotion"
            $table->enum('type', [
                'all_promote',           // All students are promoted
                'minimum_grades',         // Minimum number of specific grades
                'minimum_average',        // Minimum average score
                'minimum_subjects_passed' // Minimum number of subjects passed
            ]);
            $table->json('criteria')->nullable(); // e.g., {"min_a_count": 3, "min_b_count": 5, "min_average": 50}
            $table->boolean('is_active')->default(false); // Only one active rule
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_rules');
    }
};

