<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    /**
     * Punto 3: Crear una nueva reserva
     * 
     * @OA\Post(
     *     path="/reservations",
     *     tags={"Reservations"},
     *     summary="Crear nueva reserva",
     *     description="Crea una reserva con asignación automática de ubicación (A→B→C→D) y combinación óptima de hasta 3 mesas",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id","reservation_date","reservation_time","party_size"},
     *             @OA\Property(property="user_id", type="integer", example=1, description="ID del usuario"),
     *             @OA\Property(property="reservation_date", type="string", format="date", example="2025-12-24", description="Fecha de la reserva (YYYY-MM-DD)"),
     *             @OA\Property(property="reservation_time", type="string", pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]$", example="19:00", description="Hora de la reserva (HH:mm). L-V: 10:00-24:00, Sáb: 22:00-02:00, Dom: 12:00-16:00"),
     *             @OA\Property(property="party_size", type="integer", minimum=1, maximum=18, example=4, description="Cantidad de personas (máximo 18: 3 mesas × 6 personas)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Reserva creada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Reserva creada exitosamente"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="reservation_date", type="string", example="2025-12-24"),
     *                 @OA\Property(property="reservation_time", type="string", example="19:00"),
     *                 @OA\Property(property="party_size", type="integer", example=4),
     *                 @OA\Property(property="location", type="string", example="A", description="Ubicación asignada automáticamente"),
     *                 @OA\Property(property="duration_minutes", type="integer", example=120, description="Duración por defecto: 2 horas"),
     *                 @OA\Property(property="status", type="string", example="confirmed"),
     *                 @OA\Property(property="tables", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="location", type="string", example="A"),
     *                     @OA\Property(property="table_number", type="integer", example=3),
     *                     @OA\Property(property="capacity", type="integer", example=4)
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación o sin disponibilidad",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No hay mesas disponibles para la fecha y hora solicitada")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reservation_date' => 'required|date|after_or_equal:today',
            'reservation_time' => 'required|date_format:H:i',
            'party_size' => 'required|integer|min:1|max:18', // máximo 3 mesas x 6 personas
        ]);

        try {
            $reservation = $this->reservationService->createReservation(
                $validated['user_id'],
                $validated['reservation_date'],
                $validated['reservation_time'],
                $validated['party_size']
            );

            return response()->json([
                'success' => true,
                'message' => 'Reserva creada exitosamente',
                'data' => $reservation,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function index()
    {
        $reservations = Reservation::all();
        return response()->json($reservations);
    }

    /**
     * Estado de mesas en tiempo real para una fecha/hora específica
     * 
     * @OA\Get(
     *     path="/tables/availability",
     *     tags={"Tables"},
     *     summary="Consultar disponibilidad de mesas",
     *     description="Obtiene el estado (disponible/ocupada) de todas las mesas para una fecha y hora específica, agrupadas por ubicación",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Fecha a consultar (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2025-12-24")
     *     ),
     *     @OA\Parameter(
     *         name="time",
     *         in="query",
     *         required=true,
     *         description="Hora a consultar (HH:mm)",
     *         @OA\Schema(type="string", pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]$", example="19:00")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado de disponibilidad",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="date", type="string", example="2025-12-24"),
     *             @OA\Property(property="time", type="string", example="19:00"),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="total_tables", type="integer", example=20),
     *                 @OA\Property(property="available", type="integer", example=15),
     *                 @OA\Property(property="occupied", type="integer", example=5)
     *             ),
     *             @OA\Property(property="tables_by_location", type="object",
     *                 @OA\Property(property="A", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="table_number", type="integer", example=1),
     *                     @OA\Property(property="capacity", type="integer", example=2),
     *                     @OA\Property(property="status", type="string", enum={"available", "occupied"}, example="occupied"),
     *                     @OA\Property(property="reservation", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="user_name", type="string", example="Juan Pérez"),
     *                         @OA\Property(property="party_size", type="integer", example=2),
     *                         @OA\Property(property="time_range", type="string", example="19:00 - 21:00")
     *                     )
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function tablesAvailability(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
        ]);

        $data = $this->reservationService->getTablesAvailability(
            $validated['date'],
            $validated['time']
        );

        return response()->json([
            'success' => true,
            'date' => $validated['date'],
            'time' => $validated['time'],
            'summary' => $data['summary'],
            'tables_by_location' => $data['tables_by_location'],
        ]);
    }

    /**
     * Punto 4: Listado de reservas por fecha con mesas (query optimizada)
     * 
     * @OA\Get(
     *     path="/reservations/by-date",
     *     tags={"Reservations"},
     *     summary="Listar reservas por fecha",
     *     description="Obtiene todas las reservas de una fecha específica con sus mesas asignadas, agrupadas por ubicación. Usa una única query SQL optimizada con JOINs",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=true,
     *         description="Fecha a consultar (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date", example="2025-12-24")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Listado de reservas agrupadas por ubicación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="date", type="string", example="2025-12-24"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="A", type="array", @OA\Items(
     *                     @OA\Property(property="reservation_id", type="integer", example=1),
     *                     @OA\Property(property="location", type="string", example="A"),
     *                     @OA\Property(property="reservation_time", type="string", example="19:00"),
     *                     @OA\Property(property="party_size", type="integer", example=8),
     *                     @OA\Property(property="status", type="string", example="confirmed"),
     *                     @OA\Property(property="user_name", type="string", example="Juan Pérez"),
     *                     @OA\Property(property="user_email", type="string", example="juan@example.com"),
     *                     @OA\Property(property="tables", type="string", example="A-3, A-4", description="Mesas asignadas concatenadas")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function listByDate(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $reservations = DB::table('reservations as r')
            ->join('reservation_table as rt', 'r.id', '=', 'rt.reservation_id')
            ->join('tables as t', 'rt.table_id', '=', 't.id')
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->where('r.reservation_date', $validated['date'])
            ->where('r.status', '!=', 'cancelled')
            ->select(
                'r.id as reservation_id',
                'r.location',
                'r.reservation_time',
                'r.party_size',
                'r.status',
                'u.name as user_name',
                'u.email as user_email',
                DB::raw('GROUP_CONCAT(t.location || "-" || t.table_number, ", ") as tables')
            )
            ->groupBy('r.id', 'r.location', 'r.reservation_time', 'r.party_size', 'r.status', 'u.name', 'u.email')
            ->orderBy('r.location')
            ->orderBy('r.reservation_time')
            ->get()
            ->groupBy('location');

        return response()->json([
            'success' => true,
            'date' => $validated['date'],
            'data' => $reservations,
        ]);
    }
}
