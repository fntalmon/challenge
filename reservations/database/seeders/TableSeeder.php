<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = ['A', 'B', 'C', 'D'];
        
        // Crear 5 mesas por ubicación (20 mesas en total)
        foreach ($locations as $location) {
            for ($tableNumber = 1; $tableNumber <= 5; $tableNumber++) {
                Table::create([
                    'location' => $location,
                    'table_number' => $tableNumber,
                    'capacity' => match($tableNumber) {
                        1, 2 => 2,      // Mesas pequeñas para parejas
                        3, 4 => 4,      // Mesas medianas
                        5 => 6,         // Mesa grande
                    },
                    'is_available' => true,
                ]);
            }
        }
    }
}
