<?php

namespace App\Jobs;

use App\Services\DriveCleanupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Apaga os arquivos em lotes pequenos e se re-enfileira com o restante: o
 * worker da HostGator (cron + queue:work) mata jobs longos, e centenas de
 * chamadas à API do Drive não cabem num único job/requisição.
 */
class DeleteObsoleteDriveFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const BATCH_SIZE = 40;

    public const PROGRESS_CACHE_KEY = 'drive_cleanup:progress';

    public int $tries = 1;

    /**
     * @param  array<int, array>  $candidates
     */
    public function __construct(public array $candidates)
    {
    }

    public function handle(DriveCleanupService $service): void
    {
        $batch = array_splice($this->candidates, 0, self::BATCH_SIZE);
        $stats = $service->delete($batch);

        $progress = Cache::get(self::PROGRESS_CACHE_KEY, ['deleted' => 0, 'bytes' => 0, 'skipped' => 0, 'failed' => 0]);
        foreach ($stats as $key => $value) {
            $progress[$key] = ($progress[$key] ?? 0) + $value;
        }
        $progress['remaining'] = count($this->candidates);
        $progress['finished'] = $this->candidates === [];
        Cache::put(self::PROGRESS_CACHE_KEY, $progress, now()->addDay());

        if ($this->candidates !== []) {
            self::dispatch($this->candidates);
        } else {
            Cache::forget(DriveCleanupService::SCAN_CACHE_KEY); // varredura antiga não vale mais
        }
    }
}
