<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use App\Filament\Resources\QuestionResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'أسئلة الفئة';

    protected static ?string $modelLabel = 'سؤال';

    protected static ?string $pluralModelLabel = 'الأسئلة';

    public function form(Form $form): Form
    {
        return $form
            ->schema(QuestionResource::questionFields(withCategory: false))
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('points')
            ->columns([
                Tables\Columns\TextColumn::make('points')
                    ->label('النقاط')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('body')
                    ->label('السؤال')
                    ->limit(70)
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('answer')
                    ->label('الإجابة')
                    ->placeholder('—')
                    ->limit(40),
                Tables\Columns\TextColumn::make('media')
                    ->label('وسائط')
                    ->state(fn ($record) => implode(' ', array_filter([
                        $record->image || $record->answer_image ? '🖼️' : null,
                        $record->audio || $record->answer_audio ? '🔊' : null,
                    ])) ?: '—'),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('مفعّل'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('points')
                    ->label('النقاط')
                    ->options(QuestionResource::pointOptions()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->createAnother(true)->modalWidth('4xl'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth('4xl'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
