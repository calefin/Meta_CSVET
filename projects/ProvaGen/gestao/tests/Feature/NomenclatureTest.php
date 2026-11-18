<?php

namespace Tests\Feature;

use Tests\TestCase;

class NomenclatureTest extends TestCase
{
    /**
     * Teste para garantir que a palavra proibida "tutor" não seja utilizada no banco de dados,
     * migrations ou models (substituída obrigatoriamente por "legal_responsible_name" ou "responsável legal").
     */
    public function test_forbidden_word_tutor_is_not_used_in_database_and_models(): void
    {
        $directories = [
            app_path('Models'),
            database_path('migrations'),
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
            
            foreach ($iterator as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                
                // Remove comentários de bloco (/* ... */) e de linha (// ...) para evitar alarmes falsos
                // em notas explicativas como "Nomenclatura obrigatória (não usar tutor)"
                $cleanContent = preg_replace('!/\*.*?\*/!s', '', $content);
                $cleanContent = preg_replace('!//.*!', '', $cleanContent);

                // Busca pela palavra exata "tutor" (case insensitive) usando limite de palavras (\b)
                // para evitar que acabe bloqueando termos válidos como "tutorial"
                if (preg_match('/\btutor\b/i', $cleanContent, $matches)) {
                    $this->fail(
                        sprintf(
                            "Erro de Nomenclatura: A palavra proibida 'tutor' foi encontrada no código do arquivo:\n%s\nUse 'legal_responsible_name' ou 'responsável legal' em seu lugar.",
                            $file->getPathname()
                        )
                    );
                }
            }
        }

        // Se nenhum arquivo violou a regra
        $this->assertTrue(true);
    }
}
