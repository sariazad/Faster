<?php

namespace App\Models\Qaddaha;

use Illuminate\Database\Eloquent\Model as Eloquent;

/** Every قدّها؟ model lives on the separate "qaddaha" database. */
abstract class Model extends Eloquent
{
    protected $connection = 'qaddaha';

    protected $guarded = [];
}
