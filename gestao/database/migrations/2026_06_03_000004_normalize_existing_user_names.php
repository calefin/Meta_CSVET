<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\AdviseeAccount;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normaliza nomes de contas de aluno existentes (dado legado do profatilio
        // original não se aplica aqui — este app começa sem dados; útil só se essa
        // migration rodar depois de já existirem cadastros).
        $users = AdviseeAccount::all();

        foreach ($users as $user) {
            if (!empty($user->name)) {
                $normalized = AdviseeAccount::normalizeName($user->name);
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
