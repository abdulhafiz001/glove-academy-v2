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
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "2023/2024"
            $table->date('start_date'); // Session start date (typically September)
            $table->date('end_date'); // Session end date (typically July/August)
            $table->boolean('is_current')->default(false); // Only one can be current
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create terms table - each session has 3 terms
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->onDelete('cascade');
            $table->enum('name', ['first', 'second', 'third']); // first, second, third
            $table->string('display_name'); // "First Term", "Second Term", "Third Term"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false); // Only one can be current
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Ensure unique term names per session
            $table->unique(['academic_session_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_sessions');
    }
};

