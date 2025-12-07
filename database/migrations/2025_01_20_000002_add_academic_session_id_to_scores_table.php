<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Temporarily disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Add the column if it doesn't exist
        if (!Schema::hasColumn('scores', 'academic_session_id')) {
            Schema::table('scores', function (Blueprint $table) {
                $table->unsignedBigInteger('academic_session_id')->nullable()->after('term');
            });
            
            // Add the foreign key constraint only if column was just created
            Schema::table('scores', function (Blueprint $table) {
                $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('cascade');
            });
        }

        // Drop the old unique constraint
        try {
            DB::statement('ALTER TABLE `scores` DROP INDEX `scores_student_id_subject_id_class_id_term_unique`;');
        } catch (\Exception $e) {
            // Index might not exist or have different name, try to find it
            $indexes = DB::select("SHOW INDEX FROM `scores` WHERE Key_name LIKE '%student_id%subject_id%class_id%term%'");
            if (!empty($indexes)) {
                $indexName = $indexes[0]->Key_name;
                DB::statement("ALTER TABLE `scores` DROP INDEX `{$indexName}`;");
            }
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Add new unique constraint with academic_session_id if it doesn't exist
        $hasUnique = DB::selectOne("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'scores' 
            AND CONSTRAINT_NAME = 'scores_unique_constraint'
            LIMIT 1
        ");
        
        if (!$hasUnique) {
            Schema::table('scores', function (Blueprint $table) {
                $table->unique(['student_id', 'subject_id', 'class_id', 'term', 'academic_session_id'], 'scores_unique_constraint');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            $table->dropForeign(['academic_session_id']);
            $table->dropUnique('scores_unique_constraint');
            $table->dropColumn('academic_session_id');
            
            // Restore original unique constraint
            $table->unique(['student_id', 'subject_id', 'class_id', 'term']);
        });
    }
};

