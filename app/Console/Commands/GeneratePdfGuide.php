<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePdfGuide extends Command
{
    protected $signature   = 'templates:generate-pdf';
    protected $description = 'Gera o Guia Rápido ABNT em PDF em public/templates/';

    public function handle(): int
    {
        $this->info('Gerando guia_rapido_abnt.pdf...');

        try {
            $html = view('templates.guia-rapido-abnt')->render();

            $pdf  = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            $path = public_path('templates/guia_rapido_abnt.pdf');

            file_put_contents($path, $pdf->output());

            $this->info('Gerado: ' . round(filesize($path) / 1024, 1) . ' KB → ' . $path);
        } catch (\Throwable $e) {
            $this->error('Erro: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
