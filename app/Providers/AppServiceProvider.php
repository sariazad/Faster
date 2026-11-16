<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ألف ياء, آخر القائمة and قدّها؟ use their own SQLite files: create them on first run so `php artisan migrate` just works.
        foreach (['alefya', 'akher', 'qaddaha'] as $connection) {
            $path = config("database.connections.$connection.database");

            if (is_string($path) && $path !== '' && $path !== ':memory:' && ! file_exists($path) && is_dir(dirname($path))) {
                @touch($path);
            }
        }

        // قدّها؟: a whole party often shares one IP (same Wi-Fi), and the host polls about once a
        // second — so polling and actions get their own, roomy limits per IP and room.
        RateLimiter::for('qaddaha-poll', fn (Request $r) => Limit::perMinute(900)->by('poll|'.$r->ip().'|'.$r->route('code')));
        RateLimiter::for('qaddaha-act', fn (Request $r) => Limit::perMinute(240)->by('act|'.$r->ip().'|'.$r->route('code')));
    }
}
