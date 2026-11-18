<?php

namespace App\Filament\Resources\SystematicReviewResource\RelationManagers;

use App\Models\SrAuthorContact;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Pedidos de dados aos autores: envio, lembretes (2 e 4 semanas) e resposta. Alimenta "Results of contacting authors". */
class AuthorContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'authorContacts';

    protected static ?string $title = 'Contatos com autores';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->extractionOpen();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function schema(): array
    {
        return [
            Forms\Components\Select::make('sr_study_id')->label('Estudo')->native(false)
                ->options(fn () => $this->getOwnerRecord()->activeStudies()->get()->mapWithKeys(fn ($s) => [$s->id => $s->code() . ' — ' . $s->label])->all()),
            Forms\Components\TextInput::make('author')->label('Autor correspondente')->required()->maxLength(255),
            Forms\Components\TextInput::make('email')->label('E-mail')->email()->maxLength(255),
            Forms\Components\Textarea::make('request')->label('O que foi pedido')->required()->rows(2)->columnSpanFull(),
            Forms\Components\DatePicker::make('sent_at')->label('Enviado em')->required()->default(now()),
            Forms\Components\DatePicker::make('reminder1_at')->label('Lembrete 1 (2 semanas)'),
            Forms\Components\DatePicker::make('reminder2_at')->label('Lembrete 2 (4 semanas)'),
            Forms\Components\DatePicker::make('responded_at')->label('Respondeu em'),
            Forms\Components\Textarea::make('response')->label('Resposta / dados recebidos')->rows(2)->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('author')->label('Autor')->description(fn (SrAuthorContact $c) => $c->study?->code() . ' · ' . $c->email),
                Tables\Columns\TextColumn::make('sent_at')->label('Enviado')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('status')->label('Situação')
                    ->state(fn (SrAuthorContact $c) => $c->responded_at ? 'Respondeu ' . $c->responded_at->format('d/m/Y') : ($c->nextReminder() ?? 'Aguardando'))
                    ->badge()->color(fn (SrAuthorContact $c) => $c->responded_at ? 'success' : ($c->nextReminder() ? 'danger' : 'warning')),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Registrar pedido')->form($this->schema())])
            ->actions([Tables\Actions\EditAction::make()->label('Atualizar')->form($this->schema()), Tables\Actions\DeleteAction::make()]);
    }
}
