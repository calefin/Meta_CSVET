<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_activities', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        // Backfill: até aqui a lista era usada só pelo professor (Pomodoro/Atividades
        // era admin-only), então todo registro existente pertence ao primeiro admin.
        $adminId = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
        if ($adminId) {
            DB::table('personal_activities')->whereNull('user_id')->update(['user_id' => $adminId]);
        }
    }

    public function down(): void
    {
        Schema::table('personal_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
