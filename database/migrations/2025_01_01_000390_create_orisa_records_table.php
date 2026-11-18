<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Other Orisa "made" for the person but not through initiation
     * (e.g. an Orisa settled/received separately) - just date and Odu.
     */
    public function up(): void
    {
        Schema::create('orisa_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('orisa_name', 100);
            $table->foreignId('odu_id')->nullable()->constrained('odus');
            $table->date('performed_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orisa_records');
    }
};
