<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages;
use App\Models\Game;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $modelLabel = 'لعبة';

    protected static ?string $pluralModelLabel = 'الألعاب';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('mode')
                    ->label('القسم')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state === Game::TAQTAQ ? 'طق طق' : 'التحدي')
                    ->color(fn (?string $state) => $state === Game::TAQTAQ ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('team_a_name')
                    ->label('الفريق الأول')
                    ->description(fn (Game $record) => $record->isTaqtaq()
                        ? ($record->winner === 'a' ? '🏆 وصّل' : null)
                        : $record->team_a_score.' نقطة'),
                Tables\Columns\TextColumn::make('team_b_name')
                    ->label('الفريق الثاني')
                    ->description(fn (Game $record) => $record->isTaqtaq()
                        ? ($record->winner === 'b' ? '🏆 وصّل' : null)
                        : $record->team_b_score.' نقطة'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === Game::FINISHED ? 'انتهت' : 'جارية')
                    ->color(fn (string $state) => $state === Game::FINISHED ? 'gray' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mode')
                    ->label('القسم')
                    ->options([Game::CLASSIC => 'التحدي', Game::TAQTAQ => 'طق طق']),
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([Game::PLAYING => 'جارية', Game::FINISHED => 'انتهت']),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('فتح')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Game $record) => $record->playUrl())
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGames::route('/'),
        ];
    }
}
