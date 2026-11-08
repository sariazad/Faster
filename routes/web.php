<?php

use App\Http\Controllers\AlefYaController;
use App\Livewire\GamePlay;
use App\Livewire\GameSetup;
use App\Livewire\TaqtaqPlay;
use App\Livewire\TaqtaqSetup;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

// التحدي — the classic category board.
Route::get('/play/new', GameSetup::class)->name('game.new');
Route::get('/play/{game}', GamePlay::class)->name('game.play');

// طق طق — connect your two sides of the hex board.
Route::get('/taqtaq/new', TaqtaqSetup::class)->name('taqtaq.new');
Route::get('/taqtaq/{game}', TaqtaqPlay::class)->name('taqtaq.play');

// Keeps the session alive while a game screen sits open (called by the layout every 20 minutes).
Route::get('/ping', fn () => response()->noContent())->name('ping');

// ألف ياء — letters game with its own database (see config/database.php "alefya").
Route::prefix('alefya')->name('alefya.')->controller(AlefYaController::class)->group(function () {
    Route::get('/', 'page')->name('home');
    Route::get('/questions', 'portal')->name('portal');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/question', 'question')->name('question');
        Route::get('/solo', 'solo')->name('solo');
        Route::get('/notifications', 'notifications')->name('notifications');
        Route::get('/leaderboard', 'leaderboard')->name('leaderboard');
        Route::get('/me', 'me')->name('me');
        Route::get('/sync', 'pullSync')->name('sync.pull');
        Route::post('/sync', 'pushSync')->name('sync.push');
        Route::post('/logout', 'logout')->name('logout');
        Route::post('/scores', 'storeScore')->middleware('throttle:30,1')->name('scores');
        Route::post('/rate', 'rate')->middleware('throttle:60,1')->name('rate');

        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/login', 'login')->name('login');
            Route::post('/demo-code', 'demoCode')->name('demo');
            Route::post('/note', 'note')->name('note');
            Route::post('/contribute', 'contribute')->name('contribute');
        });
    });

    Route::get('/{page}', 'page')->whereIn('page', AlefYaController::PAGES)->name('page');
});
