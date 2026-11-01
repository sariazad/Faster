<?php

use App\Livewire\GamePlay;
use App\Livewire\GameSetup;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/play/new', GameSetup::class)->name('game.new');
Route::get('/play/{game}', GamePlay::class)->name('game.play');
