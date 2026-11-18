<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('necropsy_requests', function (Blueprint $table) {
            // Quando o professor deu retorno ao interessado (WhatsApp/e-mail).
            $table->timestamp('contacted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('necropsy_requests', function (Blueprint $table) {
            $table->dropColumn('contacted_at');
        });
    }
};
