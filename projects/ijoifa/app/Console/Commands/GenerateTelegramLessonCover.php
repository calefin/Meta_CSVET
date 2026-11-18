<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Generates the standard 1080x1080 cover image posted to the Telegram group
 * announcing each lesson (number + theme), styled to match the book covers
 * (cream background, Playfair Display serif) and the site's Tailwind palette.
 */
class GenerateTelegramLessonCover extends Command
{
    protected $signature = 'telegram-course:generate-cover
        {number : Número da aula, ex: 01}
        {theme : Tema da aula, ex: "O Uso do Obì"}
        {--subtitle= : Subtítulo opcional, ex: "Curso de Formação Contínua"}
        {--output= : Caminho de saída relativo ao disco public}';

    protected $description = 'Gera a foto de capa padrão (1080x1080) de uma aula do Curso Telegram';

    private const CREAM = '#FAF7F0';

    private const EBONY = '#1A1A1A';

    private const GREEN = '#2D6A4F';

    public function handle(): int
    {
        $number = str_pad((string) $this->argument('number'), 2, '0', STR_PAD_LEFT);
        $theme = (string) $this->argument('theme');
        $subtitle = (string) ($this->option('subtitle') ?: 'Curso de Formação Contínua no Telegram');
        $output = $this->option('output') ?: "telegram-course/covers/aula-{$number}.png";

        $fonts = base_path('resources/fonts');
        $bold = $fonts.'/PlayfairDisplay-Bold.ttf';
        $regular = $fonts.'/PlayfairDisplay-Regular.ttf';
        $italic = $fonts.'/PlayfairDisplay-Italic.ttf';

        $size = 1080;
        $manager = new ImageManager(new Driver);
        $image = $manager->create($size, $size)->fill(self::CREAM);

        // Small caps header
        $image->text('Ì J Ọ   I F Á   È J Ì O G B È', $size / 2, 130, function ($font) use ($bold) {
            $font->filename($bold);
            $font->size(30);
            $font->color(self::EBONY);
            $font->align('center');
            $font->valign('middle');
        });

        $this->drawRule($image, $size / 2, 175, 460, self::EBONY);

        // Big lesson number
        $image->text('AULA '.$number, $size / 2, 460, function ($font) use ($bold) {
            $font->filename($bold);
            $font->size(120);
            $font->color(self::GREEN);
            $font->align('center');
            $font->valign('middle');
        });

        // Theme (wraps automatically if long)
        $image->text($theme, $size / 2, 610, function ($font) use ($italic) {
            $font->filename($italic);
            $font->size(52);
            $font->color(self::EBONY);
            $font->align('center');
            $font->valign('middle');
            $font->lineHeight(1.3);
            $font->wrap(860);
        });

        $this->drawRule($image, $size / 2, 900, 460, self::EBONY);

        $image->text(mb_strtoupper($subtitle), $size / 2, 945, function ($font) use ($regular) {
            $font->filename($regular);
            $font->size(24);
            $font->color(self::EBONY);
            $font->align('center');
            $font->valign('middle');
        });

        $image->text('ijoifa.com', $size / 2, 985, function ($font) use ($regular) {
            $font->filename($regular);
            $font->size(22);
            $font->color(self::GREEN);
            $font->align('center');
            $font->valign('middle');
        });

        \Illuminate\Support\Facades\Storage::disk('public')->put($output, (string) $image->toPng());

        $this->info('Capa gerada em: '.\Illuminate\Support\Facades\Storage::disk('public')->path($output));

        return self::SUCCESS;
    }

    private function drawRule($image, int $centerX, int $y, int $width, string $color): void
    {
        $image->drawLine(function ($line) use ($centerX, $y, $width, $color) {
            $line->from($centerX - (int) ($width / 2), $y);
            $line->to($centerX + (int) ($width / 2), $y);
            $line->color($color);
            $line->width(2);
        });
    }
}
