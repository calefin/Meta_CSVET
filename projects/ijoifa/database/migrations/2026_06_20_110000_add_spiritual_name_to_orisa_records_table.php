<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orisa_records', function (Blueprint $table) {
            $table->string('spiritual_name')->nullable()->after('orisa_name');
        });
    }

    public function down(): void
    {
        Schema::table('orisa_records', function (Blueprint $table) {
            $table->dropColumn('spiritual_name');
        });
    }
};
