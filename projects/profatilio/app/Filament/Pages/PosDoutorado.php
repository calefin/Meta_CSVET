<?php

namespace App\Filament\Pages;

use App\Filament\Resources\SystematicReviewResource;
use App\Models\PostdocProject;
use App\Services\SystematicReviewService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Espaço privado do pós-doutorado do professor (UNIP): dados do estágio, formalização, cronograma,
 * entregas, documentos e o andamento da revisão do programa que é o objeto do pós-doc (P19).
 */
class PosDoutorado extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Programa de Metanálises';

    protected static ?string $navigationLabel = 'Pós-doutorado';

    protected static ?string $title = 'Pós-doutorado (UNIP)';

    protected static ?string $slug = 'pos-doutorado';

    protected static string $view = 'filament.pages.pos-doutorado';

    protected static ?int $navigationSort = -35;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $this->form->fill(PostdocProject::current()->toArray());
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Estágio')->columns(2)->schema([
                Forms\Components\Textarea::make('title')->label('Título do projeto')->rows(2)->required()->columnSpanFull(),
                Forms\Components\TextInput::make('institution')->label('Instituição'),
                Forms\Components\TextInput::make('program')->label('Programa'),
                Forms\Components\TextInput::make('research_line')->label('Linha de pesquisa'),
                Forms\Components\TextInput::make('modality')->label('Modalidade'),
                Forms\Components\TextInput::make('supervisor')->label('Supervisor'),
                Forms\Components\TextInput::make('supervisor_email')->label('E-mail do supervisor')->email(),
                Forms\Components\DatePicker::make('starts_at')->label('Início'),
                Forms\Components\DatePicker::make('ends_at')->label('Término'),
                Forms\Components\Select::make('status')->label('Situação')->options(PostdocProject::STATUS)->native(false)->required(),
                Forms\Components\TextInput::make('protocol_code')->label('Revisão do programa ligada ao pós-doc')->helperText('Ex.: P19'),
                Forms\Components\TextInput::make('registration_doi')->label('DOI do registro no OSF'),
            ]),
            Forms\Components\Section::make('Formalização')->description('Marque a data quando cada passo estiver feito.')->schema([
                Forms\Components\Repeater::make('checklist')->label('')->columns(6)->reorderable()->defaultItems(0)
                    ->schema([
                        Forms\Components\TextInput::make('item')->label('Passo')->required()->columnSpan(3),
                        Forms\Components\DatePicker::make('done_at')->label('Feito em')->columnSpan(1),
                        Forms\Components\TextInput::make('note')->label('Observação')->columnSpan(2),
                    ])->addActionLabel('Adicionar passo'),
            ]),
            Forms\Components\Section::make('Cronograma (12 meses)')->collapsible()->schema([
                Forms\Components\Repeater::make('timeline')->label('')->columns(6)->reorderable()->defaultItems(0)
                    ->schema([
                        Forms\Components\TextInput::make('activity')->label('Atividade')->required()->columnSpan(3),
                        Forms\Components\TextInput::make('months')->label('Meses')->columnSpan(1),
                        Forms\Components\Select::make('status')->label('Situação')->native(false)->columnSpan(2)
                            ->options(['pendente' => 'Pendente', 'andamento' => 'Em andamento', 'feito' => 'Feito']),
                    ])->addActionLabel('Adicionar atividade'),
            ]),
            Forms\Components\Section::make('Entregas (relatórios, workshop, artigo)')->collapsible()->schema([
                Forms\Components\Repeater::make('deliverables')->label('')->columns(6)->defaultItems(0)
                    ->schema([
                        Forms\Components\TextInput::make('title')->label('Entrega')->required()->columnSpan(2),
                        Forms\Components\DatePicker::make('due_at')->label('Prazo'),
                        Forms\Components\DatePicker::make('done_at')->label('Entregue em'),
                        Forms\Components\TextInput::make('link')->label('Link ou DOI')->columnSpan(2),
                    ])->addActionLabel('Adicionar entrega'),
            ]),
            Forms\Components\Section::make('Documentos')->description('Ficam no disco privado do site (não são públicos). Documentos pessoais (RG, CNH, certidões) só se o PPG pedir pelo site.')->schema([
                Forms\Components\FileUpload::make('documents')->label('')->multiple()->disk('local')->directory('postdoc')->visibility('private')
                    ->preserveFilenames()->downloadable()->openable()->reorderable()->maxSize(51200),
            ]),
            Forms\Components\Section::make('Anotações')->collapsible()->schema([
                Forms\Components\Textarea::make('notes')->label('')->rows(5),
            ]),
        ]);
    }

    public function save(): void
    {
        PostdocProject::current()->update($this->form->getState());
        Notification::make()->title('Pós-doutorado salvo')->success()->send();
    }

    /** Andamento da revisão ligada ao pós-doc, para o quadro do topo. */
    protected function getViewData(): array
    {
        $project = PostdocProject::current();
        $review = $project->review();
        $info = null;
        if ($review) {
            $service = app(SystematicReviewService::class);
            $info = [
                'code' => $review->protocol_code . ($review->selectionCode() ? ' · ' . $review->selectionCode() : ''),
                'status' => $review->statusLabel(),
                'records' => $review->records()->count(),
                'screeners' => $review->activeScreeners()->get()->map(fn ($s) => ['name' => $s->name, 'progress' => $service->progress($s)])->all(),
                'rounds' => collect($review->pilot_rounds ?? [])->map(fn ($r) => 'Rodada ' . $r['round'] . ': κ ' . SystematicReviewService::fmt($r['kappa'] ?? null)
                    . ' (binário ' . SystematicReviewService::fmt($r['kappa_binary'] ?? null) . ')')->all(),
                'url' => SystematicReviewResource::getUrl('view', ['record' => $review]),
            ];
        }
        $done = collect($project->checklist ?? [])->filter(fn ($c) => filled($c['done_at'] ?? null))->count();

        return ['review' => $info, 'project' => $project, 'checkDone' => $done, 'checkTotal' => count($project->checklist ?? [])];
    }
}
