<?php

namespace App\Filament\Resources\AlefYa;

use App\Filament\Resources\AlefYa\NoteResource\Pages;
use App\Models\AlefYa\Note;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $slug = 'alefya-notes';

    protected static ?string $navigationGroup = 'ألف ياء';

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $modelLabel = 'ملاحظة';

    protected static ?string $pluralModelLabel = 'ملاحظات الأسئلة';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $n = Note::where('resolved', false)->count();

        return $n ? (string) $n : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('body')->label('الملاحظة')->required()->rows(4)->columnSpanFull(),
            Forms\Components\Toggle::make('resolved')->label('تمت المعالجة'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('question.letter')->label('الحرف')->badge()->color('warning'),
                Tables\Columns\TextColumn::make('question.question')->label('السؤال')->wrap()->limit(60),
                Tables\Columns\TextColumn::make('question.answer')->label('الإجابة')->weight('bold'),
                Tables\Columns\TextColumn::make('body')->label('الملاحظة')->wrap()->limit(120)->searchable(),
                Tables\Columns\ToggleColumn::make('resolved')->label('تمت المعالجة'),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->date('Y-m-d')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('resolved')->label('تمت المعالجة')->default(false),
            ])
            ->actions([
                Tables\Actions\Action::make('editQuestion')->label('تعديل السؤال')->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (Note $n) => $n->question->only(['question', 'answer']))
                    ->form([
                        Forms\Components\Textarea::make('question')->label('السؤال')->required()->rows(3),
                        Forms\Components\TextInput::make('answer')->label('الإجابة')->required(),
                    ])
                    ->action(function (Note $n, array $data) {
                        $n->question->update($data);
                        $n->update(['resolved' => true]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNotes::route('/')];
    }
}
