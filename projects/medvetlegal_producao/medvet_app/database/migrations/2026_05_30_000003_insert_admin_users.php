<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $admins = [
            [
                'name' => 'Atilio Sersun Calefi',
                'email' => 'ascalefi@gmail.com',
                'password' => Hash::make('041188As'),
                'is_admin' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'MedVetLegal Admin',
                'email' => 'contato@medvetlegal.com.br',
                'password' => Hash::make('041188As'),
                'is_admin' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Dra. Adriana Siqueira',
                'email' => 'patoforense.adriana@gmail.com',
                'password' => Hash::make('041188As'),
                'is_admin' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($admins as $admin) {
            $exists = DB::table('users')->where('email', $admin['email'])->exists();
            if (!$exists) {
                DB::table('users')->insert($admin);
            } else {
                DB::table('users')->where('email', $admin['email'])->update(['is_admin' => true]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
