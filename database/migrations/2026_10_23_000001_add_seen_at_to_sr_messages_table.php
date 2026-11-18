<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quando o professor viu a mensagem do aluno. Marcar como vista tira o aviso
 * "aguardando mensagem" da Central sem precisar responder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sr_messages', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('sr_messages', function (Blueprint $table) {
            $table->dropColumn('seen_at');
        });
    }
};
