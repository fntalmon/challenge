# 🍽️ Sistema de Reservas de Mesas

API REST para gestión de reservas con asignación automática de ubicación y combinación inteligente de mesas.

## 🌐 Demo en Producción

**🔗 API deployada:** https://challenge-production-637e.up.railway.app

**📖 Documentación interactiva (Swagger UI):** https://challenge-production-637e.up.railway.app/api/documentation

> La API está lista para usar. Incluye datos de prueba (20 mesas en 4 ubicaciones + 6 usuarios).

## 🎯 Funcionalidades Implementadas

### Punto 3: Solicitud de Reserva
- ✅ Validación de horarios por día de la semana
  - Lunes a Viernes: 10:00 - 24:00
  - Sábado: 22:00 - 02:00
  - Domingo: 12:00 - 16:00
- ✅ Asignación automática de ubicación por orden (A → B → C → D)
- ✅ Combinación óptima de hasta 3 mesas por reserva
- ✅ Cache de disponibilidad en memoria por ubicación
- ✅ Duración default: 2 horas
- ✅ Reserva mínima: 15 minutos de anticipación
- ✅ Prevención de solapamientos entre reservas
- ✅ **Cancelación de reservas futuras**

### Punto 4: Listado por Fecha
- ✅ Consulta SQL optimizada con JOINs
- ✅ Agrupación por ubicación
- ✅ Incluye información de mesas asignadas en una sola query

## 📡 Endpoints Principales

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/reservations` | Crear nueva reserva |
| `PATCH` | `/api/reservations/{id}/cancel` | Cancelar reserva existente |
| `GET` | `/api/reservations/by-date?date=YYYY-MM-DD` | Listar reservas por fecha |
| `GET` | `/api/tables/availability?date=YYYY-MM-DD&time=HH:mm` | Consultar disponibilidad en tiempo real |

## 📖 Cómo Usar Swagger

La forma más fácil de probar la API es usando la **documentación interactiva**:

1. Abrí: https://challenge-production-637e.up.railway.app/api/documentation
2. Expandí cualquier endpoint clickeando sobre él
3. Click en **"Try it out"**
4. Completá los parámetros de ejemplo
5. Click en **"Execute"**
6. Verás la respuesta en tiempo real

**Usuarios disponibles para pruebas:** IDs del 1 al 6

## 🧪 Guía de Pruebas Paso a Paso

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
- ✅ Status: `201 Created`
- ✅ Location: `A` (primera ubicación disponible)
- ✅ Tables: 1 mesa de capacidad 2
- ✅ Duration: 120 minutos

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
- ✅ Status: `201 Created`
- ✅ Tables: 2 mesas (ej: capacidad 4 + 4 o 6 + 2)
- ✅ Todas las mesas en la misma ubicación
- ✅ Capacidad total ≥ 8

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
- ✅ Tables: 2 mesas con capacidades 6 + 4 = 10 (exceso 0)
- ✅ NO usa 3 mesas (ej: 6 + 2 + 2)
- ✅ Selección óptima con menor exceso

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
- ✅ Status: `201 Created`
- ✅ Location: `B` (saltó a siguiente ubicación)
- ✅ NO usa ubicación A (ocupada)

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
- ❌ Status: `422 Unprocessable Entity`
- ❌ Message: "Horario no válido. Lunes a Viernes: 10:00 a 24:00"

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
- ✅ Tables: 2 o 3 mesas según disponibilidad
- ✅ Combinación óptima (ej: 6 + 4 + 2)
- ✅ Capacidad total ≥ 12

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
- ✅ Status: `200 OK`
- ✅ Message: "Reserva cancelada exitosamente"
- ✅ Status de reserva: `"cancelled"`

**Validaciones automáticas:**
- ❌ No permite cancelar reservas ya canceladas
- ❌ No permite cancelar reservas pasadas
- ✅ Invalida cache de disponibilidad automáticamente

---

## 🏗️ Arquitectura Técnica

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

## ✅ Testing

Suite de **27 tests** con **113 assertions** cubriendo:

- ✅ Validación de horarios por día (L-V, Sáb, Dom)
- ✅ Combinación de 2 y 3 mesas
- ✅ Algoritmo de selección óptima
- ✅ Prevención de solapamientos
- ✅ Asignación de ubicación por orden
- ✅ Cache de disponibilidad
- ✅ **Cancelación de reservas** (futuras, pasadas, duplicadas)
- ✅ Edge cases (capacidad límite, sin disponibilidad)

```bash
php artisan test --filter ReservationTest
```

**Resultado:** ✅ 27 passed (113 assertions)

## 📊 Estructura de Datos

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

## 💻 Instalación Local (Opcional)

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

## 🛠️ Stack Tecnológico

- **Framework:** Laravel 12
- **PHP:** 8.2+
- **Base de datos:** SQLite (producción y desarrollo)
- **Cache:** Array driver (in-memory)
- **Testing:** PHPUnit
- **Documentación:** Swagger/OpenAPI (L5-Swagger)
- **Deploy:** Railway.app

## 📝 Notas de Implementación

### Decisiones de Diseño

1. **SQLite en producción:** Simplifica deployment y es suficiente para el volumen esperado
2. **Cache array:** Evita dependencias externas (Redis) manteniendo performance
3. **Validación estricta:** Horarios y solapamientos validados en servicio, no solo en controller
4. **Query optimizada:** Punto 4 resuelto con una sola consulta SQL usando JOINs y GROUP_CONCAT

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
