<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@ijoifa.com'],
            [
                'name' => 'Administrador',
                'mother_maiden_name' => 'N/A',
                'country' => 'BR',
                'role' => 'admin',
                'status' => 'active',
                'locale' => 'pt-BR',
                'password' => 'troque-esta-senha',
            ]
        );
    }
}
