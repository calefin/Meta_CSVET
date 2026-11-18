<?php

namespace App\Filament\Resources\NecropsyClassSessionResource\Pages;

use App\Filament\Resources\NecropsyClassSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNecropsyClassSession extends EditRecord
{
    protected static string $resource = NecropsyClassSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('is_cancelled', $data)) {
            $wasCancelled = (bool) $this->record->cancelled_at;
            $nowCancelled = (bool) $data['is_cancelled'];

            if ($nowCancelled && !$wasCancelled) {
                $data['cancelled_at'] = now();
            } elseif (!$nowCancelled && $wasCancelled) {
                $data['cancelled_at'] = null;
                $data['cancellation_reason'] = null;
            }

            unset($data['is_cancelled']);
        }

        return $data;
    }
}
