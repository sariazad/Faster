<?php

namespace App\Models\Akher;

use Illuminate\Database\Eloquent\Model as Eloquent;

/** Every آخر القائمة model lives on the separate "akher" database. */
abstract class Model extends Eloquent
{
    protected $connection = 'akher';

    protected $guarded = [];
}
