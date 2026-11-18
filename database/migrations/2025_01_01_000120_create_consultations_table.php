<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->nullable()->constrained('consultation_requests')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('consulted_at');
            $table->enum('modality', ['online', 'presential']);
            $table->foreignId('odu_main_id')->constrained('odus');
            $table->foreignId('odu_witness_1_id')->nullable()->constrained('odus');
            $table->foreignId('odu_witness_2_id')->nullable()->constrained('odus');
            $table->enum('ire_ibi', ['ire', 'ibi']);
            $table->foreignId('ire_ibi_type_id')->constrained('ire_ibi_types');
            $table->text('general_guidance')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->string('created_ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
