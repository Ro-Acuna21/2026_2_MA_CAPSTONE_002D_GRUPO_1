# Matriz de acceso por rol y centro

Estado verificado para las rutas Laravel disponibles al 31-10-2026. `SUPER_ADMIN` es global y usa `/plataforma/acceso`. Los demás roles pertenecen a una membresía `core.center_users` por centro, con Teams de Spatie en Core. Cada petición clínica requiere centro y membresía activos. `401` significa ausencia de sesión; `403`, rol, membresía o centro no autorizado; `404`, recurso inexistente en la base del centro activo; `422`, datos inválidos.

| Ruta o acción | SUPER_ADMIN | ADMIN | RECEPCIONISTA | PROFESIONAL | PACIENTE |
| --- | --- | --- | --- | --- | --- |
| `/api/v1/platform/centers`, especialidades globales y asignar ADMIN | Sí | 403 | 403 | 403 | 403 |
| `/api/v1/platform/centers/{center}/staff` y cambio de rol ADMIN/RECEPCIONISTA | Sí | 403 | 403 | 403 | 403 |
| `/api/v1/me` | Identidad global | Identidad del centro | Identidad del centro | Identidad del centro | Identidad del centro |
| `/api/v1/center/permissions` | 403 | Catálogo fijo backend | 403 | 403 | 403 |
| `/api/v1/center/receptionists` y revocación/reenvío | 403 | Centro activo | 403 | 403 | 403 |
| Gestionar profesionales, acceso profesional, prestaciones, disponibilidad y selección de especialidades | 403 | Centro activo | 403 | 403 | 403 |
| Leer prestaciones activas, profesionales por prestación y horarios reservables | 403 | Centro activo | Centro activo | Centro activo | Centro activo |
| `/api/v1/patients` listado/alta | 403 | 403 | Centro activo | 403 | 403 |
| `/api/v1/patients/{id}` lectura/edición | 403 | 403 | Centro activo | 403 | 403 salvo ficha propia |
| `/api/v1/appointments` agenda | 403 | 403 | Agenda del centro | Agenda propia | 403 |
| `/api/v1/appointments/{id}/history` | 403 | 403 | Citas del centro | Cita asignada | Cita propia, sin motivo interno |
| Crear/reprogramar citas | 403 | 403 | Para paciente del centro | 403 | Solo propias |
| Cambiar estado de cita | 403 | 403 | Según regla clínica | Solo cita propia autorizada | 403 |
| Cancelar cita propia | 403 | 403 | Mediante gestión de estado | 403 | Solo propia y plazo permitido |

La tabla expresa la autorización esperada antes de validar contenido. El backend también verifica propiedad de paciente/profesional y reglas de tiempo, estado y disponibilidad. La UI oculta menús por rol, pero Laravel vuelve a comprobar cada acción.

## Límites del modelo

- Una cuenta global puede tener roles distintos en centros distintos. Cada `(medical_center_id, user_id)` conserva una sola fila y un solo rol.
- La plataforma puede cambiar entre ADMIN y RECEPCIONISTA en esa fila. El evento de modelo sincroniza el nuevo rol Spatie en el equipo correspondiente. PACIENTE y PROFESIONAL no se convierten por esta ruta.
- Desactivar la membresía o el centro bloquea las rutas clínicas aunque Spatie conserve las asignaciones históricas.
- El catálogo de permisos es fijo en `CenterRolePermissionSync::ROLE_PERMISSIONS`; `GET /api/v1/center/permissions` lo expone para lectura del ADMIN. No existe un CRUD de permisos individuales.
- Resultados e informes, reportes y finanzas siguen siendo pasos posteriores; sus pantallas aún no acreditan autorización backend de esas operaciones.

Pruebas: `tests/Tenant/RoleAuthorizationMatrixTest.php` ejercita una misma cuenta como RECEPCIONISTA en un centro y PACIENTE en otro, propiedad de ficha, cambio de sesión y membresía inactiva. `tests/Feature/CenterAccessTest.php` verifica la frontera de plataforma, lectura del catálogo y transición de rol sin fuga de permisos entre equipos.
