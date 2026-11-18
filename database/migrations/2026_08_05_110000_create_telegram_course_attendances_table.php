<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_course_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_course_subscription_id')
                ->constrained('telegram_course_subscriptions', 'id', 'tc_attendances_subscription_id_foreign')
                ->cascadeOnDelete();
            $table->date('meeting_date');
            $table->boolean('attended')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['telegram_course_subscription_id', 'meeting_date'], 'sub_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_course_attendances');
    }
};
