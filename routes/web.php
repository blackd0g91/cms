<?php

use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('search', SearchController::class)->name('search');
