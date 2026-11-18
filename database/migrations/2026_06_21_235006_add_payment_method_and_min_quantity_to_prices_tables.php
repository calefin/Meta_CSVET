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
        Schema::table('animal_prices', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->after('unit_price');
            $table->decimal('min_quantity', 10, 3)->nullable()->after('payment_method');
        });

        Schema::table('supply_prices', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->after('unit_price');
            $table->decimal('min_quantity', 10, 3)->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('animal_prices', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'min_quantity']);
        });

        Schema::table('supply_prices', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'min_quantity']);
        });
    }
};
