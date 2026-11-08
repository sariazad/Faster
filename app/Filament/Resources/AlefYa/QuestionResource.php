<?php

namespace App\Filament\Resources\AlefYa;

use App\Filament\Resources\AlefYa\QuestionResource\Pages;
use App\Models\AlefYa\Question;
use App\Support\AlefYaLetters;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $slug = 'alefya-questions';

    protected static ?string $navigationGroup = 'ألف ياء';

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $modelLabel = 'سؤال حروف';

    protected static ?string $pluralModelLabel = 'أسئلة الحروف';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $letters = array_combine(AlefYaLetters::ALL, AlefYaLetters::ALL);

        return $form->schema([
            Forms\Components\Textarea::make('question')->label('السؤال')->required()->rows(3)->columnSpanFull(),
            Forms\Components\TextInput::make('answer')->label('الإجابة')->required()->maxLength(120)
                ->helperText('الحرف بيتحدد تلقائياً من أول حرف بالإجابة (بتجاهل «ال»).')
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Forms\Set $set) => ($l = AlefYaLetters::of($state)) ? $set('letter', $l) : null),
            Forms\Components\Select::make('letter')->label('الحرف')->options($letters)->required()->native(false),
            Forms\Components\TagsInput::make('options')->label('خيارات التحدي الفردي')
                ->helperText('4 خيارات كلها بتبدأ بنفس الحرف (والإجابة الصحيحة منهم). إذا تركتها فاضية بتنعمل تلقائياً.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('category')->label('التصنيف')->maxLength(60),
            Forms\Components\TextInput::make('contributor')->label('مساهمة من')->maxLength(60),
            Forms\Components\Toggle::make('is_active')->label('فعّال')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('letter')->label('الحرف')->badge()->color('warning')->sortable(),
                Tables\Columns\TextColumn::make('question')->label('السؤال')->limit(70)->wrap()->searchable(),
                Tables\Columns\TextColumn::make('answer')->label('الإجابة')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('category')->label('التصنيف')->toggleable()->sortable(),
                Tables\Columns\TextColumn::make('contributor')->label('مساهمة من')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('times_used')->label('مرات الطرح')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('rating')->label('التقييم (سهل/متوسط/صعب)')
                    ->state(fn (Question $q) => "{$q->rated_easy} / {$q->rated_medium} / {$q->rated_hard}")->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->label('فعّال')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('letter')->label('الحرف')->options(array_combine(AlefYaLetters::ALL, AlefYaLetters::ALL)),
                Tables\Filters\TernaryFilter::make('is_active')->label('فعّال'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ManageQuestions::route('/'),
        ];
    }
}
