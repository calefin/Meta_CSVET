<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DocumentSubmissionResource;
use App\Models\DocumentSubmission;
use App\Models\Student;
use App\Support\Semester;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class RelatorioEstagios extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string $view = 'filament.pages.relatorio-estagios';

    protected static ?string $navigationLabel = 'Avaliações de Estágio';

    protected static ?string $title = 'Avaliações de Estágio por Aluno e Semestre';

    protected static ?int $navigationSort = 3;

    public const ALL_SEMESTERS = 'todos';

    public const HOMOLOGATED_STATUS = 'Processo Concluído - Estágio Homologado com Sucesso';

    public string $semester = '';

    public string $search = '';

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? null : 'Orientação Acadêmica';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $this->semester = Semester::current();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lancarManual')
                ->label('Lançar avaliação manual')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->modalHeading('Lançar avaliação de estágio manualmente')
                ->modalDescription('Para fichas recebidas fora do sistema (papel, e-mail). O aluno não é notificado; a ficha entra neste relatório e, se homologada, na CH que ele vê em "Meu Estágio".')
                ->form([
                    Forms\Components\Select::make('student_id')
                        ->label('Aluno')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Student::query()
                            ->with('user')
                            ->where(fn ($q) => $q
                                ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                                ->orWhere('registration', 'like', "%{$search}%"))
                            ->limit(30)
                            ->get()
                            ->filter(fn ($student) => $student->user)
                            ->mapWithKeys(fn ($student) => [
                                $student->id => $student->user->name . ($student->registration ? " — RGM {$student->registration}" : ''),
                            ])
                            ->all())
                        ->getOptionLabelUsing(fn ($value) => Student::with('user')->find($value)?->user?->name),
                    Forms\Components\Select::make('internship_stage')
                        ->label('Disciplina de Estágio')
                        ->required()
                        ->options([
                            'Estágio Curricular Supervisionado I' => 'Estágio Curricular Supervisionado I',
                            'Estágio Curricular Supervisionado II' => 'Estágio Curricular Supervisionado II',
                        ]),
                    Forms\Components\DatePicker::make('evaluated_at')
                        ->label('Data da avaliação')
                        ->helperText('Define o semestre em que a ficha é contabilizada.')
                        ->required()
                        ->default(now())
                        ->maxDate(now()->addDay()),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('locations_count')
                            ->label('Locais')
                            ->numeric()->integer()->minValue(1)->maxValue(50)->required()->default(1),
                        Forms\Components\TextInput::make('total_hours')
                            ->label('CH (horas)')
                            ->numeric()->minValue(0)->maxValue(2000)->required(),
                        Forms\Components\TextInput::make('final_grade')
                            ->label('Nota')
                            ->numeric()->minValue(0)->maxValue(10)->required(),
                    ]),
                    Forms\Components\Toggle::make('homologated')
                        ->label('Já homologada')
                        ->default(true),
                    Forms\Components\Textarea::make('notes')
                        ->label('Observação (opcional)')
                        ->rows(2)
                        ->maxLength(1000),
                ])
                ->action(function (array $data) {
                    $student = Student::with('user')->findOrFail($data['student_id']);
                    $date = Carbon::parse($data['evaluated_at'])->setTimeFrom(now());
                    $status = $data['homologated'] ? self::HOMOLOGATED_STATUS : 'Avaliações de Estágio Registradas e Recebidas';

                    $submission = new DocumentSubmission([
                        'student_id' => $student->id,
                        'title' => 'Avaliação de Estágio (lançamento manual)',
                        'document_type' => 'Avaliações de Estágio',
                        'status' => $status,
                        'metadata' => [
                            'internship_stage' => $data['internship_stage'],
                            'locations_count' => (int) $data['locations_count'],
                            'total_hours' => (float) $data['total_hours'],
                            'final_grade' => (float) $data['final_grade'],
                            'manual_entry' => true,
                            'manual_entry_notes' => $data['notes'] ?? null,
                            'history' => [[
                                'status' => $status,
                                'old_status' => null,
                                'timestamp' => now()->format('d/m/Y - H:i:s'),
                                'actor' => auth()->user()->name . ' (lançamento manual)',
                                'comments' => $data['notes'] ?? null,
                            ]],
                        ],
                    ]);
                    $submission->process_id = DocumentSubmission::generateUniqueProcessId('Avaliações de Estágio', $date->format('Y'));
                    $submission->created_at = $date;
                    $submission->updated_at = $date;

                    // Sem eventos: lançamento administrativo não dispara e-mail/Telegram ao aluno.
                    DocumentSubmission::withoutEvents(fn () => $submission->save());

                    $this->semester = Semester::forDate($date);

                    Notification::make()
                        ->title('Avaliação lançada')
                        ->body("{$student->user->name} — {$data['total_hours']}h, nota {$data['final_grade']} (semestre {$this->semester}).")
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Todas as fichas de avaliação de estágio, homologadas ou não (só as
     * canceladas ficam de fora): o professor acompanha a média e a carga
     * horária "até o momento" e lança as notas no fim do semestre.
     *
     * O semestre de cada ficha é o da data de envio (created_at) — estável,
     * ao contrário do updated_at, que muda a cada edição/homologação.
     */
    protected function allSubmissions()
    {
        return DocumentSubmission::with('student.user')
            ->where('document_type', 'Avaliações de Estágio')
            ->where('status', '!=', 'Cancelado')
            ->get()
            ->filter(fn ($submission) => $submission->student && $submission->student->user);
    }

    public function getSemesterOptions(): array
    {
        $semesters = $this->allSubmissions()
            ->map(fn ($submission) => Semester::forDate($submission->created_at))
            ->push(Semester::current())
            ->unique()
            ->sortDesc()
            ->values();

        return [self::ALL_SEMESTERS => 'Todos os semestres'] + $semesters->combine($semesters)->all();
    }

    /**
     * @return array<string, array> semestre => linhas por aluno
     */
    public function getSections(): array
    {
        $search = mb_strtolower(trim($this->search));

        $submissions = $this->allSubmissions()
            ->map(function ($submission) {
                $submission->semester_label = Semester::forDate($submission->created_at);

                return $submission;
            })
            ->when($this->semester !== self::ALL_SEMESTERS, fn ($c) => $c->where('semester_label', $this->semester))
            ->when($search !== '', fn ($c) => $c->filter(function ($submission) use ($search) {
                return str_contains(mb_strtolower($submission->student->user->name), $search)
                    || str_contains(mb_strtolower((string) $submission->student->registration), $search);
            }));

        return $submissions
            ->groupBy('semester_label')
            ->sortKeysDesc()
            ->map(fn ($group) => $this->buildRows($group))
            ->all();
    }

    protected function buildRows($submissions): array
    {
        $filled = fn ($value) => $value !== null && $value !== '';

        return $submissions
            ->groupBy('student_id')
            ->map(function ($group) use ($filled) {
                $student = $group->first()->student;

                $grades = $group->pluck('metadata.final_grade')->filter($filled)->map(fn ($v) => (float) $v);
                $hours = $group->pluck('metadata.total_hours')->filter($filled)->map(fn ($v) => (float) $v);
                $latest = $group->sortByDesc('created_at')->first();

                return [
                    'student_name' => $student->user->name,
                    'registration' => $student->registration,
                    'campus' => $student->campus,
                    'period' => $student->period,
                    'internship_stage' => $latest->metadata['internship_stage'] ?? '—',
                    'submissions_count' => $group->count(),
                    'homologated_count' => $group->where('status', self::HOMOLOGATED_STATUS)->count(),
                    'graded_count' => $grades->count(),
                    'average_grade' => $grades->isNotEmpty() ? round($grades->avg(), 2) : null,
                    'total_hours' => $hours->sum(),
                    'records' => $group->sortBy('created_at')->map(fn ($submission) => [
                        'process_id' => $submission->process_id,
                        'manual' => !empty($submission->metadata['manual_entry']),
                        'status' => $submission->status,
                        'homologated' => $submission->status === self::HOMOLOGATED_STATUS,
                        'internship_stage' => $submission->metadata['internship_stage'] ?? '—',
                        'locations_count' => $submission->metadata['locations_count'] ?? null,
                        'total_hours' => $filled($submission->metadata['total_hours'] ?? null) ? (float) $submission->metadata['total_hours'] : null,
                        'final_grade' => $filled($submission->metadata['final_grade'] ?? null) ? (float) $submission->metadata['final_grade'] : null,
                        'sent_at' => $submission->created_at?->format('d/m/Y'),
                        'edit_url' => DocumentSubmissionResource::getUrl('edit', ['record' => $submission]),
                    ])->values()->all(),
                ];
            })
            ->sortBy('student_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
