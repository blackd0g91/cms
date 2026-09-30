<?php

use App\Http\Controllers\Site\PostController;
use Illuminate\Support\Facades\Route;

// Catch-all public routes, registered after everything else.
Route::get('{template:handle}', [PostController::class, 'index'])->name('site.template');
Route::get('{template:handle}/{post:slug}', [PostController::class, 'show'])->name('site.post');
Route::get('{template:handle}/{post:slug}/share.png', [PostController::class, 'shareImage'])->name('site.post.share-image');
