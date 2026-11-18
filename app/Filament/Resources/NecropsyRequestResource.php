<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NecropsyRequestResource\Pages;
use App\Models\NecropsyRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class NecropsyRequestResource extends Resource
{
    protected static ?string $model = NecropsyRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Solicitações de Necropsia';

    protected static ?string $modelLabel = 'Solicitação de Necropsia';

    protected static ?string $pluralModelLabel = 'Solicitações de Necropsia';

    protected static ?string $navigationGroup = 'Controle de Alunos';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = NecropsyRequest::awaitingReply()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Orçamentos aguardando seu retorno';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação do Animal')
                    ->schema([
                        Forms\Components\TextInput::make('animal_name')
                            ->label('Nome do Animal')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                        Forms\Components\TextInput::make('species')
                            ->label('Espécie')
                            ->required(),
                        Forms\Components\TextInput::make('breed')
                            ->label('Raça'),
                        Forms\Components\TextInput::make('age')
                            ->label('Idade Estimada'),
                        Forms\Components\Select::make('sex')
                            ->label('Sexo')
                            ->options([
                                'Macho' => 'Macho',
                                'Fêmea' => 'Fêmea',
                                'Indeterminado' => 'Indeterminado',
                            ]),
                        Forms\Components\TextInput::make('pelage')
                            ->label('Pelagem'),
                        Forms\Components\TextInput::make('weight')
                            ->label('Peso (kg)')
                            ->numeric()
                            ->required(),
                    ])->columns(['default' => 1, 'md' => 2, 'lg' => 3]),

                Forms\Components\Section::make('Informações Clínicas e Destinação')
                    ->schema([
                        Forms\Components\Toggle::make('is_euthanasia')
                            ->label('Foi realizado eutanásia?'),
                        Forms\Components\Toggle::make('has_cremation_plan')
                            ->label('Possui plano de cremação?')
                            ->live(),
                        Forms\Components\TextInput::make('cremation_company')
                            ->label('Empresa de Cremação')
                            ->required(fn (Forms\Get $get) => $get('has_cremation_plan') === true)
                            ->visible(fn (Forms\Get $get) => $get('has_cremation_plan') === true),
                        Forms\Components\Toggle::make('has_prosthesis')
                            ->label('Possui próteses?')
                            ->live(),
                        Forms\Components\TextInput::make('prosthesis_details')
                            ->label('Detalhes da Prótese')
                            ->required(fn (Forms\Get $get) => $get('has_prosthesis') === true)
                            ->visible(fn (Forms\Get $get) => $get('has_prosthesis') === true),
                        Forms\Components\Toggle::make('had_surgery')
                            ->label('Já realizou cirurgia?')
                            ->live(),
                        Forms\Components\TextInput::make('surgery_details')
                            ->label('Detalhes da Cirurgia')
                            ->required(fn (Forms\Get $get) => $get('had_surgery') === true)
                            ->visible(fn (Forms\Get $get) => $get('had_surgery') === true),
                    ])->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Requisitante (Responsável Legal)')
                    ->schema([
                        Forms\Components\TextInput::make('requester_name')
                            ->label('Nome Completo')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                        Forms\Components\TextInput::make('requester_email')
                            ->label('E-mail')
                            ->email()
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                        Forms\Components\TextInput::make('requester_phone')
                            ->label('Telefone / WhatsApp')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                        Forms\Components\TextInput::make('requester_cpf')
                            ->label('CPF')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                        Forms\Components\TextInput::make('requester_rg')
                            ->label('RG')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                        Forms\Components\TextInput::make('requester_cnpj')
                            ->label('CNPJ'),
                        Forms\Components\TextInput::make('requester_address')
                            ->label('Endereço')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                        Forms\Components\TextInput::make('requester_neighborhood')
                            ->label('Bairro')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                        Forms\Components\TextInput::make('requester_complement')
                            ->label('Complemento'),
                        Forms\Components\TextInput::make('requester_city')
                            ->label('Cidade')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                        Forms\Components\TextInput::make('requester_state')
                            ->label('UF')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized')
                            ->maxLength(2),
                        Forms\Components\TextInput::make('requester_zip')
                            ->label('CEP')
                            ->required(fn (Forms\Get $get): bool => $get('status') === 'authorized'),
                    ])->columns(['default' => 1, 'md' => 2, 'lg' => 3]),

                Forms\Components\Section::make('Médico Veterinário Requisitante')
                    ->schema([
                        Forms\Components\Toggle::make('requested_by_vet')
                            ->label('Solicitado por Médico Veterinário?')
                            ->live(),
                        Forms\Components\TextInput::make('vet_crmv')
                            ->label('CRMV')
                            ->required(fn (Forms\Get $get) => $get('requested_by_vet') === true)
                            ->visible(fn (Forms\Get $get) => $get('requested_by_vet') === true),
                        Forms\Components\TextInput::make('vet_state')
                            ->label('Estado CRMV')
                            ->required(fn (Forms\Get $get) => $get('requested_by_vet') === true)
                            ->visible(fn (Forms\Get $get) => $get('requested_by_vet') === true)
                            ->maxLength(2),
                        Forms\Components\TextInput::make('vet_establishment')
                            ->label('Estabelecimento / Clínica')
                            ->visible(fn (Forms\Get $get) => $get('requested_by_vet') === true),
                    ])->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Pricing & Status')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Preço Base (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->required(),
                        Forms\Components\TextInput::make('discount')
                            ->label('Desconto (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->required(),
                        Forms\Components\TextInput::make('final_price')
                            ->label('Preço Final (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'quote_generated' => 'Orçamento Gerado',
                                'authorized' => 'Autorizado e Assinado',
                                'cancelled' => 'Cancelado',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\DateTimePicker::make('contacted_at')
                            ->label('Retorno dado ao interessado em')
                            ->seconds(false),
                    ])->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Certificado de Assinatura e Auditoria Forense')
                    ->schema([
                        Forms\Components\Placeholder::make('forensic_hash')
                            ->label('Hash Forense (SHA-256)')
                            ->content(fn ($record) => $record?->forensic_hash ?? 'Não assinado'),
                        Forms\Components\Placeholder::make('forensic_timestamp')
                            ->label('Data e Hora da Assinatura')
                            ->content(fn ($record) => $record?->forensic_timestamp ?? 'N/A'),
                        Forms\Components\Placeholder::make('forensic_ip')
                            ->label('IP do Assinante')
                            ->content(fn ($record) => $record?->forensic_ip ?? 'N/A'),
                        Forms\Components\Placeholder::make('forensic_user_agent')
                            ->label('User-Agent do Navegador')
                            ->content(fn ($record) => $record?->forensic_user_agent ?? 'N/A'),
                        Forms\Components\Placeholder::make('signature_name')
                            ->label('Nome Completo Assinado')
                            ->content(fn ($record) => $record?->signature_name ?? 'N/A'),
                        Forms\Components\Placeholder::make('signature_image')
                            ->label('Assinatura Desenhada')
                            ->content(fn ($record) => $record?->signature_image ? new HtmlString("<img src='{$record->signature_image}' style='max-height: 80px; border: 1px dashed rgba(0,0,0,0.2); padding: 4px; background: white; border-radius: 8px;' />") : 'N/A'),
                    ])->columns(['default' => 1, 'md' => 2])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('animal_name')
                    ->label('Animal')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('species')
                    ->label('Espécie')
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->label('Peso')
                    ->suffix(' kg')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requester_name')
                    ->label('Responsável')
                    ->description(fn (NecropsyRequest $record): ?string => $record->requester_phone)
                    ->searchable(['requester_name', 'requester_phone', 'requester_email'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('final_price')
                    ->label('Valor Cobrado')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'quote_generated' => 'warning',
                        'authorized' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'quote_generated' => 'Orçamento Gerado',
                        'authorized' => 'Autorizado e Assinado',
                        'cancelled' => 'Cancelado',
                        default => $state,
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('contacted_at')
                    ->label('Retorno')
                    ->badge()
                    ->getStateUsing(fn (NecropsyRequest $record): string => match (true) {
                        $record->contacted_at !== null => 'Respondido ' . $record->contacted_at->format('d/m H:i'),
                        $record->status !== 'quote_generated' => '—',
                        empty($record->requester_phone) => 'Sem contato (pedido antigo)',
                        default => 'Aguardando retorno',
                    })
                    ->color(fn (NecropsyRequest $record): string => match (true) {
                        $record->contacted_at !== null => 'success',
                        $record->status === 'quote_generated' && ! empty($record->requester_phone) => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Pedido Em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('signed_at')
                    ->label('Assinado Em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'quote_generated' => 'Orçamento Gerado',
                        'authorized' => 'Autorizado e Assinado',
                        'cancelled' => 'Cancelado',
                    ])
                    ->label('Status'),
                Tables\Filters\Filter::make('awaiting_reply')
                    ->label('Aguardando meu retorno')
                    ->query(fn (Builder $query): Builder => $query->awaitingReply()),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (NecropsyRequest $record): ?string => $record->whatsappUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (NecropsyRequest $record): bool => $record->whatsappUrl() !== null),
                Tables\Actions\Action::make('markContacted')
                    ->label('Respondido')
                    ->icon('heroicon-o-check-circle')
                    ->color('gray')
                    ->action(function (NecropsyRequest $record): void {
                        $record->update(['contacted_at' => now()]);
                        \Filament\Notifications\Notification::make()
                            ->title('Marcado como respondido')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (NecropsyRequest $record): bool => $record->contacted_at === null
                        && $record->status === 'quote_generated'
                        && ! empty($record->requester_phone)),
                Tables\Actions\Action::make('termLink')
                    ->label('Link do termo')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->action(function (NecropsyRequest $record): void {
                        $message = $record->authorizationMessage();
                        $whatsapp = $record->whatsappUrl($message);

                        \Filament\Notifications\Notification::make()
                            ->title('Mensagem com o link do termo')
                            ->body('Copie a mensagem abaixo e envie' . ($record->requester_name ? " para {$record->requester_name}" : '') . ":\n\n{$message}")
                            ->success()
                            ->persistent()
                            ->actions($whatsapp ? [
                                \Filament\Notifications\Actions\Action::make('whatsapp')
                                    ->label('Abrir no WhatsApp')
                                    ->url($whatsapp, shouldOpenInNewTab: true)
                                    ->button(),
                            ] : [])
                            ->send();
                    })
                    ->visible(fn (NecropsyRequest $record): bool => $record->status === 'quote_generated'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('downloadPdf')
                    ->label('Ver Termo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (NecropsyRequest $record): string => route('necropsy.pdf', ['token' => $record->token]))
                    ->openUrlInNewTab()
                    ->visible(fn (NecropsyRequest $record): bool => !empty($record->signed_pdf_path)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNecropsyRequests::route('/'),
            'create' => Pages\CreateNecropsyRequest::route('/create'),
            'edit' => Pages\EditNecropsyRequest::route('/{record}/edit'),
        ];
    }
}
