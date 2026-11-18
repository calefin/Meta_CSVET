<?php

namespace App\Filament\Resources\SystematicReviewResource\RelationManagers;

use App\Models\SystematicReview;
use App\Models\SystematicReviewScreener;
use App\Models\User;
use App\Services\SystematicReviewService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ScreenersRelationManager extends RelationManager
{
    protected static string $relationship = 'screeners';

    protected static ?string $title = 'Triadores';

    protected static ?string $modelLabel = 'triador';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function service(): SystematicReviewService
    {
        return app(SystematicReviewService::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Externos recebem convite e são obrigados a criar login de aluno. Ninguém tria antes de assinar o termo. Os triadores não veem os votos uns dos outros.')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')
                    ->description(fn (SystematicReviewScreener $s) => $s->email . ($s->label ? ' · ' . $s->label : ''))
                    ->color(fn (SystematicReviewScreener $s) => $s->removed_at ? 'gray' : null),
                Tables\Columns\TextColumn::make('account')->label('Conta')
                    ->state(fn (SystematicReviewScreener $s) => $s->removed_at ? 'Removido' : $s->accountStatus())
                    ->badge()
                    ->color(fn (string $state) => $state === 'Conta ativa' ? 'success' : ($state === 'Removido' ? 'gray' : 'warning')),
                Tables\Columns\TextColumn::make('terms_accepted_at')->label('Termo')
                    ->state(fn (SystematicReviewScreener $s) => $s->hasSignedTerms() ? 'Assinado ' . $s->terms_accepted_at->format('d/m/Y H:i') : 'Pendente')
                    ->badge()
                    ->color(fn (SystematicReviewScreener $s) => $s->hasSignedTerms() ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('progress')->label('Progresso (etapa atual)')
                    ->state(function (SystematicReviewScreener $s) {
                        $review = $this->getOwnerRecord();
                        if ($review->status === SystematicReview::STATUS_PROTOCOL || !$s->participatesIn($review->stage)) {
                            return '—';
                        }
                        $p = $this->service()->progress($s);

                        return "{$p['done']}/{$p['total']}" . ($s->hasFinished($review->stage) ? ' ✅ concluiu' : '');
                    }),
                // Protocolo v1.1: situação da regra de parada e da checagem aleatória.
                Tables\Columns\TextColumn::make('stopping')->label('Regra de parada')
                    ->visible(fn () => $this->getOwnerRecord()->prioritisedScreening())
                    ->state(function (SystematicReviewScreener $s) {
                        $review = $this->getOwnerRecord();
                        if ($s->removed_at || $review->status === SystematicReview::STATUS_PROTOCOL || !$this->service()->prioritisedNow($review)) {
                            return '—';
                        }
                        $al = app(\App\Services\ActiveLearningService::class);
                        if ($al->isQcScreener($s)) {
                            $p = $this->service()->progress($s);

                            return "Checagem aleatória: {$p['done']}/{$p['total']}";
                        }
                        $st = $al->stoppingStatus($s);
                        $ok = collect($st['criteria'])->filter(fn ($c) => $c['ok'])->count();

                        return ($st['met'] ? '✅ cumprida' : "{$ok}/4 critérios") . " · {$st['relevant']} relevantes · {$st['streak']} irrelevantes seguidos"
                            . ($st['missing_validation'] ? ' · validação faltando: ' . count($st['missing_validation']) : '');
                    })
                    ->wrap(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('incluir')
                    ->label('Incluir triador')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Orientando já cadastrado')
                            ->placeholder('Digite nome, e-mail ou RGM…')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::query()
                                ->where(fn ($q) => $q->whereHas('student')->orWhere('role', 'admin'))
                                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhereHas('student', fn ($s) => $s->where('registration', 'like', "%{$search}%")))
                                ->orderBy('name')->limit(20)->get()
                                ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' — ' . $u->email])->all())
                            ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)
                            ->live()
                            ->helperText('Ou deixe em branco e preencha nome + e-mail de um aluno externo.'),
                        Forms\Components\Grid::make(2)
                            ->visible(fn (Forms\Get $get) => blank($get('user_id')))
                            ->schema([
                                Forms\Components\TextInput::make('name')->label('Nome completo (externo)')
                                    ->required(fn (Forms\Get $get) => blank($get('user_id')))->maxLength(255),
                                Forms\Components\TextInput::make('email')->label('E-mail (externo)')->email()
                                    ->required(fn (Forms\Get $get) => blank($get('user_id')))->maxLength(255),
                            ]),
                        Forms\Components\TextInput::make('label')->label('Rótulo (opcional)')->placeholder('Triador A')->maxLength(40),
                        Forms\Components\CheckboxList::make('stages')
                            ->label('Participa das etapas')
                            ->options(SystematicReview::STAGE_LABELS)
                            ->default(array_keys(SystematicReview::STAGE_LABELS))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        try {
                            $s = $this->service()->addScreener($this->getOwnerRecord(), $data);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        Notification::make()
                            ->title($s->user_id ? "{$s->name} incluído(a)" : "Convite enviado para {$s->email}")
                            ->body($s->user_id ? null : 'Ele(a) precisa criar o login de aluno pelo link do e-mail. A conta é liberada assim que validar o e-mail.')
                            ->success()->send();
                    }),
                Tables\Actions\Action::make('mensagemDupla')
                    ->label('Mensagem à dupla')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->visible(fn () => $this->getOwnerRecord()->activeScreeners()->exists())
                    ->modalDescription('Vai para todos os triadores ativos deste projeto, por e-mail (e Telegram, se ligado), com o botão para o portal. Use {nome} para o primeiro nome.')
                    ->form(\App\Filament\Resources\ResearchApplicationResource::messageFields())
                    ->action(function (array $data) {
                        $review = $this->getOwnerRecord();
                        $n = $this->service()->messageScreeners($review, $review->activeScreeners()->get(), $data['title'], $data['body']);
                        Notification::make()->title("Mensagem enviada para {$n} triador(es)")->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('mensagem')
                    ->label('Mensagem')
                    ->icon('heroicon-o-envelope-open')
                    ->color('primary')
                    ->visible(fn (SystematicReviewScreener $s) => !$s->removed_at)
                    ->modalHeading(fn (SystematicReviewScreener $s) => "Mensagem para {$s->name}")
                    ->form(\App\Filament\Resources\ResearchApplicationResource::messageFields())
                    ->action(function (SystematicReviewScreener $s, array $data) {
                        $this->service()->messageScreeners($this->getOwnerRecord(), [$s], $data['title'], $data['body']);
                        Notification::make()->title("Mensagem enviada para {$s->name}")->success()->send();
                    }),
                Tables\Actions\Action::make('termo')
                    ->label('Ver termo')
                    ->icon('heroicon-o-document-check')
                    ->color('gray')
                    ->visible(fn (SystematicReviewScreener $s) => $s->terms_accepted_at !== null)
                    ->url(fn (SystematicReviewScreener $s) => route('systematic-review.terms', $s), true),
                Tables\Actions\Action::make('convite')
                    ->label('Reenviar convite')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->visible(fn (SystematicReviewScreener $s) => !$s->user_id && !$s->removed_at)
                    ->requiresConfirmation()
                    ->action(function (SystematicReviewScreener $s) {
                        $url = $this->service()->inviteExternal($s);
                        Notification::make()->title('Convite reenviado')->body($url)->success()->persistent()->send();
                    }),
                Tables\Actions\Action::make('lembrete')
                    ->label('Lembrete')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->visible(fn (SystematicReviewScreener $s) => $s->user_id && !$s->removed_at && $this->getOwnerRecord()->status === SystematicReview::STATUS_SCREENING)
                    ->form([Forms\Components\Textarea::make('message')->label('Mensagem (opcional)')->rows(3)->maxLength(1000)])
                    ->action(function (SystematicReviewScreener $s, array $data) {
                        $p = $this->service()->progress($s);
                        $this->service()->notifyScreener($s, 'Lembrete da triagem', "Olá, {$s->name}!\n\n"
                            . "Você está com {$p['done']} de {$p['total']} trabalhos triados em \"{$s->systematicReview->title}\"."
                            . (filled($data['message'] ?? null) ? "\n\nRecado do Prof. Atilio: " . trim($data['message']) : ''), 'project');
                        Notification::make()->title('Lembrete enviado')->success()->send();
                    }),
                Tables\Actions\Action::make('reabrir')
                    ->label('Reabrir triagem')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (SystematicReviewScreener $s) => $s->hasFinished($this->getOwnerRecord()->stage)
                        && in_array($this->getOwnerRecord()->status, [SystematicReview::STATUS_SCREENING, SystematicReview::STATUS_CONFLICTS], true))
                    ->requiresConfirmation()
                    ->action(fn (SystematicReviewScreener $s) => $this->service()->reopenScreener($s)),
                Tables\Actions\Action::make('remover')
                    ->label('Remover')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (SystematicReviewScreener $s) => !$s->removed_at)
                    ->requiresConfirmation()
                    ->modalDescription('A pessoa perde o acesso. Os votos e o termo assinado continuam guardados (as obrigações de sigilo continuam valendo).')
                    ->form([Forms\Components\TextInput::make('reason')->label('Motivo (fica no histórico)')->maxLength(250)])
                    ->action(function (SystematicReviewScreener $s, array $data) {
                        $this->service()->removeScreener($s, $data['reason'] ?? null);
                        // Recarrega a página: os painéis de cima (confiabilidade, PRISMA) não são do relation manager e ficavam com o triador removido.
                        $this->redirect(request()->header('Referer') ?: \App\Filament\Resources\SystematicReviewResource::getUrl('view', ['record' => $this->getOwnerRecord()]));
                    }),
            ]);
    }
}
