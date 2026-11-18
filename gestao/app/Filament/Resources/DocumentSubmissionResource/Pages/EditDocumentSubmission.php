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
        $this->record->is_draft_save = !$this->isPublishing;
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
            $this->getSaveFormAction()
                ->label('Salvar Rascunho')
                ->color('gray'),
            
            Actions\Action::make('publish_dispatch')
                ->label('Publicar Despacho')
                ->color('success')
                ->icon('heroicon-o-paper-airplane')
                ->action('publishDispatch'),
                
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
