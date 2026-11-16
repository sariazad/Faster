<?php

namespace App\Filament\Resources\Qaddaha\CategoryResource\Pages;

use App\Filament\Resources\Qaddaha\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
