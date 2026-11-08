<?php

namespace App\Filament\Resources\AlefYa;

use App\Filament\Resources\AlefYa\AccountResource\Pages;
use App\Models\AlefYa\Account;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $slug = 'alefya-accounts';

    protected static ?string $navigationGroup = 'ألف ياء';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $modelLabel = 'معرّف دخول';

    protected static ?string $pluralModelLabel = 'معرّفات الدخول';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('المعرّف')->copyable()->searchable()->fontFamily('mono'),
                Tables\Columns\IconColumn::make('pin')->label('فعّل الرقم السري')->boolean()->getStateUsing(fn (Account $a) => $a->getRawOriginal('pin') !== null),
                Tables\Columns\TextColumn::make('scores_count')->label('نتائج التحدي')->counts('scores'),
                Tables\Columns\TextColumn::make('last_login_at')->label('آخر دخول')->since()->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('أُنشئ')->date('Y-m-d'),
            ])
            ->actions([
                Tables\Actions\Action::make('resetPin')->label('مسح الرقم السري')->icon('heroicon-o-arrow-path')->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('اللاعب بيختار رقم سري جديد أول ما يدخل.')
                    ->action(fn (Account $a) => $a->forceFill(['pin' => null])->save()),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAccounts::route('/')];
    }
}
