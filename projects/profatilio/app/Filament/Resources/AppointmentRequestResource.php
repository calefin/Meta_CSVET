<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentRequestResource\Pages;
use App\Models\AppointmentRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppointmentRequestResource extends Resource
{
    protected static ?string $model = AppointmentRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Solicitações de Consulta';

    protected static ?string $modelLabel = 'Solicitação de Consulta';

    protected static ?string $pluralModelLabel = 'Solicitações de Consulta';

    protected static ?string $navigationGroup = 'Atendimento Clínico';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Solicitação')
                    ->schema([
                        Forms\Components\TextInput::make('requester_name')
                            ->disabled()
                            ->label('Nome do Tutor'),
                        Forms\Components\TextInput::make('requester_email')
                            ->disabled()
                            ->label('E-mail'),
                        Forms\Components\TextInput::make('requester_phone')
                            ->disabled()
                            ->label('Telefone / WhatsApp'),
                        Forms\Components\TextInput::make('animal_type')
                            ->disabled()
                            ->label('Tipo de Animal')
                            ->formatStateUsing(fn (?string $state) => match ($state) {
                                'convencional' => 'Convencional (cão/gato)',
                                'nao_convencional' => 'Não Convencional (exótico/silvestre)',
                                default => $state,
                            }),
                        Forms\Components\TextInput::make('species')
                            ->disabled()
                            ->label('Espécie'),
                        Forms\Components\Textarea::make('complaint')
                            ->disabled()
                            ->columnSpanFull()
                            ->rows(4)
                            ->label('Queixa Principal'),
                    ])->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Acompanhamento')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pendente',
                                'contacted' => 'Contato Realizado',
                                'scheduled' => 'Agendado',
                                'completed' => 'Concluído',
                                'cancelled' => 'Cancelado',
                            ])
                            ->required()
                            ->label('Status'),
                        Forms\Components\Textarea::make('admin_notes')
                            ->rows(4)
                            ->columnSpanFull()
                            ->label('Notas Internas'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('requester_name')
                    ->searchable()
                    ->sortable()
                    ->label('Tutor'),
                Tables\Columns\TextColumn::make('species')
                    ->searchable()
                    ->label('Espécie'),
                Tables\Columns\TextColumn::make('animal_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'convencional' => 'gray',
                        'nao_convencional' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'convencional' => 'Convencional',
                        'nao_convencional' => 'Não Convencional',
                        default => $state,
                    })
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'contacted' => 'info',
                        'scheduled' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'contacted' => 'Contato Realizado',
                        'scheduled' => 'Agendado',
                        'completed' => 'Concluído',
                        'cancelled' => 'Cancelado',
                        default => $state,
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Data da Solicitação'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('animal_type')
                    ->options([
                        'convencional' => 'Convencional',
                        'nao_convencional' => 'Não Convencional',
                    ])
                    ->label('Tipo de Animal'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendente',
                        'contacted' => 'Contato Realizado',
                        'scheduled' => 'Agendado',
                        'completed' => 'Concluído',
                        'cancelled' => 'Cancelado',
                    ])
                    ->label('Status'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppointmentRequests::route('/'),
            'create' => Pages\CreateAppointmentRequest::route('/create'),
            'edit' => Pages\EditAppointmentRequest::route('/{record}/edit'),
        ];
    }
}
