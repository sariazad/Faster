<?php

namespace App\Filament\Resources\Akher\ListResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'عناصر القائمة';

    protected static ?string $modelLabel = 'عنصر';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('rank')->label('الترتيب')->numeric()->minValue(1)->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('list_id', $this->getOwnerRecord()->getKey())),
            Forms\Components\TextInput::make('name_ar')->label('الاسم بالعربي')->required()->maxLength(120),
            Forms\Components\TextInput::make('name_en')->label('الاسم بالإنجليزي')->maxLength(120),
            Forms\Components\TextInput::make('extra')->label('معلومة إضافية')->maxLength(120),
            Forms\Components\TagsInput::make('aliases')->label('أسماء بديلة')->helperText('اكتب الاسم واضغط Enter. كل اسم لازم يكون لعنصر واحد بس.')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('rank')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(100)
            ->columns([
                Tables\Columns\TextColumn::make('rank')->label('#')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('name_ar')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('name_en')->label('English')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('aliases')->label('أسماء بديلة')->badge()->separator(',')->toggleable(),
                Tables\Columns\TextColumn::make('extra')->label('معلومة'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
