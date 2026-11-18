<?php

namespace Tests\Feature;

use App\Filament\Pages\LimpezaDrive;
use App\Jobs\DeleteObsoleteDriveFiles;
use App\Models\User;
use App\Services\DriveCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class FakeDriveCleanupService extends DriveCleanupService
{
    public array $folders = [];
    public array $files = [];      // [folderId => [file, ...]]
    public array $trashed = [];    // [folderId => [file, ...]]
    public array $deletedIds = [];

    protected function appFolders(): array
    {
        return $this->folders;
    }

    protected function listFiles(string $folderId, bool $trashed): \Generator
    {
        yield from ($trashed ? $this->trashed : $this->files)[$folderId] ?? [];
    }

    public function quota(): array
    {
        return ['limit' => 100, 'usage' => 90, 'usage_in_drive' => 80, 'usage_in_trash' => 10];
    }

    public function delete(array $candidates): array
    {
        // Reaproveita a reconferência de órfãos do serviço real sem chamar a API.
        $haystack = $this->referencedHaystack();
        $stats = ['deleted' => 0, 'bytes' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($candidates as $c) {
            if ($c['category'] === 'orphan' && $this->isReferenced($c['name'], $haystack)) {
                $stats['skipped']++;
                continue;
            }
            $this->deletedIds[] = $c['id'];
            $stats['deleted']++;
        }

        return $stats;
    }
}

class DriveCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function file(string $id, string $name, int $daysAgo, int $size = 1000): array
    {
        $ts = now()->subDays($daysAgo)->getTimestamp();

        return ['id' => $id, 'name' => $name, 'size' => $size, 'timestamp' => $ts, 'modified' => date('d/m/Y', $ts)];
    }

    protected function reference(string $text): void
    {
        DB::table('email_logs')->insert([
            'recipient' => 'a@b.com', 'subject' => 's', 'body' => $text, 'status' => 'success', 'sent_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_classifica_lixeira_duplicatas_e_orfaos_sem_tocar_no_que_esta_em_uso(): void
    {
        $inUse = 'TCC-2026-0001 - Maria Souza - Concepção de TCC.pdf';
        // Referência gravada como JSON escapado, do jeito que fica em google_drive_file_id/metadata.
        $this->reference(json_encode(['submissions/' . $inUse]));

        $service = new FakeDriveCleanupService();
        $service->folders = [
            ['id' => 'f-sub', 'name' => 'submissions'],
            ['id' => 'f-cert', 'name' => 'certificates'],
        ];
        $service->files = [
            'f-sub' => [
                $this->file('atual', $inUse, 20),
                $this->file('antiga', $inUse, 40),          // mesma pasta, mesmo nome, mais velha
                $this->file('orfao', '01JABCXYZ.pdf', 30),
                $this->file('orfao-recente', '01JNOVO.pdf', 1), // novo demais: fica
            ],
            'f-cert' => [
                $this->file('cert', 'certificado-a1.pfx', 400), // pasta protegida: nunca órfão
            ],
        ];
        $service->trashed = [
            'f-sub'  => [$this->file('lixo1', $inUse, 60, 5000)],
            'f-cert' => [$this->file('lixo2', 'certificado-antigo.pfx', 500, 7000)],
        ];

        $scan = $service->scan(7);
        $byId = collect($scan['candidates'])->keyBy('id');

        $this->assertEqualsCanonicalizing(['lixo1', 'lixo2', 'antiga', 'orfao'], $byId->keys()->all());
        $this->assertSame('trash', $byId['lixo1']['category']);
        $this->assertSame('duplicate', $byId['antiga']['category']);
        $this->assertSame('orphan', $byId['orfao']['category']);
        $this->assertArrayNotHasKey('atual', $byId);
        $this->assertArrayNotHasKey('cert', $byId);
        $this->assertArrayNotHasKey('orfao-recente', $byId);
        $this->assertSame(12000, $scan['totals']['trash']['bytes']);
    }

    public function test_orfao_vinculado_depois_da_analise_nao_e_apagado(): void
    {
        $service = new FakeDriveCleanupService();
        $service->folders = [['id' => 'f-sub', 'name' => 'returns']];
        $service->files = ['f-sub' => [$this->file('orfao', 'TCC-2026-0002 - João - Assinado_Corrigido.pdf', 30)]];

        $scan = $service->scan(7);
        $this->assertSame('orphan', $scan['candidates'][0]['category']);

        // Vinculado entre a análise e a exclusão (e com URL-encoding, como em links).
        $this->reference('https://x/download?f=returns%2FTCC-2026-0002%20-%20Jo%C3%A3o%20-%20Assinado_Corrigido.pdf');

        $stats = $service->delete($scan['candidates']);
        $this->assertSame(0, $stats['deleted']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertSame([], $service->deletedIds);
    }

    public function test_pagina_de_limpeza_renderiza_so_para_admin_e_enfileira_exclusao(): void
    {
        Queue::fake();

        Cache::put(DriveCleanupService::SCAN_CACHE_KEY, [
            'scanned_at' => '22/09/2026 10:00', 'min_age_days' => 7,
            'candidates' => [
                ['id' => 'x1', 'name' => 'a.pdf', 'path' => 'submissions/a.pdf', 'size' => 2048, 'modified' => '01/08/2026', 'category' => 'trash', 'reason' => 'r'],
                ['id' => 'x2', 'name' => 'b.pdf', 'path' => 'submissions/b.pdf', 'size' => 4096, 'modified' => '01/08/2026', 'category' => 'orphan', 'reason' => 'r'],
            ],
            'totals' => ['trash' => ['count' => 1, 'bytes' => 2048], 'duplicate' => ['count' => 0, 'bytes' => 0], 'orphan' => ['count' => 1, 'bytes' => 4096]],
            'quota' => ['limit' => 15 * 1024 ** 3, 'usage' => 14 * 1024 ** 3, 'usage_in_drive' => 10 * 1024 ** 3, 'usage_in_trash' => 2 * 1024 ** 3],
        ], now()->addHour());

        $this->actingAs(User::factory()->create(['role' => 'student']));
        $this->get(LimpezaDrive::getUrl())->assertStatus(403);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(LimpezaDrive::getUrl())
            ->assertSuccessful()
            ->assertSee('submissions/b.pdf')
            ->assertSee('Apagar 1 arquivo(s)');

        Livewire::test(LimpezaDrive::class)
            ->callAction('apagar', arguments: ['category' => 'trash']);

        Queue::assertPushed(DeleteObsoleteDriveFiles::class, fn ($job) => array_column($job->candidates, 'id') === ['x1']);
        $remaining = Cache::get(DriveCleanupService::SCAN_CACHE_KEY);
        $this->assertSame(['x2'], array_column($remaining['candidates'], 'id'));
    }
}
