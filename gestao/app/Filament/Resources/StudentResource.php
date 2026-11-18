<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Filament\Resources\StudentResource\RelationManagers;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    
    public static function getNavigationLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Meu Perfil' : 'Alunos';
    }
    
    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? 'Minha Conta' : 'Controle de Alunos';
    }
    
    public static function getModelLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Meu Perfil' : 'Aluno';
    }
    
    public static function getPluralModelLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Meu Perfil' : 'Alunos';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações Acadêmicas e de Contato')
                    ->schema([
                        Forms\Components\FileUpload::make('user.profile_photo_path')
                            ->label('Foto de Perfil')
                            ->image()
                            ->avatar()
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth('150')
                            ->imageResizeTargetHeight('150')
                            ->imageResizeMode('cover')
                            ->disk('public')
                            ->directory('avatars')
                            ->maxSize(1024) // 1MB Max
                            ->helperText('A foto será otimizada automaticamente para 150x150px.')
                            ->columnSpanFull()
                            ->alignCenter(),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated()
                            ->default(fn () => auth()->id())
                            ->label('Usuário'),
                        Forms\Components\TextInput::make('registration')
                            ->required()
                            ->maxLength(255)
                            ->label('Matrícula / Registro Acadêmico')
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated()
                            ->formatStateUsing(fn ($state) => auth()->user()->role === 'student' ? '***' : $state),
                        Forms\Components\TextInput::make('user_email')
                            ->label('E-mail de Cadastro')
                            ->default(fn ($record) => $record?->user?->email)
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state, $record) => auth()->user()->role === 'student' ? '***' : ($record?->user?->email ?? $state)),
                        Forms\Components\TextInput::make('institution')
                            ->required()
                            ->maxLength(255)
                            ->label('Instituição'),
                        Forms\Components\TextInput::make('campus')
                            ->required(fn ($record) => $record?->category === 'regular')
                            ->maxLength(255)
                            ->label('Campus'),
                        Forms\Components\Select::make('period')
                            ->options([
                                'Matutino' => 'Matutino',
                                'Vespertino' => 'Vespertino',
                                'Noturno' => 'Noturno',
                            ])
                            ->required(fn ($record) => $record?->category === 'regular')
                            ->nullable()
                            ->label('Período'),
                        Forms\Components\Select::make('academic_level')
                            ->options([
                                'Graduação' => 'Graduação',
                                'Especialização' => 'Especialização',
                                'Mestrado' => 'Mestrado',
                                'Doutorado' => 'Doutorado',
                            ])
                            ->required()
                            ->label('Nível Acadêmico'),
                        Forms\Components\TextInput::make('research_topic')
                            ->required()
                            ->maxLength(255)
                            ->label('Tema de Pesquisa'),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->placeholder('Ex: (11) 99999-9999')
                            ->label('Telefone / WhatsApp')
                            ->disabled(fn () => auth()->user()->role === 'student')
                            ->dehydrated()
                            ->formatStateUsing(fn ($state) => auth()->user()->role === 'student' ? '***' : $state),
                        Forms\Components\TextInput::make('cpf')
                            ->label('CPF')
                            ->placeholder('000.000.000-00')
                            ->maxLength(14),
                        Forms\Components\TextInput::make('lattes_url')
                            ->label('Currículo Lattes (URL)')
                            ->url()
                            ->placeholder('http://lattes.cnpq.br/...')
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Ativo',
                                'inactive' => 'Inativo',
                                'awaiting_email_validation' => 'Validação de E-mail Pendente',
                                'awaiting_advisor_approval' => 'Aprovação Pendente',
                            ])
                            ->required()
                            ->visible(fn () => auth()->user()->role === 'admin')
                            ->disabled(fn ($record) => $record === null)
                            ->label('Status da Conta'),
                        Forms\Components\DateTimePicker::make('activated_at')
                            ->label('Ativo Desde')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Ainda não ativado')
                            ->visible(fn ($record) => $record !== null && !empty($record->activated_at)),
                    ])->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('📅 Situação Semestral (TCC / Estágio / IC)')
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Forms\Components\Placeholder::make('semester_history')
                            ->label('Histórico de Respostas')
                            ->content(function ($record) {
                                $entries = $record->semesterDeclarations()->orderByDesc('semester')->get();

                                if ($entries->isEmpty()) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<p class="text-sm text-slate-400 italic">Nenhuma pesquisa semestral respondida ainda.</p>'
                                    );
                                }

                                $labels = \App\Models\StudentSemesterDeclaration::situationLabels();
                                $html = '<div class="space-y-2">';
                                foreach ($entries as $entry) {
                                    $situacoes = $entry->concluded
                                        ? '<span class="text-emerald-400 font-semibold">Concluiu o curso</span>'
                                        : (collect($entry->situations ?? [])->map(fn ($s) => $labels[$s] ?? $s)->join(', ') ?: '<span class="italic text-slate-400">Nenhuma situação marcada</span>');

                                    $html .= '<div class="flex items-center justify-between gap-3 p-2.5 rounded-lg bg-slate-900/60 border border-white/5 text-sm">';
                                    $html .= '<span class="font-mono text-slate-300">' . e($entry->semester) . '</span>';
                                    $html .= '<span class="text-slate-200">' . $situacoes . '</span>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            }),
                        Forms\Components\Toggle::make('course_concluded_toggle')
                            ->label('Concluiu o Curso')
                            ->helperText('Quando ativado, o aluno não pode mais abrir novos processos de TCC/estágio/IC, mas mantém acesso para consultar e baixar seus documentos já existentes.')
                            ->default(fn ($record) => (bool) $record?->course_concluded_at)
                            ->visible(fn () => auth()->user()->role === 'admin'),
                        Forms\Components\Toggle::make('reset_semester_survey')
                            ->label('Pedir nova pesquisa semestral no próximo login')
                            ->helperText('Ative e salve para o aluno responder a pesquisa de novo, mesmo que já tenha respondido neste semestre.')
                            ->default(false)
                            ->visible(fn () => auth()->user()->role === 'admin'),
                    ]),

                Forms\Components\Section::make('💬 Notificações no Telegram')
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Forms\Components\Placeholder::make('telegram_status')
                            ->label('')
                            ->content(function ($record) {
                                if (!empty($record->telegram_chat_id)) {
                                    return new \Illuminate\Support\HtmlString('
                                        <div class="flex items-center gap-2 p-3 text-sm text-emerald-400 bg-emerald-950/30 border border-emerald-800 rounded-lg">
                                            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: inline-block; vertical-align: middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <span><strong>Vinculado!</strong> Você receberá alertas de correções e prazos diretamente no seu Telegram. (Chat ID: <code>' . e($record->telegram_chat_id) . '</code>)</span>
                                        </div>
                                    ');
                                }

                                $inviteLink = app(\App\Services\TelegramService::class)->getInviteLink($record->id);
                                return new \Illuminate\Support\HtmlString('
                                    <div class="p-4 bg-amber-950/20 border border-amber-900/60 rounded-lg">
                                        <p class="text-sm text-amber-300 mb-3">Você ainda não vinculou seu Telegram. Ative para receber notificações instantâneas e gratuitas de suas correções e prazos.</p>
                                        <a href="' . $inviteLink . '" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 rounded-lg transition" style="background-color: #0284c7; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center;">
                                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24" style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 6px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15.82-.7 3.99-1.01 5.67-.13.72-.37.96-.63.98-.56.05-1.02-.37-1.57-.73-.85-.56-1.34-.91-2.17-1.46-.96-.63-.34-.98.21-1.55.14-.15 2.66-2.44 2.71-2.65.01-.03.01-.13-.05-.18-.06-.05-.15-.03-.21-.02-.1.02-1.63 1.04-4.61 3.06-.44.3-.83.45-1.18.44-.38-.01-1.12-.22-1.66-.4-.67-.22-1.2-.34-1.15-.72.03-.2.3-.4 1.15-.79 5.25-2.29 8.76-3.8 10.51-4.53.5-.2.81-.3.94-.3.08 0 .26.02.38.12.1.08.13.19.14.27 0 .04-.01.12-.02.2z"/></svg>
                                            Iniciar Bot & Vincular Telegram
                                        </a>
                                    </div>
                                ');
                            })
                    ]),

                Forms\Components\Section::make('Histórico Acadêmico de Títulos (TCC / IC)')
                    ->schema([
                        Forms\Components\Repeater::make('titles')
                            ->relationship('titles')
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'TCC' => 'TCC (Trabalho de Conclusão de Curso)',
                                        'IC' => 'Iniciação Científica (IC)',
                                    ])
                                    ->required()
                                    ->label('Tipo de Trabalho')
                                    ->disabled(fn ($record) => auth()->user()->role === 'student' && $record !== null && \App\Models\DocumentSubmission::where('academic_title_id', $record->id)->exists()),
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Título do Projeto / Tema')
                                    ->disabled(fn ($record) => auth()->user()->role === 'student' && $record !== null && \App\Models\DocumentSubmission::where('academic_title_id', $record->id)->exists()),
                            ])
                            ->columns(['default' => 1, 'md' => 2])
                            ->label('')
                            ->itemLabel(fn (array $state): ?string => isset($state['type']) && isset($state['title']) ? "[{$state['type']}] {$state['title']}" : null)
                            ->deleteAction(
                                fn (Forms\Components\Actions\Action $action) => $action->disabled(fn ($record) => auth()->user()->role === 'student' && $record !== null && \App\Models\DocumentSubmission::where('academic_title_id', $record->id)->exists())
                            ),
                    ]),

                Forms\Components\Section::make('Termo de Consentimento e Uso de Dados (LGPD)')
                    ->schema([
                        Forms\Components\Placeholder::make('terms_text')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="p-4 rounded-lg border border-gray-700 bg-gray-900/50 text-sm leading-relaxed text-gray-300">
                                    <h4 class="font-bold text-white mb-2">TERMO DE CONSENTIMENTO LIVRE E ESCLARECIDO (TCLE)</h4>
                                    <p class="mb-2">Ao preencher este perfil e marcar a caixa de seleção abaixo, eu declaro, de minha <strong>livre e espontânea vontade</strong>, que concordo em participar das atividades de orientação acadêmica sob a supervisão do <strong>Prof. Dr. Atilio Sersun Calefi</strong>.</p>
                                    <p class="mb-2">Estou ciente e concordo que:</p>
                                    <ul class="list-disc pl-5 mb-2 flex flex-col gap-1">
                                        <li>Os meus dados pessoais (nome, e-mail, telefone e matrícula) e acadêmicos (projetos, TCCs, relatórios) serão tratados única e exclusivamente para fins de acompanhamento acadêmico e assinaturas de documentos.</li>
                                        <li>Os arquivos digitais serão armazenados em nuvem segura integrada ao Google Drive do orientador.</li>
                                        <li>Caso eu me matricule em algum curso/treinamento oferecido na plataforma, meu <strong>CPF</strong> será coletado para fins de emissão de certificado, e minhas respostas de exercícios/avaliações, progresso na trilha e dúvidas enviadas por etapa ficarão registrados — as dúvidas enviadas em cada etapa ficam visíveis aos demais alunos matriculados no mesmo curso, junto com a resposta do professor.</li>
                                        <li>Os dados coletados respeitam as exigências da Lei Geral de Proteção de Dados (<strong>LGPD - Lei nº 13.709/2018</strong>).</li>
                                    </ul>
                                    <p>Este consentimento pode ser revogado a qualquer momento mediante solicitação direta ao orientador, respeitando-se as entregas oficiais vigentes.</p>
                                </div>
                            ')),
                        Forms\Components\Checkbox::make('terms_accepted')
                            ->label('Consinto que meus dados pessoais e acadêmicos (incluindo RGM, instituição, CPF quando aplicável a cursos, e registros de acesso) serão tratados pela plataforma profatilio.com.br de forma confidencial, com a finalidade exclusiva de viabilizar a gestão, auditoria e fluxos de minha orientação acadêmica e eventuais cursos/treinamentos, em total conformidade com a LGPD (Lei nº 13.709/2018). Declaro ciência de que o Prof. Atilio terá acesso integral a estes dados para fins de validação institucional.')
                            ->accepted() // Obriga a marcar como "true" para salvar
                            ->disabled(fn ($record) => $record?->terms_accepted ?? false) // Bloqueia desmarcar se já aceitou
                            ->dehydrated()
                            ->required(),
                        Forms\Components\DateTimePicker::make('terms_accepted_at')
                            ->label('Assinado digitalmente em')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record?->terms_accepted ?? false),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('user.profile_photo_path')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->user?->name) . '&color=0D9488&background=0F172A'),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Nome')
                    ->formatStateUsing(function ($state) {
                        if (auth()->user()->role === 'student') {
                            $parts = explode(' ', trim($state));
                            return count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $state;
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('user.email')
                    ->searchable()
                    ->sortable()
                    ->label('E-mail')
                    ->formatStateUsing(fn ($state) => auth()->user()->role === 'student' ? '***' : $state),
                Tables\Columns\TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->toggleable()
                    ->formatStateUsing(fn ($state) => $state ?? '—'),
                Tables\Columns\TextColumn::make('registration')
                    ->searchable()
                    ->label('Matrícula')
                    ->formatStateUsing(fn ($state) => auth()->user()->role === 'student' ? '***' : $state),
                Tables\Columns\TextColumn::make('institution')
                    ->searchable()
                    ->label('Instituição'),
                Tables\Columns\TextColumn::make('campus')
                    ->searchable()
                    ->toggleable()
                    ->label('Campus')
                    ->formatStateUsing(fn ($state) => $state ?? '—'),
                Tables\Columns\TextColumn::make('period')
                    ->sortable()
                    ->toggleable()
                    ->label('Período')
                    ->formatStateUsing(fn ($state) => $state ?? '—'),
                Tables\Columns\TextColumn::make('academic_level')
                    ->sortable()
                    ->label('Nível'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone')
                    ->formatStateUsing(fn ($state) => auth()->user()->role === 'student' ? '***' : $state),
                Tables\Columns\IconColumn::make('terms_accepted')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('danger')
                    ->label('Termo Assinado'),
                Tables\Columns\TextColumn::make('terms_accepted_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Assinado em'),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'external' => 'warning',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'external' => 'Externo',
                        default    => 'Regular',
                    })
                    ->label('Categoria')
                    ->visible(fn () => auth()->user()->role === 'admin'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'awaiting_email_validation' => 'warning',
                        'awaiting_advisor_approval' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Ativo',
                        'inactive' => 'Inativo',
                        'awaiting_email_validation' => 'Validação de E-mail Pendente',
                        'awaiting_advisor_approval' => 'Aprovação Pendente',
                        default => $state,
                    })
                    ->label('Status da Conta'),
                Tables\Columns\TextColumn::make('activated_at')
                    ->label('Ativo Desde')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Não Ativado')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('user.name')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'regular'  => 'Regular (Orientando)',
                        'external' => 'Externo (Mentoria/Tutoria)',
                    ])
                    ->label('Categoria'),
                Tables\Filters\SelectFilter::make('academic_level')
                    ->options([
                        'Graduação' => 'Graduação',
                        'Especialização' => 'Especialização',
                        'Mestrado' => 'Mestrado',
                        'Doutorado' => 'Doutorado',
                    ])
                    ->label('Nível Acadêmico'),
            ])
            ->recordUrl(fn ($record) => auth()->user()->role === 'admin'
                ? static::getUrl('view', ['record' => $record])
                : static::getUrl('edit', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn () => auth()->user()->role === 'admin'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('relatorioOrientacao')
                    ->label('Relatório PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn ($record) => route('admin.relatorio-aluno', $record->id))
                    ->openUrlInNewTab()
                    ->visible(fn () => auth()->user()->role === 'admin'),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('sendEmail')
                    ->label('Enviar E-mail')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('subject')
                            ->label('Assunto')
                            ->required()
                            ->default('Aviso Acadêmico - Prof. Atilio'),
                        Forms\Components\Textarea::make('message')
                            ->label('Mensagem')
                            ->rows(6)
                            ->required()
                            ->placeholder('Escreva sua mensagem aqui...'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            if ($record->user) {
                                \Illuminate\Support\Facades\Mail::to($record->user->email)->send(
                                    new \App\Mail\AppNotificationMail(
                                        title: $data['subject'],
                                        messageContent: $data['message'],
                                        actionUrl: url('/admin'),
                                        actionText: 'Acessar Painel',
                                        subject: $data['subject']
                                    )
                                );
                                
                                \Filament\Notifications\Notification::make()
                                    ->title('E-mail enviado!')
                                    ->body("O e-mail foi enviado com sucesso para {$record->user->name}.")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail individual: " . $e->getMessage());
                            \Filament\Notifications\Notification::make()
                                ->title('Erro ao enviar e-mail')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn () => auth()->user()->role === 'admin'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('generatePasswordResetLink')
                        ->label('Gerar Link de Senha')
                        ->icon('heroicon-o-link')
                        ->color('warning')
                        ->form(function ($record) {
                            if (!$record?->user) {
                                return [];
                            }
                            $token = \Illuminate\Support\Facades\Password::broker()->createToken($record->user);
                            // URL *assinada*: a rota de reset do Filament exige assinatura válida
                            // (ValidateSignature). Um link montado com route() sem assinatura abre
                            // como "Acesso Negado" (403).
                            $resetUrl = \Filament\Facades\Filament::getPanel('portal')
                                ->getResetPasswordUrl($token, $record->user);

                            return [
                                Forms\Components\Placeholder::make('info')
                                    ->label('')
                                    ->content("Copie o link abaixo e envie diretamente para a aluna ({$record->user->email}) via WhatsApp ou Telegram. Válido por 24 horas:"),
                                Forms\Components\TextInput::make('reset_link')
                                    ->label('Link de Redefinição Direta')
                                    ->default($resetUrl)
                                    ->disabled()
                                    ->extraInputAttributes(['onclick' => 'this.select()']),
                            ];
                        })
                        ->modalHeading('Link de Redefinição de Senha')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Fechar'),
                    Tables\Actions\Action::make('generateTempPassword')
                        ->label('Gerar Senha Temporária')
                        ->icon('heroicon-o-sparkles')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Gerar Senha Temporária')
                        ->modalDescription('Isso define uma nova senha aleatória para o aluno agora no banco de dados. No próximo login, ele será obrigado a trocá-la antes de acessar qualquer outra tela do portal.')
                        ->modalSubmitActionLabel('Gerar Senha')
                        ->action(function ($record) {
                            if (!$record?->user) {
                                \Filament\Notifications\Notification::make()->title('Aluno ou Usuário não encontrado.')->danger()->send();
                                return;
                            }

                            $user = $record->user;
                            $tempPassword = \Illuminate\Support\Str::password(10, symbols: false);

                            $user->forceFill([
                                'password' => \Illuminate\Support\Facades\Hash::make($tempPassword),
                                'must_change_password' => true,
                            ])->save();

                            $loginUrl = url(route('filament.portal.auth.login', [], false));
                            $message = "Olá, {$user->name}! Uma senha temporária foi gerada para você acessar a plataforma.\n\n"
                                . "🔗 Acesse: {$loginUrl}\n"
                                . "📧 E-mail: {$user->email}\n"
                                . "🔑 Senha temporária: {$tempPassword}\n\n"
                                . "Assim que você entrar, o sistema vai pedir para você criar uma nova senha antes de continuar.";

                            \Filament\Notifications\Notification::make()
                                ->title('Senha Temporária Gerada!')
                                ->body("Copie a mensagem abaixo e envie para {$user->name} ({$user->email}):\n\n{$message}")
                                ->success()
                                ->persistent()
                                ->send();
                        }),
                    Tables\Actions\Action::make('changePasswordDirectly')
                        ->label('Alterar Senha Manualmente')
                        ->icon('heroicon-o-key')
                        ->color('danger')
                        ->form([
                            Forms\Components\TextInput::make('new_password')
                                ->label('Nova Senha')
                                ->password()
                                ->required()
                                ->minLength(6)
                                ->placeholder('Digite a nova senha para o aluno...'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Alterar Senha do Aluno')
                        ->modalDescription('Esta ação mudará a senha do aluno imediatamente no banco de dados.')
                        ->action(function ($record, array $data) {
                            if ($record->user) {
                                $record->user->update([
                                    'password' => \Illuminate\Support\Facades\Hash::make($data['new_password']),
                                    'must_change_password' => false,
                                ]);

                                \Filament\Notifications\Notification::make()
                                    ->title('Senha alterada!')
                                    ->body("A senha de {$record->user->name} foi atualizada com sucesso.")
                                    ->success()
                                    ->send();
                            }
                        }),
                    Tables\Actions\Action::make('sendPasswordResetEmail')
                        ->label('Reenviar E-mail de Senha')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Enviar E-mail de Redefinição?')
                        ->modalDescription('Isso gerará um novo token e enviará o e-mail oficial de redefinição de senha para o aluno.')
                        ->action(function ($record) {
                            try {
                                if ($record->user) {
                                    $token = \Illuminate\Support\Facades\Password::broker()->createToken($record->user);
                                    $record->user->sendPasswordResetNotification($token);

                                    \Filament\Notifications\Notification::make()
                                        ->title('E-mail enviado!')
                                        ->body("O e-mail de redefinição de senha foi enviado para {$record->user->email}.")
                                        ->success()
                                        ->send();
                                }
                            } catch (\Throwable $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Erro ao enviar e-mail')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
                ->label('Senha & Acesso')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->visible(fn () => auth()->user()?->role === 'admin'),
                Tables\Actions\Action::make('manuallyVerifyEmail')
                    ->label('Validar E-mail Manualmente')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'awaiting_email_validation')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'awaiting_advisor_approval',
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('E-mail verificado!')
                            ->body("O e-mail de {$record->user->name} foi validado e o aluno agora aguarda sua aprovação final.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('resendStudentActivation')
                    ->label('Reenviar Ativação')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->visible(fn ($record) => $record->status === 'awaiting_email_validation')
                    ->action(function ($record) {
                        if (empty($record->activation_token)) {
                            $record->activation_token = bin2hex(random_bytes(16));
                            $record->save();
                        }
                        try {
                            $activationUrl = str_starts_with(config('app.url'), 'https') 
                                ? secure_url("/activate?token={$record->activation_token}") 
                                : url("/activate?token={$record->activation_token}");
                            $botUsername = ltrim(config('services.telegram.bot_username') ?? '', '@');
                            $messageBody = "Olá, {$record->user->name}!\n\n" .
                                "Este é o link de ativação para a sua conta na plataforma profatilio.com.br. Para validar seu e-mail, clique no botão abaixo:\n\n" .
                                "📢 **Importante: Telegram**\n" .
                                "Você poderá vincular seu Telegram para alertas após a ativação.";

                            \Illuminate\Support\Facades\Mail::to($record->user->email)->send(
                                new \App\Mail\AppNotificationMail(
                                    title: 'Ativação de Conta',
                                    messageContent: $messageBody,
                                    actionUrl: $activationUrl,
                                    actionText: 'Validar E-mail',
                                    subject: 'Ativação de Conta - Plataforma Prof. Atilio'
                                )
                            );

                            \Filament\Notifications\Notification::make()
                                ->title('E-mail de ativação enviado!')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Erro ao enviar')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('approveStudent')
                    ->label('Aprovar Aluno')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'awaiting_advisor_approval')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'active',
                            'activated_at' => now(),
                            'activation_token' => null,
                        ]);

                        // Send welcome email to student with Telegram info
                        try {
                            if ($record->user) {
                                $botUsername = ltrim(config('services.telegram.bot_username') ?? '', '@');
                                if (empty($record->telegram_link_token)) {
                                    $record->telegram_link_token = \Illuminate\Support\Str::random(32);
                                    $record->save();
                                }
                                $tgLink = "https://t.me/{$botUsername}?start={$record->telegram_link_token}";
                                $welcomeContent = "Olá, {$record->user->name}!\n\n" .
                                    "Sua conta na plataforma profatilio.com.br foi aprovada pelo Prof. Atilio! Agora você pode acessar o painel e acompanhar suas orientações.\n\n" .
                                    "📢 **Vincule seu Telegram para Alertas em Tempo Real:**\n" .
                                    "A plataforma conta com alertas instantâneos! Você pode receber notificações no seu celular sempre que o professor revisar ou assinar seus documentos acadêmicos.\n\n" .
                                    "Para ativar, clique no link abaixo para abrir o bot:\n\n" .
                                    "{$tgLink}\n\n" .
                                    "E depois clique no botão **Começar (/start)** no Telegram.";

                                \Illuminate\Support\Facades\Mail::to($record->user->email)->send(
                                    new \App\Mail\AppNotificationMail(
                                        title: 'Sua conta foi ativada! 🎓',
                                        messageContent: $welcomeContent,
                                        actionUrl: url('/admin'),
                                        actionText: 'Acessar Painel',
                                        subject: "Sua conta foi ativada! - Plataforma Prof. Atilio"
                                    )
                                );
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail de boas-vindas: " . $e->getMessage());
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Aluno Aprovado!')
                            ->body("O aluno {$record->user->name} foi aprovado com sucesso.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('inactivateStudent')
                    ->label('Inativar Aluno')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => auth()->user()->role === 'admin' && $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => 'inactive']);

                        \Filament\Notifications\Notification::make()
                            ->title('Aluno Inativado')
                            ->body("O aluno {$record->user->name} foi marcado como inativo.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('reactivateStudent')
                    ->label('Reativar Aluno')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn ($record) => auth()->user()->role === 'admin' && $record->status === 'inactive')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'active',
                            'activated_at' => $record->activated_at ?? now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Aluno Reativado')
                            ->body("O aluno {$record->user->name} foi reativado com sucesso.")
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('solicitarDocumentoAssinatura')
                    ->label('Solicitar Documento para Assinatura')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('student_id')
                            ->label('Selecione o Aluno')
                            ->options(function () {
                                return \App\Models\Student::with('user')->get()->mapWithKeys(function ($student) {
                                    return [$student->id => ($student->user?->name ?? 'Sem Nome') . ' (' . ($student->user?->email ?? 'Sem E-mail') . ')'];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('document_title')
                            ->label('Nome do Documento')
                            ->placeholder('Ex: Carta de Aceite de Orientação')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('instructions')
                            ->label('Instruções para o Aluno (opcional)')
                            ->placeholder('Ex: Preciso da carta assinada pela coordenação do curso, em PDF...')
                            ->rows(4),
                    ])
                    ->modalHeading('Solicitar Documento para Assinatura')
                    ->modalDescription('O aluno será notificado por e-mail/Telegram para enviar o documento pelo portal.')
                    ->action(function (array $data) {
                        $student = \App\Models\Student::with('user')->find($data['student_id']);
                        if (!$student) {
                            \Filament\Notifications\Notification::make()->title('Aluno não encontrado.')->danger()->send();
                            return;
                        }

                        \App\Models\DocumentSubmission::create([
                            'student_id' => $student->id,
                            'document_type' => 'Documento Solicitado',
                            'title' => $data['document_title'],
                            'ai_executive_summary' => $data['instructions'] ?? null,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Documento Solicitado!')
                            ->body("O aluno {$student->user?->name} foi notificado para enviar \"{$data['document_title']}\" pelo portal.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()?->role === 'admin'),
                Tables\Actions\Action::make('redefinirSenhaAlunoTable')
                    ->label('Redefinir Senha de Aluno')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('student_id')
                            ->label('Selecione o Aluno')
                            ->options(function () {
                                return \App\Models\Student::with('user')->get()->mapWithKeys(function ($student) {
                                    return [$student->id => ($student->user?->name ?? 'Sem Nome') . ' (' . ($student->user?->email ?? 'Sem E-mail') . ')'];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('action_type')
                            ->label('O que deseja fazer?')
                            ->options([
                                'generate_link' => 'Gerar Link de Redefinição (Para copiar e enviar no WhatsApp/Telegram)',
                                'temp_password' => 'Gerar Senha Temporária (Aluno troca no primeiro login)',
                                'direct_change' => 'Alterar Senha Manualmente (Definir nova senha agora no banco)',
                                'send_email'    => 'Disparar E-mail de Redefinição Oficial',
                            ])
                            ->required()
                            ->default('generate_link')
                            ->live(),
                        Forms\Components\TextInput::make('new_password')
                            ->label('Nova Senha')
                            ->password()
                            ->minLength(6)
                            ->required(fn (Forms\Get $get) => $get('action_type') === 'direct_change')
                            ->visible(fn (Forms\Get $get) => $get('action_type') === 'direct_change'),
                    ])
                    ->modalHeading('Gerenciamento de Senha de Aluno')
                    ->action(function (array $data) {
                        $student = \App\Models\Student::with('user')->find($data['student_id']);
                        if (!$student || !$student->user) {
                            \Filament\Notifications\Notification::make()->title('Aluno ou Usuário não encontrado.')->danger()->send();
                            return;
                        }

                        $user = $student->user;
                        $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);
                        $resetUrl = url(route('filament.portal.auth.password-reset.reset', [
                            'token' => $token,
                            'email' => $user->email,
                        ], false));

                        if ($data['action_type'] === 'generate_link') {
                            \Filament\Notifications\Notification::make()
                                ->title('Link de Redefinição Gerado!')
                                ->body("Copie o link abaixo para a aluna {$user->name} ({$user->email}). Válido por 24 horas:\n\n{$resetUrl}")
                                ->success()
                                ->persistent()
                                ->send();
                        } elseif ($data['action_type'] === 'temp_password') {
                            $tempPassword = \Illuminate\Support\Str::password(10, symbols: false);
                            $user->forceFill([
                                'password' => \Illuminate\Support\Facades\Hash::make($tempPassword),
                                'must_change_password' => true,
                            ])->save();

                            $loginUrl = url(route('filament.portal.auth.login', [], false));
                            $message = "Olá, {$user->name}! Uma senha temporária foi gerada para você acessar a plataforma.\n\n"
                                . "🔗 Acesse: {$loginUrl}\n"
                                . "📧 E-mail: {$user->email}\n"
                                . "🔑 Senha temporária: {$tempPassword}\n\n"
                                . "Assim que você entrar, o sistema vai pedir para você criar uma nova senha antes de continuar.";

                            \Filament\Notifications\Notification::make()
                                ->title('Senha Temporária Gerada!')
                                ->body("Copie a mensagem abaixo e envie para {$user->name} ({$user->email}):\n\n{$message}")
                                ->success()
                                ->persistent()
                                ->send();
                        } elseif ($data['action_type'] === 'direct_change') {
                            $user->update([
                                'password' => \Illuminate\Support\Facades\Hash::make($data['new_password']),
                                'must_change_password' => false,
                            ]);
                            \Filament\Notifications\Notification::make()
                                ->title('Senha Alterada!')
                                ->body("A nova senha de {$user->name} foi definida com sucesso no banco de dados.")
                                ->success()
                                ->send();
                        } elseif ($data['action_type'] === 'send_email') {
                            try {
                                $user->sendPasswordResetNotification($token);
                                \Filament\Notifications\Notification::make()
                                    ->title('E-mail enviado!')
                                    ->body("O e-mail de redefinição foi enviado para {$user->email}.")
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                \Filament\Notifications\Notification::make()->title('Erro ao enviar e-mail')->body($e->getMessage())->danger()->send();
                            }
                        }
                    })
                    ->visible(fn () => auth()->user()?->role === 'admin'),
                Tables\Actions\Action::make('sendEmailToAll')
                    ->label('E-mail Geral')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->form([
                        Forms\Components\TextInput::make('subject')
                            ->label('Assunto')
                            ->required()
                            ->default('Aviso Acadêmico Geral - Prof. Atilio'),
                        Forms\Components\Textarea::make('message')
                            ->label('Mensagem')
                            ->rows(6)
                            ->required()
                            ->placeholder('Escreva sua mensagem aqui...'),
                    ])
                    ->action(function (array $data) {
                        $students = \App\Models\Student::with('user')->where('status', 'active')->get();
                        $sentCount = 0;
                        foreach ($students as $student) {
                            if (!$student->user) continue;

                            try {
                                \Illuminate\Support\Facades\Mail::to($student->user->email)->queue(
                                    new \App\Mail\AppNotificationMail(
                                        title: $data['subject'],
                                        messageContent: $data['message'],
                                        actionUrl: url('/admin'),
                                        actionText: 'Acessar Painel',
                                        subject: $data['subject']
                                    )
                                );
                                $sentCount++;
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail geral para {$student->user->name}: " . $e->getMessage());
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('E-mail Geral Enviado!')
                            ->body("{$sentCount} e-mails foram enviados com sucesso para todos os alunos ativos.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()->role === 'admin'),
                Tables\Actions\Action::make('sendTelegramAnnouncement')
                    ->label('Notificar Telegram a Todos')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar Informativo do Telegram?')
                    ->modalDescription('Esta ação enviará um e-mail explicativo para todos os alunos ativos que ainda não vincularam o Telegram ou que queiram configurá-lo.')
                    ->action(function () {
                        $students = \App\Models\Student::with('user')->where('status', 'active')->get();
                        $sentCount = 0;

                        foreach ($students as $student) {
                            if (!$student->user) continue;

                            try {
                                $botUsername = ltrim(config('services.telegram.bot_username') ?? '', '@');
                                if (empty($student->telegram_link_token)) {
                                    $student->telegram_link_token = \Illuminate\Support\Str::random(32);
                                    $student->save();
                                }
                                $link = "https://t.me/{$botUsername}?start={$student->telegram_link_token}";

                                $messageContent = "Olá, {$student->user->name}!\n\n" .
                                    "Para facilitar o acompanhamento de suas orientações acadêmicas, o Prof. Atilio agora conta com integração oficial com o Telegram! 🎓\n\n" .
                                    "Ao vincular sua conta do Telegram ao bot oficial, você receberá notificações instantâneas direto no seu celular sempre que:\n" .
                                    "- O professor devolver um documento corrigido ou assinado;\n" .
                                    "- For iniciado um novo processo acadêmico;\n" .
                                    "- Houver atualizações de prazos importantes.\n\n" .
                                    "👉 **Como Vincular:**\n" .
                                    "1. Clique no link do bot oficial abaixo:\n\n" .
                                    "{$link}\n\n" .
                                    "2. No chat do Telegram, clique em **Começar** (ou envie o comando `/start`).\n\n" .
                                    "Aproveite essa facilidade para agilizar suas orientações!";

                                \Illuminate\Support\Facades\Mail::to($student->user->email)->queue(
                                    new \App\Mail\AppNotificationMail(
                                        title: 'Vincule seu Telegram para Alertas Acadêmicos 🎓',
                                        messageContent: $messageContent,
                                        actionUrl: $link,
                                        actionText: 'Vincular Telegram Agora',
                                        subject: 'Importante: Vincule seu Telegram para Alertas Acadêmicos 🎓'
                                    )
                                );
                                $sentCount++;
                            } catch (\Throwable $e) {
                                \Illuminate\Support\Facades\Log::error("Erro ao enviar informativo do Telegram para {$student->user->name}: " . $e->getMessage());
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Informativos Enviados!')
                            ->body("{$sentCount} alunos ativos receberam o informativo sobre a integração do Telegram.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => auth()->user()->role === 'admin'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('sendBulkEmail')
                        ->label('Enviar E-mail (Lote)')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->form([
                            Forms\Components\TextInput::make('subject')
                                ->label('Assunto')
                                ->required()
                                ->default('Aviso Acadêmico - Prof. Atilio'),
                            Forms\Components\Textarea::make('message')
                                ->label('Mensagem')
                                ->rows(6)
                                ->required()
                                ->placeholder('Escreva sua mensagem aqui...'),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $sentCount = 0;
                            foreach ($records as $record) {
                                try {
                                    if ($record->user) {
                                        \Illuminate\Support\Facades\Mail::to($record->user->email)->send(
                                            new \App\Mail\AppNotificationMail(
                                                title: $data['subject'],
                                                messageContent: $data['message'],
                                                actionUrl: url('/admin'),
                                                actionText: 'Acessar Painel',
                                                subject: $data['subject']
                                            )
                                        );
                                        $sentCount++;
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error("Erro ao enviar e-mail em lote para {$record->user->name}: " . $e->getMessage());
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('E-mails enviados!')
                                ->body("{$sentCount} e-mails foram enviados com sucesso.")
                                ->success()
                                ->send();
                        })
                        ->visible(fn () => auth()->user()->role === 'admin'),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->with(['user']);
        
        if (auth()->user()->role === 'student') {
            return $query->where('user_id', auth()->id());
        }
        
        return $query;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TitlesRelationManager::class,
            RelationManagers\SubmissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'view' => Pages\ViewStudent::route('/{record}'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
