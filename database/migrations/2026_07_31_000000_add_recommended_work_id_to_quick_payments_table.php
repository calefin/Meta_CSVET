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
        Schema::table('quick_payments', function (Blueprint $table) {
            $table->foreignId('recommended_work_id')->nullable()->after('user_id')
                ->constrained('recommended_works')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quick_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recommended_work_id');
        });
    }
};
