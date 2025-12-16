<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Table extends Model
{
    protected $fillable = [
        'location',
        'table_number',
        'capacity',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    /**
     * Reservas asociadas a esta mesa
     */
    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_table')
            ->withTimestamps();
    }
}
