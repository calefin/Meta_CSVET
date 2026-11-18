<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CPF para alunos externos
        Schema::table('advisees', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->after('phone');
        });

        // Cursos ministrados pelo Prof. Atilio (CSvet)
        Schema::create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('workload'); // horas
            $table->date('start_date');
            $table->date('end_date');
            $table->text('syllabus'); // conteúdo programático
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Solicitações de certificado de alunos externos
        Schema::create('certificate_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('advisees')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            // pending → approved → available
            $table->string('status', 20)->default('pending');
            $table->string('cert_hash', 64)->unique()->nullable();
            $table->string('signed_pdf_path')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'course_id']); // um certificado por curso por aluno
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_requests');
        Schema::dropIfExists('training_courses');
        Schema::table('advisees', function (Blueprint $table) {
            $table->dropColumn('cpf');
        });
    }
};
