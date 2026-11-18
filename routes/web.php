<?php

use Illuminate\Support\Facades\Route;

// There is no UI; the root only points to the API description.
Route::get('/', fn () => response()->json([
    'name' => 'Whitelist CRM API',
    'docs' => 'See README.md and requests.http',
]));
