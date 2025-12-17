<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TablesSeeder extends Seeder
{
    /**
     * Seed the tables for the restaurant.
     * 
     * Creates 5 tables in each of the 4 locations (A, B, C, D).
     * Each location has:
     * - 2 tables with capacity for 2 people
     * - 2 tables with capacity for 4 people
     * - 1 table with capacity for 6 people
     * 
     * Total: 20 tables, 80 seats
     */
    public function run(): void
    {
        $locations = ['A', 'B', 'C', 'D'];
        
        foreach ($locations as $location) {
            for ($i = 1; $i <= 5; $i++) {
                Table::create([
                    'location' => $location,
                    'table_number' => $i,
                    'capacity' => match ($i) {
                        1, 2 => 2,  // Small tables
                        3, 4 => 4,  // Medium tables
                        5 => 6,     // Large table
                    },
                    'is_available' => true,
                ]);
            }
        }

        $this->command->info('✓ Created 20 tables across 4 locations (A, B, C, D)');
    }
}
