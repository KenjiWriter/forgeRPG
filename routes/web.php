<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Forge\ForgeController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Mining\MiningController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::post('mining/hit', [MiningController::class, 'hit'])->name('mining.hit');
    Route::post('api/mining/collect', [MiningController::class, 'collect'])->name('mining.collect');

    // Forge endpoints
    Route::get('forge', [ForgeController::class, 'index'])->name('forge');
    Route::post('forge/init', [ForgeController::class, 'init'])->name('forge.init');
    Route::post('forge/complete', [ForgeController::class, 'complete'])->name('forge.complete');
    Route::post('forge/acquire/{session}', [ForgeController::class, 'acquire'])->name('forge.acquire');

    // Inventory endpoints
    Route::post('inventory/equip/{inventory}', [InventoryController::class, 'equip'])->name('inventory.equip');
    Route::post('inventory/sell/{inventory}', [InventoryController::class, 'sell'])->name('inventory.sell');
    Route::post('api/inventory/sell', [InventoryController::class, 'sellByItemId'])->name('inventory.sell.item');
});

require __DIR__.'/settings.php';
