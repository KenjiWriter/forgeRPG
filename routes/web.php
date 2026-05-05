<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Mining\MiningController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::post('mining/hit', [MiningController::class, 'hit'])->name('mining.hit');
});

require __DIR__.'/settings.php';
