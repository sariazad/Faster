<?php

namespace App\Filament\Resources\Qaddaha\CategoryResource\Pages;

use App\Filament\Resources\Qaddaha\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
