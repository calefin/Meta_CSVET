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
        // Insert-only: each row is one price change. The latest row is the
        // current price, and the full table is the audit trail (old/new
        // values are just adjacent rows, "changed by" is created_by, "when"
        // is created_at) — no separate history table needed.
        Schema::create('consultation_price_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('base_price', 10, 2);
            $table->decimal('fee_percent', 5, 2)->default(6);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_price_settings');
    }
};
