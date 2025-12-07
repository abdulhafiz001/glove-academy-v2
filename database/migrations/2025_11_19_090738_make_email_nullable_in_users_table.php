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
        // For MySQL, we need to drop the unique index first, then modify the column, then re-add the unique index
        // This allows multiple NULL values while maintaining uniqueness for non-NULL values
        Schema::table('users', function (Blueprint $table) {
            // Drop the unique constraint on email
            $table->dropUnique(['email']);
        });

        // Modify the email column to be nullable
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');

        // Re-add unique constraint (MySQL allows multiple NULLs in unique columns)
        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Before making email required again, we need to ensure no NULL values exist
        DB::statement('UPDATE users SET email = CONCAT(username, "@gloveacademy.edu.ng") WHERE email IS NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
