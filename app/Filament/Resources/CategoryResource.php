<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers\QuestionsRelationManager;
use App\Models\Category;
use App\Models\Game;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $modelLabel = 'فئة';

    protected static ?string $pluralModelLabel = 'الفئات';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->label('اسم الفئة')
                    ->required()
                    ->maxLength(60),
                Forms\Components\TextInput::make('icon')
                    ->label('أيقونة (إيموجي)')
                    ->placeholder('⚽')
                    ->maxLength(16),
                Forms\Components\FileUpload::make('image')
                    ->label('صورة الفئة')
                    ->image()
                    ->disk('public')
                    ->directory('categories')
                    ->imageEditor()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->label('وصف قصير')
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('مفعّلة')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withLevelCounts()->withCount('questions'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->square()
                    ->size(40),
                Tables\Columns\TextColumn::make('icon')
                    ->label(''),
                Tables\Columns\TextColumn::make('name')
                    ->label('الفئة')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('questions_count')
                    ->label('الأسئلة')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('levels')
                    ->label('٢٠٠ / ٤٠٠ / ٦٠٠')
                    ->state(fn (Category $record) => collect(Game::LEVELS)
                        ->map(fn ($p) => $record->{"q{$p}_count"})
                        ->implode(' / ')),
                Tables\Columns\IconColumn::make('playable')
                    ->label('جاهزة للعب')
                    ->boolean()
                    ->state(fn (Category $record) => $record->hasEnoughQuestions())
                    ->tooltip('تحتاج '.Game::QUESTIONS_PER_LEVEL.' أسئلة مفعّلة على الأقل لكل مستوى'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('مفعّلة'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('مفعّلة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            QuestionsRelationManager::class,
        ];
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
