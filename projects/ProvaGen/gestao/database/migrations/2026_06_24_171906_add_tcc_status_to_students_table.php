<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('advisees', function (Blueprint $table) {
            $table->string('tcc_status')->default('none')->after('research_topic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisees', function (Blueprint $table) {
            $table->dropColumn('tcc_status');
        });
    }
};
