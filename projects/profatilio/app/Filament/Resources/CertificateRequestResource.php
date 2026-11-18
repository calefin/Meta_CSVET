<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CertificateRequestResource\Pages;
use App\Models\CertificateRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CertificateRequestResource extends Resource
{
    protected static ?string $model = CertificateRequest::class;
    protected static ?string $navigationIcon  = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Solicitações de Certificado';
    protected static ?string $navigationGroup = 'Certificados';
    protected static ?int    $navigationSort  = 11;
    protected static ?string $modelLabel      = 'Solicitação';
    protected static ?string $pluralModelLabel = 'Solicitações de Certificado';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados')->schema([
                Forms\Components\Placeholder::make('student_name')
                    ->label('Aluno')
                    ->content(fn ($record) => $record?->student?->user?->name ?? '—'),

                Forms\Components\Placeholder::make('student_cpf')
                    ->label('CPF')
                    ->content(fn ($record) => $record?->student?->cpf ?? '—'),

                Forms\Components\Placeholder::make('course_name')
                    ->label('Curso')
                    ->content(fn ($record) => $record?->course?->name ?? '—'),

                Forms\Components\Placeholder::make('workload')
                    ->label('Carga Horária')
                    ->content(fn ($record) => $record?->course ? $record->course->workload . 'h' : '—'),

                Forms\Components\Placeholder::make('period')
                    ->label('Período')
                    ->content(fn ($record) => $record?->course ? $record->course->formatted_period : '—'),

                Forms\Components\Placeholder::make('status_label')
                    ->label('Status')
                    ->content(fn ($record) => $record?->status_label ?? '—'),

                Forms\Components\Textarea::make('admin_notes')
                    ->label('Observações Internas')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(['default' => 1, 'md' => 2, 'lg' => 3]),
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['course', 'student.user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('student.cpf')
                    ->label('CPF')
                    ->searchable(),

                Tables\Columns\TextColumn::make('course.name')
                    ->label('Curso')
                    ->wrap()
                    ->sortable(),

                Tables\Columns\TextColumn::make('course.workload')
                    ->label('C.H.')
                    ->suffix('h'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'Aguardando',
                        'approved'  => 'Aprovado',
                        'available' => 'Disponível',
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
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Aguardando',
                        'approved'  => 'Aprovado',
                        'available' => 'Disponível',
                    ]),
            ])
            ->actions([
                // Aprovar e gerar PDF rascunho para download
                Tables\Actions\Action::make('approve_download')
                    ->label('Aprovar & Baixar Rascunho')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        if (!$record->cert_hash) {
                            $record->cert_hash = hash('sha256', 'cert-' . $record->id . '-' . Str::random(32));
                        }
                        $record->status = 'approved';
                        $record->approved_at = now();
                        $record->save();

                        return redirect('/admin/certificado-curso/' . $record->id . '/pdf-rascunho');
                    }),

                // Baixar PDF rascunho novamente (já aprovado)
                Tables\Actions\Action::make('download_draft')
                    ->label('Baixar Rascunho')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn ($record) => '/admin/certificado-curso/' . $record->id . '/pdf-rascunho')
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->status === 'approved'),

                // Upload do PDF assinado pelo admin
                Tables\Actions\Action::make('upload_signed')
                    ->label('Enviar Assinado')
                    ->icon('heroicon-o-paper-clip')
                    ->color('primary')
                    ->visible(fn ($record) => $record->status === 'approved')
                    ->form([
                        Forms\Components\FileUpload::make('signed_pdf')
                            ->label('PDF Assinado com e-CPF')
                            ->required()
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('local')
                            ->directory('certificates/signed')
                            ->maxSize(20480),
                    ])
                    ->action(function ($record, array $data) {
                        $record->signed_pdf_path = $data['signed_pdf'];
                        $record->status = 'available';
                        $record->available_at = now();
                        $record->save();

                        Notification::make()
                            ->title('Certificado disponibilizado!')
                            ->body('O aluno ' . $record->student?->user?->name . ' já pode baixar o certificado assinado.')
                            ->success()
                            ->send();
                    }),

                // Admin baixa o PDF assinado que ele mesmo fez upload
                Tables\Actions\Action::make('download_signed')
                    ->label('Baixar Assinado')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'available' && $record->signed_pdf_path)
                    ->action(function ($record) {
                        $path = storage_path('app/' . $record->signed_pdf_path);
                        if (!file_exists($path)) {
                            Notification::make()->title('Arquivo não encontrado.')->danger()->send();
                            return;
                        }
                        $name = \Illuminate\Support\Str::slug($record->student?->user?->name ?? 'aluno');
                        return response()->download($path, "certificado_assinado_{$name}.pdf");
                    }),

                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCertificateRequests::route('/'),
            'view'  => Pages\ViewCertificateRequest::route('/{record}'),
        ];
    }
}
