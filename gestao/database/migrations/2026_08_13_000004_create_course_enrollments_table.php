<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('advisees')->cascadeOnDelete();
            // users.id do ProvaGen é INT assinado, não bigint — ver nota em
            // 2026_08_24_100001_add_owner_user_id_to_domain_tables.php.
            $table->integer('enrolled_by')->nullable();
            $table->foreign('enrolled_by')->references('id')->on('users')->nullOnDelete();
            $table->string('status')->default('em_andamento'); // em_andamento | concluido
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};
