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
        // Coluna equivalente para "users" (agora advisee_accounts) já nasce na
        // migration de criação da tabela — só falta aqui a de advisees.
        Schema::table('advisees', function (Blueprint $table) {
            $table->string('telegram_link_token')->nullable()->unique()->after('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisees', function (Blueprint $table) {
            $table->dropColumn('telegram_link_token');
        });
    }
};
