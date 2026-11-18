<?php

namespace Tests\Feature;

use App\Filament\Pages\CentralMetanalises;
use App\Models\SrMessage;
use App\Models\SystematicReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** O aviso "aguardando mensagem" conta só os projetos que aparecem na Central (nada de treino ou arquivado). */
class CentralAwaitingRepliesTest extends TestCase
{
    use RefreshDatabase;

    public function test_awaiting_counts_only_program_projects(): void
    {
        $real = SystematicReview::create(['title' => 'Real', 'protocol_code' => 'P01']);
        $training = SystematicReview::create(['title' => 'Treino', 'protocol_code' => 'T01', 'is_training' => true]);
        $archived = SystematicReview::create(['title' => 'Antigo', 'protocol_code' => 'P99', 'status' => SystematicReview::STATUS_ARCHIVED]);

        foreach ([$real, $training, $archived] as $r) {
            SrMessage::create(['systematic_review_id' => $r->id, 'author_name' => 'Aluna', 'author_role' => SrMessage::ROLE_STUDENT, 'body' => 'Li']);
        }

        $this->assertSame([$real->id], CentralMetanalises::awaitingReplyIds());

        SrMessage::where('systematic_review_id', $real->id)->update(['seen_at' => now()]);
        $this->assertSame([], CentralMetanalises::awaitingReplyIds());
    }
}
