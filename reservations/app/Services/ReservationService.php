<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    /**
     * Crear una reserva con asignación automática de mesas
     */
    public function createReservation(int $userId, string $date, string $time, int $partySize): Reservation
    {
        // Validar horarios
        $this->validateSchedule($date, $time);
        
        // Validar anticipación mínima de 15 minutos
        $this->validateAdvanceTime($date, $time);
        
        // Buscar ubicación y mesas disponibles
        $assignedData = $this->findAvailableTablesWithLocation($date, $time, $partySize);
        
        if (!$assignedData) {
            throw new \Exception('No hay mesas disponibles para la fecha y hora solicitada');
        }
        
        // Crear reserva en transacción
        return DB::transaction(function () use ($userId, $date, $time, $partySize, $assignedData) {
            $reservation = Reservation::create([
                'user_id' => $userId,
                'reservation_date' => $date,
                'reservation_time' => $time,
                'party_size' => $partySize,
                'location' => $assignedData['location'],
                'duration_minutes' => Reservation::DEFAULT_DURATION_MINUTES,
                'status' => 'confirmed',
            ]);
            
            // Asociar mesas a la reserva
            $reservation->tables()->attach($assignedData['table_ids']);
            
            // Invalidar caché de disponibilidad
            $this->clearAvailabilityCache($assignedData['location'], $date);
            
            return $reservation->load('tables');
        });
    }
    
    /**
     * Validar horarios según el día de la semana
     */
    protected function validateSchedule(string $date, string $time): void
    {
        $dateTime = Carbon::parse("$date $time");
        $dayOfWeek = $dateTime->dayOfWeek;
        $timeOnly = Carbon::parse($time)->format('H:i');
        
        $schedule = match ($dayOfWeek) {
            Carbon::MONDAY, Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY 
                => Reservation::SCHEDULES['weekday'],
            Carbon::SATURDAY => Reservation::SCHEDULES['saturday'],
            Carbon::SUNDAY => Reservation::SCHEDULES['sunday'],
        };
        
        // Manejar caso especial sábado (22:00 a 02:00)
        if ($dayOfWeek === Carbon::SATURDAY) {
            $startTime = Carbon::parse($schedule['start']);
            $isValid = Carbon::parse($timeOnly)->greaterThanOrEqualTo($startTime) 
                      || Carbon::parse($timeOnly)->lessThan(Carbon::parse($schedule['end']));
            
            if (!$isValid) {
                throw new \Exception('Horario no válido. Sábados: 22:00 a 02:00');
            }
        } else {
            $startTime = Carbon::parse($schedule['start']);
            $endTime = Carbon::parse($schedule['end']);
            $currentTime = Carbon::parse($timeOnly);
            
            if ($currentTime->lessThan($startTime) || $currentTime->greaterThanOrEqualTo($endTime)) {
                $message = match ($dayOfWeek) {
                    Carbon::SUNDAY => 'Horario no válido. Domingos: 12:00 a 16:00',
                    default => 'Horario no válido. Lunes a Viernes: 10:00 a 24:00',
                };
                throw new \Exception($message);
            }
        }
    }
    
    /**
     * Validar anticipación mínima de 15 minutos
     */
    protected function validateAdvanceTime(string $date, string $time): void
    {
        $reservationDateTime = Carbon::parse("$date $time");
        $now = Carbon::now();
        
        if ($reservationDateTime->lessThanOrEqualTo($now->addMinutes(Reservation::MIN_ADVANCE_MINUTES))) {
            throw new \Exception('Las reservas deben hacerse con al menos 15 minutos de anticipación');
        }
    }
    
    /**
     * Buscar mesas disponibles y asignar ubicación automáticamente (por orden)
     */
    protected function findAvailableTablesWithLocation(string $date, string $time, int $partySize): ?array
    {
        $locations = ['A', 'B', 'C', 'D'];
        
        // Buscar por orden de ubicación
        foreach ($locations as $location) {
            $tables = $this->findAvailableTablesInLocation($location, $date, $time, $partySize);
            
            if ($tables) {
                return [
                    'location' => $location,
                    'table_ids' => $tables->pluck('id')->toArray(),
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Buscar mesas disponibles en una ubicación específica
     */
    protected function findAvailableTablesInLocation(string $location, string $date, string $time, int $partySize)
    {
        // Verificar caché de disponibilidad
        $cacheKey = "availability:{$location}:{$date}:{$time}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($location, $date, $time, $partySize) {
            $reservationDateTime = Carbon::parse("$date $time");
            $endDateTime = $reservationDateTime->copy()->addMinutes(Reservation::DEFAULT_DURATION_MINUTES);
            
            // Obtener mesas ocupadas en el rango de tiempo con lógica de overlap correcta
            $occupiedTableIds = DB::table('reservations as r')
                ->join('reservation_table as rt', 'r.id', '=', 'rt.reservation_id')
                ->where('r.location', $location)
                ->whereRaw('DATE(r.reservation_date) = ?', [$date])
                ->where('r.status', '!=', 'cancelled')
                ->select('rt.table_id', 'r.reservation_time', 'r.duration_minutes')
                ->get()
                ->filter(function ($reservation) use ($reservationDateTime, $date) {
                    $resStart = Carbon::parse("$date {$reservation->reservation_time}");
                    $resEnd = $resStart->copy()->addMinutes($reservation->duration_minutes);
                    
                    // Verificar overlap: la nueva reserva empieza antes de que termine esta
                    // Y esta reserva empieza antes de que termine la nueva
                    $newEnd = $reservationDateTime->copy()->addMinutes(Reservation::DEFAULT_DURATION_MINUTES);
                    return $reservationDateTime->lessThan($resEnd) && $resStart->lessThan($newEnd);
                })
                ->pluck('table_id')
                ->unique()
                ->toArray();
            
            // Obtener mesas disponibles en la ubicación
            $availableTables = Table::where('location', $location)
                ->where('is_available', true)
                ->whereNotIn('id', $occupiedTableIds)
                ->orderBy('capacity') // Ordenar de menor a mayor para optimizar
                ->get();
            
            // Verificar si la ubicación tiene capacidad suficiente (max 3 mesas)
            $maxCapacity = $availableTables->sortByDesc('capacity')
                ->take(Reservation::MAX_TABLES_PER_RESERVATION)
                ->sum('capacity');
            
            if ($maxCapacity < $partySize) {
                // No hay capacidad suficiente ni combinando mesas, pasar a siguiente ubicación
                return null;
            }
            
            // Intentar combinar mesas (máximo 3)
            return $this->selectOptimalTables($availableTables, $partySize);
        });
    }
    
    /**
     * Seleccionar combinación óptima de mesas (hasta 3)
     * Estrategia: usar la(s) mesa(s) más pequeña(s) que cumplan el requisito
     */
    protected function selectOptimalTables($availableTables, int $partySize)
    {
        if ($availableTables->isEmpty()) {
            return null;
        }

        $sortedTables = $availableTables->sortBy('capacity')->values();
        $maxTables = min(Reservation::MAX_TABLES_PER_RESERVATION, $sortedTables->count());

        for ($tableCount = 1; $tableCount <= $maxTables; $tableCount++) {
            $bestCombination = null;
            $bestExcess = PHP_INT_MAX;
            $bestMaxCapacity = PHP_INT_MAX;

            if ($tableCount === 1) {
                foreach ($sortedTables as $table) {
                    if ($table->capacity < $partySize) {
                        continue;
                    }

                    $excess = $table->capacity - $partySize;
                    $maxCap = $table->capacity;

                    if ($excess < $bestExcess || ($excess === $bestExcess && $maxCap < $bestMaxCapacity)) {
                        $bestExcess = $excess;
                        $bestMaxCapacity = $maxCap;
                        $bestCombination = [$table];
                    }
                }
            } elseif ($tableCount === 2) {
                $count = $sortedTables->count();
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $tables = [$sortedTables[$i], $sortedTables[$j]];
                        $totalCapacity = $tables[0]->capacity + $tables[1]->capacity;

                        if ($totalCapacity < $partySize) {
                            continue;
                        }

                        $excess = $totalCapacity - $partySize;
                        $maxCap = max($tables[0]->capacity, $tables[1]->capacity);

                        if ($excess < $bestExcess || ($excess === $bestExcess && $maxCap < $bestMaxCapacity)) {
                            $bestExcess = $excess;
                            $bestMaxCapacity = $maxCap;
                            $bestCombination = $tables;
                        }
                    }
                }
            } else {
                $count = $sortedTables->count();
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        for ($k = $j + 1; $k < $count; $k++) {
                            $tables = [$sortedTables[$i], $sortedTables[$j], $sortedTables[$k]];
                            $totalCapacity = $tables[0]->capacity + $tables[1]->capacity + $tables[2]->capacity;

                            if ($totalCapacity < $partySize) {
                                continue;
                            }

                            $excess = $totalCapacity - $partySize;
                            $maxCap = max($tables[0]->capacity, $tables[1]->capacity, $tables[2]->capacity);

                            if ($excess < $bestExcess || ($excess === $bestExcess && $maxCap < $bestMaxCapacity)) {
                                $bestExcess = $excess;
                                $bestMaxCapacity = $maxCap;
                                $bestCombination = $tables;
                            }
                        }
                    }
                }
            }

            if ($bestCombination !== null) {
                return collect($bestCombination);
            }
        }

        return null;
    }
    
    /**
     * Limpiar caché de disponibilidad
     */
    protected function clearAvailabilityCache(string $location, string $date): void
    {
        // Limpiar caché de todos los horarios de ese día/ubicación
        $pattern = "availability:{$location}:{$date}:*";
        Cache::flush(); 
    }
    
    /**
     * Obtener estado de mesas en tiempo real para una fecha/hora específica
     */
    public function getTablesAvailability(string $date, string $time): array
    {
        $checkTime = Carbon::parse("$date $time");
        
        // Obtener reservas activas en este momento específico
        $overlappingReservations = DB::table('reservations as r')
            ->join('reservation_table as rt', 'r.id', '=', 'rt.reservation_id')
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->whereRaw('DATE(r.reservation_date) = ?', [$date])
            ->where('r.status', '!=', 'cancelled')
            ->select(
                'rt.table_id',
                'r.id as reservation_id',
                'r.reservation_time',
                'r.party_size',
                'r.duration_minutes',
                'u.name as user_name',
                'u.email as user_email'
            )
            ->get()
            ->filter(function ($reservation) use ($checkTime, $date) {
                // Calcular inicio y fin de esta reserva
                $resStart = Carbon::parse("$date {$reservation->reservation_time}");
                $resEnd = $resStart->copy()->addMinutes($reservation->duration_minutes);
                
                // Verificar si el tiempo consultado cae dentro de la reserva (inclusive)
                return $checkTime->between($resStart, $resEnd, true);
            })
            ->groupBy('table_id');

        // Obtener todas las mesas
        $tables = DB::table('tables')
            ->orderBy('location')
            ->orderBy('table_number')
            ->get()
            ->groupBy('location');

        // Construir respuesta con estado de cada mesa
        $result = [];
        foreach ($tables as $location => $locationTables) {
            $result[$location] = $locationTables->map(function ($table) use ($overlappingReservations, $date) {
                $tableData = [
                    'id' => $table->id,
                    'table_number' => $table->table_number,
                    'capacity' => $table->capacity,
                    'status' => 'available',
                    'reservation' => null,
                ];

                // Verificar si está ocupada (el filtro ya hizo el trabajo de solapamiento)
                if (isset($overlappingReservations[$table->id])) {
                    $reservation = $overlappingReservations[$table->id]->first();
                    
                    $resStartTime = Carbon::parse("$date {$reservation->reservation_time}");
                    $resEndTime = $resStartTime->copy()->addMinutes($reservation->duration_minutes);
                    
                    $tableData['status'] = 'occupied';
                    $tableData['reservation'] = [
                        'id' => $reservation->reservation_id,
                        'user_name' => $reservation->user_name,
                        'user_email' => $reservation->user_email,
                        'party_size' => $reservation->party_size,
                        'time_range' => $resStartTime->format('H:i') . ' - ' . $resEndTime->format('H:i'),
                        'start_time' => $resStartTime->format('H:i'),
                        'end_time' => $resEndTime->format('H:i'),
                    ];
                }

                return $tableData;
            })->values();
        }

        // Calcular estadísticas
        $totalTables = collect($result)->flatten(1)->count();
        $occupiedTables = collect($result)->flatten(1)->where('status', 'occupied')->count();
        $availableTables = $totalTables - $occupiedTables;

        return [
            'summary' => [
                'total_tables' => $totalTables,
                'available' => $availableTables,
                'occupied' => $occupiedTables,
            ],
            'tables_by_location' => $result,
        ];
    }

    /**
     * Cancelar una reserva existente
     */
    public function cancelReservation(int $reservationId): Reservation
    {
        $reservation = Reservation::with('tables')->find($reservationId);
        
        if (!$reservation) {
            throw new \Exception('Reserva no encontrada');
        }
        
        if ($reservation->status === 'cancelled') {
            throw new \Exception('La reserva ya está cancelada');
        }
        
        // Validar que la reserva es futura (no se puede cancelar una reserva pasada o en curso)
        $reservationDate = Carbon::parse($reservation->reservation_date)->format('Y-m-d');
        $reservationDateTime = Carbon::parse($reservationDate . ' ' . $reservation->reservation_time);
        if ($reservationDateTime->isPast()) {
            throw new \Exception('No se puede cancelar una reserva pasada');
        }
        
        // Actualizar estado
        $reservation->update(['status' => 'cancelled']);
        
        // Invalidar caché de disponibilidad
        $this->clearAvailabilityCache($reservation->location, $reservationDate);
        
        return $reservation->fresh('tables');
    }
}
