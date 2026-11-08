<?php

namespace App\Filament\Resources\AlefYa;

use App\Filament\Resources\AlefYa\ContributionResource\Pages;
use App\Models\AlefYa\Contribution;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    protected static ?string $slug = 'alefya-contributions';

    protected static ?string $navigationGroup = 'ألف ياء';

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $modelLabel = 'مساهمة';

    protected static ?string $pluralModelLabel = 'مساهمات اللاعبين';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $n = Contribution::where('status', Contribution::PENDING)->count();

        return $n ? (string) $n : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('الاسم')->maxLength(60),
            Forms\Components\TextInput::make('answer')->label('الإجابة')->required()->maxLength(120),
            Forms\Components\Textarea::make('body')->label('السؤال')->required()->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('letter')->label('الحرف')->badge()->color('warning'),
                Tables\Columns\TextColumn::make('body')->label('السؤال')->wrap()->limit(80)->searchable(),
                Tables\Columns\TextColumn::make('answer')->label('الإجابة')->weight('bold'),
                Tables\Columns\TextColumn::make('name')->label('الاسم')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => Contribution::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->date('Y-m-d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')->options(Contribution::STATUSES)->default(Contribution::PENDING),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')->label('قبول')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (Contribution $c) => $c->status !== Contribution::APPROVED)
                    ->action(function (Contribution $c) {
                        $c->approve();
                        Notification::make()->title('انضاف السؤال لبنك الحروف')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')->label('رفض')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (Contribution $c) => $c->status === Contribution::PENDING)
                    ->action(fn (Contribution $c) => $c->reject()),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approveAll')->label('قبول المحدد')->icon('heroicon-o-check')->color('success')
                    ->action(fn (Collection $records) => $records->each(fn (Contribution $c) => $c->status === Contribution::APPROVED ?: $c->approve())),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageContributions::route('/')];
    }
}
