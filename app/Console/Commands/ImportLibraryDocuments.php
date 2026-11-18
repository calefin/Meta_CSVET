<?php

namespace App\Console\Commands;

use App\Models\LibraryCategory;
use App\Models\LibraryDocument;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Imports reference library documents from the extracted JSON content.
 * Converts raw text into structured HTML paragraphs with copy-prevention markers.
 */
class ImportLibraryDocuments extends Command
{
    protected $signature = 'library:import';

    protected $description = 'Importa os textos extraídos dos PDFs do arquivo JSON para a biblioteca como HTML';

    public function handle(): int
    {
        $admin = User::where('role', 'admin')->first();

        if (! $admin) {
            $this->error('Nenhum usuário admin encontrado.');

            return self::FAILURE;
        }

        $jsonPath = database_path('seeders/extracted_content.json');

        if (! file_exists($jsonPath)) {
            $this->error("Arquivo JSON de extração não encontrado em: {$jsonPath}");

            return self::FAILURE;
        }

        $importData = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Erro ao decodificar JSON: " . json_last_error_msg());

            return self::FAILURE;
        }

        foreach ($importData as $key => $data) {
            $categorySlug = $data['category'];
            $filename = $data['filename'];
            $rawText = $data['text'];

            $category = LibraryCategory::where('slug', $categorySlug)->first();

            if (! $category) {
                $this->warn("Categoria '{$categorySlug}' não existe, pulando.");

                continue;
            }

            $title = (string) Str::of($filename)->beforeLast('.pdf')->trim();

            if ($category->documents()->where('title', $title)->exists()) {
                $this->line("Já importado: {$title}");

                continue;
            }

            $language = match (true) {
                str_contains($filename, 'Yorùbá Version') => 'yo',
                str_contains($filename, 'English Version') => 'en',
                str_contains($filename, 'Handbook') => 'en',
                default => 'pt-BR',
            };

            $htmlContent = $this->formatTextToHtml($rawText);

            LibraryDocument::create([
                'category_id' => $category->id,
                'title' => $title,
                'language' => $language,
                'content' => $htmlContent,
                'file_path' => null,
                'order' => $category->documents()->count(),
                'uploaded_by' => $admin->id,
            ]);

            $this->info("Importado: {$title} ({$language}) -> {$category->name}");
        }

        return self::SUCCESS;
    }

    private function formatTextToHtml(string $text): string
    {
        // Replace page breaks with a divider
        $text = preg_replace_callback('/--- PAGE (\d+) ---/', function ($matches) {
            return "<div class='text-xs text-ebony/35 border-b border-dashed pb-1 mt-6 mb-4 font-sans select-none'>Página {$matches[1]}</div>";
        }, $text);

        // Convert line breaks to paragraphs
        $paragraphs = explode("\n", $text);
        $html = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) {
                continue;
            }
            if (str_starts_with($para, "<div")) {
                $html .= $para;
            } else {
                $html .= "<p class='mb-3 font-serif leading-relaxed text-ebony/90'>" . e($para) . "</p>";
            }
        }
        return $html;
    }
}
