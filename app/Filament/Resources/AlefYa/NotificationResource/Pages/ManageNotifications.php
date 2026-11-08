<?php

namespace App\Filament\Resources\AlefYa\NotificationResource\Pages;

use App\Filament\Resources\AlefYa\NotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageNotifications extends ManageRecords
{
    protected static string $resource = NotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
