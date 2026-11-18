<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('course_concluded_toggle', $data)) {
            $wasConcluded = (bool) $this->record->course_concluded_at;
            $nowConcluded = (bool) $data['course_concluded_toggle'];

            if ($nowConcluded && ! $wasConcluded) {
                $data['course_concluded_at'] = now();
            } elseif (! $nowConcluded && $wasConcluded) {
                $data['course_concluded_at'] = null;
            }

            unset($data['course_concluded_toggle']);
        }

        if (! empty($data['reset_semester_survey'])) {
            $data['last_semester_declared'] = null;
        }
        unset($data['reset_semester_survey']);

        return $data;
    }
}
