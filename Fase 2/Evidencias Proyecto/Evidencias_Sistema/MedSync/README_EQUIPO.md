# MedSync: actualización del entorno de cada integrante

Esta guía corresponde al código actual de `main`. Ejecutar los comandos dentro de `Fase 2/Evidencias Proyecto/Evidencias_Sistema/MedSync`, no en la copia antigua de `Fase 2/Evidencias Grupales/MedSync`.

## Si ya tienes MedSync instalado

1. Guarda un respaldo de tus bases **Core y de cada centro** antes de migrar. En Windows puedes usar `medsync_backend/scripts/backup-postgres.ps1`; su destino debe estar protegido. El procedimiento de restauración está en `medsync_backend/docs/OPERACION_PRODUCCION.md`.
2. Actualiza la rama de trabajo de acuerdo con el equipo. Para seguir la versión integrada: `git switch main` y `git pull --ff-only origin main`. No borres cambios locales para forzar el pull; revísalos primero.
3. En `medsync_backend`, ejecuta `composer install`. Comprueba `php -v` y que `php -m` incluya `pdo_pgsql` y `pgsql`; se necesita PHP 8.3 o superior. Spatie Permission viene en `composer.lock`, por lo que no hay que instalarlo por separado.
4. Conserva tu `.env` personal. No copies encima el `.env` de otra persona ni lo subas a Git. Compara las variables nuevas con `.env.example`: `DB_CONNECTION=core`, bases `DB_CORE_DATABASE` y `DB_CENTER_DATABASE`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` y, si usarás invitaciones, `MAIL_*`. Si modificaste `.env`, ejecuta `php artisan optimize:clear`.
5. Ejecuta `php artisan migrate:status` y después `php artisan migrate`. Esto aplica las migraciones pendientes, incluida la transición de especialidades globales, Spatie, resultados, precios y suscripciones. Si tienes **más de un centro**, verifica y migra cada base Center registrada; no supongas que el respaldo SQL documental contiene el esquema vigente. No ejecutes `migrate:fresh` ni reinicies bases con datos.

Si al crear una prestación aparece `services_specialty_id_foreign` y se indica que el `specialty_id` no existe en `specialties`, la base clínica aún conserva el esquema local anterior. Respalda Core y esa base Center, comprueba que la migración Core `2026_10_28_000001_migrate_center_specialties_to_global_catalog` figure como ejecutada y aplica las migraciones pendientes de esa base Center. La migración Center `2026_11_02_000001_remove_obsolete_local_specialty_constraints` elimina las referencias locales solo cuando no hay datos locales; si los hay, detiene el proceso para que primero se conviertan mediante la migración Core. No insertes manualmente IDs en `center.specialties` ni desactives claves foráneas para ocultar el problema.
6. En `medsync_frontend`, ejecuta `npm ci`. Si tu API no está en `http://localhost:8000`, define `VITE_API_URL` en un `.env` local del frontend. Usa el mismo nombre de host en navegador y API (`localhost` o `127.0.0.1`) para que funcione Sanctum.
7. Inicia `php artisan serve` en el backend y `npm run dev` en el frontend. Verifica `http://localhost:3000`, el selector de centro y el acceso contextual. Ejecuta `php artisan test` y, en el frontend, `npm test`, `npm run lint` y `npm run build` si modificaste código.

No ejecutes `php artisan db:seed` automáticamente sobre una base existente: los seeders de este proyecto cargan datos y cuentas de demostración. Úsalos solo si quieres expresamente ese dataset y ya revisaste sus efectos. Tu PostgreSQL, archivos privados y suscripciones son **datos locales**; `git pull` no los copia desde el equipo de otra persona.

## Si partes desde cero

Instala PHP 8.3+ con `pdo_pgsql`/`pgsql`, Composer, PostgreSQL y Node.js. Crea `medsync_core` y `medsync_clinica_horizonte` vacías. En `medsync_backend`, ejecuta `composer install`, copia `.env.example` a `.env`, configura PostgreSQL y las URLs locales, ejecuta `php artisan key:generate`, `php artisan migrate` y `php artisan db:seed`. El seeder carga el catálogo y cuentas de prueba; no inventa suscripciones ni pagos. En `medsync_frontend`, ejecuta `npm ci` y `npm run dev`.

Para enviar invitaciones, cada integrante configura su propio SMTP en `.env` (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`) y `FRONTEND_URL=http://localhost:3000`. La cuenta personal utilizada para verificar el envío no está en GitHub. Cambiar luego a una cuenta institucional solo requiere actualizar estas variables; no hay que editar código. No pegues credenciales en README, capturas ni commits.

## Datos y módulos

El acceso genérico no elige centro por defecto. `SUPER_ADMIN` entra por `/plataforma/acceso`; los demás roles usan `/centro/{slug}/ingresar`. Core conserva usuarios, centros, permisos y suscripciones; cada centro usa su base clínica. Los resultados se guardan en almacenamiento privado. Planes y pagos son registros comerciales manuales: una suscripción `ACTIVE` no implica un pago registrado.

Clínica Horizonte tiene una suscripción Profesional activa **solo en la base local donde se configuró**; `db:seed` en otra máquina no la replica. Si se necesita demostrar una suscripción allí, asignarla desde plataforma con el plan y estado que decida el equipo, sin registrar pagos ficticios.

## Revisión 02-11-2026

Tras actualizar el código, respalda Core y cada Center con `scripts/backup-postgres.ps1`, ejecuta `php artisan migrate:status` y luego `php artisan migrate --force` para las dos migraciones Core del 02-11-2026. Comprueba `/planes`, el envío de una solicitud ficticia y su lectura en `/plataforma/solicitudes`, la ficha pública de un centro con datos completos y el precio por fecha en una reserva. No se necesita dominio ni servidor externo para estas pruebas; las solicitudes comerciales se guardan localmente en Core.
