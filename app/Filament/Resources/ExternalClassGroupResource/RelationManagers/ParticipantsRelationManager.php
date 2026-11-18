<?php

namespace App\Filament\Resources\ExternalClassGroupResource\RelationManagers;

use App\Services\RosterImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    protected static ?string $title = 'Alunos da Turma (Planilha Importada)';

    protected static ?string $modelLabel = 'Aluno';

    protected static ?string $pluralModelLabel = 'Alunos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
                Forms\Components\TextInput::make('rgm')->label('RGM')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('rgm')->label('RGM')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable(),
                Tables\Columns\TextColumn::make('submissions_count')
                    ->label('Envios')
                    ->counts('submissions')
                    ->badge(),
            ])
            ->defaultSort('name')
            ->headerActions([
                Tables\Actions\Action::make('importarPlanilha')
                    ->label('Importar Planilha')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('roster_file')
                            ->label('Planilha (xlsx, csv ou txt — separado por vírgula ou ponto e vírgula)')
                            ->required()
                            ->disk('local')
                            ->directory('temp_roster_imports')
                            ->visibility('private'),
                    ])
                    ->action(function (array $data) {
                        $relativePath = is_array($data['roster_file']) ? reset($data['roster_file']) : $data['roster_file'];
                        $absolutePath = Storage::disk('local')->path($relativePath);
                        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

                        $result = app(RosterImportService::class)->import($this->getOwnerRecord(), $absolutePath, $extension);

                        Storage::disk('local')->delete($relativePath);

                        $body = "{$result['imported']} aluno(s) importado(s), {$result['updated']} atualizado(s).";
                        if (!empty($result['skipped'])) {
                            $body .= ' Linhas com problema: ' . implode(' | ', array_slice($result['skipped'], 0, 5));
                        }

                        Notification::make()
                            ->title('Planilha importada')
                            ->body($body)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\CreateAction::make()->label('Adicionar Manualmente'),
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
}
