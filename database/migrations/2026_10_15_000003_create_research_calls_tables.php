<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seleção de alunos para os projetos de pesquisa (chamada pública com
 * requisitos, compromissos e passo a passo) e as inscrições recebidas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_calls', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug', 80)->unique();
            $table->string('status', 20)->default('draft');
            $table->string('headline', 500)->nullable();
            $table->text('intro')->nullable();
            $table->json('benefits')->nullable();
            $table->json('requirements')->nullable();
            $table->json('commitments')->nullable();
            $table->json('steps')->nullable();
            $table->json('faq')->nullable();
            $table->json('axes')->nullable();
            $table->json('projects')->nullable();
            $table->json('interview_questions')->nullable();
            $table->unsignedTinyInteger('min_semester')->default(4);
            $table->json('eligible_courses')->nullable();
            $table->unsignedTinyInteger('min_weekly_hours')->default(4);
            $table->date('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('research_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Link de acompanhamento: busca pelo hash; o token vai cifrado
            // (APP_KEY) para poder entrar de novo nos e-mails seguintes.
            $table->string('access_token_hash', 64)->unique();
            $table->text('access_token')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('course', 80);
            $table->string('institution', 255);
            $table->unsignedTinyInteger('semester');
            $table->string('purpose', 30);
            $table->json('axis_preferences')->nullable();
            $table->json('project_preferences')->nullable();
            $table->unsignedTinyInteger('weekly_hours');
            $table->string('english_level', 30);
            $table->string('lattes_url', 255)->nullable();
            $table->string('orcid', 40)->nullable();
            $table->text('motivation');
            $table->text('experience')->nullable();
            // Texto exato de cada compromisso aceito + data (auditoria).
            $table->json('declarations')->nullable();
            $table->string('status', 20)->default('new');
            $table->dateTime('interview_at')->nullable();
            $table->string('interview_place', 500)->nullable();
            $table->json('interview_answers')->nullable();
            $table->text('interview_notes')->nullable();
            $table->string('lead_project_code', 20)->nullable();
            $table->string('reviewer_project_code', 20)->nullable();
            $table->text('decision_message')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->json('history')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->unique(['research_call_id', 'email'], 'research_app_call_email_unique');
            $table->index(['research_call_id', 'status'], 'research_app_call_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_applications');
        Schema::dropIfExists('research_calls');
    }
};
