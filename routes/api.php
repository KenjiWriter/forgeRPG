<?php

use App\Http\Controllers\Mining\MiningController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('mining/hit', [MiningController::class, 'hit'])->name('mining.hit');
});
