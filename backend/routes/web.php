<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Named target for Laravel's auth redirect on unauthenticated requests. This is
// an API + SPA app (no server-rendered login page), so a missing `login` route
// made unauthenticated hits to /api/* throw "Route [login] not defined" instead
// of a clean 401 — return the 401 directly.
Route::get('login', fn () => response()->json([
    'message' => 'Non authentifié. Veuillez vous connecter.',
], 401))->name('login');
