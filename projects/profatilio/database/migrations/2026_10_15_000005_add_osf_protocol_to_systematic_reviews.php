<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protocolo importado do pipeline_PXX.json (schema calefi-sr-pipeline/1.0),
 * congelamento com hash, dados do registro no OSF e desvios/emendas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->string('protocol_code', 10)->nullable()->unique()->after('short_name');
            $table->string('folder', 120)->nullable()->after('protocol_code');
            $table->string('family', 30)->nullable()->after('folder');
            $table->longText('pipeline')->nullable()->after('protocol');
            $table->string('protocol_version', 20)->nullable()->after('pipeline');
            $table->string('protocol_sha256', 64)->nullable()->after('protocol_version');
            $table->timestamp('protocol_frozen_at')->nullable()->after('protocol_sha256');
            $table->string('protocol_pdf_path', 500)->nullable()->after('protocol_frozen_at');
            $table->string('protocol_pdf_sha256', 64)->nullable()->after('protocol_pdf_path');
            $table->string('osf_project_url', 255)->nullable()->after('registration_id');
        });

        Schema::create('systematic_review_deviations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('systematic_review_id')->constrained(indexName: 'sr_deviations_review_fk')->cascadeOnDelete();
            $table->string('type', 20); // deviation | amendment
            $table->string('section', 120);
            $table->text('description');
            $table->text('justification');
            $table->string('requested_by', 255)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('osf_update_ref', 255)->nullable();
            $table->string('protocol_version_after', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('systematic_review_deviations');
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->dropUnique(['protocol_code']);
            $table->dropColumn(['protocol_code', 'folder', 'family', 'pipeline', 'protocol_version', 'protocol_sha256',
                'protocol_frozen_at', 'protocol_pdf_path', 'protocol_pdf_sha256', 'osf_project_url']);
        });
    }
};
