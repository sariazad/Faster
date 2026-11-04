<?php

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
