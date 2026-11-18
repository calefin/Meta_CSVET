<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_mentions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('url');
            $table->string('outlet');
            $table->string('section')->nullable();
            $table->string('author')->nullable();
            $table->date('published_at');
            $table->text('excerpt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_mentions');
    }
};
