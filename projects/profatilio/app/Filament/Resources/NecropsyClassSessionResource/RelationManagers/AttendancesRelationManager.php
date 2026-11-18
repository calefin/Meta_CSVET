<?php

namespace App\Filament\Resources\NecropsyClassSessionResource\RelationManagers;

use App\Models\NecropsyClassParticipant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Presenças Registradas';

    protected static ?string $modelLabel = 'Presença';

    protected static ?string $pluralModelLabel = 'Presenças';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('necropsy_class_participant_id')
                    ->label('Aluno')
                    ->options(fn () => NecropsyClassParticipant::query()->get()->mapWithKeys(
                        fn (NecropsyClassParticipant $p) => [$p->id => "{$p->name} — RGM {$p->rgm}"]
                    ))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
                        Forms\Components\TextInput::make('rgm')->label('RGM')->maxLength(255),
                        Forms\Components\TextInput::make('institution')->label('Instituição')->maxLength(255),
                    ])
                    ->createOptionUsing(fn (array $data) => NecropsyClassParticipant::create($data)->id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('participant.name')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('participant.rgm')
                    ->label('RGM')
                    ->searchable(),
                Tables\Columns\TextColumn::make('participant.institution')
                    ->label('Instituição'),
                Tables\Columns\IconColumn::make('added_manually')
                    ->label('Manual')
                    ->boolean(),
                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Confirmado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('checked_in_at', 'asc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Presença Manualmente')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['checked_in_at'] = now();
                        $data['added_manually'] = true;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
