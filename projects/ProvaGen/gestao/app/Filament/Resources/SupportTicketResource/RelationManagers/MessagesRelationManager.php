<?php

namespace App\Filament\Resources\SupportTicketResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversa';

    protected static ?string $modelLabel = 'Mensagem';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('message')
                ->label('Mensagem')
                ->required()
                ->rows(4)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('De')
                    ->badge()
                    ->color(fn ($record) => $record->user?->role === 'admin' ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Mensagem')
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'asc')
            ->paginated(false)
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Responder')
                    ->visible(fn () => $this->getOwnerRecord()->isOpen())
                    ->using(function (array $data) {
                        abort_unless($this->getOwnerRecord()->isOpen(), 403);

                        $data['user_id'] = auth()->id();

                        return $this->getOwnerRecord()->messages()->create($data);
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
