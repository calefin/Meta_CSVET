<?php

namespace App\Console\Commands;

use App\Services\DriveCleanupService;
use Illuminate\Console\Command;

class CleanupObsoleteDriveFiles extends Command
{
    protected $signature = 'app:limpar-drive
        {--apagar : Apaga de verdade (sem isso, só mostra o que seria apagado)}
        {--categoria=* : trash, duplicate e/ou orphan (padrão: todas)}
        {--dias=7 : Idade mínima, em dias, de duplicatas e órfãos}';

    protected $description = 'Lista (ou apaga definitivamente) arquivos obsoletos do Google Drive: lixeira, versões antigas duplicadas e órfãos';

    public function handle(DriveCleanupService $service): int
    {
        if (!DriveCleanupService::isAvailable()) {
            $this->error('O disco padrão não é o Google Drive (FILESYSTEM_DISK=google).');
            return self::FAILURE;
        }

        $categories = $this->option('categoria') ?: array_keys(DriveCleanupService::CATEGORY_LABELS);
        $scan = $service->scan((int) $this->option('dias'));
        $candidates = array_values(array_filter($scan['candidates'], fn ($c) => in_array($c['category'], $categories, true)));

        $this->table(
            ['Categoria', 'Arquivo', 'Tamanho', 'Data'],
            array_map(fn ($c) => [$c['category'], $c['path'], self::human($c['size']), $c['modified']], $candidates)
        );

        foreach ($scan['totals'] as $category => $total) {
            $this->line(sprintf('%-35s %4d arquivo(s)  %s', DriveCleanupService::CATEGORY_LABELS[$category], $total['count'], self::human($total['bytes'])));
        }

        if ($scan['quota']) {
            $q = $scan['quota'];
            $this->line(sprintf('Cota do Drive: %s de %s (lixeira: %s)', self::human($q['usage']), $q['limit'] ? self::human($q['limit']) : 'ilimitado', self::human($q['usage_in_trash'])));
        }

        if (!$this->option('apagar')) {
            $this->warn('Simulação — nada foi apagado. Rode com --apagar para excluir definitivamente.');
            return self::SUCCESS;
        }

        if ($candidates === []) {
            $this->info('Nada a apagar.');
            return self::SUCCESS;
        }

        $stats = $service->delete($candidates);
        $this->info(sprintf('Apagados: %d (%s) | pulados: %d | falhas: %d', $stats['deleted'], self::human($stats['bytes']), $stats['skipped'], $stats['failed']));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    public static function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return number_format($value, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
    }
}
