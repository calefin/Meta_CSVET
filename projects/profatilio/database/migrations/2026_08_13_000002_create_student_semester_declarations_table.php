<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_semester_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('semester'); // Ex: "2026/2"
            $table->json('situations')->nullable(); // ['tcc', 'estagio', 'ic']
            $table->boolean('concluded')->default(false);
            $table->timestamp('declared_at');
            $table->timestamps();

            $table->unique(['student_id', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_semester_declarations');
    }
};
