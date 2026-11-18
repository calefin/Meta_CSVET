<?php

namespace App\Filament\Resources\SystematicReviewResource\RelationManagers;

use App\Models\SrMeeting;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Atas curtas das reuniões quinzenais (decisões, pendências, responsáveis, prazos). */
class MeetingsRelationManager extends RelationManager
{
    protected static string $relationship = 'meetings';

    protected static ?string $title = 'Atas de reunião';

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function schema(): array
    {
        return [
            Forms\Components\DateTimePicker::make('held_at')->label('Data da reunião')->required()->seconds(false)->default(now()),
            Forms\Components\TextInput::make('participants')->label('Participantes')->maxLength(500),
            Forms\Components\Textarea::make('decisions')->label('Decisões')->required()->rows(3)->columnSpanFull(),
            Forms\Components\Textarea::make('pending')->label('Pendências (responsável e prazo)')->rows(3)->columnSpanFull(),
            Forms\Components\DatePicker::make('next_at')->label('Próxima reunião')->default(now()->addWeeks(2)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Reunião de 30 minutos a cada 15 dias. Decisões que mudam o método viram desvio ou emenda do protocolo.')
            ->columns([
                Tables\Columns\TextColumn::make('held_at')->label('Data')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('decisions')->label('Decisões')->wrap()->limit(120),
                Tables\Columns\TextColumn::make('next_at')->label('Próxima')->date('d/m/Y')->placeholder('—'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Nova ata')->form($this->schema())])
            ->actions([Tables\Actions\EditAction::make()->form($this->schema()), Tables\Actions\DeleteAction::make()]);
    }
}
