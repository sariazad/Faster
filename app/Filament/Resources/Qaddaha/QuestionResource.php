<?php

namespace App\Filament\Resources\Qaddaha;

use App\Filament\Resources\Qaddaha\QuestionResource\Pages;
use App\Models\Qaddaha\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $slug = 'qaddaha-questions';

    protected static ?string $navigationGroup = 'قدّها؟';

    protected static ?string $navigationIcon = 'heroicon-o-hand-raised';

    protected static ?string $modelLabel = 'سؤال';

    protected static ?string $pluralModelLabel = 'الأسئلة';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category_id')->label('الفئة')->relationship('category', 'name')->required()->preload(),
            Forms\Components\Select::make('level')->label('المستوى')->options([200 => '200 (سهل)', 400 => '400 (متوسط)', 600 => '600 (صعب)'])->required(),
            Forms\Components\TextInput::make('text')->label('السؤال')->required()->maxLength(255)->columnSpanFull()
                ->placeholder('كم نوع خبز تقدر تذكر؟'),
            Forms\Components\TagsInput::make('answers')->label('الإجابات النموذجية')->required()->columnSpanFull()
                ->helperText('اكتب الإجابة واضغط Enter. ٨ إجابات على الأقل أحسن، والمستوى الأصعب قائمة أقصر أو أدق.')
                ->rules(['array', 'min:3']),
            Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($q) => $q->with('category'))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('text')->label('السؤال')->searchable()->wrap()->weight('bold'),
                Tables\Columns\TextColumn::make('category.name')->label('الفئة')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('level')->label('المستوى')->badge()
                    ->color(fn (int $state) => [200 => 'success', 400 => 'warning', 600 => 'danger'][$state] ?? 'gray')->sortable(),
                Tables\Columns\TextColumn::make('answers')->label('الإجابات')->formatStateUsing(fn ($state, Question $record) => count($record->answers ?? []))->badge(),
                Tables\Columns\TextColumn::make('times_used')->label('انلعب')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('مفعّل')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('الفئة')->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('level')->label('المستوى')->options([200 => '200', 400 => '400', 600 => '600']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
