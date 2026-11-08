<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ألف ياء uses its own SQLite file: create it on first run so `php artisan migrate` just works.
        $path = config('database.connections.alefya.database');

        if (is_string($path) && $path !== '' && $path !== ':memory:' && ! file_exists($path) && is_dir(dirname($path))) {
            @touch($path);
        }
    }
}
