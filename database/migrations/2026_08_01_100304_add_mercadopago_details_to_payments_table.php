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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('mp_fee_amount', 10, 2)->nullable()->after('mp_payment_id');
            $table->decimal('mp_net_amount', 10, 2)->nullable()->after('mp_fee_amount');
            $table->string('mp_payment_type')->nullable()->after('mp_net_amount');
            $table->string('mp_payment_method')->nullable()->after('mp_payment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['mp_fee_amount', 'mp_net_amount', 'mp_payment_type', 'mp_payment_method']);
        });
    }
};
