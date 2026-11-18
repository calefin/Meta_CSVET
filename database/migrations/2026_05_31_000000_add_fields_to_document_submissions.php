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
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->string('document_type')->default('TCC')->after('title'); // TCC, Projeto de Iniciação Científica, Relatório, Assinatura de Documento, Outro
            $table->string('google_drive_returned_file_id')->nullable()->after('google_drive_file_id'); // Link ou ID do arquivo assinado/corrigido retornado pelo professor
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'google_drive_returned_file_id']);
        });
    }
};
