<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_payments', function (Blueprint $table) {
            $table->json('options')->nullable()->after('items');
            $table->unsignedTinyInteger('selected_option_index')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('quick_payments', function (Blueprint $table) {
            $table->dropColumn(['options', 'selected_option_index']);
        });
    }
};
