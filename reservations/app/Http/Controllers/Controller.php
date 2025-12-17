<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Reservations API",
 *     version="1.0.0",
 *     description="Sistema de reservas de mesas con asignación automática de ubicación y combinación inteligente de mesas",
 *     @OA\Contact(
 *         email="federico@example.com",
 *         name="Federico"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 * 
 * @OA\Tag(
 *     name="Reservations",
 *     description="Gestión de reservas"
 * )
 * 
 * @OA\Tag(
 *     name="Tables",
 *     description="Consulta de disponibilidad de mesas"
 * )
 */
abstract class Controller
{
    //
}
