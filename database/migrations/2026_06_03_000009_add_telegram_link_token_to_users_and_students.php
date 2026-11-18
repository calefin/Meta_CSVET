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
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_link_token')->nullable()->unique()->after('telegram_chat_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('telegram_link_token')->nullable()->unique()->after('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telegram_link_token');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('telegram_link_token');
        });
    }
};
