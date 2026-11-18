<?php

// Registrar o autoloader do Composer
require __DIR__.'/vendor/autoload.php';

// Inicializar a aplicação Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Buscar e atualizar o usuário
$user = \App\Models\User::where('email', 'ascalefi@gmail.com')->first();

if ($user) {
    $user->role = 'admin';
    $user->save();
    echo "\n[SUCESSO] O usuario 'ascalefi@gmail.com' foi definido como 'admin' com sucesso!\n\n";
} else {
    echo "\n[ERRO] Usuario 'ascalefi@gmail.com' nao encontrado no banco de dados.\n";
    echo "Certifique-se de que ja criou o usuario usando: php artisan make:filament-user\n\n";
}
