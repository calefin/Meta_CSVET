<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Direito ao esquecimento" (LGPD sec. 12): a request the user files
     * from their panel, reviewed/resolved by the admin - not an automatic
     * deletion, since religious/spiritual records may need retention review.
     */
    public function up(): void
    {
        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_requests');
    }
};
