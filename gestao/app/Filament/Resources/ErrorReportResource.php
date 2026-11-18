<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ErrorReportResource\Pages;
use App\Models\ErrorReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ErrorReportResource extends Resource
{
    protected static ?string $model = ErrorReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Relatar Erro / Suporte' : 'Relatórios de Erros';
    }

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? 'Minha Conta' : 'Monitoramento e Logs';
    }

    public static function getModelLabel(): string
    {
        return 'Relatório de Erro';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Relatórios de Erros';
    }

    public static function form(Form $form): Form
    {
        $isAdmin = auth()->user()?->role === 'admin';

        return $form
            ->schema([
                Forms\Components\Section::make('Detalhes do Erro Encontrado')
                    ->schema([
                        Forms\Components\Select::make('category')
                            ->options([
                                'Upload de Arquivos' => 'Upload de Arquivos / Avaliações',
                                'Telegram' => 'Notificações no Telegram',
                                'Design/Visual' => 'Problema Visual / Botão Quebrado',
                                'Assinatura Gov.br' => 'Erro na Assinatura Gov.br',
                                'Outro Erro' => 'Outro Erro / Problema',
                            ])
                            ->required()
                            ->disabled(!$isAdmin && fn ($record) => $record?->status !== 'pending')
                            ->label('Categoria do Erro'),

                        Forms\Components\TextInput::make('url')
                            ->maxLength(255)
                            ->disabled(!$isAdmin && fn ($record) => $record?->status !== 'pending')
                            ->placeholder('Ex: http://profatilio.com.br/portal/document-submissions')
                            ->label('URL da Página (Opcional)'),

                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->rows(5)
                            ->maxLength(2000)
                            ->disabled(!$isAdmin && fn ($record) => $record?->status !== 'pending')
                            ->placeholder('Por favor, descreva detalhadamente o erro que você encontrou e os passos para reproduzi-lo.')
                            ->columnSpanFull()
                            ->label('Descrição do Problema'),

                        Forms\Components\FileUpload::make('screenshot_path')
                            ->image()
                            ->directory('error-screenshots')
                            ->disabled(!$isAdmin && fn ($record) => $record?->status !== 'pending')
                            ->maxSize(5120) // 5MB limit
                            ->label('Print/Screenshot do Erro (Opcional)')
                            ->columnSpanFull(),
                    ])->columns(['default' => 1, 'md' => 2]),

                Forms\Components\Section::make('Acompanhamento e Resolução (Uso do Professor)')
                    ->visible($isAdmin || fn ($record) => $record !== null)
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pendente',
                                'investigating' => 'Sob Investigação',
                                'resolved' => 'Resolvido',
                                'closed' => 'Arquivado / Fechado',
                            ])
                            ->required()
                            ->disabled(!$isAdmin)
                            ->label('Status do Chamado'),

                        Forms\Components\Textarea::make('admin_notes')
                            ->disabled(!$isAdmin)
                            ->placeholder($isAdmin ? 'Notas sobre a investigação e como o erro foi corrigido...' : 'Nenhuma nota do professor registrada ainda.')
                            ->rows(3)
                            ->columnSpanFull()
                            ->label('Observações do Professor'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isAdmin = auth()->user()?->role === 'admin';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable()
                    ->visible($isAdmin),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Upload de Arquivos' => 'danger',
                        'Telegram' => 'info',
                        'Design/Visual' => 'warning',
                        'Assinatura Gov.br' => 'primary',
                        default => 'gray',
                    })
                    ->label('Categoria'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->label('Descrição'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'investigating' => 'info',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'investigating' => 'Em Investigação',
                        'resolved' => 'Resolvido',
                        'closed' => 'Fechado',
                        default => $state,
                    })
                    ->label('Status'),

                Tables\Columns\ImageColumn::make('screenshot_path')
                    ->label('Print')
                    ->circular(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->label('Relatado em'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Upload de Arquivos' => 'Upload de Arquivos',
                        'Telegram' => 'Notificações no Telegram',
                        'Design/Visual' => 'Problema Visual',
                        'Assinatura Gov.br' => 'Assinatura Gov.br',
                        'Outro Erro' => 'Outro Erro',
                    ])
                    ->label('Categoria'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendente',
                        'investigating' => 'Em Investigação',
                        'resolved' => 'Resolvido',
                        'closed' => 'Fechado',
                    ])
                    ->label('Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(fn ($record) => $isAdmin ? 'Editar / Resolver' : (($record->status === 'pending') ? 'Editar' : 'Visualizar')),
                Tables\Actions\DeleteAction::make()
                    ->visible($isAdmin),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible($isAdmin),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->with(['user']);

        if (auth()->user()?->role === 'student') {
            return $query->where('user_id', auth()->id());
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListErrorReports::route('/'),
            'create' => Pages\CreateErrorReport::route('/create'),
            'edit' => Pages\EditErrorReport::route('/{record}/edit'),
        ];
    }
}
