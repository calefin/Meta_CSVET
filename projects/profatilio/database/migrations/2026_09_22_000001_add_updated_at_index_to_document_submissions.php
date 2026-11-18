<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sem índice em updated_at, o "order by updated_at" dos painéis fazia o
     * MySQL ordenar linhas inteiras (com o JSON metadata, que guarda o texto
     * das etapas de TCC) e estourava o sort buffer da HostGator
     * ("1038 Out of sort memory"). Com o índice, o MySQL lê já na ordem.
     */
    public function up(): void
    {
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->index('updated_at', 'doc_sub_updated_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->dropIndex('doc_sub_updated_at_idx');
        });
    }
};
