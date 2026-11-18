<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_module_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->json('quiz_answers')->nullable();
            $table->unsignedTinyInteger('quiz_score')->nullable();
            $table->timestamps();

            $table->unique(['course_enrollment_id', 'course_module_id'], 'course_module_completions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_module_completions');
    }
};
