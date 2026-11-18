<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PdfCompressorService
{
    /**
     * Tenta compactar um PDF usando o Ghostscript (gs).
     * Retorna true se a compactação foi bem-sucedida e gerou um arquivo válido.
     *
     * @param string $inputPath Caminho absoluto local do PDF de entrada
     * @param string $outputPath Caminho absoluto local para salvar o PDF compactado
     * @param string $settings Nível de compressão (/screen ou /ebook)
     * @return bool
     */
    public function compress(string $inputPath, string $outputPath, string $settings = '/ebook'): bool
    {
        // 1. Verifica se a função exec está disponível no php.ini
        if (!function_exists('exec')) {
            Log::warning('PdfCompressorService: A função PHP exec() está desabilitada no servidor.');
            return false;
        }

        // 2. Verifica se o Ghostscript está instalado no sistema operacional
        $checkGs = [];
        $checkStatus = -1;
        @exec('gs --version 2>&1', $checkGs, $checkStatus);
        if ($checkStatus !== 0) {
            Log::warning('PdfCompressorService: Ghostscript (gs) não está instalado ou não está acessível no PATH do servidor.');
            return false;
        }

        // 3. Monta o comando Ghostscript
        // Níveis de PDFSETTINGS:
        // /screen = Baixa resolução (72 dpi) - Menor tamanho possível
        // /ebook = Resolução intermediária (150 dpi) - Melhor custo/benefício
        $escapedInput = escapeshellarg($inputPath);
        $escapedOutput = escapeshellarg($outputPath);
        $escapedSettings = escapeshellarg($settings);

        $command = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS={$escapedSettings} -dNOPAUSE -dQUIET -dBATCH -sOutputFile={$escapedOutput} {$escapedInput} 2>&1";

        $output = [];
        $status = -1;
        @exec($command, $output, $status);

        if ($status === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
            return true;
        }

        Log::error("PdfCompressorService: Falha ao executar o comando Ghostscript. Status: {$status}. Saída: " . implode("\n", $output));
        return false;
    }
}
