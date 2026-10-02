<?php

use App\Http\Controllers\Cp\AccountController;
use App\Http\Controllers\Cp\Auth\LoginController;
use App\Http\Controllers\Cp\Auth\SetPasswordController;
use App\Http\Controllers\Cp\DashboardController;
use App\Http\Controllers\Cp\LinkController;
use App\Http\Controllers\Cp\MarkdownPreviewController;
use App\Http\Controllers\Cp\MediaController;
use App\Http\Controllers\Cp\PostController;
use App\Http\Controllers\Cp\SettingsController;
use App\Http\Controllers\Cp\TagController;
use App\Http\Controllers\Cp\TemplateController;
use App\Http\Controllers\Cp\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('cp')->name('cp.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
        // From a link on the users page: to accept an invitation, or for a new password.
        Route::get('password/{token}', [SetPasswordController::class, 'edit'])->name('password.edit');
        Route::post('password', [SetPasswordController::class, 'update'])->name('password.update');
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

        Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');
        Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password');

        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
        Route::put('tags/{tag:id}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('tags/{tag:id}', [TagController::class, 'destroy'])->name('tags.destroy');

        // Editors write posts and look after tags and media; the rest is for admins.
        Route::middleware('can:admin')->group(function () {
            Route::get('links', [LinkController::class, 'edit'])->name('links.edit');
            Route::put('links', [LinkController::class, 'update'])->name('links.update');

            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

            Route::resource('templates', TemplateController::class)->except('show');
            Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate'])->name('templates.duplicate');

            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::post('users/{user}/link', [UserController::class, 'link'])->name('users.link');
        });

        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('posts/create', [PostController::class, 'choose'])->name('posts.create');
        Route::resource('templates.posts', PostController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->scoped(['post' => 'id']);
        Route::get('templates/{template}/posts/{post}/revisions/{revision}', [PostController::class, 'revision'])
            ->scopeBindings()
            ->name('templates.posts.revisions.show');
        Route::post('templates/{template}/posts/{post}/duplicate', [PostController::class, 'duplicate'])
            ->scopeBindings()
            ->name('templates.posts.duplicate');
    });
});
