# Contrato propuesto para Laravel

Prefijo: `/api/v1`. Las respuestas de recursos usan `{ "data": ... }` y los errores de validación siguen el formato estándar de Laravel (`message` + `errors`).

## Autenticación con Sanctum

- `GET /sanctum/csrf-cookie`
- `POST /api/login` — `{ email, password }`
- `POST /api/logout`
- `GET /api/v1/me` — usuario, rol y permisos de Spatie

El frontend envía cookies con `credentials: include`. Configura `SANCTUM_STATEFUL_DOMAINS`, CORS y el dominio de sesión.

## Recursos principales

| Método | Ruta | Uso |
| --- | --- | --- |
| GET | `/api/v1/bootstrap` | Catálogos y datos iniciales visibles para el rol |
| GET | `/api/v1/appointments` | Agenda paginada y filtrable |
| POST | `/api/v1/appointments` | Crear reserva |
| GET | `/api/v1/appointments/{id}` | Detalle e historial |
| PATCH | `/api/v1/appointments/{id}` | Estado o reprogramación |
| GET | `/api/v1/slots` | Disponibilidad por profesional, prestación y fecha |
| GET/POST/PATCH | `/api/v1/patients` | Gestión de pacientes |
| GET/POST/PATCH | `/api/v1/professionals` | Gestión de profesionales |
| GET/POST/PATCH | `/api/v1/specialties` | Catálogo de especialidades |
| GET/POST/PATCH | `/api/v1/services` | Catálogo de prestaciones |
| GET | `/api/v1/reports/summary` | Indicadores administrativos |

La API es responsable de validar disponibilidad, prevenir solapamientos, registrar historial y aplicar permisos. Nunca debe confiar en los controles visuales del frontend.

## Aislamiento por centro

Cada recurso de negocio debe incluir `organization_id`. El backend determina el centro desde el dominio o subdominio solicitado y valida que la cuenta autenticada pertenezca a ese mismo centro; no debe confiar en un identificador enviado libremente por el navegador. No existe un selector de organizaciones en el portal clínico.

Las cuentas de pacientes y trabajadores son independientes por centro. El correo y el RUT deben usar restricciones únicas compuestas con `organization_id`, por lo que la misma persona puede tener registros separados en dos instituciones sin que una revele la existencia de la otra. Las cookies de sesión deberán limitarse al dominio del portal correspondiente.

## Ampliación propuesta: plataforma, registro e informes

Estas rutas son un contrato futuro; el proveedor del frontend continúa siendo local. El rol global `SUPER_ADMIN` administra la plataforma. Las cuentas `ADMIN`, `RECEPCIONISTA`, `PROFESIONAL` y `PACIENTE` pertenecen a un solo centro. La respuesta de sesión deberá incluir el centro, sus permisos y la ficha vinculada.

El acceso visual separado (`/plataforma/acceso`) mejora la navegación, pero la seguridad depende del backend. Laravel debe rechazar cuentas de centro en las rutas de plataforma y rechazar cuentas `SUPER_ADMIN` en las rutas clínicas. El administrador del centro recibe configuración, usuarios y estadísticas agregadas; las APIs de agenda, reservas, pacientes y documentos clínicos no deben autorizarlo.

| Método | Ruta propuesta | Uso |
| --- | --- | --- |
| POST | `/api/register` | Registro del paciente y membresía inicial; validar RUT, duplicados y contraseña en servidor |
| GET/POST/PATCH | `/api/v1/platform/centers` | Centros, planes, suscripciones y habilitación, exclusivo de plataforma |
| PUT | `/api/v1/platform/centers/{id}/administrator` | Asignación del administrador de un centro |
| PUT | `/api/v1/users/{id}/access` | Rol, ficha vinculada y permiso opcional de carga para recepción dentro del centro resuelto |
| GET/POST/PATCH | `/api/v1/availability` | Horarios recurrentes de profesionales |
| GET/POST | `/api/v1/result-types` | Tipos documentales propios del centro, separados de prestaciones |
| GET/POST | `/api/v1/results` | Lista autorizada y carga de borradores |
| POST | `/api/v1/results/{id}/publish` | Publicación por el profesional responsable |
| GET | `/api/v1/results/{id}/document` | Descarga autorizada; el paciente solo accede a sus documentos publicados |

En producción, los documentos deben transferirse como archivos y almacenarse privadamente; el contenido `data:` de la demo no forma parte del contrato remoto. El backend debe validar centro, paciente, profesional y atención relacionados, estado borrador/publicado, tipo de archivo, tamaño y permisos. El superadministrador no recibe contenidos clínicos. Una suspensión conserva los datos; las reglas comerciales por estado de suscripción aún deben acordarse.

Registro no equivale a verificar identidad. Las fichas preexistentes requieren un proceso de vinculación autorizado y la verificación de correo deberá implementarse en backend. Todos los controles locales de esta demo deben repetirse y reforzarse en Laravel, Eloquent y Spatie.
