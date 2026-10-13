<?php

use App\Http\Controllers\Site\AccountController;
use App\Http\Controllers\Site\Auth\EmailVerificationController;
use App\Http\Controllers\Site\Auth\LoginController;
use App\Http\Controllers\Site\Auth\PasswordResetController;
use App\Http\Controllers\Site\Auth\RegisterController;
use App\Http\Controllers\Site\ClickController;
use App\Http\Controllers\Site\FeedController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\SearchController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Site\TagController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('search', SearchController::class)->name('search');
Route::get('tags', [TagController::class, 'index'])->name('site.tags');
Route::get('tags/{tag}', [TagController::class, 'show'])->name('site.tag');

// Sent by site.ts when a sidebar link or profile icon is followed.
Route::post('clicks', ClickController::class)->middleware('throttle:60,1')->name('clicks');

Route::get('feed.xml', [FeedController::class, 'index'])->name('feed');
Route::get('{template:handle}/feed.xml', [FeedController::class, 'template'])->name('site.template.feed');
Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Accounts on the site: members sign up here, and anyone with an account
// logs in here. The names are the ones Laravel's emails link to.
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:5,60')->name('register.store');
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.store');
});

Route::view('privacy', 'site.privacy')->name('privacy');

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account', [AccountController::class, 'update'])->name('account.update');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::delete('account', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::put('account/location', [AccountController::class, 'updateLocation'])->middleware('throttle:30,1')->name('account.location');
    Route::delete('account/location', [AccountController::class, 'destroyLocation'])->name('account.location.destroy');
    Route::get('verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('verify-email', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
});
