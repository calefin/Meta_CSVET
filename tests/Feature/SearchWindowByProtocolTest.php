<?php

namespace Tests\Feature;

use App\Models\SystematicReview;
use App\Support\ProgramJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * O prazo da busca segue o que o protocolo registrou: 14 dias depois do registro na leva 1;
 * "depois do registro e antes da triagem" (sem prazo) nas levas 2 e 3.
 */
class SearchWindowByProtocolTest extends TestCase
{
    use RefreshDatabase;

    protected function review(string $code, string $start): SystematicReview
    {
        return SystematicReview::create([
            'title' => "Teste {$code}", 'protocol_code' => $code,
            'pipeline' => ['protocol_id' => $code, 'osf_fields' => ['376-14' => $start]],
            'registration_id' => '10.17605/OSF.IO/ABCDE', 'registered_at' => '2026-11-13',
        ]);
    }

    public function test_wave1_protocol_keeps_the_14_day_deadline(): void
    {
        $r = $this->review('P01', 'November 2026 (formal database searches to be run within two weeks after registration)');

        $this->assertTrue(ProgramJourney::hasRegisteredSearchDeadline($r));
        $this->assertSame('late', ProgramJourney::searchWindow($r, Carbon::parse('2026-11-28'))['level']);
        $this->assertSame('Busca até 27/11 · faltam 14 dias', ProgramJourney::searchWindow($r, Carbon::parse('2026-11-13'))['text']);
    }

    public function test_wave2_and_3_protocols_have_no_deadline_and_no_alert(): void
    {
        $r = $this->review('P21', 'After registration, in the second wave of the research programme. Reviewers are allocated progressively; formal database searches are run after registration and before screening starts, the search date is reported.');

        $this->assertFalse(ProgramJourney::hasRegisteredSearchDeadline($r));
        $late = ProgramJourney::searchWindow($r, Carbon::parse('2027-03-01'));
        $this->assertSame('ok', $late['level']);
        $this->assertNull($late['until']);
        $this->assertSame('Busca antes de abrir a triagem (o protocolo não fixa prazo)', $late['text']);

        Carbon::setTestNow('2027-03-01 10:00');
        $this->artisan('app:alertar-prazo-busca', ['--dry-run' => true])->expectsOutput('Nenhuma busca perto do prazo.')->assertSuccessful();
        Carbon::setTestNow();
    }
}
