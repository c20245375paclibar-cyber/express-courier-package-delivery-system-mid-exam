<?php

use App\Http\Controllers\CourierDispatchRequestController;
use App\Http\Controllers\CourierPaymentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParcelController;
use App\Http\Controllers\ShipmentStatusLogController;
use App\Http\Controllers\TransitHubController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('parcels/{parcel}/restore', [ParcelController::class, 'restore'])->name('parcels.restore');
    Route::post('shipment-status-logs/{shipmentStatusLog}/restore', [ShipmentStatusLogController::class, 'restore'])->name('shipment-status-logs.restore');
    Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
    Route::post('transit-hubs/{transitHub}/restore', [TransitHubController::class, 'restore'])->name('transit-hubs.restore');
    Route::post('courier-payments/{courierPayment}/restore', [CourierPaymentController::class, 'restore'])->name('courier-payments.restore');
    Route::post('dispatch-requests/{dispatchRequest}/restore', [CourierDispatchRequestController::class, 'restore'])->name('dispatch-requests.restore');
    Route::resource('parcels', ParcelController::class);
    Route::resource('shipment-status-logs', ShipmentStatusLogController::class);
    Route::resource('customers', CustomerController::class);
    Route::resource('transit-hubs', TransitHubController::class);
    Route::resource('courier-payments', CourierPaymentController::class);
    Route::resource('dispatch-requests', CourierDispatchRequestController::class);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
