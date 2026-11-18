<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('course_concluded_at')->nullable()->after('status');
            $table->string('last_semester_declared')->nullable()->after('course_concluded_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['course_concluded_at', 'last_semester_declared']);
        });
    }
};
