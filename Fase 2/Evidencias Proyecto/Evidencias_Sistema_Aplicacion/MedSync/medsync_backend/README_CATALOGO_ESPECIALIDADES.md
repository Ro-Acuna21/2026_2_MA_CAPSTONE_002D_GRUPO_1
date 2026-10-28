# Catálogo global de especialidades — guía de prueba

Este README explica cómo probar el flujo de dos niveles que pidió el profesor: el administrador de la plataforma (`SUPER_ADMIN`) crea las especialidades en un catálogo global, y el administrador de cada centro (`ADMIN`) solo elige cuáles activar en su centro.

Rama de integración: `codex/integracion-especialidades-globales`. Esta guía contempla el catálogo de plataforma, la selección por centro y el uso de esos IDs globales en prestaciones y profesionales.

## 1. Qué se agregó

En `medsync_backend`:

- `app/Models/Core/Specialty.php` — catálogo global (`medsync_core.specialties`).
- `app/Models/Core/MedicalCenterSpecialty.php` — selección de cada centro (`medsync_core.medical_center_specialties`).
- `app/Http/Controllers/Api/PlatformSpecialtyController.php` — `GET/POST/PATCH /api/v1/platform/specialties`, exclusivo de `SUPER_ADMIN`.
- `app/Http/Controllers/Api/CenterSpecialtyController.php` — `GET /api/v1/center/specialties` y `PUT /api/v1/center/specialties/{id}`, exclusivo de `ADMIN` del centro.
- `routes/api.php` — rutas nuevas agregadas; ninguna ruta existente se modificó.
- `app/Services/Specialties/CenterSpecialtyCatalog.php` — valida que el catálogo esté activo globalmente y seleccionado en el centro.
- `database/migrations/core/2026_10_28_000001_migrate_center_specialties_to_global_catalog.php` — guarda el mapeo de IDs, adapta prestaciones y profesionales y elimina la tabla local de cada centro.

`services.specialty_id` y `professional_specialty.specialty_id` usan los IDs globales. La migración debe ejecutarse en cada entorno antes de usar esta versión; no se ha ejecutado sobre las bases de datos del equipo.

## 2. Antes de probar

1. Actualiza tu rama local (`git pull`) para tener los archivos nuevos si no los tienes ya.
2. Haz respaldo de Core y de cada base de centro. Luego corre las migraciones pendientes:

   ```
   php artisan migrate
   ```

3. Necesitas dos sesiones distintas para probar los dos roles:
   - Una cuenta `SUPER_ADMIN` (acceso de plataforma, `/plataforma/acceso`).
   - Una cuenta `ADMIN` de un centro (por ejemplo, la de Clínica Horizonte que ya usas).

   Si no tienes a mano un `SUPER_ADMIN` de prueba, créalo con un `tinker` o seeder apuntando a la conexión `core`, con `system_role = 'SUPER_ADMIN'`.

4. Levanta backend y frontend como siempre (`php artisan serve`, `npm run dev`).

## 3. Probar el lado del SUPER_ADMIN (crear el catálogo global)

1. Entra en `/plataforma/acceso` con la cuenta `SUPER_ADMIN`.
2. Ve a `/plataforma/especialidades`.
3. En "Nueva especialidad global", crea una (por ejemplo "Cardiología") y guarda.
   - Debe aparecer en la lista como "Activa".
4. Intenta crear otra especialidad con el mismo nombre (puedes usar mayúsculas o espacios distintos, ej. " cardiología "). Debe rechazarla con el mensaje de nombre duplicado.
5. Abre la especialidad creada, cambia el nombre o la descripción y guarda. Debe actualizarse sin crear un registro nuevo.
6. Usa el botón "Desactivar globalmente". El estado debe cambiar a "Inactiva".

## 4. Probar el lado del ADMIN del centro (solo activar)

1. Cierra la sesión de plataforma y entra como `ADMIN` del centro (`/centro/{slug}/ingresar`).
2. Ve a "Administración del centro" → tarjeta **"Especialidades que ofrece el centro"**.
3. Debes ver la especialidad creada en el paso anterior, marcada como "No seleccionada".
4. Haz clic en "Ofrecer en mi centro". Debe cambiar a "Activa en este centro".
5. Haz clic en "Desactivar en mi centro". Debe cambiar a "Inactiva en este centro" (sin desaparecer de la lista).
6. Verifica que el centro **no** tiene ninguna opción para crear, renombrar ni borrar especialidades del catálogo global — solo el botón de activar/desactivar.

## 5. Caso de rechazo (especialidad inactiva globalmente)

1. Como `SUPER_ADMIN`, desactiva globalmente una especialidad que el centro ya tenía activada (paso 3.6).
2. Como `ADMIN` del centro, recarga la tarjeta de especialidades.
   - Debe mostrarse como "Inactiva en el catálogo global" y el botón de activar debe aparecer deshabilitado.
3. Esto confirma que un centro no puede activar algo que la plataforma desactivó.

## 6. Verificar que nada antiguo se rompió

1. En "Administración del centro" → "Prestaciones del centro", comprueba que puedes crear/editar una prestación solo bajo una especialidad habilitada.
2. Asigna a un profesional una especialidad habilitada y reserva una cita que use esa prestación.
3. Deshabilita la especialidad en el centro; nuevas reservas ya no deben ofrecerla, pero las citas históricas deben seguir mostrándose.
3. Si algo de esto falla, es un problema aparte de este cambio — avisa para revisarlo.

## 7. Si algo no funciona

- **"Cargando catálogo global…" que no avanza o error 403/401**: revisa que la cuenta usada tenga `system_role = 'SUPER_ADMIN'` (plataforma) o el rol `ADMIN` dentro de `center_users` (centro). También confirma que la migración Core y la migración de IDs ya se aplicaron.
- **La especialidad no aparece en el centro después de crearla como SUPER_ADMIN**: recarga la página (botón "Reintentar"); la lista del centro no se actualiza en vivo entre pestañas/sesiones distintas.
- **Error de validación al activar**: revisa si esa especialidad está desactivada globalmente (ver sección 5).
