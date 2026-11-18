<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\DocumentSubmission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Altera todos os status antigos de IC concluído para o novo formato
        DocumentSubmission::where('status', 'Processo Concluído - Projeto Aprovado/Finalizado')
            ->update(['status' => 'Processo Concluído - Projeto Arquivado']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DocumentSubmission::where('status', 'Processo Concluído - Projeto Arquivado')
            ->update(['status' => 'Processo Concluído - Projeto Aprovado/Finalizado']);
    }
};
