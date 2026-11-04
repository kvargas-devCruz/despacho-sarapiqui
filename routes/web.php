<?php

use Illuminate\Support\Facades\Route;

// La dirección principal lleva directo al panel de despacho
Route::redirect('/', '/admin');
