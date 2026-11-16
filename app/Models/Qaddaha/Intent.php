<?php

namespace App\Models\Qaddaha;

class Intent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'intents';

    protected $casts = ['payload' => 'array', 'team' => 'integer'];
}
