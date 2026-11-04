<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardCell extends Model
{
    protected $fillable = ['game_id', 'row', 'col', 'category_id', 'owner', 'protected'];

    protected function casts(): array
    {
        return [
            'game_id' => 'integer',
            'category_id' => 'integer',
            'row' => 'integer',
            'col' => 'integer',
            'protected' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isFree(): bool
    {
        return $this->owner === null;
    }
}
