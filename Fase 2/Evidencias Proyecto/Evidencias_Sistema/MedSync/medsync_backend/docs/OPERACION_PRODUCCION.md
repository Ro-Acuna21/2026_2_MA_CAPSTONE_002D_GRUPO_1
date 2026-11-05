# Operación de MedSync fuera del equipo local

## HTTPS y sesión

Publicar el frontend y la API bajo dominios HTTPS definidos por el operador. El servidor web o proxy debe presentar un certificado válido y dirigir las solicitudes a Laravel; `php artisan serve` es solo para desarrollo. Para dominios hermanos como `app.ejemplo.cl` y `api.ejemplo.cl`, configurar en el backend:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.ejemplo.cl
FRONTEND_URL=https://app.ejemplo.cl
CORS_ALLOWED_ORIGINS=https://app.ejemplo.cl
SANCTUM_STATEFUL_DOMAINS=app.ejemplo.cl,api.ejemplo.cl
SESSION_DOMAIN=.ejemplo.cl
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Construir el frontend con `VITE_API_URL=https://api.ejemplo.cl`. No usar dominios de ejemplo en el despliegue real. Laravel toma los orígenes CORS de `CORS_ALLOWED_ORIGINS`; en producción, si no se define, usa `FRONTEND_URL`. Los orígenes localhost solo son predeterminados para desarrollo y pruebas. Si un proxy termina TLS, configurar únicamente sus direcciones confiables para las cabeceras `X-Forwarded-*`; no confiar en cualquier origen.

Generar `APP_KEY` para ese entorno, usar credenciales PostgreSQL y SMTP propias, y conservar `.env` fuera de Git. Desactivar el modo de depuración y verificar cookies `Secure` y solicitudes API autenticadas desde el dominio público. Los archivos de resultados se sirven mediante rutas autorizadas y están en `storage/app/private/medical-results`, fuera de `public`.

El remitente y proveedor de correo se configuran con `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME`. Para pasar de la cuenta personal de prueba a una institucional, actualizar esas variables en el `.env` del entorno, limpiar la caché de configuración si existe y enviar un correo de prueba. Las notificaciones no tienen direcciones SMTP fijas en el código. No subir `.env` ni registrar su contraseña en documentación o logs.

## Respaldo y restauración

`scripts/backup-postgres.ps1` lee Core para descubrir todas las bases clínicas registradas. Crea archivos `pg_dump` de formato personalizado, verifica que `pg_restore` pueda leerlos, calcula SHA-256 y copia los documentos clínicos privados. Requiere PostgreSQL CLI y acceso a las bases. Ejemplo desde `medsync_backend`:

```powershell
./scripts/backup-postgres.ps1 -OutputDirectory 'D:\RespaldosSeguros\MedSync' -PgBin 'D:\Program Files\PostgresSQL\18\bin'
```

El destino debe estar protegido contra acceso público y tener una copia independiente del servidor. Programar su ejecución, retención y comprobación de hashes según la operación real. Cada snapshot incluye `manifest.json`; no subir respaldos ni documentos clínicos a Git.

Para probar recuperación, crear bases **nuevas y vacías** en un entorno aislado y restaurar cada archivo con `pg_restore --no-owner --no-acl -d <base_nueva> <archivo.dump>`. Verificar tablas, conteos y la correspondencia `core.medical_centers.database_name`; restaurar también `medical-results` en el almacenamiento privado. No sobrescribir una base activa para hacer esta prueba. En el equipo local se restauraron Core y Center en bases temporales y se comprobaron 23 y 13 tablas públicas, respectivamente.

## Dependencias externas

La publicación HTTPS necesita dominio, alojamiento y certificado. La entrega real de invitaciones necesita un SMTP operativo y una credencial válida en `MAIL_PASSWORD`. Registrar una suscripción del centro requiere decidir plan y estado contractual; el sistema no registra pagos bancarios por sí mismo.
