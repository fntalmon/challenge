<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear mesas de prueba
        $locations = ['A', 'B', 'C', 'D'];
        foreach ($locations as $location) {
            for ($i = 1; $i <= 5; $i++) {
                Table::create([
                    'location' => $location,
                    'table_number' => $i,
                    'capacity' => match ($i) {
                        1, 2 => 2,
                        3, 4 => 4,
                        5 => 6,
                    },
                    'is_available' => true,
                ]);
            }
        }

        // Crear usuarios de prueba
        User::factory()->count(5)->create();
    }

    protected function nextWeekdayDate(): string
    {
        return Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');
    }

    /** @test */
    public function puede_crear_reserva_basica_lunes_viernes()
    {
        $user = User::first();
        $futureDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '12:00',
            'party_size' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'location', 'tables']
            ]);

        $this->assertDatabaseHas('reservations', [
            'user_id' => $user->id,
            'party_size' => 2,
        ]);
    }

    /** @test */
    public function rechaza_horario_antes_de_10am_lunes_viernes()
    {
        $user = User::first();
        $futureDate = Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d');

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '08:00',
            'party_size' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function acepta_horario_valido_sabado_22_a_02()
    {
        $user = User::first();
        $nextSaturday = Carbon::now()->next(Carbon::SATURDAY)->format('Y-m-d');

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $nextSaturday,
            'reservation_time' => '23:00',
            'party_size' => 4,
        ]);

        $response->assertStatus(201);
    }

    /** @test */
    public function rechaza_horario_invalido_sabado()
    {
        $user = User::first();
        $nextSaturday = Carbon::now()->next(Carbon::SATURDAY)->format('Y-m-d');

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $nextSaturday,
            'reservation_time' => '20:00',
            'party_size' => 4,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function acepta_horario_valido_domingo_12_a_16()
    {
        $user = User::first();
        $nextSunday = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $nextSunday,
            'reservation_time' => '13:00',
            'party_size' => 4,
        ]);

        $response->assertStatus(201);
    }

    /** @test */
    public function rechaza_horario_invalido_domingo()
    {
        $user = User::first();
        $nextSunday = Carbon::now()->next(Carbon::SUNDAY)->format('Y-m-d');

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $nextSunday,
            'reservation_time' => '18:00',
            'party_size' => 4,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function combina_dos_mesas_para_8_personas()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '14:00',
            'party_size' => 8,
        ]);

        $response->assertStatus(201);

        $reservation = Reservation::first();
        $this->assertGreaterThanOrEqual(2, $reservation->tables->count());
        $this->assertLessThanOrEqual(3, $reservation->tables->count());

        // Todas las mesas deben ser de la misma ubicación
        $locations = $reservation->tables->pluck('location')->unique();
        $this->assertEquals(1, $locations->count());
    }

    /** @test */
    public function combina_tres_mesas_para_12_personas()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '14:00',
            'party_size' => 12,
        ]);

        $response->assertStatus(201);

        $reservation = Reservation::first();
        $this->assertGreaterThanOrEqual(2, $reservation->tables->count());
        $this->assertLessThanOrEqual(3, $reservation->tables->count());
    }

    /** @test */
    public function asigna_ubicacion_automaticamente_por_orden()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '15:00',
            'party_size' => 2,
        ]);

        $response->assertStatus(201);

        $reservation = Reservation::first();
        $this->assertEquals('A', $reservation->location);
    }

    /** @test */
    public function previene_solapamiento_de_reservas()
    {
        $user1 = User::first();
        $user2 = User::skip(1)->first();
        $futureDate = $this->nextWeekdayDate();

        // Primera reserva 18:00-20:00
        $response1 = $this->postJson('/api/reservations', [
            'user_id' => $user1->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '18:00',
            'party_size' => 14, // Ocupar todas las mesas de ubicación A
        ]);
        $response1->assertStatus(201);

        // Intentar reservar a las 19:00 (solapa con anterior)
        $response2 = $this->postJson('/api/reservations', [
            'user_id' => $user2->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'party_size' => 2,
        ]);

        // Debe crear en otra ubicación o usar ubicación B si A está ocupada
        $response2->assertStatus(201);

        $reservation1 = Reservation::find(1);
        $reservation2 = Reservation::find(2);

        // Si son misma ubicación, no deben compartir mesas
        if ($reservation1->location === $reservation2->location) {
            $tables1 = $reservation1->tables->pluck('id');
            $tables2 = $reservation2->tables->pluck('id');
            $this->assertTrue($tables1->intersect($tables2)->isEmpty());
        }
    }

    /** @test */
    public function listado_por_fecha_agrupa_por_ubicacion()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        // Crear múltiples reservas
        $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '12:00',
            'party_size' => 2,
        ]);

        $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '14:00',
            'party_size' => 4,
        ]);

        $response = $this->getJson("/api/reservations/by-date?date={$futureDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'date',
                'data'
            ]);
    }

    /** @test */
    public function valida_campos_requeridos()
    {
        $response = $this->postJson('/api/reservations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'reservation_date', 'reservation_time', 'party_size']);
    }

    /** @test */
    public function rechaza_party_size_invalido()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '14:00',
            'party_size' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['party_size']);
    }

    /** @test */
    public function rechaza_usuario_inexistente()
    {
        $futureDate = $this->nextWeekdayDate();

        $response = $this->postJson('/api/reservations', [
            'user_id' => 9999,
            'reservation_date' => $futureDate,
            'reservation_time' => '14:00',
            'party_size' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    /** @test */
    public function duracion_default_es_2_horas()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '14:00',
            'party_size' => 2,
        ]);

        $reservation = Reservation::first();
        $this->assertEquals(120, $reservation->duration_minutes);
    }

    /**
     * @test
     * TODO: Bug detectado - el overlap detection no funciona correctamente en tests
     * Las reservas se crean pero no se detectan como "ocupadas" en queries subsiguientes
     * Posible causa: formato de reservation_time o lógica de Carbon::parse con fechas futuras
     * Este test debería pasar una vez que se corrija el overlap detection
     */
    public function salta_ubicacion_si_capacidad_insuficiente()
    {
        //$this->markTestSkipped('BUG: Overlap detection no funciona en tests - requiere investigación');

        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        // Estrategia: llenar ubicación A con reservas no-solapantes para dejar capacidad limitada
        // Luego en el mismo horario, forzar el salto a B

        // 3 reservas en A a las 14:00 (ocupar 3 mesas)
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/reservations', [
                'user_id' => $user->id,
                'reservation_date' => $futureDate,
                'reservation_time' => '14:00',
                'party_size' => 4,
            ])->assertStatus(201);
            \Illuminate\Support\Facades\Cache::flush();
        }

        // Ahora A tiene 2 mesas libres (2+2=4 o 2+6=8 dependiendo)
        // Pedir 12 personas → necesita 3 mesas, solo tiene 2 disponibles
        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '14:00',
            'party_size' => 12,
        ]);

        $response->assertStatus(201);
        $reservation = Reservation::orderByDesc('id')->first();


        dump($reservation->toArray());
        // Como A no tiene 3 mesas disponibles, debe ir a B
        $this->assertNotEquals('A', $reservation->location);
    }

    /** @test */
    public function asigna_12_personas_a_A_si_tiene_capacidad()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '13:00',
            'party_size' => 12,
        ]);

        $response->assertStatus(201);
        $reservation = Reservation::first();
        $this->assertEquals('A', $reservation->location);
        $this->assertGreaterThanOrEqual(2, $reservation->tables->count());
        $this->assertLessThanOrEqual(3, $reservation->tables->count());
    }

    /** @test */
    public function availability_endpoint_marks_occupied()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '11:00',
            'party_size' => 2,
        ])->assertStatus(201);

        $response = $this->getJson("/api/tables/availability?date={$futureDate}&time=11:00");
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertGreaterThan(0, $data['summary']['occupied']);

        // Al menos una mesa en A debe estar 'occupied'
        $found = false;
        if (isset($data['tables_by_location']['A'])) {
            foreach ($data['tables_by_location']['A'] as $t) {
                if ($t['status'] === 'occupied') {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found);
    }

    /** @test */
    public function rechaza_cuando_no_hay_disponibilidad()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        // Llenar todas las mesas con reservas para la misma hora
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/reservations', [
                'user_id' => $user->id,
                'reservation_date' => $futureDate,
                'reservation_time' => '20:00',
                'party_size' => 2,
            ])->assertStatus(201);
        }

        // La siguiente reserva debe ser rechazada (422)
        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '20:00',
            'party_size' => 2,
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function combina_mesas_eficientemente_para_10_personas()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        // 10 personas: algoritmo debe elegir 2 mesas (6+4=10) en vez de 3
        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '19:00',
            'party_size' => 10,
        ]);

        $response->assertStatus(201);
        $reservation = Reservation::first();
        
        // Debe usar 2 mesas (óptimo: 6+4 con exceso=0)
        $this->assertEquals(2, $reservation->tables->count());
        
        // La capacidad total debe ser exacta o mínima
        $totalCapacity = $reservation->tables->sum('capacity');
        $this->assertEquals(10, $totalCapacity);
        
        // Verificar que contiene mesa de 6 y mesa de 4
        $capacities = $reservation->tables->pluck('capacity')->sort()->values();
        $this->assertEquals([4, 6], $capacities->toArray());
    }

    /** @test */
    public function cache_de_disponibilidad_se_invalida_correctamente()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        // Primera consulta de disponibilidad (se cachea)
        $response1 = $this->getJson("/api/tables/availability?date={$futureDate}&time=15:00");
        $response1->assertStatus(200);
        $availableBefore = $response1->json('summary.available');

        // Crear reserva (debe invalidar cache)
        $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '15:00',
            'party_size' => 4,
        ])->assertStatus(201);

        // Segunda consulta debe mostrar menos disponibilidad
        $response2 = $this->getJson("/api/tables/availability?date={$futureDate}&time=15:00");
        $response2->assertStatus(200);
        $availableAfter = $response2->json('summary.available');

        $this->assertLessThan($availableBefore, $availableAfter);
        $this->assertGreaterThan(0, $response2->json('summary.occupied'));
    }

    /** @test */
    public function mantiene_orden_ubicaciones_con_capacidad_empatada()
    {
        $user = User::first();
        $futureDate = $this->nextWeekdayDate();

        // Llenar ubicación A parcialmente (dejar 1 mesa de 2 personas)
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/reservations', [
                'user_id' => $user->id,
                'reservation_date' => $futureDate,
                'reservation_time' => '16:00',
                'party_size' => 2,
            ])->assertStatus(201);
        }

        // A tiene 1 mesa libre (cap 6), B tiene todas libres
        // Pedir 6 personas → A puede con 1 mesa, B también
        // Debe elegir A por orden de prioridad
        $response = $this->postJson('/api/reservations', [
            'user_id' => $user->id,
            'reservation_date' => $futureDate,
            'reservation_time' => '16:00',
            'party_size' => 6,
        ]);

        $response->assertStatus(201);
        $reservation = Reservation::orderByDesc('id')->first();
        $this->assertEquals('A', $reservation->location);
    }
}
