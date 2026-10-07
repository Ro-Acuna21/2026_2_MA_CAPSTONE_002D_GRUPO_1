# Capstone-MedSync-002D-GRUPO-1

## MedSync

Proyecto Capstone de una plataforma SaaS multi-centro para gestión clínica. La implementación se encuentra en:

- `Fase 2/Evidencias Grupales/MedSync/medsync_frontend`: React, TypeScript y Vite.
- `Fase 2/Evidencias Grupales/MedSync/medsync_backend`: Laravel, Sanctum y PostgreSQL.

### Accesos principales

- `/`: landing pública de MediSync.
- `/centros`: directorio público de centros asociados.
- `/centros/{slug}`: micrositio público de un centro.
- `/ingresar`: selector de centro; no presupone ningún tenant.
- `/centro/{slug}/ingresar`: acceso clínico contextual.
- `/plataforma/acceso`: acceso exclusivo de `SUPER_ADMIN`.

El selector de acceso y registro consulta `GET /api/public/centers`; `POST /api/register` exige `center_slug`. Laravel valida el centro activo en Core y resuelve su base clínica desde `medical_centers.database_name`. El despliegue local puede tener solo un centro aprovisionado aunque el flujo ya acepta otros centros configurados en Core.

La arquitectura, reglas de negocio y operación se documentan en `Fase 2/Evidencias Grupales/MedSync/INDICACIONES_BACKEND.md`, `REQUERIMIENTOS_BD.md` y los README de frontend/backend.
