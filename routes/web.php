<?php

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

Route::get('feed.xml', [FeedController::class, 'index'])->name('feed');
Route::get('{template:handle}/feed.xml', [FeedController::class, 'template'])->name('site.template.feed');
Route::get('sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');
