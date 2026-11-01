<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameQuestion extends Model
{
    public const PENDING = 'pending';

    public const ANSWERED = 'answered';

    protected $fillable = [
        'game_id',
        'question_id',
        'category_id',
        'points',
        'slot',
        'status',
        'opened_by',
        'answered_by',
        'awarded_points',
        'effects',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'slot' => 'integer',
            'awarded_points' => 'integer',
            'effects' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function hasEffect(string $helper): bool
    {
        return in_array($helper, $this->effects ?? [], true);
    }
}
