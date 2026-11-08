<?php

namespace App\Filament\Resources\AlefYa;

use App\Filament\Resources\AlefYa\NotificationResource\Pages;
use App\Models\AlefYa\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;

    protected static ?string $slug = 'alefya-notifications';

    protected static ?string $navigationGroup = 'ألف ياء';

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $modelLabel = 'إشعار';

    protected static ?string $pluralModelLabel = 'الإشعارات';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('العنوان')->required()->maxLength(120)->columnSpanFull(),
            Forms\Components\Textarea::make('body')->label('النص')->rows(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العنوان')->weight('bold'),
                Tables\Columns\TextColumn::make('body')->label('النص')->limit(100)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->date('Y-m-d'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNotifications::route('/')];
    }
}
