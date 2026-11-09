<?php

namespace App\Filament\Resources\Akher;

use App\Filament\Resources\Akher\ListResource\Pages;
use App\Filament\Resources\Akher\ListResource\RelationManagers\ItemsRelationManager;
use App\Models\Akher\GameList;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ListResource extends Resource
{
    protected static ?string $model = GameList::class;

    protected static ?string $slug = 'akher-lists';

    protected static ?string $navigationGroup = 'آخر القائمة';

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $modelLabel = 'قائمة';

    protected static ?string $pluralModelLabel = 'القوائم';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('id')->label('المعرّف (إنجليزي)')->required()->maxLength(40)
                ->regex('/^[a-z0-9_-]+$/')->disabledOn('edit')->helperText('مثل: area أو capitals'),
            Forms\Components\TextInput::make('title_ar')->label('العنوان')->required()->maxLength(120),
            Forms\Components\TextInput::make('extra_label')->label('وحدة المعلومة الإضافية')->placeholder('كم²')->maxLength(60),
            Forms\Components\TextInput::make('unit_hint')->label('نص حقل التخمين')->placeholder('اكتب اسم دولة')->maxLength(60),
            Forms\Components\TextInput::make('source')->label('المصدر')->maxLength(255)->columnSpanFull(),
            Forms\Components\TextInput::make('updated')->label('تاريخ البيانات')->maxLength(20),
            Forms\Components\TextInput::make('sort')->label('الترتيب')->numeric()->default(10),
            Forms\Components\Toggle::make('is_active')->label('متاحة للعب')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\TextColumn::make('title_ar')->label('العنوان')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('id')->label('المعرّف')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('items_count')->label('العناصر')->counts('items'),
                Tables\Columns\TextColumn::make('updated')->label('تاريخ البيانات'),
                Tables\Columns\IconColumn::make('is_active')->label('متاحة')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('export')->label('تصدير JSON')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->action(fn (GameList $record) => response()->streamDownload(
                        fn () => print (json_encode($record->load('items')->toFile(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)),
                        $record->id.'.json',
                        ['Content-Type' => 'application/json; charset=utf-8'],
                    )),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLists::route('/'),
            'create' => Pages\CreateList::route('/create'),
            'edit' => Pages\EditList::route('/{record}/edit'),
        ];
    }
}
