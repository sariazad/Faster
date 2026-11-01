<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Game;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $modelLabel = 'سؤال';

    protected static ?string $pluralModelLabel = 'الأسئلة';

    protected static ?int $navigationSort = 2;

    public static function pointOptions(): array
    {
        return collect(Game::LEVELS)->mapWithKeys(fn ($p) => [$p => (string) $p])->all();
    }

    /**
     * Shared with the category's questions relation manager.
     */
    public static function questionFields(bool $withCategory = true): array
    {
        return array_values(array_filter([
            $withCategory ? Forms\Components\Select::make('category_id')
                ->label('الفئة')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->required() : null,
            Forms\Components\ToggleButtons::make('points')
                ->label('النقاط')
                ->options(self::pointOptions())
                ->inline()
                ->required()
                ->default(Game::LEVELS[0]),
            Forms\Components\Textarea::make('body')
                ->label('السؤال')
                ->required()
                ->rows(3)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('answer')
                ->label('الإجابة')
                ->required()
                ->maxLength(500)
                ->columnSpanFull(),
            Forms\Components\FileUpload::make('image')
                ->label('صورة (اختياري)')
                ->image()
                ->disk('public')
                ->directory('questions')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')
                ->label('مفعّل')
                ->default(true),
        ]));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->schema(self::questionFields())
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('الفئة')
                    ->sortable(),
                Tables\Columns\TextColumn::make('points')
                    ->label('النقاط')
                    ->badge()
                    ->color(fn (int $state) => match ($state) {
                        200 => 'success',
                        400 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('body')
                    ->label('السؤال')
                    ->limit(70)
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('answer')
                    ->label('الإجابة')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\ImageColumn::make('image')
                    ->label('صورة')
                    ->disk('public')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('مفعّل'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('الفئة')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('points')
                    ->label('النقاط')
                    ->options(self::pointOptions()),
                Tables\Filters\TernaryFilter::make('is_active')->label('مفعّل'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit' => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
