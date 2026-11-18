<?php
define('LARAVEL_START', microtime(true));

// Access restriction (optional, but good practice for safety)
// You can remove or comment this if you want to run it without host checks.
$allowedHosts = ['localhost', '127.0.0.1', 'profatilio.com.br', 'www.profatilio.com.br'];
if (!in_array($_SERVER['HTTP_HOST'] ?? '', $allowedHosts)) {
    abort(403, 'Acesso não autorizado.');
}

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// Resolve console kernel to run Artisan commands
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "<pre>";
echo "=== Diagnóstico e Limpeza de Cache ===\n\n";

// 1. Reset OPcache (caches PHP compiled bytecode in memory)
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "[OK] OPcache limpo com sucesso! (Arquivos PHP recarregados do disco)\n";
    } else {
        echo "[Erro] Falha ao limpar OPcache.\n";
    }
} else {
    echo "[Aviso] Função opcache_reset não está disponível.\n";
}

// 2. Clear Laravel configuration cache
$status = $kernel->call('config:clear');
echo "[Artisan] config:clear - Código de saída: $status\n";

// 3. Clear Laravel route cache
$status = $kernel->call('route:clear');
echo "[Artisan] route:clear - Código de saída: $status\n";

// 4. Clear Laravel view cache
$status = $kernel->call('view:clear');
echo "[Artisan] view:clear - Código de saída: $status\n";

// 5. Clear general cache
$status = $kernel->call('cache:clear');
echo "[Artisan] cache:clear - Código de saída: $status\n";

echo "\nProcesso concluído! Verifique se o erro na submissão sumiu.\n";
echo "</pre>";
