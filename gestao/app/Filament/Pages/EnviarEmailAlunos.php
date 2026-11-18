<?php

namespace App\Filament\Pages;

use App\Mail\BulkEmailAlunos;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class EnviarEmailAlunos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-envelope';
    protected static string  $view            = 'filament.pages.enviar-email-alunos';
    protected static ?string $navigationLabel = 'Enviar E-mail';
    protected static ?string $title           = 'Enviar E-mail aos Alunos';
    protected static ?string $navigationGroup = 'Comunicações';
    protected static ?int    $navigationSort  = 1;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function mount(): void
    {
        $this->form->fill([
            'destinatarios'  => 'todos',
            'copia_para_mim' => true,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Destinatários')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Radio::make('destinatarios')
                            ->label('Enviar para')
                            ->options([
                                'todos'               => 'Todos os alunos ativos',
                                'ativos_e_inativos'   => 'Todos os alunos (ativos e inativos) - ex: Marketing',
                                'orientandos'         => 'Meus orientandos de TCC/IC em andamento',
                                'orientandos_parados' => 'Orientandos sem progresso no prazo',
                                'especificos'         => 'Selecionar alunos específicos',
                            ])
                            ->default('todos')
                            ->live()
                            ->required(),

                        Select::make('alunos_ids')
                            ->label('Alunos destinatários')
                            ->multiple()
                            ->searchable()
                            ->options(fn () => Student::with('user')
                                ->whereIn('status', ['active', 'awaiting_advisor_approval', 'inactive'])
                                ->whereHas('user')
                                ->get()
                                ->mapWithKeys(fn ($s) => [
                                    $s->id => ($s->user->name ?? '—') . '  ·  ' . ($s->user->email ?? '—') . ($s->status === 'inactive' ? ' (Inativo)' : ''),
                                ])
                            )
                            ->placeholder('Digite para pesquisar por nome ou e-mail…')
                            ->visible(fn ($get) => $get('destinatarios') === 'especificos')
                            ->required(fn ($get) => $get('destinatarios') === 'especificos'),
                    ]),

                Section::make('Mensagem')
                    ->icon('heroicon-o-pencil-square')
                    ->schema([
                        TextInput::make('assunto')
                            ->label('Assunto')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Lembrete de prazo — Relatório de IC'),

                        RichEditor::make('corpo')
                            ->label('Conteúdo do e-mail')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'blockquote',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'undo',
                                'redo',
                            ])
                            ->placeholder('Escreva o conteúdo do e-mail aqui…')
                            ->columnSpanFull(),

                        Toggle::make('copia_para_mim')
                            ->label('Receber uma cópia deste e-mail')
                            ->helperText('Será enviada uma cópia para ' . (Auth::user()?->email ?? 'seu endereço'))
                            ->onColor('primary'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enviar')
                ->label('Enviar E-mail')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar envio')
                ->modalDescription('Tem certeza que deseja enviar este e-mail? Esta ação não pode ser desfeita.')
                ->modalSubmitActionLabel('Sim, enviar agora')
                ->modalCancelActionLabel('Cancelar')
                ->action('enviarEmail'),
        ];
    }

    public function enviarEmail(): void
    {
        $data = $this->form->getState();

        if (empty(trim(strip_tags($data['corpo'] ?? '')))) {
            Notification::make()->title('O corpo do e-mail não pode estar vazio.')->warning()->send();
            return;
        }

        $query = Student::with(['user', 'titles.submissions', 'titles.deadlines'])->whereHas('user');

        if ($data['destinatarios'] === 'todos') {
            $query->whereIn('status', ['active', 'awaiting_advisor_approval']);
        } elseif ($data['destinatarios'] === 'ativos_e_inativos') {
            $query->whereIn('status', ['active', 'awaiting_advisor_approval', 'inactive']);
        } elseif ($data['destinatarios'] === 'orientandos') {
            $query->whereHas('titles');
        } elseif ($data['destinatarios'] === 'orientandos_parados') {
            $query->whereHas('titles');
        } else {
            $ids = $data['alunos_ids'] ?? [];
            if (empty($ids)) {
                Notification::make()->title('Selecione pelo menos um aluno.')->warning()->send();
                return;
            }
            $query->whereIn('id', $ids);
        }

        $students = $query->get();

        if ($data['destinatarios'] === 'orientandos') {
            $students = $students->filter(
                fn ($student) => $student->titles->contains(fn ($title) => $title->getProgressInfo()['waiting_on'] !== 'concluido')
            )->values();
        } elseif ($data['destinatarios'] === 'orientandos_parados') {
            $students = $students->filter(
                fn ($student) => $student->titles->contains(fn ($title) => $title->getProgressInfo()['is_stalled'])
            )->values();
        }

        if ($students->isEmpty()) {
            Notification::make()->title('Nenhum destinatário encontrado.')->warning()->send();
            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($students as $student) {
            $email = $student->user?->email;
            if (!$email) continue;

            try {
                Mail::to($email)->queue(new BulkEmailAlunos(
                    subject:       $data['assunto'],
                    htmlBody:      $data['corpo'],
                    recipientName: $student->user->name,
                ));

                $sent++;
            } catch (\Throwable $exception) {
                $failed++;

                \Illuminate\Support\Facades\Log::error('Falha ao enfileirar e-mail em massa para aluno.', [
                    'student_id' => $student->id,
                    'email'      => $email,
                    'error'      => $exception->getMessage(),
                ]);

                \App\Models\EmailLog::create([
                    'recipient'     => $email,
                    'subject'       => $data['assunto'],
                    'body'          => $data['corpo'],
                    'status'        => 'failed',
                    'error_message' => $exception->getMessage(),
                    'sent_at'       => now(),
                ]);
            }
        }

        $copyFailed = false;

        if (!empty($data['copia_para_mim'])) {
            $adminEmail = Auth::user()->email;
            $adminName  = Auth::user()->name;

            try {
                Mail::to($adminEmail)->send(new BulkEmailAlunos(
                    subject:       '[CÓPIA] ' . $data['assunto'],
                    htmlBody:      $data['corpo'],
                    recipientName: $adminName,
                ));
            } catch (\Throwable $exception) {
                $copyFailed = true;
                \Illuminate\Support\Facades\Log::error('Falha ao enviar cópia do e-mail em massa: ' . $exception->getMessage());
            }
        }

        $notification = Notification::make()
            ->title($failed > 0
                ? "E-mail enfileirado para {$sent} aluno(s); falha ao enfileirar para {$failed}."
                : "E-mail enviado para {$sent} aluno(s) com sucesso!");

        $bodyParts = [
            'As mensagens foram enfileiradas e serão entregues em instantes.',
        ];

        if ($failed > 0) {
            $bodyParts[] = "{$failed} destinatário(s) não puderam ser enfileirados — veja o Mural de E-mails ou os logs para detalhes.";
        }

        $bodyParts[] = $copyFailed
            ? 'Atenção: não foi possível enviar sua cópia agora — verifique a configuração de e-mail do servidor.'
            : 'Uma cópia foi enviada para o seu e-mail agora.';

        $notification
            ->body(implode(' ', $bodyParts))
            ->status($failed > 0 ? 'warning' : 'success')
            ->send();

        $this->form->fill([
            'destinatarios'  => 'todos',
            'assunto'        => null,
            'corpo'          => null,
            'copia_para_mim' => true,
        ]);
    }
}
