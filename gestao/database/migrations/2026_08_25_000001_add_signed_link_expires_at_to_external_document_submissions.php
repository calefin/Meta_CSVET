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
        Schema::table('external_document_submissions', function (Blueprint $table) {
            // Data de expiração do link assinado (temporarySignedRoute) enviado
            // por e-mail ao aluno quando o documento é assinado — puramente
            // informativa (a validade real está embutida na assinatura HMAC da
            // própria URL); usada só pra exibir "expira em" no painel do
            // professor.
            $table->timestamp('signed_link_expires_at')->nullable()->after('returned_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('external_document_submissions', function (Blueprint $table) {
            $table->dropColumn('signed_link_expires_at');
        });
    }
};
