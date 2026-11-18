<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            // 'cobranca' = prazo de entrega, cobra recorrentemente após vencer.
            // 'apresentacao' = data de apresentação do TCC, avisa em contagem
            // regressiva antes da data (30/15/7/3/1/0 dias), sem cobrança.
            $table->string('type')->default('cobranca')->after('academic_title_id');
            $table->json('notified_milestones')->nullable()->after('last_charged_at');
        });
    }

    public function down(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropColumn(['type', 'notified_milestones']);
        });
    }
};
