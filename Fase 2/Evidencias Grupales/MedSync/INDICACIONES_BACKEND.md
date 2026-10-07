# Indicaciones para backend — MedSync

## Objetivo

Implementar una API REST con Laravel, PostgreSQL, Eloquent, Sanctum y Spatie Permission que sustituya los datos demo de `medsync_frontend`. El contrato inicial está en `medsync_frontend/docs/api-contract.md` y el modelo requerido en `REQUERIMIENTOS_BD.md`.

## Orden recomendado de implementación

1. Corregir y migrar el esquema actual de `medsync_BD` siguiendo `REQUERIMIENTOS_BD.md`.
2. Instalar y configurar Sanctum y Spatie Permission; crear el superadministrador de plataforma y los roles del centro.
3. ✅ Resolver el centro para rutas clínicas mediante sesión: tras `auth:sanctum`, el middleware valida `active_medical_center_id`, `medical_centers.is_active` y la membresía activa en `center_users`; configura la conexión `center` desde `medical_centers.database_name`. Nunca usar un `medical_center_id` ni nombre de base libre enviado por el cliente.
4. Implementar autenticación, sesión, `GET /api/v1/me` y el bootstrap mínimo por rol.
5. Implementar catálogos, fichas de pacientes y profesionales, horarios, disponibilidad y agenda.
6. Implementar resultados médicos con almacenamiento privado y publicación por profesional.
7. Reemplazar gradualmente el proveedor mock del frontend por llamadas a `clinicApi`; no conectar las páginas directamente con Eloquent ni rutas HTTP.

## Reglas ya acordadas con frontend

### Ficha previa y activación de cuenta

Recepción puede crear una ficha de paciente sin cuenta. Cuando el paciente se registre con el mismo RUT y correo dentro del mismo centro, se debe vincular la nueva cuenta con esa ficha existente y no crear otra. Si solo coincide uno de esos datos, rechazar la operación y pedir corrección a recepción. En producción agregar verificación de identidad o correo antes de activar la cuenta.

### Agenda

El paciente puede cancelar o reprogramar hasta 24 horas antes de la hora de inicio. Recepción puede hacerlo fuera de ese plazo. Una cita no puede marcarse `ATENDIDA` antes de su hora de inicio ni `NO_SHOW` antes de su hora de término. Calcular todos los plazos con la zona horaria del centro y repetirlos en Form Requests, servicios y pruebas.

### Reasignación por ausencia de profesional

Recepción puede reasignar una cita pendiente o confirmada cuando el profesional original no puede atender. El endpoint de reprogramación acepta `professional_id` y `reassignment_reason` cuando quien ejecuta la acción sea `RECEPCIONISTA`; pacientes y profesionales no pueden enviar esos campos.

- Validar que el profesional de reemplazo esté activo, pertenezca al centro resuelto y atienda la especialidad de la prestación de la cita.
- Validar de forma transaccional la disponibilidad, duración y ausencia de solapamientos del profesional de reemplazo para la fecha y hora solicitadas. No confiar en el horario calculado por React.
- Recalcular `end_time`, actualizar `professional_id` y registrar un evento inmutable `REASIGNACION` con profesional anterior, nuevo profesional, motivo, actor y fecha. El motivo es obligatorio y de máximo 300 caracteres.
- Autorizar solo a recepción; no exponer el cambio de profesional en las rutas de paciente. Devolver la cita con sus resúmenes de prestación y profesional actualizados.
- Añadir feature tests para permisos, aislamiento de tenant, especialidad incompatible, profesional inactivo, horario ocupado, historial y reasignación fuera de la ventana de 24 horas por recepción.

### Agenda real de recepción

El frontend de recepción ya no debe usar pacientes, prestaciones, profesionales ni horas mock para reservar o reprogramar. El backend debe entregar `GET /api/v1/patients` exclusivamente a `RECEPCIONISTA`, con el mínimo necesario para seleccionar un paciente activo (`id`, nombre y RUT), y aceptar `patient_id` únicamente cuando una recepción hace `POST /api/v1/appointments`.

Pacientes de recepción reutiliza ese listado y lo amplía con datos administrativos de la ficha. `POST /api/v1/patients` crea la ficha sin cuenta; `GET/PATCH /api/v1/patients/{id}` consulta y corrige la ficha del tenant resuelto. `PACIENTE` puede consultar y editar únicamente su propia ficha según `center_users.patient_id`, sin modificar RUT, correo de acceso, previsión ni consentimiento. Recepción puede corregir el correo de una ficha sin cuenta; una ficha vinculada requiere un flujo separado para actualizar también `core.users`. `ADMIN`, `PROFESIONAL` y `SUPER_ADMIN` no reciben acceso a estas rutas.

- Para recepción, el servidor resuelve y valida el paciente activo dentro del tenant y crea la cita con `source = RECEPCION`.
- Para paciente, `patient_id` no se acepta: el servidor mantiene la resolución desde la sesión y crea con `source = WEB`.
- Catálogo, profesionales compatibles y horarios continúan saliendo de `/v1/services`, `/v1/services/{service}/professionals` y `/v1/appointments/available-slots`; toda validación final permanece en el servidor.

### Informes clínicos

El profesional responsable carga, publica y administra sus informes. Un informe publicado no se borra físicamente en producción: se anula con estado `VOIDED`, motivo, fecha y usuario responsable; el paciente deja de verlo. Los borradores pueden eliminarse o archivarse según la política clínica. Recepción, si tiene permiso de carga, nunca publica y debe recibir solo el acceso mínimo necesario.

## Aislamiento SaaS obligatorio

- El portal de cada centro usa una ruta o dominio propio. El paciente nunca debe seleccionar ni descubrir otros centros que usen MedSync.
- Todo recurso clínico se consulta y modifica solo dentro del `medical_center_id` resuelto en servidor.
- El mismo correo o RUT puede existir en centros distintos. Aplicar índices únicos compuestos por centro.
- Las policies, queries de Eloquent y validaciones deben comprobar centro, relación de usuario y rol. No confiar en los permisos que muestra React.
- `SUPER_ADMIN` administra centros, planes y suscripciones; no puede ver pacientes, agenda ni documentos clínicos. Las cuentas de centro no acceden a rutas de plataforma.

## Responsabilidades por rol

| Rol | Puede hacer | No puede hacer |
| --- | --- | --- |
| `SUPER_ADMIN` | Crear o desactivar centros, gestionar plan y suscripción, asignar administrador inicial | Acceder a agenda, fichas de pacientes o resultados clínicos |
| `ADMIN` | Gestionar usuarios, roles, profesionales, especialidades, prestaciones, horarios base, tipos de resultado y reportes agregados | Crear/modificar citas, revisar pacientes o resultados clínicos |
| `RECEPCIONISTA` | Crear fichas de pacientes, agenda, citas, cambios y reprogramaciones | Otorgar roles, crear profesionales o publicar resultados |
| `PROFESIONAL` | Consultar su agenda, cargar borradores y publicar sus propios resultados | Gestionar otros profesionales o centros |
| `PACIENTE` | Registrarse en su portal, crear reservas permitidas, ver sus propios resultados publicados | Ver datos de otros pacientes o informes en borrador |

El permiso opcional `results.upload` puede habilitar a recepción a cargar borradores, pero nunca a publicar.

## Endpoints y respuestas

Usar prefijo `/api/v1`, respuestas de recursos como `{ "data": ... }`, paginación estándar de Laravel y errores de validación como `{ "message": "...", "errors": { "campo": ["..."] } }`.

- Autenticación: `GET /sanctum/csrf-cookie`, `GET /api/public/centers`, `POST /api/login`, `POST /api/logout`, `GET /api/v1/me`, `POST /api/register`. El login clínico recibe `email`, `password` y `center_slug`; Laravel valida el slug y la membresía antes de guardar el centro activo en sesión. El registro también exige `center_slug`, valida que el centro esté activo en Core y resuelve su base clínica antes de consultar o crear pacientes.
- Agenda: `GET/POST/PATCH /api/v1/appointments`, `GET /api/v1/appointments/{id}`, `GET /api/v1/slots`; recepción obtiene sus pacientes activos desde `GET /api/v1/patients`.
- Gestión del centro: `GET/POST/PATCH /api/v1/patients`, `/professionals`, `/specialties`, `/services`, `/availability`, `/result-types`.
- Resultados: `GET/POST /api/v1/results`, `POST /api/v1/results/{id}/publish`, `GET /api/v1/results/{id}/document`.
- Plataforma: `GET/POST/PATCH /api/v1/platform/centers` y `PUT /api/v1/platform/centers/{id}/administrator`.

Las rutas y cargas completas están documentadas en `medsync_frontend/docs/api-contract.md`.

Para reasignación, `PATCH /api/v1/appointments/{appointment}/reschedule` debe admitir opcionalmente:

```json
{
  "appointment_date": "2026-10-10",
  "start_time": "10:30",
  "professional_id": 42,
  "reassignment_reason": "Ausencia por licencia médica"
}
```

`professional_id` y `reassignment_reason` se procesan como una unidad: ambos son obligatorios si la persona cambia, y ambos se omiten para una reprogramación normal.

## Validaciones necesarias

- Validar RUT chileno con módulo 11 en servidor, además de formato, correo, teléfono, fecha de nacimiento y consentimiento al registrar pacientes.
- Validar contraseña, confirmación, correo único dentro del centro y RUT único dentro del centro.
- En la activación de una ficha existente, exigir coincidencia conjunta de RUT y correo; no permitir que una coincidencia parcial cree o vincule una cuenta.
- Al reservar, calcular disponibilidad en servidor, verificar horario del profesional, duración de la prestación y evitar solapamientos de forma transaccional.
- Aplicar el plazo de 24 horas para cancelación y reprogramación de pacientes; validar los tiempos mínimos antes de `ATENDIDA` y `NO_SHOW`.
- Al crear o modificar relaciones, comprobar que los registros involucrados pertenezcan al mismo centro.
- Al publicar un resultado, validar que el profesional sea responsable, que el registro esté en borrador y que paciente, atención y tipo correspondan al mismo centro.

## Archivos y seguridad

- Usar el disco privado de Laravel para documentos; servirlos solo mediante una ruta autorizada.
- Nunca almacenar archivos clínicos como `data:` o base64 en producción.
- Validar MIME real, extensión, tamaño, nombre seguro y autorización de descarga. Mantener una lista inicial de PDF, PNG, JPEG y TXT.
- Configurar CORS, `SANCTUM_STATEFUL_DOMAINS`, cookies seguras y dominio de sesión para el portal correspondiente.
- Registrar cambios de citas y, si se implementa `audit_logs`, cambios administrativos relevantes.
- Mantener trazabilidad de resultados anulados y de descargas de documentos clínicos cuando corresponda.

## Criterios de entrega para backend

- Migraciones reproducibles desde una base PostgreSQL vacía y seeders con datos ficticios.
- Feature tests para aislamiento entre dos centros, permisos de cada rol, RUT, creación de citas, prevención de solapamientos y acceso a resultados.
- Ninguna consulta clínica puede devolver registros de otro centro.
- Documentar variables de entorno, comandos de instalación y cómo ejecutar pruebas en `medsync_backend/README.md`.
- Antes de conectar el frontend, acordar con el equipo las estructuras JSON definitivas y actualizar `medsync_frontend/docs/api-contract.md` si cambian.
- Acordar el comportamiento comercial de suscripciones vencidas o suspendidas, el alcance de recepción sobre informes y los límites definitivos de archivos antes de habilitar producción.

## Integración comercial de frontend

Las vistas de plataforma incorporan gestión comercial de centros, planes y suscripciones; las vistas del `ADMIN` muestran indicadores económicos únicamente de su propio centro. La interfaz usa valores temporales mientras no existen endpoints, pero backend debe reemplazarlos sin que el cliente pueda elegir otro centro.

- Plataforma (`SUPER_ADMIN`): exponer centros con plan, estado de suscripción, fecha de inicio, próxima renovación, usuarios/profesionales asociados, valor mensual y estado de pago. Agregar `GET /api/v1/platform/centers/{id}/subscription` y `PATCH /api/v1/platform/centers/{id}/subscription`; solo `SUPER_ADMIN` puede consultar o cambiar estos recursos.
- Administración del centro (`ADMIN`): exponer `GET /api/v1/commercial/summary?from=&to=&professional_id=&service_id=` y `GET /api/v1/commercial/reports` resueltos exclusivamente desde el centro autenticado. Deben devolver ingresos estimados, atenciones, ticket promedio, cancelaciones, no-show, pérdidas estimadas, series mensuales y agrupaciones por servicio/profesional.
- Los importes comerciales no se calculan ni se aceptan desde React. El servidor debe usar precios, pagos y citas de su propio centro, aplicar autorización por rol/centro y devolver agregados sin datos clínicos identificatorios innecesarios.
