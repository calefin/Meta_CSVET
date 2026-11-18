<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_command_creates_a_compressed_dump_and_logs_success(): void
    {
        $this->artisan('backup:run')->assertExitCode(0);

        $log = BackupLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertStringEndsWith('.sql.gz', $log->filename);
        $this->assertGreaterThan(0, $log->size_bytes);

        $this->assertTrue(Storage::disk('local')->exists('backups/'.$log->filename));

        Storage::disk('local')->delete('backups/'.$log->filename);
    }
}
