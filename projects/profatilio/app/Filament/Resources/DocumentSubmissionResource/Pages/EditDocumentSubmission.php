<?php

namespace App\Filament\Resources\DocumentSubmissionResource\Pages;

use App\Filament\Resources\DocumentSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentSubmission extends EditRecord
{
    protected static string $resource = DocumentSubmissionResource::class;

    public bool $isPublishing = false;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_assinado')
                ->label('Baixar Assinado (PDF)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn () => route('documento.download', ['id' => $this->record->id, 'type' => 'returned']))
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->record->google_drive_returned_file_id)),
            DocumentSubmissionResource::getReadequarRotaPageAction(),
            Actions\Action::make('timeline')
                ->label('Ver Linha do Tempo')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->url(fn () => route('documento.timeline', ['id' => $this->record->id]))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        // Para o professor toda gravação publica e notifica (ver getFormActions)
        $this->record->is_draft_save = auth()->user()->role === 'student' ? !$this->isPublishing : false;
    }

    protected function getRedirectUrl(): ?string
    {
        return auth()->user()->role === 'student' ? null : $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        if (auth()->user()->role === 'student') {
            return parent::getSavedNotification();
        }

        return \Filament\Notifications\Notification::make()
            ->title('Despacho Publicado')
            ->success()
            ->body('O despacho foi publicado com sucesso e o aluno foi notificado.');
    }

    protected function getFormActions(): array
    {
        if (auth()->user()->role === 'student') {
            return [
                $this->getSaveFormAction()
                    ->label('Salvar Rascunho')
                    ->color('gray'),
                
                Actions\Action::make('enviar_correcoes')
                    ->label('Enviar Correções / Comentários')
                    ->color('success')
                    ->icon('heroicon-o-paper-airplane')
                    ->action('enviarCorrecoes'),
                    
                $this->getCancelFormAction(),
            ];
        }

        return [
            // Antes havia um segundo botão "Salvar Rascunho" que gravava a edição sem
            // notificar o aluno (marcava is_draft_save=true) — o professor clicava nele
            // por ser o botão padrão/mais à esquerda, achando que só estava salvando o
            // status/parecer, e o aluno nunca era avisado por e-mail/Telegram da mudança.
            // Para o professor não existe "rascunho": toda edição salva aqui já é uma
            // decisão final sobre o processo, então há um único botão que sempre publica
            // e notifica — igual ao que a ação rápida "Publicar Despacho" da listagem já
            // fazia. Corrigido em 2026-09-14.
            //
            // É o botão de submit padrão do formulário (não uma Action com ->action()):
            // só botões de submit ficam travados enquanto um upload está em andamento.
            // Com a Action avulsa, clicar antes de o PDF de devolução terminar de subir
            // salvava e notificava o aluno SEM o arquivo, sem erro nenhum (caso
            // CONCL-TCC-2026-0008, 2026-10-02).
            $this->getSaveFormAction()
                ->label('Salvar e Notificar Aluno')
                ->color('success')
                ->icon('heroicon-o-paper-airplane'),

            $this->getCancelFormAction(),
        ];
    }

    public function publishDispatch()
    {
        $this->isPublishing = true;
        $this->record->is_draft_save = false;
        
        $this->save(shouldRedirect: false);
        
        \Filament\Notifications\Notification::make()
            ->title('Despacho Publicado')
            ->success()
            ->body('O despacho foi publicado com sucesso e o aluno foi notificado.')
            ->send();
            
        $this->redirect($this->getResource()::getUrl('index'));
    }

    public function enviarCorrecoes()
    {
        $this->isPublishing = true;
        $this->record->is_draft_save = false;
        
        $this->save(shouldRedirect: false);
        
        \Filament\Notifications\Notification::make()
            ->title('Correções Enviadas')
            ->success()
            ->body('Suas correções e observações foram enviadas ao professor orientador com sucesso.')
            ->send();
            
        $this->redirect($this->getResource()::getUrl('index'));
    }
}
