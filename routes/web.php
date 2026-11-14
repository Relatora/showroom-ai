<?php

use App\Http\Controllers\Leads\LeadActivityController;
use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\Leads\LeadStageController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('leads', LeadController::class)->only(['index', 'show', 'store', 'update']);
    Route::patch('leads/{lead}/stage', [LeadStageController::class, 'update'])->name('leads.stage.update');
    Route::post('leads/{lead}/activities', [LeadActivityController::class, 'store'])->name('leads.activities.store');
});

require __DIR__.'/settings.php';
