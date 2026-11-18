<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fases P7–P14 do pipeline: estudos (relatos agrupados), extração e risco de
 * viés em duplicata, reconciliação, trava da base com hash, análises com
 * reexecução, GRADE, contato com autores, atas e submissão.
 * Tabelas curtas (sr_*) para os nomes automáticos de FK ficarem < 64.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->timestamp('extraction_opened_at')->nullable();
            $table->unsignedSmallInteger('extraction_pilot_size')->default(3);
            $table->timestamp('extraction_pilot_released_at')->nullable();
            $table->string('extraction_form_version', 20)->nullable();
            $table->json('manuscript')->nullable(); // checklist, revista, datas, DOI do artigo
        });

        Schema::create('sr_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->json('record_ids')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('pilot')->default(false);
            $table->string('status', 20)->default('active'); // active | merged | excluded
            $table->json('reconciled_values')->nullable();
            $table->json('reconciled_outcomes')->nullable();
            $table->json('reconciled_rob')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('reconciled_by', 255)->nullable();
            $table->text('reconcile_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sr_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sr_study_id')->constrained('sr_studies')->cascadeOnDelete();
            $table->foreignId('systematic_review_screener_id')->constrained(indexName: 'sr_extractions_screener_fk')->cascadeOnDelete();
            $table->string('form_version', 20)->nullable();
            $table->json('field_values')->nullable(); // 'values' é palavra reservada no MySQL
            $table->json('sources')->nullable();
            $table->json('outcomes')->nullable();
            $table->json('rob')->nullable(); // {tool: {domain: {judgement, support}}, overall}
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft'); // draft | submitted
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['sr_study_id', 'systematic_review_screener_id'], 'sr_extractions_study_screener_unique');
        });

        Schema::create('sr_dataset_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->longText('extraction_csv');
            $table->string('extraction_sha256', 64);
            $table->longText('rob_csv');
            $table->string('rob_sha256', 64);
            $table->string('locked_by', 255)->nullable();
            $table->text('justification')->nullable();
            $table->timestamps();
            $table->unique(['systematic_review_id', 'version'], 'sr_locks_review_version_unique');
        });

        Schema::create('sr_analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sr_dataset_lock_id')->nullable()->constrained('sr_dataset_locks')->nullOnDelete();
            $table->string('script_name', 255)->nullable();
            $table->string('script_path', 500)->nullable();
            $table->string('script_sha256', 64)->nullable();
            $table->json('outputs')->nullable(); // [{name, path, sha256}]
            $table->longText('session_info')->nullable();
            $table->string('run_by', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('rerun_by', 255)->nullable();
            $table->timestamp('rerun_at')->nullable();
            $table->boolean('rerun_matches')->nullable();
            $table->text('rerun_notes')->nullable();
            $table->boolean('post_hoc')->default(false);
            $table->timestamps();
        });

        Schema::create('sr_grade_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained()->cascadeOnDelete();
            $table->string('outcome_id', 20);
            $table->string('outcome_name', 500);
            $table->unsignedInteger('k')->nullable();
            $table->unsignedInteger('n')->nullable();
            $table->string('effect', 120)->nullable();
            $table->string('ci', 120)->nullable();
            $table->string('pi', 120)->nullable();
            $table->string('certainty', 20)->nullable(); // high | moderate | low | very_low
            $table->json('reasons')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sr_author_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sr_study_id')->nullable()->constrained('sr_studies')->nullOnDelete();
            $table->string('author', 255);
            $table->string('email', 255)->nullable();
            $table->text('request');
            $table->date('sent_at');
            $table->date('reminder1_at')->nullable();
            $table->date('reminder2_at')->nullable();
            $table->date('responded_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();
        });

        Schema::create('sr_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained()->cascadeOnDelete();
            $table->dateTime('held_at');
            $table->text('participants')->nullable();
            $table->text('decisions');
            $table->text('pending')->nullable();
            $table->date('next_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sr_meetings');
        Schema::dropIfExists('sr_author_contacts');
        Schema::dropIfExists('sr_grade_rows');
        Schema::dropIfExists('sr_analysis_runs');
        Schema::dropIfExists('sr_dataset_locks');
        Schema::dropIfExists('sr_extractions');
        Schema::dropIfExists('sr_studies');
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->dropColumn(['extraction_opened_at', 'extraction_pilot_size', 'extraction_pilot_released_at', 'extraction_form_version', 'manuscript']);
        });
    }
};
