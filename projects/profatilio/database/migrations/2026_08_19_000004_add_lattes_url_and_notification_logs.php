<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('lattes_url')->nullable()->after('cpf');
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_title_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel'); // email, telegram
            $table->string('category'); // cobranca_ic, cobranca_tcc, apresentacao_milestone
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('status')->default('success'); // success, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('lattes_url');
        });
    }
};
