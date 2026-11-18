<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('requester_phone');
            $table->string('animal_type'); // 'convencional' | 'nao_convencional'
            $table->string('species');
            $table->text('complaint');
            $table->text('admin_notes')->nullable();
            $table->string('status')->default('pending'); // pending, contacted, scheduled, completed, cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_requests');
    }
};
