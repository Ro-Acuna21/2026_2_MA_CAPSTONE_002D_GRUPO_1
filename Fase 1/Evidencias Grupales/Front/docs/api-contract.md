# Contrato propuesto para Laravel

Prefijo: `/api/v1`. Las respuestas de recursos usan `{ "data": ... }` y los errores de validación siguen el formato estándar de Laravel (`message` + `errors`).

## Autenticación con Sanctum

- `GET /sanctum/csrf-cookie`
- `POST /api/login` — `{ email, password }`
- `POST /api/logout`
- `GET /api/v1/me` — usuario, rol y permisos de Spatie
- `PUT /api/v1/session/organization` — selecciona una organización permitida para el usuario

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

## Multiempresa

Cada recurso de negocio debe incluir `organization_id`. El backend determina la organización activa desde la sesión y la membresía autenticada; no debe confiar en un identificador enviado libremente por el navegador. Un usuario puede pertenecer a una o varias organizaciones, pero cada consulta y mutación queda limitada a una sola organización activa.
