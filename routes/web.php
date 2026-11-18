<?php

use App\Http\Controllers\CourierPaymentController;
use App\Http\Controllers\CourierDispatchRequestController;
use App\Http\Controllers\ParcelController;
use App\Http\Controllers\ShipmentStatusLogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TransitHubController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [ParcelController::class, 'index'])->name('dashboard');
    Route::resource('parcels', ParcelController::class)->except(['show']);
    Route::resource('shipment-status-logs', ShipmentStatusLogController::class)->except(['show']);
    Route::resource('customers', CustomerController::class);
    Route::resource('transit-hubs', TransitHubController::class);
    Route::resource('courier-payments', CourierPaymentController::class);
    Route::resource('dispatch-requests', CourierDispatchRequestController::class)->except(['show']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
