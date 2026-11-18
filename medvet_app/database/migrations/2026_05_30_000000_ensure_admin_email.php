<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereIn('email', ['ascalefi@gmail.com', 'contato@medvetlegal.com.br'])
            ->update(['is_admin' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to revert
    }
};
