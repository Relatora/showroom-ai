<?php

use App\Http\Controllers\Appointments\AppointmentController;
use App\Http\Controllers\Assistant\AssistantController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Inventory\VehicleController;
use App\Http\Controllers\Knowledge\DocumentController;
use App\Http\Controllers\Leads\LeadActivityController;
use App\Http\Controllers\Leads\LeadAssistantController;
use App\Http\Controllers\Leads\LeadController;
use App\Http\Controllers\Leads\LeadStageController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('leads', LeadController::class)->only(['index', 'show', 'store', 'update']);
    Route::patch('leads/{lead}/stage', [LeadStageController::class, 'update'])->name('leads.stage.update');
    Route::post('leads/{lead}/activities', [LeadActivityController::class, 'store'])->name('leads.activities.store');
    // The AI helpers on a lead's page share the assistant's per-user budget.
    Route::middleware('throttle:assistant')->group(function () {
        Route::post('leads/{lead}/matches', [LeadAssistantController::class, 'matches'])->name('leads.matches');
        Route::post('leads/{lead}/follow-up', [LeadAssistantController::class, 'followUp'])->name('leads.follow-up');
    });

    Route::get('inventory', [VehicleController::class, 'index'])->name('inventory.index');
    Route::get('inventory/{vehicle}', [VehicleController::class, 'show'])->name('inventory.show');

    Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::patch('appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');

    Route::get('knowledge', [DocumentController::class, 'index'])->name('knowledge.index');
    Route::post('knowledge', [DocumentController::class, 'store'])->name('knowledge.store');
    Route::get('knowledge/{document}', [DocumentController::class, 'show'])->name('knowledge.show');
    Route::post('knowledge/{document}/reindex', [DocumentController::class, 'reindex'])->name('knowledge.reindex');
    Route::delete('knowledge/{document}', [DocumentController::class, 'destroy'])->name('knowledge.destroy');

    Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
    // Each answer keeps the model busy for a while, so one person can't queue up dozens.
    Route::post('assistant/ask', [AssistantController::class, 'ask'])
        ->middleware('throttle:assistant')
        ->name('assistant.ask');
});

require __DIR__.'/settings.php';
