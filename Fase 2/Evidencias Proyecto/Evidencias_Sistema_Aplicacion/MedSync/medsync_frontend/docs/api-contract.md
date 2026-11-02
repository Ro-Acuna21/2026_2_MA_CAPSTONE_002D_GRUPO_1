# Contrato propuesto para Laravel

Prefijo: `/api/v1`. Las respuestas de recursos usan `{ "data": ... }` y los errores de validación siguen el formato estándar de Laravel (`message` + `errors`).

## Autenticación con Sanctum

- `GET /sanctum/csrf-cookie`
- `GET /api/public/centers` — `{ data: [{ id, name, slug }] }`; solo centros activos, sin `database_name`.
- `POST /api/register` — registro público: datos del paciente y `center_slug` obligatorio; devuelve `201` o `422` de validación.
- `POST /api/login` — login clínico contextual: `{ email, password, center_slug }`
- `POST /api/logout`
- `GET /api/v1/me` — identidad, rol y centro activo; los permisos se aplican en Laravel y no se enumeran en esta respuesta.

El frontend envía cookies con `credentials: include`. Configura `SANCTUM_STATEFUL_DOMAINS`, CORS y el dominio de sesión.

## Recursos principales

| Método | Ruta | Uso |
| --- | --- | --- |
| GET | `/api/v1/bootstrap` | Catálogos y datos iniciales visibles para el rol |
| GET | `/api/v1/appointments` | Agenda paginada y filtrable |
| POST | `/api/v1/appointments` | Crear reserva |
| GET | `/api/v1/appointments/{id}` | Detalle de la cita |
| GET | `/api/v1/appointments/{id}/history` | Eventos persistidos para recepción, paciente dueño o profesional asignado |
| PATCH | `/api/v1/appointments/{id}` | Estado o reprogramación |
| GET | `/api/v1/slots` | Disponibilidad por profesional, prestación y fecha |
| GET/POST | `/api/v1/patients` | Listado de pacientes activos y alta por recepción; implementado |
| GET/PATCH | `/api/v1/patients/{id}` | Detalle y edición por recepción o del propio paciente; implementado |
| GET/POST/PATCH | `/api/v1/professionals` | Gestión de profesionales |
| GET/POST/PATCH | `/api/v1/specialties` | Catálogo de especialidades |
| GET/POST/PATCH | `/api/v1/services` | Catálogo de prestaciones |
| GET | `/api/v1/reports/summary` | Indicadores administrativos |

La API es responsable de validar disponibilidad, prevenir solapamientos, registrar historial y aplicar permisos. Nunca debe confiar en los controles visuales del frontend.

El historial devuelve `{ data: { appointment, events } }`, con eventos en orden descendente. Cada evento incluye tipo, estados y fechas anteriores/nuevos, profesional anterior/nuevo con nombre, motivo y fecha de registro. El motivo se omite para el paciente porque puede contener información interna de personal. La plataforma y ADMIN no acceden a este recurso clínico.

Pacientes usa `{ data: ... }` con identificador, nombres, RUT, nacimiento, correo, teléfono, previsión, seguro complementario, dirección, consentimiento, estado y vínculo de cuenta. El listado conserva `id`, `first_name`, `last_name` y `rut` para Reservas. `POST` devuelve `201`; las lecturas y `PATCH`, `200`. RUT, consentimiento, vínculo de cuenta y estado no son editables. El paciente solo accede a su propia ficha y no cambia correo ni previsión; recepción puede corregir el correo antes de vincular una cuenta. Se usan `401`, `403`, `404` y `422` según autenticación, permisos, existencia y validación. La conexión clínica se resuelve desde Core, nunca desde el navegador.

## Aislamiento por centro

El centro se selecciona públicamente antes del login y React envía únicamente `center_slug` a `POST /api/login`. Laravel busca el slug en `core.medical_centers`, exige que esté activo y comprueba la membresía activa en `core.center_users`. Si es correcto guarda `active_medical_center_id` en la sesión. Un slug no autoriza por sí solo a una cuenta.

Para `POST /api/register`, Laravel valida el `center_slug` contra centros activos de Core y configura `center` desde `medical_centers.database_name` antes de consultar previsiones o pacientes. El navegador no envía el nombre de la base. El registro crea la ficha clínica y la membresía únicamente en el centro resuelto. El esquema PostgreSQL no cambió para este flujo.

En cada ruta clínica autenticada, Laravel recupera ese centro desde la sesión y configura la conexión `center` con `medical_centers.database_name` antes del route-model binding y de los controladores. No debe confiar en `organization_id`, `medical_center_id` o nombre de base enviado desde el navegador. El acceso de plataforma `SUPER_ADMIN` es independiente y no abre una conexión clínica.

La identidad y contraseña son globales en Core; cada centro conserva su propia ficha clínica y una membresía con un rol. El correo de `core.users` es único globalmente en el esquema actual. Las consultas clínicas usan la base del centro validado en la sesión.

## Ampliación propuesta: plataforma, registro e informes

Las rutas de esta sección mezclan contratos históricos con módulos pendientes; para los endpoints vigentes de plataforma y accesos, consultar las secciones implementadas al final de este documento. El rol global `SUPER_ADMIN` administra la plataforma. Una cuenta puede tener un rol diferente en cada centro, con una sola membresía por centro. La respuesta de sesión incluye el rol, el centro y la ficha vinculada cuando corresponde.

El acceso visual separado (`/plataforma/acceso`) mejora la navegación, pero la seguridad depende del backend. Laravel debe rechazar cuentas de centro en las rutas de plataforma y rechazar cuentas `SUPER_ADMIN` en las rutas clínicas. El administrador del centro recibe configuración, usuarios y estadísticas agregadas; las APIs de agenda, reservas, pacientes y documentos clínicos no deben autorizarlo.

| Método | Ruta propuesta | Uso |
| --- | --- | --- |
| POST | `/api/register` | Registro del paciente y membresía inicial; validar RUT, duplicados y contraseña en servidor |
| GET/POST/PATCH | `/api/v1/platform/centers` | Centros y habilitación, exclusivo de plataforma; muestra plan y estado de la suscripción abierta |
| GET/POST | `/api/v1/platform/centers/{id}/administrators` | Asignación persistente de ADMIN; implementado |
| GET/PATCH | `/api/v1/platform/centers/{id}/staff` | Listado y cambio de rol ADMIN/RECEPCIONISTA en la misma membresía; implementado |
| GET/POST | `/api/v1/center/receptionists` | Asignación persistente de recepción por ADMIN; implementado |
| GET/POST/PATCH | `/api/v1/availabilities` | Horarios recurrentes de profesionales; implementado |
| GET/POST | `/api/v1/result-types` | Tipos documentales propios del centro, separados de prestaciones |
| GET/POST | `/api/v1/results` | Lista autorizada y carga de borradores |
| POST | `/api/v1/results/{id}/publish` | Publicación por el profesional responsable |
| GET | `/api/v1/results/{id}/document` | Descarga autorizada; el paciente solo accede a sus documentos publicados |

En producción, los documentos deben transferirse como archivos y almacenarse privadamente; el contenido `data:` de la demo no forma parte del contrato remoto. El backend debe validar centro, paciente, profesional y atención relacionados, estado borrador/publicado, tipo de archivo, tamaño y permisos. El superadministrador no recibe contenidos clínicos. Una suspensión conserva los datos; las reglas comerciales por estado de suscripción aún deben acordarse.

Registro no equivale a verificar identidad. Las fichas preexistentes requieren un proceso de vinculación autorizado y la verificación de correo deberá implementarse en backend. Todos los controles locales de esta demo deben repetirse y reforzarse en Laravel, Eloquent y Spatie.

## Observación Capstone: horarios y catálogo global (24-10-2026)

### Existente y reutilizable

`GET /api/v1/availabilities` devuelve `{ "data": [{ "id": 1, "professional_id": 17, "weekday": 1, "start_time": "09:00", "end_time": "13:00", "is_active": true }] }`. `weekday` es ISO 1–7. `POST /api/v1/availabilities` y `PATCH /api/v1/availabilities/{id}` reciben `professional_id`, `weekday`, `start_time`, `end_time`, `is_active`; responden `{ "data": <bloque> }`. Solo `ADMIN` del centro. React agrupa estos bloques por `professional_id`. No existe `DELETE`: `is_active: false` desactiva el bloque sin borrarlo.

`GET/POST/PATCH /api/v1/specialties` actualmente trabaja con `center.specialties` y devuelve `{ id, name, description, is_active }`. Es una API **local heredada**, no el catálogo global del PR #19. No se debe reinterpretar silenciosamente su ID como uno de Core. Prestaciones y profesionales todavía referencian los IDs locales.

### Propuesta para backend; ninguna de estas rutas existe todavía

| Rol | Método y ruta propuesta | Solicitud | Respuesta |
| --- | --- | --- | --- |
| `SUPER_ADMIN` | `GET /api/v1/platform/specialties` | — | `{ "data": [{ "id": 5, "name": "Cardiología", "description": null, "is_active": true }] }` |
| `SUPER_ADMIN` | `POST /api/v1/platform/specialties` | `{ "name": "Cardiología", "description": null }` | `201`, `{ "data": <especialidad global> }` |
| `SUPER_ADMIN` | `PATCH /api/v1/platform/specialties/{id}` | Uno o más de `name`, `description`, `is_active` | `200`, `{ "data": <especialidad global> }` |
| `ADMIN` | `GET /api/v1/center/specialties` | Centro resuelto desde sesión | `{ "data": [{ "id": 5, "name": "Cardiología", "description": null, "is_active": true, "center_is_active": null }] }` |
| `ADMIN` | `PUT /api/v1/center/specialties/{id}` | `{ "is_active": true }` o `false` | `200`, `{ "data": <misma fila con center_is_active booleano> }` |

En `GET /center/specialties`, `is_active` es el estado **global** y `center_is_active` es `null` si el centro no la seleccionó, `true` si la ofrece y `false` si la desactivó. La respuesta muestra el catálogo global sin revelar qué seleccionaron otros centros. El `PUT` crea o actualiza solo la fila de `core.medical_center_specialties` del centro autorizado; el cliente nunca envía `medical_center_id` ni nombre de base. Debe rechazarse activar una especialidad global inactiva. Desactivar globalmente no borra historial clínico ni asociaciones.

Autenticación por Sanctum; `401` sin sesión, `403` rol incorrecto o centro no autorizado, `404` ID ausente y `422` validación. Backend debe distinguir rutas de plataforma de rutas clínicas y aplicar el rol en servidor. El frontend muestra un estado de integración pendiente al recibir `404`, `405` o `501` de estas rutas propuestas y solo actualiza la pantalla tras una respuesta exitosa.

En el flujo de reserva existente, `GET /api/v1/services`, `GET /api/v1/services/{id}/professionals` y `GET /api/v1/appointments/available-slots` requieren sesión clínica válida. Si la API responde `401`, React limpia la identidad local y vuelve a `/centro/{slug}/ingresar`; un fallo de catálogo se distingue de una lista legítimamente vacía. Laravel debe devolver `401` JSON para `/api/*` incluso sin la cabecera `Accept` y conservar las validaciones y contratos actuales de estos tres endpoints.

## Endpoints implementados: centros e incorporación de pacientes (2026-10-31)

Todos usan sesión Sanctum. `GET/POST /api/v1/platform/centers`, `PATCH /api/v1/platform/centers/{center}` y `POST /api/v1/platform/centers/{center}/provision` exigen `SUPER_ADMIN`. Crear recibe `name`, `slug`, `rut`, `email?`, `phone?` e `is_active`. Plan y estado no se editan en el formulario del centro: provienen de la suscripción abierta en Core. La respuesta incluye `provisioning_status` (`PENDING|READY|FAILED`) y `provisioned_at`, pero no `database_name`. Un fallo devuelve `503` y deja el centro inactivo; el reintento usa el mismo ID.

`POST /api/login` con credenciales correctas y centro activo sin membresía responde `409` con `{code: "CENTER_ENROLLMENT_REQUIRED", message}` y una sesión limitada al centro pendiente. No da acceso clínico. `GET /api/v1/patient-enrollment` devuelve el centro pendiente y correo de la cuenta autenticada. `POST /api/v1/patient-enrollment` recibe `first_name`, `last_name`, `rut`, `birth_date`, `phone`, `health_insurance`, `medical_insurance?`, `address?`, `region_code?`, `commune_code?`, `consent`; el correo proviene de la cuenta global. Devuelve `201` y `patient_id`; repetición válida devuelve `200` sin duplicar. RUT/correo ya usados en ese centro devuelven `422` hasta verificar identidad. Una membresía inactiva o de otro rol no habilita inscripción.

### Accesos persistentes de centro

`GET/POST /api/v1/platform/centers/{center}/administrators`, `PATCH .../{membership}` y `POST .../{membership}/resend-invitation` requieren `SUPER_ADMIN`. `GET/POST /api/v1/center/receptionists`, `PATCH .../{membership}` y `POST .../{membership}/resend-invitation` requieren ADMIN del tenant activo. `POST` recibe `name,email`; `PATCH` recibe `is_active`. La respuesta informa `invitation_pending`, `invitation_sent_at`, `account_created`, `invitation_sent` y `message`; reenvío fallido devuelve `503`. La cuenta existente conserva su contraseña. `POST /api/auth/activate-account` consume el token y limpia el estado pendiente.

`GET /api/v1/platform/centers/{center}/staff` lista ADMIN y RECEPCIONISTA del centro para `SUPER_ADMIN`. `PATCH /api/v1/platform/centers/{center}/staff/{membership}/role` acepta `role: ADMIN|RECEPCIONISTA` y actualiza la fila existente; rechaza miembros de otro centro y roles PACIENTE/PROFESIONAL. `GET /api/v1/center/permissions` devuelve `{data:{source:"fixed_backend_catalog",roles:{...}}}` solo al ADMIN del centro. Es lectura del catálogo fijo sincronizado por Spatie, no un CRUD de permisos. La matriz de rutas y roles está en `medsync_backend/docs/MATRIZ_ACCESO_ROLES.md`.

## Pasos 5 y 6 · 31-10-2026
Resultados, precios y reportes (pasos 5 y 6): ver `README_RESULTADOS_PRECIOS_REPORTES.md` en la raíz MedSync. API de archivos privados, permisos por centro, vigencia de tarifas e indicadores estimados; aplicar la migración Core de permisos y respaldar almacenamiento local privado.

## Paso 7: suscripciones persistidas (31-10-2026)
`GET /api/public/plans` publica planes activos. `GET/POST /api/v1/platform/plans` y `PATCH /api/v1/platform/plans/{plan}` son solo de SUPER_ADMIN. `GET /api/v1/platform/centers/{center}/subscription`, `POST /api/v1/platform/centers/{center}/subscriptions`, `PATCH /api/v1/platform/centers/{center}/subscriptions/{subscription}` y `POST .../payments` operan Core, no bases clínicas. `GET /api/v1/center/subscription` da al ADMIN del centro la misma suscripción abierta e historial visible para plataforma. `PAID` es un registro manual con fecha y referencia, nunca una confirmación bancaria.

## Revisión 02-11-2026

- `GET /api/public/plans`: planes activos con `name`, `description`, `monthly_price_clp`, `professional_limit`, `user_limit` y `features` para `/planes`.
- `POST /api/public/commercial-inquiries`: `plan_id` opcional (activo), `contact_name`, `center_name`, `email`, `phone` y `message` opcionales. Responde `201 {message}`; validación `422`; límite `5/minuto`. Guarda en Core, sin suscripción, correo automático ni pago.
- `GET /api/v1/platform/commercial-inquiries`: últimas 100 solicitudes con plan; requiere sesión `SUPER_ADMIN`. Otros roles reciben `403`.
- `GET /api/public/centers`: centros activos con `id`, `name`, `slug`, `phone`, `description`, `address`, `commune`, `region` y `specialties`; nunca incluye `database_name`. `POST/PATCH /api/v1/platform/centers` aceptan `public_description`, `address_line`, `commune` y `region` además de los campos anteriores.
- `GET /api/v1/services`: cada prestación incluye `current_price_clp` o `null`. `GET /api/v1/services/{service}/price?date=YYYY-MM-DD` devuelve `{data:{service_id,date,amount_clp}}`; requiere centro activo, devuelve `404` para prestación inactiva o especialidad deshabilitada. `amount_clp: null` significa tarifa no informada. La respuesta no constituye un pago ni una garantía de tarifa posterior.
