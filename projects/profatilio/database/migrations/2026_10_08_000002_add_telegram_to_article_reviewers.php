<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisão de artigos/livros: participante (inclusive externo, sem conta)
 * pode vincular o Telegram pelo bot para receber os avisos do processo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_reviewers', function (Blueprint $table) {
            $table->string('telegram_chat_id', 40)->nullable()->after('phone');
            $table->string('telegram_link_token', 40)->nullable()->unique()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('article_reviewers', function (Blueprint $table) {
            $table->dropUnique(['telegram_link_token']);
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token']);
        });
    }
};
