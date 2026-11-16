<?php

namespace App\Filament\Resources\Qaddaha;

use App\Filament\Resources\Qaddaha\CategoryResource\Pages;
use App\Models\Qaddaha\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $slug = 'qaddaha-categories';

    protected static ?string $navigationGroup = 'قدّها؟';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $modelLabel = 'فئة';

    protected static ?string $pluralModelLabel = 'الفئات';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(80),
            Forms\Components\TextInput::make('slug')->label('المعرّف (إنجليزي)')->required()->maxLength(40)
                ->regex('/^[a-z0-9_-]+$/')->unique(ignoreRecord: true)->helperText('مثل: food أو sport'),
            Forms\Components\TextInput::make('icon')->label('أيقونة (إيموجي)')->maxLength(16),
            Forms\Components\TextInput::make('sort')->label('الترتيب')->numeric()->default(50),
            Forms\Components\Toggle::make('is_active')->label('متاحة للعب')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($q) => $q->withLevelCounts())
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label(''),
                Tables\Columns\TextColumn::make('name')->label('الفئة')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('q200_count')->label('200')->badge()->color(fn ($state) => $state >= Category::MIN_PER_LEVEL ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('q400_count')->label('400')->badge()->color(fn ($state) => $state >= Category::MIN_PER_LEVEL ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('q600_count')->label('600')->badge()->color(fn ($state) => $state >= Category::MIN_PER_LEVEL ? 'success' : 'danger'),
                Tables\Columns\IconColumn::make('is_active')->label('متاحة')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('questions')->label('الأسئلة')->icon('heroicon-o-queue-list')->color('gray')
                    ->url(fn (Category $record) => QuestionResource::getUrl('index', ['tableFilters' => ['category_id' => ['value' => $record->id]]])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
