<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * error_reports, personal_activities e support_ticket_messages têm um
     * `user_id` que, no profatilio original, podia apontar tanto pro
     * professor quanto pro aluno (uma única tabela `users` com role).
     * Agora que são duas tabelas (Owner=users do ProvaGen, AdviseeAccount=
     * advisee_accounts daqui), user_id sozinho é ambíguo — os dois têm
     * sequências de id independentes. `user_type` guarda a classe (padrão
     * de morphTo do Laravel) para resolver o relacionamento sem ambiguidade.
     */
    private const TABLES = ['error_reports', 'personal_activities', 'support_ticket_messages'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('user_type')->default(\App\Models\AdviseeAccount::class)->after('user_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('user_type');
            });
        }
    }
};
