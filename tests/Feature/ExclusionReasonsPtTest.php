<?php

namespace Tests\Feature;

use App\Models\SystematicReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExclusionReasonsPtTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fills_portuguese_labels_without_overwriting_edits(): void
    {
        $r = SystematicReview::create(['title' => 'Kenney-Doig', 'protocol_code' => 'P18', 'pilot_size' => 0,
            'exclusion_reasons' => ['E1 — Studies using only modified classifications', 'E3 — Narrative reviews, editorials'],
            'exclusion_reasons_pt' => ['E3' => 'Editado no admin']]);

        $this->artisan('metanalises:motivos-pt')->assertSuccessful();
        $r->refresh();
        $this->assertStringContainsString('Kenney-Doig', $r->exclusion_reasons_pt['E1']);
        $this->assertSame('Editado no admin', $r->exclusion_reasons_pt['E3']);
        $this->assertArrayNotHasKey('E10', $r->exclusion_reasons_pt, 'Código que não existe no protocolo não entra.');
        $this->assertStringContainsString('Kenney-Doig', (string) $r->exclusionReasonPt('E1 — Studies using only modified classifications'));

        $this->artisan('metanalises:motivos-pt --force')->assertSuccessful();
        $this->assertSame('Revisão, editorial, carta ou capítulo, sem dados próprios', $r->fresh()->exclusion_reasons_pt['E3']);
    }
}
