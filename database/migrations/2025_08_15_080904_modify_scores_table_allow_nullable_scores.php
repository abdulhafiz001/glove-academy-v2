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
        Schema::table('scores', function (Blueprint $table) {
            // Make score fields nullable and remove default values
            $table->decimal('first_ca', 5, 2)->nullable()->default(null)->change();
            $table->decimal('second_ca', 5, 2)->nullable()->default(null)->change();
            $table->decimal('exam_score', 5, 2)->nullable()->default(null)->change();
            $table->decimal('total_score', 5, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scores', function (Blueprint $table) {
            // Revert back to default values
            $table->decimal('first_ca', 5, 2)->default(0)->change();
            $table->decimal('second_ca', 5, 2)->default(0)->change();
            $table->decimal('exam_score', 5, 2)->default(0)->change();
            $table->decimal('total_score', 5, 2)->default(0)->change();
        });
    }
};
