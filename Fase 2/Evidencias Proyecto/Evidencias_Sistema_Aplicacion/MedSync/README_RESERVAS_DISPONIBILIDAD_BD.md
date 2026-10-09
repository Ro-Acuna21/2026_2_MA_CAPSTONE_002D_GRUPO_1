# MedSync — Guía de integración de Reservas y Disponibilidad

## 1. Objetivo

Este documento explica la estructura de base de datos y los modelos Eloquent implementados para soportar el flujo de reservas de MedSync.

Su propósito es servir como referencia técnica para implementar en Laravel la lógica de negocio de:

- Consultar servicios.
- Consultar profesionales asociados a especialidades.
- Consultar disponibilidad de profesionales.
- Calcular horarios reservables.
- Crear reservas.
- Evitar reservas solapadas.
- Registrar historial.
- Reprogramar y cancelar reservas.
- Mostrar las reservas del paciente en **Mis Reservas**.

La estructura de base de datos necesaria ya fue implementada y probada. Lo pendiente es conectar estas entidades mediante servicios, controladores, validaciones y endpoints de Laravel.

---

## 2. Arquitectura actual de MedSync

MedSync utiliza dos conexiones PostgreSQL:

```text
core
center
```

### Base central

La conexión `core` apunta actualmente a:

```text
medsync_core
```

Contiene información global de la plataforma, por ejemplo:

```text
users
medical_centers
center_users
personal_access_tokens
cache
jobs
...
```

### Base de datos del centro

La conexión `center` apunta actualmente a:

```text
medsync_clinica_horizonte
```

Contiene la información administrativa del centro:

```text
health_insurances
patients
patient_addresses
professionals
specialties
professional_specialty
services
availabilities
appointments
appointment_history
```

Por este motivo, las tablas operacionales del centro no necesitan `medical_center_id`: la propia base seleccionada identifica el tenant.

> Importante: la resolución dinámica del centro por `slug` y el cambio automático de conexión `center` todavía deben implementarse más adelante.

---

## 3. Modelo general

```text
Specialty
    │
    ├── Services
    │
Professional
    ├── Specialties
    ├── Availabilities
    └── Appointments

Patient
    └── Appointments

Appointment
    ├── Patient
    ├── Professional
    ├── Service
    └── AppointmentHistory
```

Responsabilidades:

```text
specialties
→ define especialidades.

services
→ define prestaciones y su duración.

professional_specialty
→ indica qué especialidades puede atender un profesional.

availabilities
→ indica cuándo trabaja un profesional.

appointments
→ almacena las reservas realizadas.

appointment_history
→ conserva cambios importantes de una reserva.
```

---

## 4. Specialties

Tabla:

```text
specialties
```

Campos principales:

```text
id
name
description
is_active
created_at
updated_at
```

Reglas implementadas:

- El nombre no puede quedar vacío.
- El nombre es único ignorando mayúsculas/minúsculas y espacios exteriores.
- Puede desactivarse mediante `is_active`.

Relaciones Eloquent:

```text
Specialty
├── professionals()
└── services()
```

---

## 5. Professional Specialty

Tabla pivote:

```text
professional_specialty
```

Campos:

```text
professional_id
specialty_id
created_at
updated_at
```

La clave primaria es compuesta:

```text
professional_id + specialty_id
```

Antes de permitir una reserva debe comprobarse que el profesional tenga asociada la especialidad del servicio seleccionado.

Conceptualmente:

```text
service.specialty_id
        =
una specialty_id asociada al profesional
```

---

## 6. Services

Tabla:

```text
services
```

Campos principales:

```text
id
specialty_id
name
description
duration_minutes
is_active
created_at
updated_at
```

Ejemplo:

```text
Especialidad: Cardiología
Servicio: Consulta cardiológica
Duración: 30 minutos
```

Reglas implementadas:

- `specialty_id` debe existir.
- El nombre no puede quedar vacío.
- `duration_minutes` debe ser mayor que cero.
- El nombre no se repite dentro de una misma especialidad ignorando mayúsculas/minúsculas.
- Puede desactivarse.

Relaciones:

```text
Service
├── specialty()
└── appointments()
```

`appointments` guarda `service_id`, no `specialty_id`, porque la especialidad se obtiene mediante:

```text
Appointment
    ↓
Service
    ↓
Specialty
```

---

## 7. Availabilities

Tabla:

```text
availabilities
```

Campos:

```text
id
professional_id
weekday
start_time
end_time
is_active
created_at
updated_at
```

Relación:

```text
Professional 1:N Availability
```

Ejemplo:

```text
Ana

Lunes
09:00 - 13:00

Lunes
14:00 - 18:00
```

### Numeración de `weekday`

```text
1 = lunes
2 = martes
3 = miércoles
4 = jueves
5 = viernes
6 = sábado
7 = domingo
```

### Restricciones implementadas

```text
weekday BETWEEN 1 AND 7
start_time < end_time
professional_id → professionals.id
```

Índice implementado:

```text
(professional_id, weekday, is_active, start_time)
```

Relaciones Eloquent:

```php
$professional->availabilities;
$availability->professional;
```

### Regla pendiente en backend

Actualmente la base puede aceptar bloques de disponibilidad solapados, por ejemplo:

```text
09:00 - 13:00
11:00 - 15:00
```

Laravel debe impedirlo usando:

```text
existing.start_time < new.end_time
AND
existing.end_time > new.start_time
```

para el mismo:

```text
professional_id
weekday
```

---

## 8. Appointments

Tabla:

```text
appointments
```

Campos:

```text
id
patient_id
professional_id
service_id
appointment_date
start_time
end_time
status
source
note
overbook
created_by
created_at
updated_at
```

No guarda `medical_center_id` porque vive dentro de la base del centro.

No guarda `specialty_id` porque puede obtenerse desde `service_id`.

### Estados permitidos

```text
PENDIENTE
CONFIRMADA
ATENDIDA
CANCELADA
NO_SHOW
```

### Orígenes permitidos

```text
WEB
RECEPCION
DEMO
```

### Restricciones

```text
start_time < end_time

patient_id → patients.id
professional_id → professionals.id
service_id → services.id
```

### `created_by`

`created_by` representa un usuario global de `medsync_core.users`.

No tiene FK PostgreSQL porque `appointments` vive en la base del centro y `users` vive en `medsync_core`.

Laravel debe validar la existencia del usuario.

### Relaciones Eloquent

```text
Appointment
├── patient()
├── professional()
├── service()
└── history()

Patient
└── appointments()

Professional
└── appointments()

Service
└── appointments()
```

---

## 9. Índices de Appointments

Índices implementados:

```text
professional_id + appointment_date + start_time
patient_id + appointment_date + start_time
status + appointment_date
service_id + appointment_date
```

Sirven para:

- Agenda del profesional.
- Reservas de un paciente.
- Consultas por estado.
- Consultas por prestación.
- Apoyar detección de solapamientos.

---

## 10. Appointment History

Tabla:

```text
appointment_history
```

Campos principales:

```text
id
appointment_id
actor_user_id
event_type
previous_status
new_status
old_date
old_start_time
old_end_time
new_date
new_start_time
new_end_time
old_professional_id
new_professional_id
old_service_id
new_service_id
reason
created_at
```

No usa `updated_at`.

El historial debe tratarse como inmutable.

### Eventos permitidos

```text
CREACION
CAMBIO_ESTADO
REPROGRAMACION
MODIFICACION
CANCELACION
```

Ejemplo de creación:

```text
event_type = CREACION
new_status = PENDIENTE
new_date
new_start_time
new_end_time
new_professional_id
new_service_id
```

Ejemplo de reprogramación:

```text
event_type = REPROGRAMACION

old_date
old_start_time
old_end_time

new_date
new_start_time
new_end_time
```

Ejemplo de cancelación:

```text
event_type = CANCELACION
previous_status = PENDIENTE
new_status = CANCELADA
```

Relación:

```text
Appointment 1:N AppointmentHistory
```

---

## 11. Pruebas realizadas

Se realizaron pruebas manuales con Laravel Tinker.

### Services

Se comprobó:

```text
Specialty → Services
Service → Specialty
```

### Appointments

Se comprobó:

```text
Appointment → Patient
Appointment → Professional
Appointment → Service
Appointment → Service → Specialty
```

También se probaron relaciones inversas.

### Reprogramación

Se modificó una reserva de:

```text
2026-10-10
10:00 - 10:30
```

a:

```text
2026-10-11
11:00 - 11:30
```

y se registró correctamente el evento `REPROGRAMACION`.

### Cancelación

La cita pasó:

```text
PENDIENTE
→
CANCELADA
```

sin eliminar físicamente la fila.

También se registró `CANCELACION`.

### Restricciones probadas

PostgreSQL rechazó correctamente:

```text
start_time > end_time
start_time = end_time
status inválido
source inválido
patient_id inexistente
professional_id inexistente
service_id inexistente
event_type inválido en history
appointment_id inexistente en history
weekday = 8
professional_id inexistente en availability
```

---

## 12. Lógica que debe implementar ahora el backend

La creación de una reserva no debería realizarse directamente desde un controlador sin validaciones.

Se recomienda centralizar la lógica en una clase de servicio, por ejemplo:

```text
app/Services/Appointments/AppointmentService.php
```

o una estructura equivalente acordada por el equipo.

Flujo esperado:

```text
Paciente selecciona servicio
        ↓
selecciona profesional
        ↓
selecciona fecha
        ↓
backend obtiene weekday
        ↓
consulta Availability
        ↓
calcula horarios según duration_minutes
        ↓
descarta horarios ocupados
        ↓
frontend muestra horarios disponibles
        ↓
paciente selecciona una hora
        ↓
backend vuelve a validar
        ↓
transacción
        ↓
crea Appointment
        ↓
crea AppointmentHistory
```

---

## 13. Validación Professional ↔ Service

Antes de aceptar una reserva:

```text
Professional
        ↓
professional_specialty
        ↓
Specialty

Service
        ↓
Specialty
```

La especialidad del servicio debe estar asociada al profesional.

Ejemplo conceptual:

```php
$professional->specialties()
    ->whereKey($service->specialty_id)
    ->exists();
```

Si no existe, retornar un error de validación.

---

## 14. Validación contra Availability

Supongamos:

```text
Fecha: 2026-10-12
Hora: 10:00
Servicio: 30 minutos
```

Laravel calcula:

```text
start_time = 10:00
end_time   = 10:30
```

usando:

```text
services.duration_minutes
```

Luego debe existir una disponibilidad activa que contenga completamente el intervalo:

```text
availability.start_time <= appointment.start_time
AND
availability.end_time >= appointment.end_time
```

Ejemplo válido:

```text
Disponibilidad:
09:00 - 13:00

Reserva:
10:00 - 10:30
```

Ejemplo inválido:

```text
Disponibilidad:
09:00 - 13:00

Reserva:
12:45 - 13:15
```

---

## 15. Evitar reservas solapadas

Antes de insertar, buscar otra reserva del mismo profesional y fecha que se solape.

Condición:

```text
existing.start_time < new.end_time
AND
existing.end_time > new.start_time
```

Ejemplo:

```text
Existente:
10:00 - 10:30

Nueva:
10:15 - 10:45

Resultado:
SOLAPAMIENTO
```

No basta con comprobar una hora de inicio idéntica.

### Estados que deben bloquear una hora

Como mínimo:

```text
PENDIENTE
CONFIRMADA
```

Una cita `CANCELADA` no debe seguir bloqueando el horario.

---

## 16. Overbook

La tabla `appointments` ya posee:

```text
overbook
```

Para reservas del paciente desde web:

```text
overbook = false
```

El paciente no debe poder saltarse la validación.

Un sobreturno podría habilitarse después para:

```text
ADMIN
RECEPCIONISTA
```

previa validación de permisos.

---

## 17. Creación transaccional

La creación debe ejecutarse dentro de una transacción.

Ejemplo conceptual:

```php
DB::connection('center')->transaction(function () {
    // Validar nuevamente disponibilidad.
    // Validar nuevamente solapamientos.
    // Crear Appointment.
    // Crear AppointmentHistory.
});
```

Esto es importante porque dos pacientes pueden ver la misma hora disponible al mismo tiempo.

Laravel debe volver a validar inmediatamente antes del `INSERT`.

---

## 18. Historial automático

Al crear una cita debe crearse también su historial dentro de la misma transacción.

Ejemplo:

```text
Appointment
status = PENDIENTE
source = WEB
```

y:

```text
AppointmentHistory
event_type = CREACION
new_status = PENDIENTE
new_date
new_start_time
new_end_time
new_professional_id
new_service_id
actor_user_id
```

Si falla el historial, debe revertirse también la creación de la cita.

---

## 19. Reprogramación

La reprogramación debe volver a validar:

- Profesional.
- Servicio.
- Especialidad del profesional.
- Availability.
- Duración.
- Solapamiento.

Al buscar conflictos debe ignorar la misma cita:

```php
->where('id', '!=', $appointment->id)
```

Debe registrarse:

```text
event_type = REPROGRAMACION

old_date
old_start_time
old_end_time

new_date
new_start_time
new_end_time
```

Si cambia profesional o servicio:

```text
old_professional_id
new_professional_id
old_service_id
new_service_id
```

---

## 20. Cancelación

Cancelar no significa borrar.

Debe modificarse:

```text
status = CANCELADA
```

y registrarse:

```text
event_type = CANCELACION
previous_status = estado anterior
new_status = CANCELADA
reason = motivo opcional
```

Esto conserva información para historial, auditoría y reportes.

---

## 21. Regla de 24 horas

Según los requerimientos actuales, el paciente debería poder cancelar o reprogramar hasta 24 horas antes de:

```text
appointment_date + start_time
```

Esta regla debe validarse en Laravel usando la zona horaria del centro.

Recepción puede tener permisos diferentes.

---

## 22. Cambios de estado

Reglas pendientes en backend:

```text
ATENDIDA
```

solo debería poder asignarse desde la hora de inicio.

```text
NO_SHOW
```

solo debería poder asignarse después de la hora de término.

---

## 23. Horarios disponibles para React

React no debería calcular por sí solo la disponibilidad definitiva.

Se recomienda un endpoint conceptual como:

```text
GET /available-slots
```

con:

```text
service_id
professional_id
date
```

El backend:

1. Obtiene el servicio.
2. Obtiene `duration_minutes`.
3. Calcula el `weekday`.
4. Consulta `availabilities`.
5. Genera intervalos posibles.
6. Consulta `appointments`.
7. Elimina intervalos ocupados.
8. Devuelve solo horarios válidos.

Ejemplo conceptual de respuesta:

```json
{
  "date": "2026-10-12",
  "professional_id": 1,
  "service_id": 1,
  "duration_minutes": 30,
  "slots": [
    "09:00",
    "09:30",
    "10:30",
    "11:00",
    "11:30",
    "12:00",
    "12:30"
  ]
}
```

La ruta exacta es una propuesta y puede adaptarse a las convenciones del backend.

---

## 24. Crear una reserva desde React

Endpoint conceptual:

```text
POST /appointments
```

Payload recomendado:

```json
{
  "service_id": 1,
  "professional_id": 1,
  "appointment_date": "2026-10-12",
  "start_time": "10:00"
}
```

El backend debería derivar:

```text
patient_id
→ paciente asociado al usuario autenticado.

end_time
→ start_time + service.duration_minutes.

status
→ PENDIENTE.

source
→ WEB.

overbook
→ false.

created_by
→ usuario autenticado.
```

Esto reduce la posibilidad de manipulación desde el frontend.

---

## 25. Mis Reservas

El apartado **Mis Reservas** debe obtener las citas del paciente autenticado.

Flujo:

```text
User autenticado
        ↓
center_users
        ↓
patient_id
        ↓
Patient
        ↓
Appointments
```

Consulta conceptual:

```php
$patient->appointments()
    ->with([
        'service.specialty',
        'professional',
    ])
    ->orderBy('appointment_date')
    ->orderBy('start_time')
    ->get();
```

El paciente no debe poder enviar otro `patient_id` para consultar reservas ajenas.

Laravel debe resolver el paciente desde la sesión/usuario autenticado.

---

## 26. Endpoints sugeridos

Estas rutas son propuestas, no una implementación actual:

```text
GET   /services
GET   /services/{service}/professionals
GET   /professionals/{professional}/availability
GET   /available-slots

POST  /appointments
GET   /appointments/my

PATCH /appointments/{appointment}/reschedule
PATCH /appointments/{appointment}/cancel
```

---

## 27. Validaciones mínimas al crear una reserva

```text
[ ] Usuario autenticado.
[ ] Usuario pertenece al centro actual.
[ ] Usuario está vinculado a un Patient.
[ ] Patient está activo.
[ ] Professional existe y está activo.
[ ] Service existe y está activo.
[ ] Specialty del servicio está activa.
[ ] Professional posee esa Specialty.
[ ] La fecha solicitada es válida.
[ ] Existe Availability activa para ese día.
[ ] La cita completa cabe dentro del bloque.
[ ] No existe Appointment solapada.
[ ] El paciente no puede forzar overbook.
[ ] end_time se calcula en backend.
[ ] created_by corresponde al usuario autenticado.
[ ] Appointment y AppointmentHistory se crean en la misma transacción.
```

---

## 28. Qué no debe decidir el frontend

React puede ocultar o deshabilitar opciones para mejorar UX, pero Laravel debe validar siempre:

```text
si la hora está disponible
si existe solapamiento
si el profesional ofrece el servicio
si overbook está autorizado
si una reprogramación cumple las 24 horas
si una cita puede marcarse ATENDIDA
si una cita puede marcarse NO_SHOW
```

---

## 29. Estado actual

### Base de datos implementada

```text
Specialties                         OK
Professional Specialty             OK
Services                            OK
Availabilities                      OK
Appointments                        OK
Appointment History                 OK

Relaciones Eloquent                 OK
Foreign Keys                        OK
CHECK constraints                   OK
Índices principales                 OK
Pruebas manuales con Tinker         OK
```

### Pendiente en backend

```text
Consulta de slots disponibles
Validación Professional ↔ Service
Validación contra Availability
Detección de solapamientos
Transacción de creación
Historial automático
Reprogramación real
Cancelación real
Regla de 24 horas
Control de overbook
Mis Reservas
Autorización por usuario/rol
```

### Pendiente de arquitectura SaaS

```text
Resolución dinámica del centro por slug
Cambio dinámico de conexión center
Migraciones automáticas para múltiples tenants
```

---

## 30. Flujo objetivo de esta iteración

```text
Paciente inicia sesión
        ↓
selecciona servicio
        ↓
selecciona profesional
        ↓
selecciona fecha
        ↓
ve solamente horarios disponibles
        ↓
selecciona una hora
        ↓
Laravel valida nuevamente
        ↓
se crea Appointment
        ↓
se crea AppointmentHistory
        ↓
la reserva aparece en Mis Reservas
```

Si la hora ya fue tomada:

```text
"El horario seleccionado ya no se encuentra disponible."
```

La reserva no debe insertarse.

---

## 31. Archivos principales relacionados

Modelos:

```text
app/Models/Center/Specialty.php
app/Models/Center/ProfessionalSpecialty.php
app/Models/Center/Service.php
app/Models/Center/Professional.php
app/Models/Center/Patient.php
app/Models/Center/Availability.php
app/Models/Center/Appointment.php
app/Models/Center/AppointmentHistory.php
```

Migraciones:

```text
database/migrations/center/
```

Allí se encuentran las migraciones relacionadas con:

```text
specialties
professional_specialty
services
availabilities
appointments
appointment_history
```

---

## 32. Resumen para implementación

Antes de crear una reserva:

```text
Service válido
        ↓
Professional válido
        ↓
Professional pertenece a Specialty
        ↓
Availability válida
        ↓
duración calculada
        ↓
sin solapamiento
        ↓
transacción
        ↓
Appointment
        +
AppointmentHistory
```

La base ya proporciona la estructura necesaria.

La siguiente etapa consiste en implementar esta lógica en Laravel y exponerla mediante la API para que React complete el flujo:

```text
Reservar
+
Mis Reservas
```
