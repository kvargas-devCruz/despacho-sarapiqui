<?php

use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

// La dirección principal lleva directo al panel de despacho
Route::redirect('/', '/admin');

// Seguimiento compartido del servicio (público, con enlace único)
Route::controller(TrackingController::class)
    ->prefix('seguimiento')
    ->name('tracking.')
    ->group(function () {
        Route::get('/{token}', 'show')->name('show');
        Route::get('/{token}/estado', 'state')->middleware('throttle:60,1')->name('state');
    });
