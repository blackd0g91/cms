<?php

use App\Http\Controllers\Cp\Auth\LoginController;
use App\Http\Controllers\Cp\DashboardController;
use App\Http\Controllers\Cp\MarkdownPreviewController;
use App\Http\Controllers\Cp\MediaController;
use App\Http\Controllers\Cp\PostController;
use App\Http\Controllers\Cp\SettingsController;
use App\Http\Controllers\Cp\TemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('cp')->name('cp.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::post('markdown/preview', MarkdownPreviewController::class)->name('markdown.preview');

        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/library', [MediaController::class, 'library'])->name('media.library');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::resource('templates', TemplateController::class)->except('show');
        Route::resource('templates.posts', PostController::class)->except('show')->scoped(['post' => 'id']);
    });
});
