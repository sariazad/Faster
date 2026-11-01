<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getRedirectUrl(): string
    {
        // Go straight to the edit page so questions can be added below.
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
