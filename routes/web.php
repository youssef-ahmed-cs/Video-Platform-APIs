<?php

use App\Http\Controllers\V1\PodcastController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/podcasts/{slug}', [PodcastController::class, 'playBySlug'])->where('slug', '[a-zA-Z0-9\-_]+');
Route::get('/{slug}', [PodcastController::class, 'playBySlug'])->where('slug', '[a-zA-Z0-9\-_]+');

