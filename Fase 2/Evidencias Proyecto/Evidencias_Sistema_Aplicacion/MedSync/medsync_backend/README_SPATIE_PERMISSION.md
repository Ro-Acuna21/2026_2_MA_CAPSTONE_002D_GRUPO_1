# Implementación de Spatie Laravel Permission

## Objetivo

El backend usa [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v8/introduction) para comprobar qué acciones puede ejecutar una persona dentro de cada centro médico. Se conserva `center_users` como la relación entre la cuenta global y cada centro, y como fuente de los datos de negocio asociados a esa membresía (`patient_id`, `professional_id` y estado activo).

Una cuenta puede pertenecer a más de un centro y tener un rol distinto en cada uno. Spatie guarda roles y asignaciones en Core, con el modo **Teams** activado y `medical_center_id` como identificador del equipo. Las tablas de autorización no se copian a las bases de datos clínicas.

## Roles y permisos incluidos

| Rol del centro | Permisos que aplica el backend |
| --- | --- |
| `ADMIN` | `professionals.manage`, `professionals.access.manage`, `services.manage`, `availability.manage`, `center.specialties.manage` |
| `RECEPCIONISTA` | `patients.manage`, `appointments.view.all`, `appointments.create.for_patient`, `appointments.reschedule.for_patient`, `appointments.status.manage` |
| `PROFESIONAL` | `appointments.view.own`, `appointments.status.manage.own` |
| `PACIENTE` | `patients.view.own`, `patients.update.own`, `appointments.view.own`, `appointments.create.own`, `appointments.reschedule.own`, `appointments.cancel.own` |

Los permisos se asignan al rol del centro mediante `CenterRolePermissionSync`. Al crear o cambiar una membresía `CenterUser`, se sincroniza el rol correspondiente; la migración de carga inicial hace lo mismo para las membresías existentes.

## Cambio de centro y aislamiento

`ResolveCenterTenant` valida la membresía activa, establece el ID del centro en el `PermissionRegistrar` antes de procesar la petición y lo limpia al terminar, incluso si la petición falla. También limpia las relaciones de roles y permisos cargadas en el modelo de usuario al cambiar de contexto. Así, una autorización otorgada en un centro no se reutiliza en otro.

El rol global `SUPER_ADMIN` continúa en `users.system_role`: administra la plataforma y no es un rol de centro. Las rutas del superadministrador conservan sus comprobaciones actuales. El flujo de inicio de sesión y selección de centro también conserva `center_users`.

## Operaciones protegidas

Los controladores de pacientes, agenda, profesionales, acceso de profesionales, prestaciones, disponibilidad y especialidades del centro comprueban permisos Spatie. Las reglas de negocio que dependen de la identidad (por ejemplo, que el paciente opere sobre su propia ficha o que el profesional actualice una cita que le corresponde) siguen comprobándose además de los permisos.

Las pantallas de roles/permisos de la plataforma y el formulario genérico de acceso que actualmente guarda información local en el navegador no quedan conectados por este cambio. Tampoco se añaden todavía módulos de informes o resultados que no tengan lógica de backend funcional. La interfaz debe integrarse en un trabajo posterior si se requiere administrar permisos desde ella.

## Archivos principales

- `config/permission.php`: modelos Core, nombres de tablas y Teams con `medical_center_id`.
- `app/Models/Core/Role.php` y `Permission.php`: modelos Spatie conectados a Core.
- `app/Services/Authorization/CenterRolePermissionSync.php`: catálogo de permisos y sincronización de roles por centro.
- `app/Http/Middleware/ResolveCenterTenant.php`: establece y limpia el contexto de Teams.
- `database/migrations/core/2026_10_28_000002_create_permission_tables.php`: crea las cinco tablas Spatie en Core.
- `database/migrations/core/2026_10_28_000003_backfill_center_roles_to_spatie.php`: asigna los roles a membresías ya existentes.
- `tests/Tenant/CenterRolePermissionTest.php`: comprueba roles diferentes para la misma cuenta en dos centros.

## Migraciones

Después de respaldar Core y actualizar el código/dependencias, ejecutar desde `medsync_backend`. Para aplicar solo las migraciones de Spatie:

```powershell
composer install
php artisan migrate:status --database=core
php artisan migrate --database=core --path=database/migrations/core/2026_10_28_000002_create_permission_tables.php
php artisan migrate --database=core --path=database/migrations/core/2026_10_28_000003_backfill_center_roles_to_spatie.php
```

Las migraciones Spatie usan explícitamente la conexión `core`. En la base local usada para esta tarea se aplicaron solo esas dos migraciones. `migrate:status` mostró otras migraciones antiguas pendientes; por eso no se ejecutó `php artisan migrate` sin `--path`, que intentaría procesarlas también. Revisa esas migraciones pendientes por separado antes de aplicarlas.

```powershell
php artisan migrate:status
```

La migración inversa de carga inicial elimina los roles base gestionados por esta implementación y sus permisos; no se debe usar rollback en producción sin respaldo y revisión del impacto. La migración que crea las tablas también se revierte al retroceder esa tanda.

## Verificación

La prueba nueva valida que la misma cuenta recibe permisos de recepción en un centro y de paciente en otro. La suite de este proyecto puede ejecutarse con:

```powershell
php artisan test
```

Verificación realizada durante esta implementación: las dos pruebas nuevas de permisos pasaron (2 pruebas, 8 aserciones), incluida una membresía insertada sin eventos para simular datos previos. Todas las migraciones también corrieron correctamente en SQLite en memoria. Se corrigieron los fixtures de catálogo/pivote y se fijó el reloj del caso `NO_SHOW`; luego la suite completa pasó: **30 pruebas, 178 aserciones**.

Además, la validación manual recomendada es iniciar sesión con una misma cuenta que tenga roles distintos en dos centros y confirmar que un endpoint administrativo permitido en el primer centro devuelve `403` en el segundo.
