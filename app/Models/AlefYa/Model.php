<?php

namespace App\Models\AlefYa;

use Illuminate\Database\Eloquent\Model as Eloquent;

/** Every ألف ياء model lives on the separate "alefya" database. */
abstract class Model extends Eloquent
{
    protected $connection = 'alefya';

    protected $guarded = [];
}
