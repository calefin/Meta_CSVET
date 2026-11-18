<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão sistemática / metanálise: protocolo, registros importados das
 * bases, triadores (com termo assinado), decisões da triagem cega em dupla
 * e vínculo com o processo de revisão do artigo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A 1ª execução em produção falhou no meio (nome de FK > 64 caracteres) e,
        // como DDL no MySQL não tem rollback, deixou tabelas parciais e vazias.
        // A migration nunca foi registrada, então é seguro descartá-las aqui.
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('systematic_review_decisions');
        Schema::dropIfExists('systematic_review_screeners');
        Schema::dropIfExists('systematic_review_records');
        Schema::dropIfExists('systematic_reviews');
        Schema::enableForeignKeyConstraints();

        Schema::create('systematic_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->string('short_name', 80)->nullable();
            $table->string('review_type', 30)->default('meta_analysis');
            $table->string('framework', 20)->default('PICO');
            $table->string('status', 30)->default('protocol');
            // Etapa de triagem em andamento: title_abstract | full_text
            $table->string('stage', 20)->default('title_abstract');
            $table->json('protocol')->nullable();
            $table->json('exclusion_reasons')->nullable();
            $table->json('highlight_terms')->nullable();
            $table->text('screener_instructions')->nullable();
            $table->unsignedInteger('pilot_size')->default(0);
            $table->timestamp('pilot_released_at')->nullable();
            $table->string('registration_platform', 40)->nullable();
            $table->string('registration_id', 255)->nullable();
            $table->date('registered_at')->nullable();
            $table->date('embargo_until')->nullable();
            $table->text('registration_waiver')->nullable();
            $table->date('search_date')->nullable();
            $table->json('search_counts')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamp('screening_started_at')->nullable();
            $table->json('stage_closed_at')->nullable();
            $table->json('history')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('systematic_review_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->string('dedupe_key', 64);
            $table->text('title');
            $table->longText('abstract')->nullable();
            $table->text('authors')->nullable();
            $table->string('year', 10)->nullable();
            $table->string('journal', 500)->nullable();
            $table->string('doi', 255)->nullable();
            $table->string('pmid', 30)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('sources', 255)->nullable();
            $table->text('keywords')->nullable();
            // volume, fascículo, páginas, ISSN, PMCID, idioma, tipo de publicação...
            $table->json('extra')->nullable();
            // Decisão final por etapa (consenso ou desempate do professor).
            $table->string('ta_decision', 10)->nullable();
            $table->string('ta_reason', 255)->nullable();
            $table->boolean('ta_resolved_by_professor')->default(false);
            $table->string('ft_decision', 10)->nullable();
            $table->string('ft_reason', 255)->nullable();
            $table->boolean('ft_resolved_by_professor')->default(false);
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->unique(['systematic_review_id', 'dedupe_key']);
            $table->index(['systematic_review_id', 'seq']);
        });

        Schema::create('systematic_review_screeners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('label', 40)->nullable();
            $table->json('stages')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 20)->nullable();
            $table->string('terms_sha256', 64)->nullable();
            $table->longText('terms_text')->nullable();
            $table->string('terms_signature')->nullable();
            $table->string('terms_ip', 64)->nullable();
            $table->string('terms_user_agent', 500)->nullable();
            $table->json('finished_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->unique(['systematic_review_id', 'email']);
        });

        Schema::create('systematic_review_decisions', function (Blueprint $table) {
            $table->id();
            // Nomes explícitos: os gerados automaticamente passam do limite de 64 do MySQL.
            $table->foreignId('systematic_review_record_id')->constrained(indexName: 'sr_decisions_record_fk')->cascadeOnDelete();
            $table->foreignId('systematic_review_screener_id')->constrained(indexName: 'sr_decisions_screener_fk')->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('decision', 10);
            $table->string('reason', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['systematic_review_record_id', 'systematic_review_screener_id', 'stage'], 'sr_decision_unique');
        });

        Schema::table('article_reviews', function (Blueprint $table) {
            $table->foreignId('systematic_review_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('invite_tokens', function (Blueprint $table) {
            // Convite gerado pela triagem: o aluno externo já foi escolhido pelo
            // professor, então a conta é liberada assim que o e-mail é validado.
            $table->boolean('auto_approve')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('invite_tokens', function (Blueprint $table) {
            $table->dropColumn('auto_approve');
        });
        Schema::table('article_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('systematic_review_id');
        });
        Schema::dropIfExists('systematic_review_decisions');
        Schema::dropIfExists('systematic_review_screeners');
        Schema::dropIfExists('systematic_review_records');
        Schema::dropIfExists('systematic_reviews');
    }
};
