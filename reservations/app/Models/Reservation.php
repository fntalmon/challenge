<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'reservation_date',
        'reservation_time',
        'party_size',
        'location',
        'duration_minutes',
        'status',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'reservation_time' => 'datetime:H:i',
    ];

    // Constantes de horarios por día
    public const SCHEDULES = [
        'weekday' => ['start' => '10:00', 'end' => '24:00'], // L-V
        'saturday' => ['start' => '22:00', 'end' => '02:00'], // Sábado
        'sunday' => ['start' => '12:00', 'end' => '16:00'], // Domingo
    ];

    public const MAX_TABLES_PER_RESERVATION = 3;
    public const DEFAULT_DURATION_MINUTES = 120;
    public const MIN_ADVANCE_MINUTES = 15;

    /**
     * Usuario que realizó la reserva
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mesas asignadas a esta reserva (hasta 3)
     */
    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(Table::class, 'reservation_table')
            ->withTimestamps();
    }
}
