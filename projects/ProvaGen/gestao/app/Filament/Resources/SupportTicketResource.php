<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Filament\Resources\SupportTicketResource\RelationManagers;
use App\Models\DocumentSubmission;
use App\Models\Student;
use App\Models\SupportTicket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $modelLabel = 'Chamado';
    protected static ?string $pluralModelLabel = 'Central de Dúvidas';

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->role === 'student' ? 'Fale com o Professor' : 'Central de Dúvidas';
    }

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->role === 'student' ? 'Minha Conta' : 'Comunicações';
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->role === 'student';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['student.user', 'documentSubmission']);

        if (auth()->user()?->role === 'student') {
            $student = Student::where('user_id', auth()->id())->first();
            $query->where('student_id', $student?->id ?? 0);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $userId = auth()->id();

        $query = SupportTicket::where('status', 'open')
            ->whereHas('messages', fn (Builder $q) => $q->whereNull('read_at')->where('user_id', '!=', $userId));

        if (auth()->user()?->role === 'student') {
            $student = Student::where('user_id', $userId)->first();
            $query->where('student_id', $student?->id ?? 0);
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected static function submissionOptions(?SupportTicket $record): array
    {
        $studentId = $record?->student_id ?? Student::where('user_id', auth()->id())->value('id');

        if (!$studentId) {
            return [];
        }

        return DocumentSubmission::where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (DocumentSubmission $submission) => !$submission->isConcluded())
            ->mapWithKeys(fn (DocumentSubmission $submission) => [
                $submission->id => "{$submission->document_type} — {$submission->title} ({$submission->process_id})",
            ])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Chamado')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->label('Assunto')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),

                    Forms\Components\Select::make('document_submission_id')
                        ->label('Processo / TCC em Andamento (opcional)')
                        ->options(fn (?SupportTicket $record) => static::submissionOptions($record))
                        ->searchable()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('first_message')
                        ->label('Sua Mensagem')
                        ->required()
                        ->rows(5)
                        ->columnSpanFull()
                        ->visible(fn (?SupportTicket $record) => $record === null),

                    Forms\Components\Placeholder::make('status_display')
                        ->label('Status')
                        ->content(fn (?SupportTicket $record) => $record?->isOpen() ? 'Aberto' : 'Encerrado')
                        ->visible(fn (?SupportTicket $record) => $record !== null),

                    Forms\Components\Placeholder::make('aluno_info')
                        ->label('Aluno')
                        ->content(fn (?SupportTicket $record) => $record
                            ? trim(($record->student?->user?->name ?? '—') . ' — RGM ' . ($record->student?->registration ?? '—') . ' — ' . ($record->student?->institution ?? '—') . ' (Campus ' . ($record->student?->campus ?? '—') . ')')
                            : '—')
                        ->visible(fn (?SupportTicket $record) => $record !== null && auth()->user()?->role === 'admin'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->searchable()
                    ->visible(fn () => auth()->user()?->role === 'admin'),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Assunto')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('documentSubmission.title')
                    ->label('Processo Vinculado')
                    ->default('—')
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'open' ? 'Aberto' : 'Encerrado')
                    ->color(fn (string $state) => $state === 'open' ? 'success' : 'gray'),

                Tables\Columns\IconColumn::make('unread')
                    ->label('Não Lida')
                    ->getStateUsing(fn (SupportTicket $record) => $record->messages()
                        ->whereNull('read_at')
                        ->where('user_id', '!=', auth()->id())
                        ->exists())
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_message_at')
                    ->label('Última Mensagem')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Aberto',
                        'closed' => 'Encerrado',
                    ]),
            ])
            ->recordUrl(fn (SupportTicket $record) => static::getUrl('view', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('Nenhum chamado ainda')
            ->emptyStateDescription(
                auth()->user()?->role === 'student'
                    ? 'Ficou com alguma dúvida? Abra um chamado para falar diretamente com o Prof. Atilio.'
                    : 'Nenhum aluno abriu um chamado ainda.'
            );
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'create' => Pages\CreateSupportTicket::route('/create'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }
}
