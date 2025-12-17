<?php

use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Routes para reservas (sin CSRF)
Route::prefix('api')->middleware('api')->group(function () {

    Route::get('/allReservations', [ReservationController::class, 'index']);

    // Punto 3: Crear reserva
    Route::post('/reservations', [ReservationController::class, 'store']);
    
    // Punto 4: Listar reservas por fecha
    Route::get('/reservations/by-date', [ReservationController::class, 'listByDate']);
    
    // Estado de mesas en tiempo real
    Route::get('/tables/availability', [ReservationController::class, 'tablesAvailability']);
});
