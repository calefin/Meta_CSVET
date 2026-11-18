<?php

namespace App\Filament\Pages;

use App\Filament\Resources\NotificationLogResource;
use App\Filament\Resources\StudentResource;
use App\Models\AcademicTitle;
use App\Models\DocumentSubmission;
use App\Models\Student;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CadastrarProjetoIC extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static string $view = 'filament.pages.cadastrar-projeto-ic';

    protected static ?string $slug = 'cadastrar-projeto-ic';

    protected static ?string $navigationLabel = 'Cadastrar Projeto de IC';

    protected static ?string $title = 'Cadastrar Novo Projeto de Iniciação Científica';

    protected static ?string $navigationGroup = 'Orientação Acadêmica';

    protected static ?int $navigationSort = 41;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-30',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Aluno')
                    ->icon('heroicon-o-user')
                    ->description('Selecione um aluno já cadastrado. Se ele ainda não existir no sistema, cadastre-o primeiro em "Alunos".')
                    ->schema([
                        Select::make('student_id')
                            ->label('Aluno / Orientando')
                            ->options(fn () => Student::with('user')->get()->mapWithKeys(fn (Student $s) => [$s->id => ($s->user?->name ?? 'Sem nome') . ' — ' . ($s->institution ?: 'Instituição não informada')]))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText(new \Illuminate\Support\HtmlString('Aluno não encontrado? <a href="' . StudentResource::getUrl('create') . '" class="underline text-primary-500" target="_blank">Cadastre-o primeiro aqui</a>, depois volte a esta página.'))
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                $student = $state ? Student::find($state) : null;
                                $set('cpf', $student?->cpf);
                                $set('lattes_url', $student?->lattes_url);
                                $set('title', $student?->research_topic);
                            }),
                        TextInput::make('cpf')
                            ->label('CPF do Aluno')
                            ->placeholder('000.000.000-00')
                            ->maxLength(14),
                        TextInput::make('lattes_url')
                            ->label('Currículo Lattes (URL)')
                            ->url()
                            ->placeholder('http://lattes.cnpq.br/...')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Projeto de Iniciação Científica')
                    ->icon('heroicon-o-beaker')
                    ->description('Título, bolsa e vigência do projeto — a contagem de tempo e a cobrança mensal começam a partir da data de início.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Título do Projeto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('scholarship_type')
                            ->label('Tipo de Bolsa')
                            ->options([
                                'FAPESP' => 'FAPESP',
                                'CNPq' => 'CNPq',
                                'PIBIC' => 'PIBIC',
                                'Voluntária' => 'Voluntária',
                                'Outra' => 'Outra',
                            ])
                            ->required(),
                        DatePicker::make('start_date')
                            ->label('Vigência - Início')
                            ->native(false)
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('Vigência - Término')
                            ->native(false)
                            ->required(),
                    ])->columns(2),

                Section::make('Documentos')
                    ->icon('heroicon-o-document-arrow-up')
                    ->description('Arquivos PDF do comprovante de matrícula e do projeto de pesquisa.')
                    ->schema([
                        FileUpload::make('enrollment_proof')
                            ->label('Comprovante de Matrícula (PDF)')
                            ->disk(in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public')
                            ->directory('submissions')
                            ->acceptedFileTypes(['application/pdf'])
                            ->required()
                            ->maxSize(20480),
                        FileUpload::make('project_pdf')
                            ->label('PDF do Projeto de Pesquisa')
                            ->disk(in_array(config('filesystems.default'), ['google', 'onedrive']) ? config('filesystems.default') : 'public')
                            ->directory('submissions')
                            ->acceptedFileTypes(['application/pdf'])
                            ->required()
                            ->maxSize(20480),
                    ])->columns(2),

                Section::make('Compromissos da Orientação de IC')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Checkbox::make('check_1')
                            ->label('Entrega obrigatória de relatórios/evolução mensal sob pena de encerramento imediato.')
                            ->accepted()
                            ->required(),
                        Checkbox::make('check_2')
                            ->label('Compromisso de entrega de resultados em forma de artigo, relatório final ou resumo (Padrão FAPESP), sob pena de bloqueio de certificados.')
                            ->accepted()
                            ->required(),
                        Checkbox::make('check_3')
                            ->label('Propriedade e exclusividade dos dados brutos pertencentes ao laboratório do Prof. Atilio.')
                            ->accepted()
                            ->required(),
                        Checkbox::make('check_4')
                            ->label('Cumprimento estrito das Boas Práticas Científicas contra plágio e falsificação de dados.')
                            ->accepted()
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $data = $this->form->getState();

        $student = Student::find($data['student_id']);
        if (!$student) {
            Notification::make()->title('Aluno não encontrado.')->danger()->send();
            return;
        }

        $studentDirty = false;
        if (!empty($data['cpf']) && $data['cpf'] !== $student->cpf) {
            $student->cpf = $data['cpf'];
            $studentDirty = true;
        }
        if (!empty($data['lattes_url']) && $data['lattes_url'] !== $student->lattes_url) {
            $student->lattes_url = $data['lattes_url'];
            $studentDirty = true;
        }
        if ($studentDirty) {
            $student->save();
        }

        $academicTitle = AcademicTitle::create([
            'student_id' => $student->id,
            'type' => 'IC',
            'title' => $data['title'],
        ]);

        $enrollmentProof = is_array($data['enrollment_proof'] ?? null) ? reset($data['enrollment_proof']) : ($data['enrollment_proof'] ?? null);
        $projectPdf = is_array($data['project_pdf'] ?? null) ? reset($data['project_pdf']) : ($data['project_pdf'] ?? null);

        $submission = new DocumentSubmission();
        $submission->student_id = $student->id;
        $submission->academic_title_id = $academicTitle->id;
        $submission->title = $data['title'];
        $submission->document_type = 'Termo de Aceite de IC';
        $submission->status = 'Processo Concluído - Orientação de IC Homologada e Ativa';
        $submission->google_drive_file_id = $projectPdf;
        $submission->metadata = [
            'ic_project_title' => $data['title'],
            'ic_scholarship_type' => $data['scholarship_type'],
            'ic_start_date' => $data['start_date'],
            'ic_end_date' => $data['end_date'],
            'ic_enrollment_proof_path' => $enrollmentProof,
            'ic_check_1' => true,
            'ic_check_2' => true,
            'ic_check_3' => true,
            'ic_check_4' => true,
        ];
        $submission->save();

        \App\Models\Deadline::create([
            'student_id' => $student->id,
            'academic_title_id' => $academicTitle->id,
            'type' => 'cobranca',
            'title' => 'Entrega de Evolução Mensal do Projeto de IC',
            'due_date' => \Carbon\Carbon::parse($data['start_date'])->addMonth(),
            'recurrence_days' => 30,
            'status' => 'pending',
        ]);

        Notification::make()
            ->title('Projeto de IC cadastrado com sucesso!')
            ->body('A contagem de vigência começou e a cobrança mensal de evolução foi agendada automaticamente.')
            ->success()
            ->send();

        $this->form->fill([
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-30',
        ]);

        $this->redirect(NotificationLogResource::getUrl('index'));
    }
}
