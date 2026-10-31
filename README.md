# Capstone-MedSync-002D-GRUPO-1

## MedSync

Proyecto Capstone de una plataforma SaaS multi-centro para gestión clínica. La implementación se encuentra en:

- `Fase 2/Evidencias Proyecto/Evidencias_Sistema_Aplicacion/MedSync/medsync_frontend`: React, TypeScript y Vite.
- `Fase 2/Evidencias Proyecto/Evidencias_Sistema_Aplicacion/MedSync/medsync_backend`: Laravel, Sanctum y PostgreSQL.

### Accesos principales

- `/`: landing pública de MediSync.
- `/centros`: directorio público de centros asociados.
- `/centros/{slug}`: micrositio público de un centro.
- `/ingresar`: selector de centro; no presupone ningún tenant.
- `/centro/{slug}/ingresar`: acceso clínico contextual.
- `/plataforma/acceso`: acceso exclusivo de `SUPER_ADMIN`.

El selector de acceso y registro consulta `GET /api/public/centers`; `POST /api/register` exige `center_slug`. Laravel valida el centro activo en Core y resuelve su base clínica desde `medical_centers.database_name`. El despliegue local puede tener solo un centro aprovisionado aunque el flujo ya acepta otros centros configurados en Core.

La arquitectura, reglas de negocio y operación se documentan en `Fase 2/Evidencias Proyecto/Evidencias_Sistema_Aplicacion/MedSync/INDICACIONES_BACKEND.md`, `REQUERIMIENTOS_BD.md` y los README de frontend/backend.

### Pasos 1 y 2: centros e incorporación de pacientes

`SUPER_ADMIN` crea y edita centros desde `/plataforma`; Laravel guarda sus datos en Core y crea automáticamente una base PostgreSQL clínica aislada. Un centro solo se publica después de migrar y verificar esa base. El usuario PostgreSQL configurado requiere privilegio `CREATEDB`. Una cuenta global existente puede iniciar sesión en otro centro y, con consentimiento explícito, crear allí una ficha local sin duplicar la cuenta ni copiar datos clínicos.

La plataforma asigna ADMIN por centro y cada ADMIN asigna recepción dentro de su tenant. Las cuentas nuevas reciben una invitación de activación por correo; el envío depende de un SMTP operativo.

### Roles y permisos por centro

Laravel aplica el catálogo fijo de Spatie en Core con Teams por `medical_center_id`. La plataforma puede cambiar ADMIN y RECEPCIONISTA en una misma membresía; el ADMIN ve el catálogo real del backend en modo lectura. `SUPER_ADMIN` solo conserva `/api/v1/me` entre las rutas con contexto clínico y recibe `403` en el resto. Ver `medsync_backend/docs/MATRIZ_ACCESO_ROLES.md` para el detalle de autorización.
