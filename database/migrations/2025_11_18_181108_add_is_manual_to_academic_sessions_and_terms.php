<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('is_current');
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('is_current');
        });
    }

    public function down()
    {
        Schema::table('academic_sessions', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};