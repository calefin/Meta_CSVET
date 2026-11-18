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
        // Update the two broken Unsplash URLs with active, guaranteed image IDs
        DB::table('posts')
            ->where('slug', 'necropsia-veterinaria-forense-quando-e-necessaria-como-solicitar')
            ->update(['image' => 'https://images.unsplash.com/photo-1518152006812-edab29b069ac?q=80&w=600&auto=format&fit=crop']);

        DB::table('posts')
            ->where('slug', 'exumacao-de-animais-e-possivel-realizar-pericia-apos-o-enterro')
            ->update(['image' => 'https://images.unsplash.com/photo-1448375240586-882707db888b?q=80&w=600&auto=format&fit=crop']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
