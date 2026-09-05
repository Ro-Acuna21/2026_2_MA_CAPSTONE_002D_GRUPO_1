# Indicaciones para backend — MedSync

## Objetivo

Implementar una API REST con Laravel, PostgreSQL, Eloquent, Sanctum y Spatie Permission que sustituya los datos demo de `medsync_frontend`. El contrato inicial está en `medsync_frontend/docs/api-contract.md` y el modelo requerido en `REQUERIMIENTOS_BD.md`.

## Orden recomendado de implementación

1. Corregir y migrar el esquema actual de `medsync_BD` siguiendo `REQUERIMIENTOS_BD.md`.
2. Instalar y configurar Sanctum y Spatie Permission; crear el superadministrador de plataforma y los roles del centro.
3. Resolver el centro por dominio o subdominio antes de atender rutas clínicas. En desarrollo se puede usar un middleware o cabecera controlada, nunca un `medical_center_id` libre enviado por el cliente.
4. Implementar autenticación, sesión, `GET /api/v1/me` y el bootstrap mínimo por rol.
5. Implementar catálogos, fichas de pacientes y profesionales, horarios, disponibilidad y agenda.
6. Implementar resultados médicos con almacenamiento privado y publicación por profesional.
7. Reemplazar gradualmente el proveedor mock del frontend por llamadas a `clinicApi`; no conectar las páginas directamente con Eloquent ni rutas HTTP.

## Reglas ya acordadas con frontend

### Ficha previa y activación de cuenta

Recepción puede crear una ficha de paciente sin cuenta. Cuando el paciente se registre con el mismo RUT y correo dentro del mismo centro, se debe vincular la nueva cuenta con esa ficha existente y no crear otra. Si solo coincide uno de esos datos, rechazar la operación y pedir corrección a recepción. En producción agregar verificación de identidad o correo antes de activar la cuenta.

### Agenda

El paciente puede cancelar o reprogramar hasta 24 horas antes de la hora de inicio. Recepción puede hacerlo fuera de ese plazo. Una cita no puede marcarse `ATENDIDA` antes de su hora de inicio ni `NO_SHOW` antes de su hora de término. Calcular todos los plazos con la zona horaria del centro y repetirlos en Form Requests, servicios y pruebas.

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

- Autenticación: `GET /sanctum/csrf-cookie`, `POST /api/login`, `POST /api/logout`, `GET /api/v1/me`, `POST /api/register`.
- Agenda: `GET/POST/PATCH /api/v1/appointments`, `GET /api/v1/appointments/{id}`, `GET /api/v1/slots`.
- Gestión del centro: `GET/POST/PATCH /api/v1/patients`, `/professionals`, `/specialties`, `/services`, `/availability`, `/result-types`.
- Resultados: `GET/POST /api/v1/results`, `POST /api/v1/results/{id}/publish`, `GET /api/v1/results/{id}/document`.
- Plataforma: `GET/POST/PATCH /api/v1/platform/centers` y `PUT /api/v1/platform/centers/{id}/administrator`.

Las rutas y cargas completas están documentadas en `medsync_frontend/docs/api-contract.md`.

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
