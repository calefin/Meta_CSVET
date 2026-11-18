<?php

namespace Tests\Feature;

use App\Models\ResearchCall;
use App\Models\SystematicReview;
use App\Models\SystematicReviewScreener;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Nomes da seleção e dos triadores no mesmo padrão do cadastro, sem tocar no termo assinado. */
class NormalizePersonNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizer_handles_caps_prepositions_and_roman_suffix(): void
    {
        $this->assertSame('Julia Zagolin Costa', User::normalizeName('JULIA ZAGOLIN COSTA'));
        $this->assertSame('Samara Angelina de Freitas Carvalho Pinto', User::normalizeName('Samara Angelina de Freitas Carvalho pinto'));
        $this->assertSame('Laíse Mascarenhas Quinto Azadinho', User::normalizeName('laíse mascarenhas  QUINTO azadinho'));
        $this->assertSame('José da Silva Neto II', User::normalizeName('JOSÉ DA SILVA NETO II'));
    }

    public function test_new_records_are_saved_normalized(): void
    {
        $review = SystematicReview::create(['title' => 'X', 'review_type' => 'meta_analysis', 'framework' => 'PICO']);
        $screener = SystematicReviewScreener::create(['systematic_review_id' => $review->id, 'name' => 'JULIA ZAGOLIN COSTA', 'email' => 'j@x.com']);
        $this->assertSame('Julia Zagolin Costa', $screener->fresh()->name);
    }

    public function test_command_lists_by_default_and_applies_without_touching_signed_terms(): void
    {
        $review = SystematicReview::create(['title' => 'X', 'review_type' => 'meta_analysis', 'framework' => 'PICO']);
        $screener = SystematicReviewScreener::create(['systematic_review_id' => $review->id, 'name' => 'x', 'email' => 'j@x.com',
            'terms_text' => 'Participante: JULIA ZAGOLIN COSTA', 'terms_signature' => 'JULIA ZAGOLIN COSTA', 'terms_sha256' => hash('sha256', 'Participante: JULIA ZAGOLIN COSTA')]);
        // Registro antigo, gravado antes da padronização.
        DB::table('systematic_review_screeners')->where('id', $screener->id)->update(['name' => 'JULIA ZAGOLIN COSTA']);

        $this->artisan('app:normalizar-nomes')->expectsOutputToContain('1 nome(s) mudariam')->assertSuccessful();
        $this->assertSame('JULIA ZAGOLIN COSTA', DB::table('systematic_review_screeners')->value('name'));

        $this->artisan('app:normalizar-nomes --aplicar')->assertSuccessful();
        $row = DB::table('systematic_review_screeners')->first();
        $this->assertSame('Julia Zagolin Costa', $row->name);
        $this->assertSame('JULIA ZAGOLIN COSTA', $row->terms_signature);
        $this->assertSame(hash('sha256', $row->terms_text), $row->terms_sha256);

        $this->artisan('app:normalizar-nomes')->expectsOutputToContain('0 nome(s) mudariam')->assertSuccessful();
    }
}
