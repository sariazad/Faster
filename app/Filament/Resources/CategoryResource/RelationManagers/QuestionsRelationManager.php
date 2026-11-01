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
                    ->limit(40),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('مفعّل'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('points')
                    ->label('النقاط')
                    ->options(QuestionResource::pointOptions()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->createAnother(true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
