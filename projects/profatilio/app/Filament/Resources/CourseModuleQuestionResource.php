<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseModuleQuestionResource\Pages;
use App\Models\CourseModuleQuestion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CourseModuleQuestionResource extends Resource
{
    protected static ?string $model = CourseModuleQuestion::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Dúvidas dos Cursos';
    protected static ?string $navigationGroup = 'Certificados';
    protected static ?int $navigationSort = 9;
    protected static ?string $modelLabel = 'Dúvida';
    protected static ?string $pluralModelLabel = 'Dúvidas dos Cursos';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = CourseModuleQuestion::whereNull('answer')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dúvida')
                ->schema([
                    Forms\Components\Placeholder::make('student_name')
                        ->label('Aluno')
                        ->content(fn ($record) => $record?->student?->user?->name ?? '—'),
                    Forms\Components\Placeholder::make('module_context')
                        ->label('Curso / Etapa')
                        ->content(fn ($record) => ($record?->module?->course?->name ?? '—') . ' — ' . ($record?->module?->title ?? '—')),
                    Forms\Components\Placeholder::make('question_text')
                        ->label('Pergunta')
                        ->content(fn ($record) => $record?->question ?? '—')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('answer')
                        ->label('Sua Resposta')
                        ->rows(5)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['module.course', 'student.user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('module.course.name')
                    ->label('Curso')
                    ->wrap(),
                Tables\Columns\TextColumn::make('module.title')
                    ->label('Etapa')
                    ->wrap(),
                Tables\Columns\TextColumn::make('question')
                    ->label('Pergunta')
                    ->limit(60)
                    ->wrap(),
                Tables\Columns\IconColumn::make('answer')
                    ->label('Respondida')
                    ->getStateUsing(fn (CourseModuleQuestion $record) => $record->isAnswered())
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Perguntado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('answer')
                    ->label('Respondida')
                    ->nullable()
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('answer'),
                        false: fn ($query) => $query->whereNull('answer'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('responder')
                    ->label('Responder')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->form([
                        Forms\Components\Placeholder::make('question_display')
                            ->label('Pergunta')
                            ->content(fn ($record) => $record->question),
                        Forms\Components\Textarea::make('answer')
                            ->label('Resposta')
                            ->required()
                            ->rows(5)
                            ->default(fn ($record) => $record->answer),
                    ])
                    ->action(function (CourseModuleQuestion $record, array $data) {
                        $record->update([
                            'answer' => $data['answer'],
                            'answered_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Resposta enviada!')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseModuleQuestions::route('/'),
        ];
    }
}
