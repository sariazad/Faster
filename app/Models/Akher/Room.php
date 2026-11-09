<?php

namespace App\Models\Akher;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    public const LOBBY = 'lobby';
    public const PLAYING = 'playing';
    public const DONE = 'done';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'order' => 'array',
        'scores' => 'array',
        'wrong_penalty' => 'boolean',
        'turn_deadline' => 'datetime',
        'expires_at' => 'datetime',
        'turn_idx' => 'integer',
        'version' => 'integer',
        'round' => 'integer',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(Player::class, 'room_code')->orderBy('id');
    }

    public function guesses(): HasMany
    {
        return $this->hasMany(Guess::class, 'room_code')->orderBy('id');
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(GameList::class, 'list_id');
    }

    public function currentPlayerId(): ?int
    {
        return $this->status === self::PLAYING ? ($this->order[$this->turn_idx] ?? null) : null;
    }

    /** Bumps the version clients poll on. */
    public function touchVersion(): void
    {
        $this->version++;
    }
}
