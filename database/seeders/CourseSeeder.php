<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = database_path('seeders/extracted_content.json');
        if (! file_exists($jsonPath)) {
            return;
        }

        // The export is an object keyed by "category/filename.pdf", whose value is
        // itself a {category, filename, text} record (the key duplicates category+filename).
        $importData = json_decode(file_get_contents($jsonPath), true);
        if (empty($importData)) {
            return;
        }

        // Open content for every iniciado, free, no purchase needed (App\Models\Course::userCanAccess).
        $course = Course::firstOrCreate(
            ['title' => 'Formação Tradicional e Práticas de Ifá'],
            [
                'description' => 'Reza diária, ofós e encantamentos, como consultar com Obi e realizar o Osé, e mitologia Yorùbá — conteúdo aberto a todo iniciado.',
                'target' => 'iniciado',
                'active' => true,
                'price' => 0.00,
            ]
        );

        // Map source folder (category slug) to a module name in the user's vocabulary.
        $moduleNames = [
            'rezas-diarias' => 'Reza Diária',
            'tecnicas-de-consulta' => 'Como consultar com Obi e fazer Osé',
            'incantations' => 'Ofós e Encantamentos',
            'mitologia-e-terminologia' => 'Mitologia e Terminologia Yorùbá',
        ];

        $modules = [];
        $order = 0;
        foreach ($moduleNames as $slug => $name) {
            $modules[$slug] = CourseModule::firstOrCreate(
                ['course_id' => $course->id, 'title' => $name],
                ['order' => $order++]
            );
        }

        foreach ($importData as $data) {
            $categorySlug = $data['category'] ?? null;
            $filename = $data['filename'] ?? null;
            $rawText = $data['text'] ?? null;

            if (! $categorySlug || ! $filename || ! isset($modules[$categorySlug]) || ! is_string($rawText) || trim($rawText) === '') {
                continue;
            }

            $module = $modules[$categorySlug];
            $title = (string) Str::of($filename)->beforeLast('.pdf')->trim();

            Lesson::firstOrCreate(
                ['module_id' => $module->id, 'title' => $title],
                [
                    'type' => 'text',
                    'content' => $this->formatTextToHtml($rawText),
                    'order' => $module->lessons()->count(),
                ]
            );
        }
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
