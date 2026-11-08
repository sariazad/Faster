<?php

namespace App\Filament\Resources\AlefYa\AccountResource\Pages;

use App\Filament\Resources\AlefYa\AccountResource;
use App\Models\AlefYa\Account;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageAccounts extends ManageRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')->label('توليد معرّف جديد')->icon('heroicon-o-plus')
                ->action(function () {
                    $code = Account::generate()->code;
                    Notification::make()->title("المعرّف الجديد: {$code}")->success()->persistent()->send();
                }),
        ];
    }
}
