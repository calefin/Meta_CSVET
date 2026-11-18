<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('instrucao'); // instrucao | avaliacao
            $table->longText('content')->nullable(); // instrucoes / conteudo em HTML
            $table->json('quiz_questions')->nullable(); // [{question, options: [...], correct_index}]
            $table->unsignedTinyInteger('passing_score')->default(70); // % minimo para aprovar avaliacao
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_modules');
    }
};
