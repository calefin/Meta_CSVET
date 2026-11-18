<?php

namespace App\Filament\Resources\ExternalClassGroupResource\RelationManagers;

use App\Mail\AppNotificationMail;
use App\Models\ExternalDocumentSubmission;
use App\Services\PdfSecurityValidator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Documentos Enviados';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documentos';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_type')
            ->modifyQueryUsing(fn ($query) => $query->with('participant'))
            ->columns([
                Tables\Columns\TextColumn::make('participant.name')
                    ->label('Aluno')
                    ->searchable(),
                Tables\Columns\TextColumn::make('participant.rgm')
                    ->label('RGM'),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Documento')
                    ->badge(),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referência')
                    ->state(fn (ExternalDocumentSubmission $record) => $record->referencePeriodLabel()),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ExternalDocumentSubmission::STATUS_SIGNED => 'success',
                        ExternalDocumentSubmission::STATUS_RETURNED => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('baixarOriginal')
                    ->label('Baixar Original')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (ExternalDocumentSubmission $record) => !empty($record->file_path))
                    ->action(fn (ExternalDocumentSubmission $record) => Storage::disk('local')->download($record->file_path, $record->original_filename ?: 'documento.pdf')),

                Tables\Actions\Action::make('baixarAssinado')
                    ->label('Baixar Assinado')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (ExternalDocumentSubmission $record) => !empty($record->returned_file_path))
                    ->action(fn (ExternalDocumentSubmission $record) => Storage::disk('local')->download($record->returned_file_path, 'assinado_' . $record->id . '.pdf')),

                Tables\Actions\Action::make('devolver')
                    ->label('Devolver')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (ExternalDocumentSubmission $record) => $record->status !== ExternalDocumentSubmission::STATUS_SIGNED)
                    ->form([
                        Forms\Components\Textarea::make('return_reason')
                            ->label('Motivo da devolução')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ExternalDocumentSubmission $record, array $data) {
                        $record->update([
                            'status' => ExternalDocumentSubmission::STATUS_RETURNED,
                            'return_reason' => $data['return_reason'],
                        ]);

                        $this->notifyParticipant(
                            $record,
                            'Documento devolvido para correção',
                            "Olá, {$record->participant->name}!\n\nSeu documento \"{$record->document_type}\" ({$record->referencePeriodLabel()}) foi devolvido pelo professor para correção.\n\nMotivo: {$data['return_reason']}\n\nAcesse o link da turma para reenviar o documento corrigido."
                        );

                        Notification::make()->title('Documento devolvido')->success()->send();
                    }),

                Tables\Actions\Action::make('anexarAssinado')
                    ->label('Anexar PDF Assinado')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn (ExternalDocumentSubmission $record) => $record->status !== ExternalDocumentSubmission::STATUS_SIGNED)
                    ->form([
                        Forms\Components\FileUpload::make('signed_file')
                            ->label('PDF assinado digitalmente (A3/A1)')
                            ->required()
                            ->disk('local')
                            ->directory('temp_signed_external')
                            ->visibility('private'),
                    ])
                    ->action(function (ExternalDocumentSubmission $record, array $data) {
                        $relativePath = is_array($data['signed_file']) ? reset($data['signed_file']) : $data['signed_file'];
                        $content = Storage::disk('local')->get($relativePath);

                        [$isValid, $error] = PdfSecurityValidator::isValidPdf($content);
                        if (!$isValid) {
                            Storage::disk('local')->delete($relativePath);
                            Notification::make()->title('Arquivo rejeitado')->body($error)->danger()->send();
                            return;
                        }

                        if (!PdfSecurityValidator::isDigitallySigned($content)) {
                            Storage::disk('local')->delete($relativePath);
                            Notification::make()
                                ->title('Arquivo sem assinatura digital')
                                ->body('O PDF enviado não contém uma assinatura digital válida. Assine o arquivo localmente (A3/A1) antes de anexar.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $finalPath = 'external_submissions/signed/' . Str::uuid() . '.pdf';
                        Storage::disk('local')->put($finalPath, $content);
                        Storage::disk('local')->delete($relativePath);

                        $metadata = $record->metadata ?? [];
                        $metadata['returned_sha256'] = hash('sha256', $content);
                        $metadata['returned_timestamp'] = now()->timezone('America/Sao_Paulo')->format('d/m/Y - H:i:s');

                        $record->update([
                            'returned_file_path' => $finalPath,
                            'status' => ExternalDocumentSubmission::STATUS_SIGNED,
                            'metadata' => $metadata,
                        ]);

                        $this->notifyParticipant(
                            $record,
                            'Documento assinado disponível',
                            "Olá, {$record->participant->name}!\n\nSeu documento \"{$record->document_type}\" ({$record->referencePeriodLabel()}) foi assinado pelo professor e já está disponível para download no link da sua turma."
                        );

                        Notification::make()->title('PDF assinado anexado com sucesso')->success()->send();
                    }),

                Tables\Actions\Action::make('excluirOriginal')
                    ->label('Excluir Original')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('O arquivo original enviado pelo aluno será apagado permanentemente do servidor. O PDF assinado e o histórico do processo continuam preservados. Esta ação não pode ser desfeita.')
                    ->visible(fn (ExternalDocumentSubmission $record) => $record->status === ExternalDocumentSubmission::STATUS_SIGNED && !empty($record->file_path))
                    ->action(function (ExternalDocumentSubmission $record) {
                        Storage::disk('local')->delete($record->file_path);
                        $record->update(['file_path' => null]);

                        Notification::make()->title('Arquivo original excluído')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('limparOriginaisAssinados')
                    ->label('Limpar Originais Já Assinados')
                    ->icon('heroicon-o-trash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Apaga do servidor o arquivo original de todos os documentos selecionados que já estejam com status Assinado, mantendo apenas o PDF assinado.')
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status === ExternalDocumentSubmission::STATUS_SIGNED && !empty($record->file_path)) {
                                Storage::disk('local')->delete($record->file_path);
                                $record->update(['file_path' => null]);
                                $count++;
                            }
                        }

                        Notification::make()->title("{$count} arquivo(s) original(is) excluído(s)")->success()->send();
                    }),
            ]);
    }

    protected function notifyParticipant(ExternalDocumentSubmission $record, string $title, string $body): void
    {
        try {
            $participant = $record->participant;
            if (!$participant || !$participant->email) {
                return;
            }

            Mail::to($participant->email)->queue(new AppNotificationMail(
                title: $title,
                messageContent: $body,
                actionUrl: $record->group->publicUrl(),
                actionText: 'Acessar Portal da Turma',
                subject: $title,
            ));
        } catch (\Exception $e) {
            Log::error('Erro ao notificar participante externo: ' . $e->getMessage());
        }
    }
}
