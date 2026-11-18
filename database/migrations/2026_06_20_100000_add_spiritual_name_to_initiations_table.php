<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('initiations', function (Blueprint $table) {
            $table->string('spiritual_name')->nullable()->after('odu_id');
        });
    }

    public function down(): void
    {
        Schema::table('initiations', function (Blueprint $table) {
            $table->dropColumn('spiritual_name');
        });
    }
};
