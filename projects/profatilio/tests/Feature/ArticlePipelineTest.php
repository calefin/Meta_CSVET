<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleReviewResource\Pages\ViewArticleReview;
use App\Models\ArticleReview;
use App\Models\SystematicReview;
use App\Models\User;
use App\Services\ArticleReviewService;
use App\Support\ArticleTypes;
use App\Support\ManuscriptMetrics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ArticlePipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function manuscript(): array
    {
        return [
            'Meloxicam in rabbits: a randomized trial',
            'Abstract',
            str_repeat('word ', 120),
            'Keywords: rabbit; analgesia',
            'Introduction',
            str_repeat('text ', 300),
            'Figure 1. Pain scores over time.',
            'Table 1. Baseline data.',
            'Table 2. Outcomes.',
            str_repeat('more ', 200),
            'References',
            '1. Silva A, Souza B. Meloxicam in rabbits. J Exot Pet Med. 2020;3:1-5.',
            '2. Doe J. Pain in lagomorphs. Vet J. 2019;10:10-20. doi:10.1/x',
            '3. Roe K. Analgesia review. Animals. 2021;11:100.',
        ];
    }

    protected function makeReview(array $attrs = []): ArticleReview
    {
        $paragraphs = $this->manuscript();
        Storage::disk('local')->put('article_reviews/originals/a.docx', ArticleReviewFlowTest::docx($paragraphs));

        return ArticleReview::create(array_merge([
            'title' => 'Meloxicam em coelhos',
            'work_type' => 'article',
            'article_type' => 'original_rct',
            'original_file_path' => 'article_reviews/originals/a.docx',
            'original_filename' => 'a.docx',
            'default_review_days' => 7,
            'status' => ArticleReview::STATUS_REVIEWS_DONE,
        ], $attrs));
    }

    public function test_metrics_from_paragraphs(): void
    {
        $m = ManuscriptMetrics::fromParagraphs($this->manuscript());
        $this->assertSame(120, $m['abstract_words']);
        $this->assertSame(1 + 300 + 200, $m['text_words']); // "Introduction" + corpo, sem legendas
        $this->assertSame(3, $m['references']);
        $this->assertSame(1, $m['figures']);
        $this->assertSame(2, $m['tables']);

        $inline = ManuscriptMetrics::fromParagraphs(['Abstract: ' . str_repeat('a ', 50), 'Body ' . str_repeat('b ', 10)]);
        $this->assertSame(50, $inline['abstract_words']);
        $this->assertNull($inline['references']);
    }

    public function test_checklist_blocks_submission_until_resolved(): void
    {
        $review = $this->makeReview();
        $this->assertSame(ArticleTypes::COMMON_ITEMS['limits'], ArticleTypes::pending($review)[0]);
        $this->assertArrayHasKey('randomization', ArticleTypes::checklist('original_rct'));
        $this->assertArrayNotHasKey('randomization', ArticleTypes::checklist('case_report'));

        try {
            app(ArticleReviewService::class)->markSubmitted($review, ['journal' => 'NVAS', 'submitted_at' => now()]);
            $this->fail('Deveria bloquear com pendências.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pendente', $e->getMessage());
        }

        $review->update(['submission_checklist' => array_fill_keys(array_keys(ArticleTypes::checklist('original_rct')), 'done')]);
        app(ArticleReviewService::class)->markSubmitted($review->fresh(), ['journal' => 'NVAS', 'submitted_at' => now()]);
        $this->assertSame(ArticleReview::STATUS_SUBMITTED, $review->fresh()->status);

        // Processos antigos, sem tipo de artigo, continuam fechando normalmente.
        $old = $this->makeReview(['article_type' => null]);
        app(ArticleReviewService::class)->markSubmitted($old, ['journal' => 'X', 'submitted_at' => now()]);
        $this->assertSame(ArticleReview::STATUS_SUBMITTED, $old->fresh()->status);
    }

    public function test_systematic_review_link_proves_items_automatically(): void
    {
        $sr = SystematicReview::create(['title' => 'RS', 'registration_id' => '10.17605/OSF.IO/X', 'registration_platform' => 'OSF',
            'search_date' => now()->subMonths(2), 'search_counts' => ['sources' => ['PubMed' => 10, 'Scopus' => 5]],
            'checklist' => ['grey_lit' => '2026-10-01'], 'stage_closed_at' => ['title_abstract' => '2026-10-10 10:00:00']]);
        $review = $this->makeReview(['article_type' => 'systematic_review', 'systematic_review_id' => $sr->id]);
        $status = ArticleTypes::status($review);
        foreach (['sr_registration', 'sr_databases', 'sr_search_date', 'sr_flow'] as $key) {
            $this->assertSame('auto', $status[$key]['state'], $key);
        }
        $this->assertSame('pending', $status['sr_dual']['state']);

        $sr->update(['search_date' => now()->subMonths(14)]);
        $this->assertSame('pending', ArticleTypes::status($review->fresh())['sr_search_date']['state']);
    }

    public function test_view_page_shows_pipeline_and_saves_checklist(): void
    {
        $review = $this->makeReview();
        $this->get(\App\Filament\Resources\ArticleReviewResource::getUrl('view', ['record' => $review]))
            ->assertOk()->assertSee('Pipeline de submissão')->assertSee('CONSORT')->assertSee('≈ 501', false);

        $items = array_fill_keys(array_keys(ArticleTypes::checklist('original_rct')), 'done');
        $items['mapa'] = 'na';
        $items['forjado'] = 'done';
        Livewire::test(ViewArticleReview::class, ['record' => $review->getRouteKey()])
            ->callAction('checklistSubmissao', ['items' => $items])
            ->assertHasNoActionErrors();
        $saved = $review->fresh()->submission_checklist;
        $this->assertSame('na', $saved['mapa']);
        $this->assertArrayNotHasKey('forjado', $saved);
        $this->assertSame([], ArticleTypes::pending($review->fresh()));
    }
}
