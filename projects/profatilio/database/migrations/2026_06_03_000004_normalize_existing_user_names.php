<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normaliza todos os nomes de usuários existentes no banco de dados de produção
        $users = User::all();

        foreach ($users as $user) {
            if (!empty($user->name)) {
                $normalized = User::normalizeName($user->name);
                if ($user->name !== $normalized) {
                    $user->name = $normalized;
                    $user->saveQuietly();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // A reversão de normalização de dados históricos não é aplicável
    }
};
