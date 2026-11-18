<?php

namespace App\Filament\Resources\NecropsyClassSessionResource\Pages;

use App\Filament\Resources\NecropsyClassSessionResource;
use Filament\Forms\Form;
use Filament\Resources\Pages\ViewRecord;

class ViewNecropsyClassSession extends ViewRecord
{
    protected static string $resource = NecropsyClassSessionResource::class;

    public function form(Form $form): Form
    {
        return NecropsyClassSessionResource::form($form)->disabled();
    }
}
