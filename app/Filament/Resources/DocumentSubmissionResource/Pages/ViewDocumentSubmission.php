<?php

namespace App\Filament\Resources\DocumentSubmissionResource\Pages;

use App\Filament\Resources\DocumentSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentSubmission extends ViewRecord
{
    protected static string $resource = DocumentSubmissionResource::class;

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
        ];
    }
}
