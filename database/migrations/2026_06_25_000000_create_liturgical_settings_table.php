<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liturgical_settings', function (Blueprint $table) {
            $table->id();
            $table->date('orun_anchor');
            $table->timestamps();
        });

        DB::table('liturgical_settings')->insert([
            'id' => 1,
            'orun_anchor' => '2026-06-22',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('liturgical_settings');
    }
};
