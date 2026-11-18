<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InviteTokenResource\Pages;
use App\Models\InviteToken;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InviteTokenResource extends Resource
{
    protected static ?string $model = InviteToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Links de Convite';

    protected static ?string $modelLabel = 'Link de Convite';

    protected static ?string $pluralModelLabel = 'Links de Convite';

    protected static ?string $navigationGroup = 'Controle de Alunos';

    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->label('E-mail do Destinatário (Opcional)')
                    ->email()
                    ->placeholder('aluno@exemplo.com')
                    ->rules([
                        fn (Forms\Get $get, ?InviteToken $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($record) {
                            if (empty($value)) {
                                return;
                            }
                            $query = InviteToken::where('email', $value)
                                ->whereNull('used_at')
                                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

                            if ($record) {
                                $query->where('id', '!=', $record->id);
                            }

                            if ($query->exists()) {
                                $fail('Já existe um convite ativo e válido enviado para este e-mail.');
                            }
                        }
                    ]),
                Forms\Components\Select::make('category')
                    ->label('Categoria')
                    ->options([
                        'regular' => 'Regular (Orientando TCC/IC)',
                        'external' => 'Externo (Mentoria/Tutoria)',
                    ])
                    ->required()
                    ->default('regular'),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Data de Expiração (Opcional)')
                    ->placeholder('Selecione uma data limite')
                    ->helperText('Deixe em branco para que o link tenha validade indeterminada.'),
                Forms\Components\Toggle::make('send_email')
                    ->label('Enviar convite por e-mail automaticamente')
                    ->default(true)
                    ->dehydrated(false)
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Envia um e-mail com as instruções e o link de cadastro na fila de processamento.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Destinatário')
                    ->placeholder('Qualquer pessoa (Link aberto)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'regular' => 'info',
                        'external' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'regular' => 'Regular',
                        'external' => 'Externo',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('invite_url')
                    ->label('Link de Cadastro')
                    ->getStateUsing(fn ($record) => str_starts_with(config('app.url'), 'https') 
                        ? secure_url("/invite?token={$record->token}") 
                        : url("/invite?token={$record->token}"))
                    ->copyable()
                    ->copyMessage('Link copiado!')
                    ->copyMessageDuration(1500)
                    ->icon('heroicon-m-clipboard-document')
                    ->color('primary')
                    ->tooltip('Clique para copiar o link'),
                Tables\Columns\TextColumn::make('used_at')
                    ->label('Utilizado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('Ainda não utilizado')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('Sem expiração')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Gerado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'valid' => 'Válidos (Disponíveis)',
                        'used' => 'Utilizados',
                        'expired' => 'Expirados',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'valid') {
                            return $query->whereNull('used_at')
                                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                        }
                        if ($data['value'] === 'used') {
                            return $query->whereNotNull('used_at');
                        }
                        if ($data['value'] === 'expired') {
                            return $query->whereNull('used_at')
                                ->whereNotNull('expires_at')
                                ->where('expires_at', '<=', now());
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar/Prorrogar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Revogar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInviteTokens::route('/'),
        ];
    }
}
