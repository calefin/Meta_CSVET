<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Até quando o aluno leu a conversa do projeto. Dá o "N mensagens novas" no
 * Painel, no menu e na lista de projetos, para ele saber onde estão as mensagens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_review_screeners', function (Blueprint $table) {
            $table->timestamp('chat_read_at')->nullable()->after('removed_at');
        });
    }

    public function down(): void
    {
        Schema::table('systematic_review_screeners', function (Blueprint $table) {
            $table->dropColumn('chat_read_at');
        });
    }
};
