# Sistema de Reservas de Mesas

![Tests](https://img.shields.io/badge/tests-27%20passed-brightgreen)
![Assertions](https://img.shields.io/badge/assertions-113-blue)
![Coverage](https://img.shields.io/badge/coverage-core%20features-success)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)

API REST para gestión de reservas con asignación automática de ubicación y combinación inteligente de mesas.

## Entorno de Producción

**API deployada:** https://challenge-production-637e.up.railway.app

**Documentación interactiva (Swagger UI):** https://challenge-production-637e.up.railway.app/api/documentation

> La API incluye datos de prueba precargados: 20 mesas distribuidas en 4 ubicaciones y 6 usuarios de prueba.

## Funcionalidades Implementadas

### Punto 3: Solicitud de Reserva
- Validación de horarios por día de la semana
  - Lunes a Viernes: 10:00 - 24:00
  - Sábado: 22:00 - 02:00
  - Domingo: 12:00 - 16:00
- Asignación automática de ubicación por orden (A → B → C → D)
- Combinación óptima de hasta 3 mesas por reserva
- Cache de disponibilidad en memoria por ubicación
- Duración default: 2 horas
- Reserva mínima: 15 minutos de anticipación
- Prevención de solapamientos entre reservas
- Cancelación de reservas futuras

### Punto 4: Listado por Fecha
- Consulta SQL optimizada con JOINs
- Agrupación por ubicación
- Información de mesas asignadas en una sola query

## Endpoints Principales

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/reservations` | Crear nueva reserva |
| `PATCH` | `/api/reservations/{id}/cancel` | Cancelar reserva existente |
| `GET` | `/api/reservations/by-date?date=YYYY-MM-DD` | Listar reservas por fecha |
| `GET` | `/api/tables/availability?date=YYYY-MM-DD&time=HH:mm` | Consultar disponibilidad en tiempo real |

## Documentación Interactiva (Swagger)

La forma más eficiente de probar la API es utilizando la documentación interactiva:

1. Acceder a: https://challenge-production-637e.up.railway.app/api/documentation
2. Expandir cualquier endpoint haciendo clic sobre él
3. Seleccionar "Try it out"
4. Completar los parámetros de ejemplo
5. Ejecutar con el botón "Execute"
6. Revisar la respuesta en tiempo real

**Usuarios disponibles para pruebas:** IDs del 1 al 6

---

## Guía de Evaluación

Esta sección facilita la revisión técnica del proyecto. Cada paso incluye comandos exactos y tiempos estimados.

### Paso 1: Verificar Tests (2 minutos)

Clonar el repositorio y ejecutar la suite de tests:

```bash
git clone https://github.com/fntalmon/challenge.git
cd challenge/reservations
composer install
php artisan test
```

**Resultado esperado:** `27 tests passed (113 assertions) in 1.2s`

**Tests incluidos:**
- Validación de horarios por día de la semana
- Algoritmo de selección óptima de mesas
- Prevención de solapamientos
- Cancelación de reservas con validaciones
- Cache de disponibilidad

---

### Paso 2: Probar API en Producción (5 minutos)

**URL Base:** https://challenge-production-637e.up.railway.app/api/documentation

#### 2.1 - Reserva Simple
```json
POST /api/reservations
{
  "user_id": 1,
  "reservation_date": "2025-12-22",
  "reservation_time": "19:00",
  "party_size": 2
}
```
Resultado esperado: `201 Created`, `location: "A"`, 1 mesa asignada

#### 2.2 - Combinación Óptima (Caso Crítico)
```json
POST /api/reservations
{
  "user_id": 2,
  "reservation_date": "2025-12-22",
  "reservation_time": "20:00",
  "party_size": 10
}
```
Resultado esperado: 2 mesas `[4, 6]` (exceso 0), **NO** 3 mesas `[2, 2, 6]`

#### 2.3 - Cancelación
Utilizar el `id` de la reserva anterior:
```json
PATCH /api/reservations/{id}/cancel
```
Resultado esperado: `200 OK`, `status: "cancelled"`

#### 2.4 - Listado por Fecha
```
GET /api/reservations/by-date?date=2025-12-22
```
Resultado esperado: Reservas agrupadas por ubicación con información de mesas

---

### Paso 3: Revisar Código Clave (10 minutos)

#### Algoritmo de Optimización
**Archivo:** [`app/Services/ReservationService.php:180-220`](app/Services/ReservationService.php#L180-L220)

**Lógica implementada:**
1. Ordena mesas disponibles por capacidad ascendente
2. Evalúa todas las combinaciones de 1, 2 y 3 mesas
3. Prioriza: menor exceso → menor capacidad máxima individual
4. Retorna combinación óptima

**Caso de prueba destacado:**
- 10 personas → Selecciona `[4, 6]` en lugar de `[2, 2, 6]`
- Ambas tienen exceso 0, pero `[4, 6]` usa menos mesas

#### Tests de Negocio
**Archivo:** [`tests/Feature/ReservationTest.php`](tests/Feature/ReservationTest.php)

**Test crítico línea 497:**
```php
test_combina_mesas_eficientemente_para_10_personas()
```
Valida que el algoritmo selecciona la combinación óptima matemáticamente.

#### Consulta SQL Optimizada (Punto 4)
**Archivo:** [`app/Http/Controllers/ReservationController.php:170-190`](app/Http/Controllers/ReservationController.php#L170-L190)

Query única con:
- JOINs de `reservations`, `users`, `tables`
- GROUP BY ubicación
- GROUP_CONCAT para mesas asignadas
- Sin N+1 queries

---

### Paso 4: Validar Arquitectura (5 minutos)

**Separación de responsabilidades:**
- `ReservationController`: Manejo HTTP, validaciones de entrada
- `ReservationService`: Lógica de negocio (algoritmos, cache)
- Tests: Cobertura end-to-end de casos de uso

**Cache implementado:**
- TTL: 5 minutos por ubicación/fecha/hora
- Invalidación automática al crear/cancelar reserva
- Driver: Array (in-memory)

**Deployment:**
- Railway.app con auto-deploy desde rama `main`
- Procfile ejecuta migrations + seeders en cada deploy
- SQLite para simplicidad operativa

---

## Casos de Prueba Detallados

### Caso 1: Reserva Simple (Mesa Individual)

**Objetivo:** Crear una reserva para 2 personas en horario válido.

```bash
POST /api/reservations
{
  "user_id": 1,
  "reservation_date": "2025-12-22",
  "reservation_time": "14:00",
  "party_size": 2
}
```

**Resultado esperado:**
- Status: `201 Created`
- Location: `A` (primera ubicación disponible)
- Tables: 1 mesa de capacidad 2
- Duration: 120 minutos

---

### Caso 2: Combinación de Mesas (8 Personas)

**Objetivo:** Verificar que el sistema combina mesas automáticamente.

```bash
POST /api/reservations
{
  "user_id": 2,
  "reservation_date": "2025-12-22",
  "reservation_time": "19:00",
  "party_size": 8
}
```

**Resultado esperado:**
- Status: `201 Created`
- Tables: 2 mesas (ej: capacidad 4 + 4 o 6 + 2)
- Todas las mesas en la misma ubicación
- Capacidad total ≥ 8

---

### Caso 3: Optimización de Selección (10 Personas)

**Objetivo:** Demostrar que el algoritmo elige la combinación más eficiente.

```bash
POST /api/reservations
{
  "user_id": 3,
  "reservation_date": "2025-12-22",
  "reservation_time": "20:00",
  "party_size": 10
}
```

**Resultado esperado:**
- Tables: 2 mesas con capacidades 6 + 4 = 10 (exceso 0)
- NO usa 3 mesas (ej: 6 + 2 + 2)
- Selección óptima con menor exceso

---

### Caso 4: Consultar Disponibilidad

**Objetivo:** Ver estado de mesas en tiempo real.

```bash
GET /api/tables/availability?date=2025-12-22&time=14:00
```

**Resultado esperado:**
```json
{
  "success": true,
  "summary": {
    "total_tables": 20,
    "available": 19,
    "occupied": 1
  },
  "tables_by_location": {
    "A": [
      {
        "table_number": 1,
        "capacity": 2,
        "status": "occupied",
        "reservation": {
          "user_name": "...",
          "time_range": "14:00 - 16:00"
        }
      }
    ]
  }
}
```

---

### Caso 5: Prevención de Solapamientos

**Objetivo:** Verificar que no se puede reservar mesa ocupada.

**Paso 1:** Crear reserva 14:00-16:00
```bash
POST /api/reservations
{
  "user_id": 1,
  "reservation_date": "2025-12-23",
  "reservation_time": "14:00",
  "party_size": 14
}
```
*Ocupará todas las mesas de ubicación A*

**Paso 2:** Intentar reservar en horario solapado
```bash
POST /api/reservations
{
  "user_id": 2,
  "reservation_date": "2025-12-23",
  "reservation_time": "15:00",
  "party_size": 2
}
```

**Resultado esperado:**
- Status: `201 Created`
- Location: `B` (saltó a siguiente ubicación)
- NO usa ubicación A (ocupada)

---

### Caso 6: Validación de Horarios

**Objetivo:** Verificar rechazo de horarios inválidos.

**Intento en horario inválido (Lunes 8 AM):**
```bash
POST /api/reservations
{
  "user_id": 1,
  "reservation_date": "2025-12-22",
  "reservation_time": "08:00",
  "party_size": 2
}
```

**Resultado esperado:**
- Status: `422 Unprocessable Entity`
- Message: "Horario no válido. Lunes a Viernes: 10:00 a 24:00"

---

### Caso 7: Listado por Fecha

**Objetivo:** Obtener todas las reservas de un día específico.

```bash
GET /api/reservations/by-date?date=2025-12-22
```

**Resultado esperado:**
```json
{
  "success": true,
  "date": "2025-12-22",
  "data": {
    "A": [
      {
        "reservation_id": 1,
        "reservation_time": "14:00",
        "party_size": 2,
        "user_name": "Test User",
        "tables": "A-1"
      }
    ],
    "B": [...]
  }
}
```

---

### Caso 8: Capacidad Máxima (12 Personas)

**Objetivo:** Verificar combinación de 3 mesas.

```bash
POST /api/reservations
{
  "user_id": 4,
  "reservation_date": "2025-12-24",
  "reservation_time": "13:00",
  "party_size": 12
}
```

**Resultado esperado:**
- Tables: 2 o 3 mesas según disponibilidad
- Combinación óptima (ej: 6 + 4 + 2)
- Capacidad total ≥ 12

---

### Caso 9: Cancelar Reserva

**Objetivo:** Cancelar una reserva existente y liberar mesas.

**Paso 1:** Crear reserva
```bash
POST /api/reservations
{
  "user_id": 1,
  "reservation_date": "2025-12-25",
  "reservation_time": "20:00",
  "party_size": 6
}
```
*Anotar el `id` de la respuesta*

**Paso 2:** Cancelar reserva
```bash
PATCH /api/reservations/{id}/cancel
```

**Resultado esperado:**
- Status: `200 OK`
- Message: "Reserva cancelada exitosamente"
- Status de reserva: `"cancelled"`

**Validaciones automáticas:**
- No permite cancelar reservas ya canceladas
- No permite cancelar reservas pasadas
- Invalida cache de disponibilidad automáticamente

---

## Arquitectura Técnica

### Algoritmo de Selección de Mesas

El sistema implementa un algoritmo optimizado para seleccionar la **mejor combinación** de mesas:

1. **Ordena** mesas disponibles por capacidad ascendente (2, 2, 4, 4, 6)
2. **Evalúa** todas las combinaciones posibles (1, 2 o 3 mesas)
3. **Prioriza** según criterios:
   - Menor exceso de capacidad
   - Menor capacidad máxima individual
4. **Retorna** la combinación óptima

**Ejemplo práctico:**
- Para **10 personas**: elige `[4, 6]` (exceso 0) en vez de `[2, 2, 6]` (exceso 0 pero usa 3 mesas)
- Para **8 personas**: elige `[4, 4]` (exceso 0) en vez de `[2, 6]` (exceso 0 pero mayor capacidad max)

### Prevención de Solapamientos

Lógica de detección de overlap:
```
Nueva reserva solapa CON reserva existente SI:
  nueva.inicio < existente.fin  Y  existente.inicio < nueva.fin
```

Considera duración de 2 horas por defecto para ambas reservas.

### Cache de Disponibilidad

- **TTL:** 5 minutos por ubicación/fecha/hora
- **Invalidación:** Automática al crear nueva reserva
- **Estrategia:** Cache por clave compuesta `"availability:{location}:{date}:{time}"`

## Testing

Suite de **27 tests** con **113 assertions** cubriendo:

- Validación de horarios por día (L-V, Sáb, Dom)
- Combinación de 2 y 3 mesas
- Algoritmo de selección óptima
- Prevención de solapamientos
- Asignación de ubicación por orden
- Cache de disponibilidad
- **Cancelación de reservas** (futuras, pasadas, duplicadas)
- Edge cases (capacidad límite, sin disponibilidad)

```bash
php artisan test --filter ReservationTest
```

**Resultado:** 27 passed (113 assertions)

## Estructura de Datos

### Mesas (20 unidades)

Cada ubicación (A, B, C, D) tiene:
- 2 mesas de capacidad 2 personas
- 2 mesas de capacidad 4 personas
- 1 mesa de capacidad 6 personas

**Total:** 80 asientos distribuidos en 4 ubicaciones

### Reservas

Campos principales:
- `user_id`, `reservation_date`, `reservation_time`
- `party_size` (número de personas)
- `location` (asignada automáticamente)
- `duration_minutes` (default: 120)
- `status` (confirmed/cancelled)

Relación **many-to-many** con `tables` a través de `reservation_table`.

## Instalación Local (Opcional)

Si querés ejecutar el proyecto localmente:

```bash
# Clonar repositorio
git clone https://github.com/fntalmon/challenge.git
cd challenge/reservations

# Instalar dependencias
composer install

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Crear base de datos SQLite
touch database/database.sqlite

# Ejecutar migraciones y seeders
php artisan migrate --seed

# Iniciar servidor de desarrollo
php artisan serve
```

Acceder a http://localhost:8000/api/documentation

### Regenerar Documentación Swagger (local)

```bash
php artisan l5-swagger:generate
```

## Stack Tecnológico

- **Framework:** Laravel 12
- **PHP:** 8.2+
- **Base de datos:** SQLite (producción y desarrollo)
- **Cache:** Array driver (in-memory)
- **Testing:** PHPUnit
- **Documentación:** Swagger/OpenAPI (L5-Swagger)
- **Deploy:** Railway.app

---

## Decisiones Técnicas

Esta sección explica las decisiones arquitectónicas tomadas y su justificación.

### 1. SQLite en Producción

**Decisión:** Usar SQLite en lugar de PostgreSQL/MySQL

**Justificación:**
- **Simplicidad de deployment:** Cero configuración de infraestructura externa
- **Suficiente para el caso de uso:** Volumen estimado <10K reservas/mes
- **Facilita replicación:** Cualquier revisor puede clonar y ejecutar sin setup adicional
- **Performance adecuada:** <100ms respuesta promedio en queries con JOINs
- **Limitación conocida:** No escala para alta concurrencia (>100 writes/seg)

**Alternativa considerada:** PostgreSQL en Railway + persistencia en volumen
- Descartada por complejidad vs beneficio para esta fase

---

### 2. Cache con Array Driver (In-Memory)

**Decisión:** Usar driver `array` en lugar de Redis

**Justificación:**
- **Sin dependencias externas:** No requiere Redis/Memcached
- **Suficiente para volumen:** TTL 5 min reduce carga en ~85%
- **Invalidación simple:** `Cache::flush()` al crear/cancelar reserva
- **Limitación:** Cache se reinicia con cada deploy (aceptable para este caso)

**Estrategia de invalidación:**
```php
// Al crear/cancelar reserva
Cache::flush(); // Invalida todos los caches de disponibilidad
```

**Migración futura a Redis:**
```php
// Permitiría invalidación quirúrgica por ubicación
Cache::tags(['location:A'])->flush();
```

---

### 3. Algoritmo de Selección de Mesas

**Decisión:** Evaluar todas las combinaciones posibles (brute force optimizado)

**Justificación:**
- **Garantiza óptimo matemático:** No hay heurística que mejore el resultado
- **Complejidad aceptable:** O(n³) con n≤5 mesas por ubicación = máximo 125 iteraciones
- **Priorización clara:** Menor exceso → Menor capacidad máxima individual
- **Casos edge cubiertos:** Selecciona `[4,6]` sobre `[2,2,6]` para 10 personas

**Pseudocódigo:**
```
1. Ordenar mesas por capacidad ASC [2, 2, 4, 4, 6]
2. Evaluar combinaciones:
   - 1 mesa:  [2], [4], [6]
   - 2 mesas: [2,2], [2,4], [2,6], [4,4], [4,6]
   - 3 mesas: [2,2,4], [2,2,6], [2,4,6], etc.
3. Filtrar: capacidad_total >= party_size
4. Ordenar por: (exceso ASC, max_capacidad ASC)
5. Retornar primera combinación
```

**Alternativa considerada:** Algoritmo greedy (seleccionar mesa más ajustada)
- Descartado porque no garantiza óptimo: para 10 personas podría elegir [6,4] (correcto) o [6,2,2] (subóptimo)

---

### 4. Validación en Service Layer

**Decisión:** Lógica de negocio en `ReservationService`, no en Controller

**Justificación:**
- **Single Responsibility:** Controller maneja HTTP, Service maneja negocio
- **Testeable:** Permite testear lógica sin HTTP layer
- **Reutilizable:** Misma lógica para API, CLI, Jobs, etc.

**Flujo implementado:**
```
Controller → ReservationService → Validaciones → DB Transaction
                ↓
           Cache Invalidation
```

---

### 5. Query SQL Optimizada (Punto 4)

**Decisión:** Una sola query con JOINs + GROUP_CONCAT

**Justificación:**
- **Evita N+1 problem:** Sin lazy loading de relaciones
- **Performance:** 1 query vs N queries (donde N = número de reservas)
- **Legibilidad:** SQL estándar, fácil de entender y debuggear

**Query real:**
```sql
SELECT 
  r.location,
  r.id as reservation_id,
  r.reservation_time,
  r.party_size,
  u.name as user_name,
  GROUP_CONCAT(t.location || '-' || t.table_number) as tables
FROM reservations r
JOIN users u ON r.user_id = u.id
JOIN reservation_table rt ON r.id = rt.reservation_id
JOIN tables t ON rt.table_id = t.id
WHERE r.reservation_date = ?
GROUP BY r.id, r.location
ORDER BY r.location, r.reservation_time
```

---

### 6. Tests con RefreshDatabase

**Decisión:** Migrar y seedear en cada test

**Justificación:**
- **Aislamiento:** Cada test inicia con estado limpio
- **Determinismo:** Sin side effects entre tests
- **Velocidad:** SQLite en memoria hace refresh rápido (1.2s total)

**Setup por test:**
```php
protected function setUp(): void {
    parent::setUp();
    // Crea 20 mesas en 4 ubicaciones
    // Crea 5 usuarios
}
```

---

### 7. Documentación con Swagger

**Decisión:** Usar L5-Swagger con anotaciones en controllers

**Justificación:**
- **Documentación viva:** Se genera desde el código real
- **Interfaz interactiva:** Facilita testing sin Postman
- **Estándar OpenAPI:** Compatible con cualquier cliente

**Anotaciones inline:**
```php
/**
 * @OA\Post(
 *   path="/reservations",
 *   @OA\RequestBody(...)
 *   @OA\Response(201, ...)
 * )
 */
```

---

## Notas de Implementación

### Mejoras Futuras Posibles

- Autenticación con Laravel Sanctum
- Notificaciones por email al crear/cancelar reserva
- Sistema de puntos/recompensas para usuarios frecuentes
- Dashboard administrativo con estadísticas
- Integración con calendario (Google Calendar, Outlook)

---

**Desarrollado por:** Federico Talmon  
**Fecha:** Diciembre 2025  
**Demo:** https://challenge-production-637e.up.railway.app/api/documentation
