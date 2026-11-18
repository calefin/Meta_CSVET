<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_course_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('telegram_username');
            $table->decimal('monthly_price', 10, 2)->default(180.00);
            $table->string('status')->default('pending_payment'); // pending_payment, active, overdue, cancelled
            $table->boolean('terms_accepted')->default(true);
            $table->timestamp('terms_accepted_at');
            $table->string('terms_ip_address')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_course_subscriptions');
    }
};
