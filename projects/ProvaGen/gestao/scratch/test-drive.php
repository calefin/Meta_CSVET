<?php

// Para rodar este teste, execute no terminal:
// C:\xampp\php\php.exe scratch/test-drive.php

echo "=== INICIANDO TESTE DE CONEXÃO COM GOOGLE DRIVE ===\n";

include __DIR__ . '/../vendor/autoload.php';
$app = include __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $diskName = config('filesystems.default');
    echo "Disco padrão configurado: '{$diskName}'\n";
    
    if ($diskName !== 'google') {
        echo "AVISO: O disco padrão está configurado como '{$diskName}' e não 'google'.\n";
        echo "Forçando conexão no disco 'google' para teste...\n";
    }
    
    $disk = Illuminate\Support\Facades\Storage::disk('google');
    $testFileName = 'teste_conexao_profatilio_' . time() . '.txt';
    $testContent = 'Esta é uma mensagem de teste para validar a conexão da plataforma profatilio.com.br com o Google Drive.';
    
    echo "1. Tentando criar arquivo de teste '{$testFileName}' no Google Drive...\n";
    $disk->put($testFileName, $testContent);
    echo "   -> OK: Arquivo criado com sucesso!\n";
    
    echo "2. Tentando ler o conteúdo do arquivo criado...\n";
    $readContent = $disk->get($testFileName);
    
    if ($readContent === $testContent) {
        echo "   -> OK: Conteúdo lido coincide perfeitamente!\n";
    } else {
        echo "   -> ERRO: Conteúdo lido difere do original!\n";
        echo "      Original: '{$testContent}'\n";
        echo "      Lido: '{$readContent}'\n";
    }
    
    echo "3. Tentando apagar o arquivo de teste...\n";
    $disk->delete($testFileName);
    echo "   -> OK: Arquivo de teste removido da nuvem com sucesso!\n";
    
    echo "\n🏆 SUCESSO: A conexão com o seu Google Drive está funcionando perfeitamente!\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: Falha na conexão com o Google Drive!\n";
    echo "Mensagem de erro: " . $e->getMessage() . "\n";
    echo "Verifique se o seu CLIENT_ID, CLIENT_SECRET, REFRESH_TOKEN e FOLDER_ID no .env estão corretos.\n";
}
echo "===================================================\n";
