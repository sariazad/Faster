<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CellQuestion extends Model
{
    protected $fillable = ['game_id', 'board_cell_id', 'question_id', 'opened_by', 'answered_by'];

    public function cell(): BelongsTo
    {
        return $this->belongsTo(BoardCell::class, 'board_cell_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
