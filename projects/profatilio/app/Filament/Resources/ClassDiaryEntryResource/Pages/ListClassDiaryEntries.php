<?php

namespace App\Filament\Resources\ClassDiaryEntryResource\Pages;

use App\Filament\Resources\ClassDiaryEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListClassDiaryEntries extends ListRecords
{
    protected static string $resource = ClassDiaryEntryResource::class;
}
