# Paso 7: suscripciones y presentación comercial

Fecha de revisión: 31-10-2026.

## Decisión de cobro

El alcance actual usa **registro manual de pagos**. MedSync no cobra, no consulta bancos ni verifica comprobantes con un proveedor externo. Un registro `PAID` significa que un operador `SUPER_ADMIN` informó un pago, con fecha y referencia obligatorias. La interfaz lo llama «Pago informado manualmente»; no calcula ni afirma «pago al día». `recorded_by` y `created_at` conservan autor y fecha de registro. Una referencia no puede repetirse dentro de la misma suscripción.

No se bloquea automáticamente el acceso clínico por `EXPIRED` o `SUSPENDED`: falta una regla contractual aprobada para ello y bloquear atención sin esa regla sería riesgoso.

## Datos y API

Core `plans` es el catálogo. El seeder idempotente `PlanCatalogSeeder` carga Básico y Profesional con los importes que antes aparecían en el frontend (49.990 y 89.990 CLP), sin inventar suscripciones ni cobros. La plataforma puede editar/desactivar esos planes. El sitio público consulta `GET /api/public/plans`; un catálogo vacío se presenta como tal.

La suscripción abierta en Core `subscriptions` es la fuente para plataforma y centro. `medical_centers.plan` y `subscription_status` quedan como campos de compatibilidad, sincronizados al crear/editar una suscripción, pero las respuestas visibles se derivan de `subscriptions`. El cambio de plan cierra la fila abierta y conserva historial. `monthly_price_clp_snapshot` preserva el precio de contratación frente a ediciones futuras del catálogo.

- `GET/POST/PATCH /api/v1/platform/plans[/{plan}]`: solo `SUPER_ADMIN`.
- `GET /api/v1/platform/centers/{center}/subscription`: estado e historial, solo `SUPER_ADMIN`.
- `POST /api/v1/platform/centers/{center}/subscriptions` y `PATCH .../subscriptions/{subscription}`: asignación/cambio de estado manual, solo `SUPER_ADMIN`.
- `POST .../subscriptions/{subscription}/payments`: registro manual, solo `SUPER_ADMIN`.
- `GET /api/v1/center/subscription`: misma proyección, solo ADMIN del centro autenticado; no abre datos clínicos para plataforma.

La misma cuenta de plataforma no puede consultar `/api/v1/center/subscription`. Un administrador clínico no puede editar planes, suscripciones ni pagos.

## Paso 8: revisión integral del sistema

Comprobado en el entorno local: migraciones Core y Center aplicadas; tablas de resultados, precios, planes y suscripciones presentes; catálogo con dos planes y cero suscripciones/pagos inventados; sitio y API pública responden HTTP 200; ruta comercial protegida responde 401 con `Accept: application/json`. La suite Laravel pasó 53 pruebas y 363 aserciones; Vitest pasó 48 pruebas; compilación y lint completados (una advertencia heredada de Fast Refresh en `button.tsx`). Los flujos de permisos, aislamiento, resultados ficticios, precios y suscripciones están cubiertos por pruebas automatizadas.

Chrome recorrió con sesión real las páginas principales de plataforma, ADMIN, recepción y paciente: planes y especialidades, administración, informes, precios, reportes, agenda, reservas, pacientes y perfil. No hubo errores de JavaScript, alertas de fallo ni respuestas HTTP 500. El historial de citas se leyó desde PostgreSQL como paciente y recepción; el paciente no recibió el motivo interno y recepción sí. La ruta de resultados ya coincide con los roles autorizados. Los planes sin límite configurado dicen «No definido».

En la verificación posterior, se creó una membresía profesional temporal vinculada a una ficha demo existente. Chrome abrió Inicio, Agenda e Informes con esa sesión, sin errores ni alertas; se retiró la cuenta temporal al terminar. Ese profesional no tenía citas asignadas, por lo que la lectura de historial propio sigue verificada por la prueba Laravel. Se respaldaron Core y Center con `pg_dump`, se restauraron en bases nuevas y se verificaron 23 y 13 tablas públicas; las bases temporales se eliminaron. El script `medsync_backend/scripts/backup-postgres.ps1` también generó ambos archivos y un manifiesto SHA-256. Se generó otro respaldo después de asignar la suscripción. CORS acepta un origen HTTPS configurado para producción y conserva los orígenes de Vite en local.

Por decisión del responsable del sistema, Clínica Horizonte tiene ahora la suscripción **Profesional** con estado **ACTIVE**, inicio 31-10-2026 y precio mensual registrado de 89.990 CLP. Plataforma y ADMIN del centro reciben la misma proyección por API (HTTP 200); no se registraron pagos ni fecha de renovación ficticios.

### Pendientes antes de llamarlo sistema completo

1. **Correo de invitaciones:** el SMTP temporal aceptó un correo real de prueba y se confirmó su recepción en la bandeja Gmail. Las pruebas Laravel cubren envío de invitación, reenvío a recepción, activación y rechazo de tokens vencidos o reutilizados; falta repetir la prueba con la futura cuenta institucional cuando exista. El cambio de cuenta usa solo `.env`.
2. **Operación comercial:** el centro tiene plan Profesional activo por decisión del responsable, pero el precio del catálogo proviene de los valores de demostración previos; confirmarlo antes de usarlo como oferta contractual externa. No hay pago registrado, comprobante ni fecha de renovación. Los límites de usuarios/profesionales no se aplican automáticamente; por eso el catálogo no promete cupos.
3. **Producción:** el entorno inspeccionado es `local` con `APP_DEBUG=true`, cola `sync` y sesión en archivo. El respaldo y la restauración están comprobados localmente; para publicar aún hacen falta dominio, servidor, certificado HTTPS, destino de respaldo independiente, programación, retención y prueba de recuperación en esa infraestructura. Configurar el entorno según `medsync_backend/docs/OPERACION_PRODUCCION.md`.
4. **Escala:** reportes del paso 6 cargan citas de hasta 366 días en memoria del proceso para agregar; revisar paginación/SQL agregado si el volumen de cada centro crece. La política de retención de resultados privados sigue pendiente de decisión.

Esta revisión corresponde al único centro configurado en el entorno local. Un despliegue con varios centros debe repetir migraciones y pruebas por cada base Center.
