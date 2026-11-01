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

    private static function imageUpload(string $field, string $label): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($field)
            ->label($label)
            ->image()
            ->imageEditor()
            ->disk('public')
            ->directory('questions')
            ->maxSize(10240);
    }

    private static function audioUpload(string $field, string $label): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($field)
            ->label($label)
            ->acceptedFileTypes(['audio/*'])
            ->disk('public')
            ->directory('questions/audio')
            ->maxSize(20480)
            ->helperText('mp3 أو m4a أو wav — حتى ٢٠ ميغا');
    }

    /**
     * Shared with the category's questions relation manager.
     */
    public static function questionFields(bool $withCategory = true): array
    {
        return [
            Forms\Components\Grid::make(2)
                ->schema(array_values(array_filter([
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
                ])))
                ->columnSpanFull(),

            Forms\Components\Section::make('السؤال')
                ->description('النص مطلوب، والصورة والصوت اختياريين: مثلاً «مين صاحب هالصوت؟» مع ملف صوتي.')
                ->icon('heroicon-o-question-mark-circle')
                ->schema([
                    Forms\Components\Textarea::make('body')
                        ->label('نص السؤال')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                    self::imageUpload('image', 'صورة السؤال'),
                    self::audioUpload('audio', 'صوت السؤال'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Forms\Components\Section::make('الإجابة')
                ->description('تنعرض بعد ما يضغط المقدّم «إظهار الإجابة». لازم يكون فيها نص أو صورة أو صوت.')
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Forms\Components\TextInput::make('answer')
                        ->label('نص الإجابة')
                        ->maxLength(500)
                        ->requiredWithoutAll(['answer_image', 'answer_audio'])
                        ->validationMessages(['required_without_all' => 'اكتب الإجابة أو أضف صورة أو صوت.'])
                        ->columnSpanFull(),
                    self::imageUpload('answer_image', 'صورة الإجابة'),
                    self::audioUpload('answer_audio', 'صوت الإجابة'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Forms\Components\Toggle::make('is_active')
                ->label('مفعّل')
                ->default(true),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(self::questionFields())->columns(2);
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
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('media')
                    ->label('وسائط')
                    ->state(fn (Question $record) => implode(' ', array_filter([
                        $record->image ? '🖼️' : null,
                        $record->audio ? '🔊' : null,
                        $record->hasAnswerMedia() ? '✅'.($record->answer_image ? '🖼️' : '').($record->answer_audio ? '🔊' : '') : null,
                    ])) ?: '—')
                    ->tooltip('🖼️ صورة · 🔊 صوت · ✅ وسائط بالإجابة'),
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
