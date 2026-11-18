<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_course_subscriptions', function (Blueprint $table) {
            $table->string('telegram_user_id')->nullable()->after('telegram_username');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_course_subscriptions', function (Blueprint $table) {
            $table->dropColumn('telegram_user_id');
        });
    }
};
