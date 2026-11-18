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
        Schema::create('quick_payments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 64)->unique();
            $table->enum('type', ['consulta', 'trabalhos_gerais', 'iniciacao', 'aborisa']);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('items')->nullable();
            $table->decimal('markup_percent', 5, 2)->default(0);
            $table->decimal('fee_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quick_payments');
    }
};
