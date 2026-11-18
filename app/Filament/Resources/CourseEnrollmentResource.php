<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseEnrollmentResource\Pages;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CourseEnrollmentResource extends Resource
{
    protected static ?string $model = CourseEnrollment::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'Matrículas em Cursos';
    protected static ?string $navigationGroup = 'Certificados';
    protected static ?int $navigationSort = 9;
    protected static ?string $modelLabel = 'Matrícula';
    protected static ?string $pluralModelLabel = 'Matrículas em Cursos';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = CourseEnrollment::where('status', 'solicitado')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Matrícula')
                ->schema([
                    Forms\Components\Select::make('course_id')
                        ->label('Curso')
                        ->options(fn () => Course::whereHas('modules')->pluck('name', 'id'))
                        ->helperText('Só aparecem aqui cursos com etapas cadastradas (trilha interativa).')
                        ->required()
                        ->searchable()
                        ->disabled(fn ($record) => $record !== null)
                        ->dehydrated(),
                    Forms\Components\Select::make('student_id')
                        ->label('Aluno')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => \App\Models\Student::query()
                            ->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhere('registration', 'like', "%{$search}%")
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn ($s) => [$s->id => $s->user?->name . ' (' . $s->institution . ')']))
                        ->getOptionLabelUsing(fn ($value) => \App\Models\Student::find($value)?->user?->name)
                        ->required()
                        ->disabled(fn ($record) => $record !== null)
                        ->dehydrated()
                        ->unique(
                            table: 'course_enrollments',
                            column: 'student_id',
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule, Forms\Get $get) => $rule->where('course_id', $get('course_id')),
                        )
                        ->validationMessages([
                            'unique' => 'Este aluno já está matriculado neste curso.',
                        ]),
                    Forms\Components\Hidden::make('enrolled_by')
                        ->default(fn () => auth()->id())
                        ->dehydrated(fn ($record) => $record === null),
                    Forms\Components\Hidden::make('status')
                        ->default('em_andamento')
                        ->dehydrated(fn ($record) => $record === null),
                ])
                ->columns(2),

            Forms\Components\Section::make('Progresso')
                ->visible(fn ($record) => $record !== null)
                ->schema([
                    Forms\Components\Placeholder::make('progress')
                        ->label('Etapas Concluídas')
                        ->content(fn ($record) => $record->completions()->count() . ' de ' . $record->course->modules()->count() . ' (' . $record->progressPercent() . '%)'),
                    Forms\Components\Placeholder::make('current_step')
                        ->label('Etapa Atual')
                        ->content(fn ($record) => $record->currentModule()?->title ?? '— Concluiu todas as etapas —'),
                    Forms\Components\Placeholder::make('completed_at_display')
                        ->label('Concluído em')
                        ->content(fn ($record) => $record->completed_at?->format('d/m/Y H:i') ?? '—'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Histórico de Avaliações (Todas as Tentativas)')
                ->visible(fn ($record) => $record !== null && $record->quizAttempts()->exists())
                ->schema([
                    Forms\Components\Placeholder::make('quiz_attempts_history')
                        ->label('')
                        ->content(function ($record) {
                            $html = '<div class="space-y-1.5">';
                            foreach ($record->quizAttempts as $attempt) {
                                $color = $attempt->passed ? 'text-emerald-400' : 'text-red-400';
                                $status = $attempt->passed ? 'Aprovado' : 'Reprovado';
                                $html .= '<div class="flex items-center justify-between text-xs p-2 rounded-lg bg-slate-900/60 border border-white/5">';
                                $html .= '<span class="text-slate-300">' . e($attempt->module?->title ?? '—') . '</span>';
                                $html .= '<span class="' . $color . ' font-semibold">' . $attempt->score . '% — ' . $status . '</span>';
                                $html .= '<span class="text-slate-500 font-mono">' . $attempt->created_at->format('d/m/Y H:i') . '</span>';
                                $html .= '</div>';
                            }
                            $html .= '</div>';

                            return new \Illuminate\Support\HtmlString($html);
                        }),
                ]),
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['course', 'enrolledBy', 'student.user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('course.name')
                    ->label('Curso')
                    ->wrap()
                    ->sortable(),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progresso')
                    ->state(fn (CourseEnrollment $record) => $record->progressPercent() . '%')
                    ->badge()
                    ->color(fn (CourseEnrollment $record) => $record->progressPercent() >= 100 ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'solicitado' => 'Solicitado',
                        'concluido' => 'Concluído',
                        default => 'Em Andamento',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'solicitado' => 'warning',
                        'concluido' => 'success',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('enrolledBy.name')
                    ->label('Matriculado por')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Matriculado em')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Curso')
                    ->relationship('course', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'solicitado' => 'Solicitado',
                        'em_andamento' => 'Em Andamento',
                        'concluido' => 'Concluído',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('liberarAcesso')
                    ->label('Liberar Acesso')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CourseEnrollment $record) => $record->status === 'solicitado')
                    ->requiresConfirmation()
                    ->modalDescription(fn (CourseEnrollment $record) => 'Liberar o acesso de ' . ($record->student->user->name ?? 'aluno') . ' ao curso "' . $record->course->name . '"?')
                    ->action(function (CourseEnrollment $record) {
                        $record->update(['status' => 'em_andamento', 'enrolled_by' => auth()->id()]);

                        \Filament\Notifications\Notification::make()
                            ->title('Acesso liberado!')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('recusarSolicitacao')
                    ->label('Recusar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (CourseEnrollment $record) => $record->status === 'solicitado')
                    ->requiresConfirmation()
                    ->modalDescription('Isso remove a solicitação. O aluno poderá solicitar novamente depois.')
                    ->action(fn (CourseEnrollment $record) => $record->delete()),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseEnrollments::route('/'),
            'create' => Pages\CreateCourseEnrollment::route('/create'),
            'edit' => Pages\EditCourseEnrollment::route('/{record}/edit'),
        ];
    }
}
