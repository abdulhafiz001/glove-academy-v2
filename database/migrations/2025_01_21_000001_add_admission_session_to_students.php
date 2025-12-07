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
        Schema::table('students', function (Blueprint $table) {
            // Track which academic session and term the student was admitted
            $table->foreignId('admission_academic_session_id')->nullable()->after('class_id')
                ->constrained('academic_sessions')->onDelete('set null');
            $table->enum('admission_term', ['first', 'second', 'third'])->nullable()->after('admission_academic_session_id');
            // Track promotion status
            $table->enum('status', ['active', 'graduated', 'repeated'])->default('active')->after('is_active');
            // Track if student has been promoted in current session
            $table->boolean('promoted_this_session')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['admission_academic_session_id']);
            $table->dropColumn(['admission_academic_session_id', 'admission_term', 'status', 'promoted_this_session']);
        });
    }
};

