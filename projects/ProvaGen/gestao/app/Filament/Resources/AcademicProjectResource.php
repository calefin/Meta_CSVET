<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AcademicProjectResource\Pages;
use App\Filament\Resources\AcademicProjectResource\RelationManagers;
use App\Models\AcademicTitle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AcademicProjectResource extends Resource
{
    protected static ?string $model = AcademicTitle::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Meus Projetos' : 'Arquivo de Projetos';
    }

    public static function getModelLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Meu Projeto' : 'Projeto Acadêmico';
    }

    public static function getPluralModelLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Meus Projetos' : 'Arquivo de Projetos';
    }

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? 'Meus Trabalhos' : 'Orientação Acadêmica';
    }

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações Gerais do Projeto')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->label('Título do Projeto / Tema')
                            ->disabled(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'TCC' => 'TCC (Trabalho de Conclusão de Curso)',
                                'IC' => 'Iniciação Científica (IC)',
                            ])
                            ->required()
                            ->label('Tipo de Projeto')
                            ->disabled(),
                        Forms\Components\TextInput::make('student_name')
                            ->label('Aluno / Orientando')
                            ->default(fn ($record) => $record?->student?->user?->name ?? 'N/A')
                            ->disabled(),
                        Forms\Components\TextInput::make('academic_level')
                            ->label('Nível Acadêmico')
                            ->default(fn ($record) => $record?->student?->academic_level ?? 'N/A')
                            ->disabled(),
                        Forms\Components\TextInput::make('validity')
                            ->label('Vigência da Orientação (IC)')
                            ->default(fn ($record) => $record?->validity ?? 'N/A')
                            ->disabled(),
                        Forms\Components\TextInput::make('scholarship_type')
                            ->label('Tipo de Bolsa (IC)')
                            ->default(fn ($record) => $record?->scholarship_type ?? 'N/A')
                            ->disabled(),
                    ])->columns(['default' => 1, 'md' => 2])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'TCC' => 'primary',
                        'IC' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->label('Título do Projeto / Tema'),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Aluno')
                    ->formatStateUsing(function ($state) {
                        if (auth()->user()->role === 'student') {
                            $parts = explode(' ', trim($state));
                            return count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $state;
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('andamento')
                    ->label('Andamento')
                    ->badge()
                    ->state(fn ($record) => $record->getProgressInfo()['label'])
                    ->color(fn ($record) => $record->getProgressInfo()['color']),
                Tables\Columns\TextColumn::make('validity')
                    ->label('Vigência')
                    ->state(fn ($record) => $record->validity),
                Tables\Columns\TextColumn::make('scholarship_type')
                    ->label('Bolsa')
                    ->state(fn ($record) => $record->scholarship_type)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'TCC' => 'TCC',
                        'IC' => 'Iniciação Científica',
                    ])
                    ->label('Tipo de Projeto'),
                Tables\Filters\Filter::make('parados')
                    ->label('Sem progresso (parados)')
                    ->toggle()
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query) {
                        $studentActionable = \App\Models\DocumentSubmission::STUDENT_ACTIONABLE_STATUSES;

                        return $query->where(function ($q) use ($studentActionable) {
                            $q->whereHas('submissions', function ($sq) use ($studentActionable) {
                                $sq->whereIn('status', $studentActionable)
                                    ->where('updated_at', '<', now()->subDays(15));
                            })->orWhereHas('deadlines', function ($dq) {
                                $dq->where('status', 'pending')->where('type', 'cobranca')->where('due_date', '<', now());
                            });
                        });
                    })
                    ->visible(fn () => auth()->user()?->role === 'admin'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Abrir Arquivo'),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->with(['student.user', 'submissions', 'deadlines']);
        
        if (auth()->user()->role === 'student') {
            return $query->where('student_id', auth()->user()->student?->id);
        }
        
        return $query;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SubmissionsRelationManager::class,
            RelationManagers\DeadlinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicProjects::route('/'),
            'view' => Pages\ViewAcademicProject::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
