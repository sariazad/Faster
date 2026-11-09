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
        // ألف ياء and آخر القائمة use their own SQLite files: create them on first run so `php artisan migrate` just works.
        foreach (['alefya', 'akher'] as $connection) {
            $path = config("database.connections.$connection.database");

            if (is_string($path) && $path !== '' && $path !== ':memory:' && ! file_exists($path) && is_dir(dirname($path))) {
                @touch($path);
            }
        }
    }
}
