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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->onDelete('cascade');
            $table->string('term'); // first, second, third
            $table->integer('week'); // 1-14
            $table->string('day'); // Monday, Tuesday, Wednesday, etc.
            $table->date('date'); // Specific date of attendance
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->text('remark')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['student_id', 'class_id', 'subject_id', 'academic_session_id', 'term', 'week', 'day'], 'attendance_main_idx');
            $table->index(['teacher_id', 'class_id', 'subject_id', 'academic_session_id'], 'attendance_teacher_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};

