<?php

use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Routes para reservas (sin CSRF)
Route::prefix('api')->middleware('api')->group(function () {

    Route::get('/allReservations', [ReservationController::class, 'index']);
    
    // Endpoint temporal para inicializar datos
    Route::post('/init-data', function () {
        \Artisan::call('db:seed', ['--force' => true]);
        return response()->json([
            'success' => true,
            'message' => 'Datos inicializados',
            'users_count' => \App\Models\User::count(),
            'tables_count' => \App\Models\Table::count(),
        ]);
    });

    // Punto 3: Crear reserva
    Route::post('/reservations', [ReservationController::class, 'store']);
    
    // Cancelar reserva
    Route::patch('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);
    
    // Punto 4: Listar reservas por fecha
    Route::get('/reservations/by-date', [ReservationController::class, 'listByDate']);
    
    // Estado de mesas en tiempo real
    Route::get('/tables/availability', [ReservationController::class, 'tablesAvailability']);
});
