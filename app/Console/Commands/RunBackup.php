<?php

namespace App\Console\Commands;

use App\Models\BackupLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Dump the database, compress it, copy it to external storage via rclone, and prune old backups (spec sec. 13)';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $date = now()->format('Y-m-d_His');
        $sqlFilename = "backup-{$date}.sql";
        $gzFilename = "{$sqlFilename}.gz";
        $sqlPath = storage_path("app/private/backups/{$sqlFilename}");
        $gzPath = "{$sqlPath}.gz";

        $disk->makeDirectory('backups');

        try {
            $this->dumpDatabase($sqlPath);
            $this->compress($sqlPath, $gzPath);
            @unlink($sqlPath);

            $sizeBytes = filesize($gzPath);

            $this->syncToRemote($gzPath, $gzFilename);
            $this->applyRetention();

            BackupLog::create([
                'status' => 'success',
                'filename' => $gzFilename,
                'size_bytes' => $sizeBytes,
            ]);

            $this->info("Backup criado: {$gzFilename} (".number_format($sizeBytes / 1024 / 1024, 2)." MB)");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            BackupLog::create([
                'status' => 'failure',
                'filename' => $gzFilename,
                'message' => $e->getMessage(),
            ]);

            $this->error('Backup falhou: '.$e->getMessage());
            $this->notifyFailure($e->getMessage());

            return self::FAILURE;
        }
    }

    private function dumpDatabase(string $sqlPath): void
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            $database = config('database.connections.sqlite.database');
            if ($database === ':memory:') {
                file_put_contents($sqlPath, '-- In-memory SQLite database backup');
                return;
            }
            if (!copy($database, $sqlPath)) {
                throw new \RuntimeException("Falha ao copiar base de dados SQLite de {$database} para {$sqlPath}");
            }
            return;
        }

        $config = config('database.connections.'.$driver);

        $process = new Process([
            'mysqldump',
            '-h', $config['host'] ?? '127.0.0.1',
            '-P', (string) ($config['port'] ?? 3306),
            '-u', $config['username'],
            '--password='.$config['password'],
            '--single-transaction',
            '--no-tablespaces',
            $config['database'],
            '--result-file='.$sqlPath,
        ]);

        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysqldump falhou: '.$process->getErrorOutput());
        }
    }

    private function compress(string $sqlPath, string $gzPath): void
    {
        $sql = fopen($sqlPath, 'rb');
        $gz = gzopen($gzPath, 'wb9');

        while (! feof($sql)) {
            gzwrite($gz, fread($sql, 1024 * 512));
        }

        fclose($sql);
        gzclose($gz);
    }

    /**
     * Requires the `rclone` binary configured with a remote named in
     * RCLONE_REMOTE (e.g. "gdrive:ijoifa-backups"). If not configured,
     * the backup still succeeds locally but only the local copy is kept.
     */
    private function syncToRemote(string $gzPath, string $gzFilename): void
    {
        $remote = config('services.rclone.remote');

        if (! $remote) {
            return;
        }

        $process = new Process(['rclone', 'copy', $gzPath, $remote]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('rclone falhou: '.$process->getErrorOutput());
        }
    }

    /**
     * Keeps the last 30 daily backups locally; anything older that isn't
     * the first backup of its month (kept for 12 months) is deleted.
     */
    private function applyRetention(): void
    {
        $disk = Storage::disk('local');
        $files = collect($disk->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.gz'))
            ->sortByDesc(fn ($f) => $disk->lastModified($f))
            ->values();

        $daily = $files->take(30);
        $monthlyKeepers = $files->groupBy(fn ($f) => date('Y-m', $disk->lastModified($f)))
            ->map(fn ($group) => $group->sortByDesc(fn ($f) => $disk->lastModified($f))->first())
            ->take(12);

        $keep = $daily->merge($monthlyKeepers)->unique();

        $files->diff($keep)->each(fn ($f) => $disk->delete($f));
    }

    private function notifyFailure(string $message): void
    {
        $adminEmail = config('services.admin_email');

        if (! $adminEmail) {
            return;
        }

        Mail::raw(
            "Falha no backup automatico de ".config('app.name')." em ".now()->format('d/m/Y H:i').":\n\n{$message}",
            fn ($mail) => $mail->to($adminEmail)->subject('Falha no backup - '.config('app.name'))
        );
    }
}
