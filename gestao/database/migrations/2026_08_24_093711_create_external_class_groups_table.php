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
        Schema::create('external_class_groups', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('university_name');
            $table->string('group_name');
            $table->string('course')->nullable();
            $table->string('semester')->nullable();
            $table->unsignedSmallInteger('year');
            $table->boolean('active')->default(true);
            // users.id do ProvaGen é INT assinado, não bigint — ver nota em
            // 2026_08_24_100001_add_owner_user_id_to_domain_tables.php.
            $table->integer('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_class_groups');
    }
};
