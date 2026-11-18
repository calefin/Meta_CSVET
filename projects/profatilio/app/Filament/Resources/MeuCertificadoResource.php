<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MeuCertificadoResource\Pages;
use App\Models\CertificateRequest;
use App\Models\Course;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MeuCertificadoResource extends Resource
{
    protected static ?string $model = CertificateRequest::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Meus Certificados';
    protected static ?string $navigationGroup = 'Cursos & Certificados';
    protected static ?int    $navigationSort  = 6;
    protected static ?string $modelLabel      = 'Certificado';
    protected static ?string $pluralModelLabel = 'Meus Certificados';

    // Só aparece no portal (adicionado manualmente ao StudentPanelProvider)
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user || $user->role === 'admin') return false;

        $student = Student::where('user_id', $user->id)->first();
        if (!$student) return false;

        // Alunos externos sempre podem solicitar/ver certificados. Alunos
        // regulares só veem esta área se já tiverem um certificado gerado
        // automaticamente por terem concluído uma trilha de curso.
        return $student->category === 'external' || $student->certificateRequests()->exists();
    }

    // Autosolicitação (botão "Nova Solicitação") só faz sentido para alunos
    // externos — orientandos regulares ganham o certificado automaticamente
    // ao concluir a trilha do curso, nunca solicitam manualmente.
    public static function canCreate(): bool
    {
        $student = Student::where('user_id', auth()->id())->first();
        return $student?->category === 'external';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $student = Student::where('user_id', auth()->id())->first();
        return parent::getEloquentQuery()
            ->where('student_id', $student?->id ?? 0)
            ->with(['course']);
    }

    public static function form(Form $form): Form
    {
        $student = Student::where('user_id', auth()->id())->first();
        $existingCourseIds = $student
            ? CertificateRequest::where('student_id', $student->id)->pluck('course_id')->toArray()
            : [];

        return $form->schema([
            Forms\Components\Section::make('Solicitar Certificado')->schema([
                Forms\Components\Select::make('course_id')
                    ->label('Curso')
                    ->required()
                    ->options(
                        Course::where('active', true)
                            ->whereDoesntHave('modules')
                            ->whereNotIn('id', $existingCourseIds)
                            ->get()
                            ->mapWithKeys(fn ($c) => [
                                $c->id => $c->name . ' (' . $c->workload . 'h — ' . $c->formatted_period . ')'
                            ])
                    )
                    ->searchable()
                    ->columnSpanFull()
                    ->helperText('Selecione o curso que você concluiu para solicitar o certificado.'),

                Forms\Components\Hidden::make('student_id')
                    ->default(fn () => Student::where('user_id', auth()->id())->value('id')),

                Forms\Components\Hidden::make('status')
                    ->default('pending'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('course.name')
                    ->label('Curso')
                    ->wrap(),

                Tables\Columns\TextColumn::make('course.workload')
                    ->label('C.H.')
                    ->suffix('h'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'Aguardando Aprovação',
                        'approved'  => 'Em Assinatura',
                        'available' => 'Disponível para Download',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'approved'  => 'info',
                        'available' => 'success',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Solicitado em')
                    ->dateTime('d/m/Y'),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Baixar Certificado')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => '/portal/certificado/' . $record->id . '/download')
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->status === 'available'),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMeusCertificados::route('/'),
            'create' => Pages\CreateMeuCertificado::route('/create'),
        ];
    }
}
