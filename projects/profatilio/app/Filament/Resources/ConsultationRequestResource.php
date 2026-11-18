<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConsultationRequestResource\Pages;
use App\Models\ConsultationRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ConsultationRequestResource extends Resource
{
    protected static ?string $model = ConsultationRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Solicitações de Contato';

    protected static ?string $modelLabel = 'Solicitação';

    protected static ?string $pluralModelLabel = 'Solicitações de Contato';

    protected static ?string $navigationGroup = 'Controle de Alunos';

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
                Forms\Components\Section::make('Informações do Solicitante')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->disabled()
                            ->label('Nome / Razão Social'),
                        Forms\Components\TextInput::make('email')
                            ->disabled()
                            ->label('E-mail'),
                        Forms\Components\TextInput::make('phone')
                            ->disabled()
                            ->label('Telefone / WhatsApp'),
                        Forms\Components\TextInput::make('request_type')
                            ->disabled()
                            ->label('Tipo de Solicitação'),
                        Forms\Components\Textarea::make('message')
                            ->disabled()
                            ->columnSpanFull()
                            ->rows(5)
                            ->label('Mensagem'),
                    ])->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Acompanhamento e Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pendente',
                                'contacted' => 'Contato Realizado',
                                'archived' => 'Arquivado',
                            ])
                            ->required()
                            ->label('Status do Atendimento'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nome'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->label('E-mail'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefone'),
                Tables\Columns\TextColumn::make('request_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Consultoria' => 'primary',
                        'Parceria' => 'warning',
                        'Palestra' => 'info',
                        'Cursos' => 'success',
                        default => 'gray',
                    })
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'contacted' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'contacted' => 'Contato Realizado',
                        'archived' => 'Arquivado',
                        default => $state,
                    })
                    ->label('Status'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Data da Solicitação'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('request_type')
                    ->options([
                        'Consultoria' => 'Consultoria',
                        'Parceria' => 'Parceria',
                        'Palestra' => 'Palestra',
                        'Cursos' => 'Cursos',
                        'Outro' => 'Outro',
                    ])
                    ->label('Tipo'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendente',
                        'contacted' => 'Contato Realizado',
                        'archived' => 'Arquivado',
                    ])
                    ->label('Status'),
            ])
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
            'index' => Pages\ListConsultationRequests::route('/'),
            'create' => Pages\CreateConsultationRequest::route('/create'),
            'edit' => Pages\EditConsultationRequest::route('/{record}/edit'),
        ];
    }
}
