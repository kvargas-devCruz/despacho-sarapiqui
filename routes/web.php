<?php

use App\Http\Controllers\CrewController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\TransferDocumentController;
use Illuminate\Support\Facades\Route;

// La dirección principal lleva directo al panel de despacho
Route::redirect('/', '/admin');

// Seguimiento compartido del servicio (público, con enlace único)
Route::controller(TrackingController::class)
    ->prefix('seguimiento')
    ->name('tracking.')
    ->group(function () {
        Route::get('/{token}', 'show')->middleware('throttle:30,1')->name('show');
        Route::get('/{token}/estado', 'state')->middleware('throttle:60,1')->name('state');
    });

// App de tripulación: se entra con el enlace privado de cada unidad
Route::controller(CrewController::class)
    ->prefix('unidad')
    ->name('crew.')
    ->group(function () {
        Route::get('/{token}', 'show')->middleware('throttle:30,1')->name('show');
        Route::get('/{token}/estado', 'state')->middleware('throttle:60,1')->name('state');
        Route::get('/{token}/manifest.webmanifest', 'manifest')->middleware('throttle:30,1')->name('manifest');
        Route::post('/{token}/ubicacion', 'location')->middleware('throttle:60,1')->name('location');
        Route::post('/{token}/paso', 'advance')->middleware('throttle:30,1')->name('advance');
        Route::post('/{token}/rechazar', 'reject')->middleware('throttle:10,1')->name('reject');
        Route::post('/{token}/recibido', 'receive')->middleware('throttle:10,1')->name('receive');
        Route::post('/{token}/documento', 'document')->middleware('throttle:10,1')->name('document');
    });

// Documentos del traslado y hoja para imprimir (usuarios del panel con permiso)
Route::middleware('auth')->controller(TransferDocumentController::class)->group(function () {
    Route::get('/documentos-traslado/{document}', 'download')->name('transfer-documents.download');
    Route::get('/servicios/{dispatch}/hoja-de-traslado', 'sheet')->name('transfer-documents.sheet');
});
