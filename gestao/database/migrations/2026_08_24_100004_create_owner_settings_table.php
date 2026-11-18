<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configurações do professor que não existem na `users` do ProvaGen
     * (Telegram, credenciais de storage próprio — Google Drive/OneDrive).
     * 1:1 com um owner_user_id. Ver App\Models\OwnerSettings.
     */
    public function up(): void
    {
        Schema::create('owner_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('owner_user_id')->unique();
            $table->foreign('owner_user_id')->references('id')->on('users');
            $table->string('telegram_chat_id')->nullable()->unique();
            $table->string('telegram_link_token')->nullable()->unique();
            $table->json('storage_settings')->nullable(); // Drive/OneDrive: client/refresh token por professor
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_settings');
    }
};
