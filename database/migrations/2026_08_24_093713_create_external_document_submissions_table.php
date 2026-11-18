<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('external_document_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_class_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('external_class_participant_id')
                ->constrained(indexName: 'ext_doc_sub_participant_fk')
                ->cascadeOnDelete();
            $table->string('document_type');
            $table->unsignedTinyInteger('reference_month_start');
            $table->unsignedTinyInteger('reference_month_end');
            $table->unsignedSmallInteger('reference_year');
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('status')->default('Aguardando Análise');
            $table->string('returned_file_path')->nullable();
            $table->text('return_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['external_class_participant_id', 'document_type'], 'ext_doc_sub_participant_doctype_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_document_submissions');
    }
};
