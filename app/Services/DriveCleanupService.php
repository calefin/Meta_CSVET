<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Localiza (e apaga definitivamente) arquivos obsoletos do Google Drive do app.
 *
 * Categorias:
 *  - trash:     arquivos das pastas do app que já estão na lixeira do Drive. O
 *               move() do adaptador manda para a lixeira a versão anterior de
 *               mesmo nome a cada correção reenviada — e a lixeira conta na cota.
 *  - duplicate: vários arquivos com o mesmo nome na mesma pasta; mantém só o
 *               mais recente (o Drive aceita nomes repetidos).
 *  - orphan:    arquivo cujo nome não aparece em nenhum registro do banco
 *               (versões substituídas, uploads abandonados, temporários).
 *
 * Nunca usa o delete() por caminho do adaptador: com nomes repetidos ele apaga
 * TODAS as cópias, inclusive a que está em uso. Sempre apaga por ID.
 */
class DriveCleanupService
{
    /** Pastas onde órfãos/duplicatas podem ser apagados. */
    public const CLEANABLE_FOLDERS = ['submissions', 'returns', 'temp_signatures', 'livewire-tmp'];

    /** Tabelas ignoradas na busca por referências (guardam cópias de listagens do Drive, não referências reais). */
    protected const IGNORED_TABLES = ['cache', 'cache_locks', 'sessions', 'migrations'];

    protected const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public const SCAN_CACHE_KEY = 'drive_cleanup:last_scan';

    public const CATEGORY_LABELS = [
        'trash'     => 'Lixeira do Drive',
        'duplicate' => 'Versões antigas duplicadas',
        'orphan'    => 'Órfãos (sem vínculo no sistema)',
    ];

    public static function isAvailable(): bool
    {
        return config('filesystems.default') === 'google';
    }

    protected ?\Google\Service\Drive $service = null;

    protected function service(): \Google\Service\Drive
    {
        if ($this->service) {
            return $this->service;
        }

        $adapter = Storage::disk('google')->getAdapter();
        if ($adapter instanceof \App\Filesystems\CachedGoogleDriveAdapter) {
            $adapter = $adapter->getAdapter();
        }

        $adapter->refreshToken();

        return $this->service = $adapter->getService();
    }

    /**
     * A raiz que o adaptador usa de fato — não o GOOGLE_DRIVE_FOLDER_ID cru: com
     * display paths o adaptador tratou esse ID como NOME e criou uma pasta
     * chamada "<id>" dentro dele; é lá que ficam submissions/, returns/ etc.
     */
    protected function rootFolderId(): string
    {
        $adapter = Storage::disk('google')->getAdapter();
        if ($adapter instanceof \App\Filesystems\CachedGoogleDriveAdapter) {
            $adapter = $adapter->getAdapter();
        }

        try {
            $root = (new \ReflectionProperty($adapter, 'root'))->getValue($adapter);
            if (is_string($root) && $root !== '') {
                return $root;
            }
        } catch (\Throwable $e) {
            // cai no config abaixo
        }

        return config('filesystems.disks.google.folder') ?: 'root';
    }

    /**
     * @return array{limit: ?int, usage: int, usage_in_drive: int, usage_in_trash: int}
     */
    public function quota(): array
    {
        $quota = $this->service()->about->get(['fields' => 'storageQuota'])->getStorageQuota();

        return [
            'limit'          => $quota->getLimit() !== null ? (int) $quota->getLimit() : null,
            'usage'          => (int) $quota->getUsage(),
            'usage_in_drive' => (int) $quota->getUsageInDrive(),
            'usage_in_trash' => (int) $quota->getUsageInDriveTrash(),
        ];
    }

    /**
     * Varre o Drive e classifica os candidatos a exclusão.
     *
     * @return array{scanned_at: string, min_age_days: int, candidates: array<int, array>, totals: array, quota: ?array}
     */
    public function scan(int $minAgeDays = 7): array
    {
        $cutoff = now()->subDays($minAgeDays)->getTimestamp();
        $folders = $this->appFolders();
        $haystack = $this->referencedHaystack();
        $candidates = [];

        foreach ($folders as $folder) {
            // Lixeira: tudo que o próprio app já descartou em qualquer pasta dele.
            foreach ($this->listFiles($folder['id'], trashed: true) as $file) {
                $candidates[] = $this->candidate($file, $folder['name'], 'trash', 'Já estava na lixeira (versão substituída)');
            }

            if (!in_array($folder['name'], self::CLEANABLE_FOLDERS, true)) {
                continue;
            }

            $byName = [];
            foreach ($this->listFiles($folder['id'], trashed: false) as $file) {
                $byName[$file['name']][] = $file;
            }

            foreach ($byName as $name => $files) {
                usort($files, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);
                $newest = array_shift($files);

                foreach ($files as $older) {
                    if ($older['timestamp'] < $cutoff) {
                        $candidates[] = $this->candidate($older, $folder['name'], 'duplicate', 'Existe versão mais recente com o mesmo nome');
                    }
                }

                if ($newest['timestamp'] < $cutoff && !$this->isReferenced($name, $haystack)) {
                    $candidates[] = $this->candidate($newest, $folder['name'], 'orphan', 'Nenhum registro do sistema aponta para este arquivo');
                }
            }
        }

        $totals = [];
        foreach (array_keys(self::CATEGORY_LABELS) as $category) {
            $items = array_filter($candidates, fn ($c) => $c['category'] === $category);
            $totals[$category] = [
                'count' => count($items),
                'bytes' => array_sum(array_column($items, 'size')),
            ];
        }

        try {
            $quota = $this->quota();
        } catch (\Throwable $e) {
            $quota = null;
        }

        $result = [
            'scanned_at'   => now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'min_age_days' => $minAgeDays,
            'candidates'   => $candidates,
            'totals'       => $totals,
            'quota'        => $quota,
        ];

        Cache::put(self::SCAN_CACHE_KEY, $result, now()->addHours(6));

        return $result;
    }

    /**
     * Apaga definitivamente (sem passar pela lixeira) os arquivos informados.
     * Órfãos são reconferidos contra o banco na hora, caso algo tenha sido
     * vinculado a eles desde a varredura.
     *
     * @param  array<int, array{id: string, name: string, category: string}>  $candidates
     * @return array{deleted: int, bytes: int, skipped: int, failed: int}
     */
    public function delete(array $candidates): array
    {
        $service = $this->service();
        $haystack = null;
        $stats = ['deleted' => 0, 'bytes' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($candidates as $candidate) {
            if ($candidate['category'] === 'orphan') {
                $haystack ??= $this->referencedHaystack();
                if ($this->isReferenced($candidate['name'], $haystack)) {
                    $stats['skipped']++;
                    continue;
                }
            }

            try {
                $service->files->delete($candidate['id']);
                $stats['deleted']++;
                $stats['bytes'] += (int) ($candidate['size'] ?? 0);

                Log::info('Limpeza do Drive: arquivo apagado definitivamente.', [
                    'id'       => $candidate['id'],
                    'path'     => $candidate['path'] ?? $candidate['name'],
                    'category' => $candidate['category'],
                ]);
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() === 404) {
                    $stats['skipped']++; // já não existe
                    continue;
                }
                $stats['failed']++;
                Log::warning('Limpeza do Drive: falha ao apagar arquivo.', ['id' => $candidate['id'], 'error' => $e->getMessage()]);
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::warning('Limpeza do Drive: falha ao apagar arquivo.', ['id' => $candidate['id'], 'error' => $e->getMessage()]);
            }
        }

        // Listagens/exists em cache podem apontar para arquivos que não existem mais.
        $this->forgetDriveCache();

        return $stats;
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function appFolders(): array
    {
        $folders = [];
        $pageToken = null;

        do {
            $response = $this->service()->files->listFiles([
                'q'         => sprintf("'%s' in parents and mimeType = '%s' and trashed = false", $this->rootFolderId(), self::FOLDER_MIME),
                'fields'    => 'nextPageToken, files(id, name)',
                'pageSize'  => 1000,
                'pageToken' => $pageToken,
            ]);

            foreach ($response->getFiles() as $folder) {
                $folders[] = ['id' => $folder->getId(), 'name' => $folder->getName()];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $folders;
    }

    /**
     * @return \Generator<array{id: string, name: string, size: int, timestamp: int, modified: string}>
     */
    protected function listFiles(string $folderId, bool $trashed): \Generator
    {
        $pageToken = null;

        do {
            $response = $this->service()->files->listFiles([
                'q'         => sprintf("'%s' in parents and mimeType != '%s' and trashed = %s", $folderId, self::FOLDER_MIME, $trashed ? 'true' : 'false'),
                'fields'    => 'nextPageToken, files(id, name, size, createdTime, modifiedTime)',
                'pageSize'  => 1000,
                'pageToken' => $pageToken,
            ]);

            foreach ($response->getFiles() as $file) {
                $timestamp = max(strtotime($file->getCreatedTime() ?? '') ?: 0, strtotime($file->getModifiedTime() ?? '') ?: 0);

                yield [
                    'id'        => $file->getId(),
                    'name'      => $file->getName(),
                    'size'      => (int) $file->getSize(),
                    'timestamp' => $timestamp,
                    'modified'  => $timestamp ? date('d/m/Y', $timestamp) : '-',
                ];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);
    }

    protected function candidate(array $file, string $folder, string $category, string $reason): array
    {
        return [
            'id'       => $file['id'],
            'name'     => $file['name'],
            'path'     => "{$folder}/{$file['name']}",
            'size'     => $file['size'],
            'modified' => $file['modified'],
            'category' => $category,
            'reason'   => $reason,
        ];
    }

    /**
     * Junta todo o texto do banco (colunas de texto de todas as tabelas) num
     * único "palheiro" normalizado, para checar se um nome de arquivo é
     * referenciado em qualquer lugar — google_drive_file_id, metadata, anexos
     * de orçamento, logs, filas etc. Conservador de propósito: na dúvida, mantém.
     */
    protected function referencedHaystack(): string
    {
        $parts = [];

        // Só o banco atual (sem schema, o Laravel 12 lista todos os bancos da conta).
        $schema = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) ? DB::connection()->getDatabaseName() : null;

        foreach (array_unique(Schema::getTableListing($schema, false)) as $table) {
            if (in_array($table, self::IGNORED_TABLES, true)) {
                continue;
            }

            $columns = collect(Schema::getColumns($table))
                ->filter(fn ($c) => preg_match('/char|text|json|blob/i', $c['type_name'] ?? $c['type'] ?? ''))
                ->pluck('name')
                ->all();

            if (empty($columns)) {
                continue;
            }

            foreach (DB::table($table)->select($columns)->cursor() as $row) {
                foreach ((array) $row as $value) {
                    if (is_string($value) && $value !== '') {
                        $parts[] = self::normalize($value);
                    }
                }
            }
        }

        return implode('|', $parts);
    }

    protected function isReferenced(string $fileName, string $haystack): bool
    {
        $needle = self::normalize($fileName);

        return $needle === '' || str_contains($haystack, $needle);
    }

    /**
     * Reduz a letras/dígitos minúsculos, para que escapes de JSON (\/, ç),
     * URL-encoding e caracteres trocados pelo Drive não escondam uma referência.
     */
    protected static function normalize(string $value): string
    {
        if (str_contains($value, '\\u')) {
            $value = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', fn ($m) => mb_chr(hexdec($m[1]), 'UTF-8'), $value);
        }
        if (str_contains($value, '%')) {
            $value = rawurldecode($value);
        }

        $value = \Illuminate\Support\Str::ascii($value);

        return preg_replace('/[^a-z0-9]/', '', strtolower($value));
    }

    protected function forgetDriveCache(): void
    {
        try {
            DB::table(config('cache.stores.database.table', 'cache'))
                ->where('key', 'like', '%gdrive:file_exists:%')
                ->orWhere('key', 'like', '%gdrive:list_contents:%')
                ->orWhere('key', 'like', '%gdrive:dir_exists:%')
                ->delete();
        } catch (\Throwable $e) {
            // cache em outro driver: expira sozinho em 10 min
        }
    }
}
