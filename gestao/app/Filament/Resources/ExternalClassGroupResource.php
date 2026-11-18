<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExternalClassGroupResource\Pages;
use App\Filament\Resources\ExternalClassGroupResource\RelationManagers;
use App\Filament\Support\RosterImportWizardSteps;
use App\Models\ExternalClassGroup;
use App\Services\RosterImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ExternalClassGroupResource extends Resource
{
    protected static ?string $model = ExternalClassGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Turmas de Estágio';

    protected static ?string $modelLabel = 'Turma de Estágio';

    protected static ?string $pluralModelLabel = 'Turmas de Estágio';

    protected static ?string $navigationGroup = 'Assinatura Externa';

    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Turma')
                    ->schema([
                        Forms\Components\TextInput::make('university_name')
                            ->label('Universidade')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('group_name')
                            ->label('Nome da Turma/Grupo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Medicina Veterinária — Turma A'),
                        Forms\Components\TextInput::make('course')
                            ->label('Curso')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('semester')
                            ->label('Semestre')
                            ->maxLength(50)
                            ->placeholder('Ex: 8º'),
                        Forms\Components\TextInput::make('year')
                            ->label('Ano')
                            ->numeric()
                            ->required()
                            ->default(now()->year),
                        Forms\Components\Toggle::make('active')
                            ->label('Turma ativa (link público habilitado)')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group_name')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('university_name')
                    ->label('Universidade')
                    ->searchable(),
                Tables\Columns\TextColumn::make('course')
                    ->label('Curso')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('year')
                    ->label('Ano')
                    ->sortable(),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Alunos')
                    ->counts('participants')
                    ->badge(),
                Tables\Columns\TextColumn::make('submissions_count')
                    ->label('Envios')
                    ->counts('submissions')
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('token')
                    ->label('Link Público')
                    ->formatStateUsing(fn (ExternalClassGroup $record) => $record->publicUrl())
                    ->copyable()
                    ->copyMessage('Link copiado!')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('importarPlanilha')
                    ->label('Nova Turma + Importar Planilha')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalWidth('2xl')
                    ->steps([
                        Forms\Components\Wizard\Step::make('Dados da Turma')
                            ->schema([
                                Forms\Components\TextInput::make('university_name')->label('Universidade')->required(),
                                Forms\Components\TextInput::make('group_name')->label('Nome da Turma/Grupo')->required(),
                                Forms\Components\TextInput::make('course')->label('Curso'),
                                Forms\Components\TextInput::make('semester')->label('Semestre'),
                                Forms\Components\TextInput::make('year')->label('Ano')->numeric()->required()->default(now()->year),
                            ])->columns(2),
                        RosterImportWizardSteps::uploadStep(),
                        RosterImportWizardSteps::previewStep(null),
                    ])
                    ->action(function (array $data) {
                        $group = ExternalClassGroup::create([
                            'university_name' => $data['university_name'],
                            'group_name' => $data['group_name'],
                            'course' => $data['course'] ?? null,
                            'semester' => $data['semester'] ?? null,
                            'year' => $data['year'],
                            'created_by' => auth()->id(),
                        ]);

                        $relativePath = is_array($data['roster_file']) ? reset($data['roster_file']) : $data['roster_file'];
                        $absolutePath = Storage::disk('local')->path($relativePath);
                        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

                        $service = app(RosterImportService::class);
                        $analysis = $service->analyze($group, $absolutePath, $extension);
                        $result = $service->commit($group, $analysis['rows']);

                        Storage::disk('local')->delete($relativePath);

                        $body = "{$result['imported']} aluno(s) importado(s), {$result['updated']} atualizado(s).";
                        if ($analysis['errorCount'] > 0) {
                            $body .= " {$analysis['errorCount']} linha(s) com erro não foram importadas (veja a pré-visualização que você conferiu).";
                        }

                        Notification::make()
                            ->title('Turma criada e planilha importada')
                            ->body($body)
                            ->success()
                            ->send();
                    }),
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
        return [
            RelationManagers\ParticipantsRelationManager::class,
            RelationManagers\SubmissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExternalClassGroups::route('/'),
            'create' => Pages\CreateExternalClassGroup::route('/create'),
            'edit' => Pages\EditExternalClassGroup::route('/{record}/edit'),
        ];
    }
}
