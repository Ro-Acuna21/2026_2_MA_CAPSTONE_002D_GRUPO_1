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

Comprobado en el entorno local: migraciones Core y Center aplicadas; tablas de resultados, precios, planes y suscripciones presentes; catálogo con dos planes y cero suscripciones/pagos inventados; sitio y API pública responden HTTP 200; ruta comercial protegida responde 401 con `Accept: application/json`; suite Laravel, Vitest, compilación y lint ejecutados. Los flujos de permisos, aislamiento, resultados ficticios, precios y suscripciones están cubiertos por pruebas automatizadas.

### Pendientes antes de llamarlo sistema completo

1. **Historial de citas en la web:** `AppointmentHistoryPage` usa `data.history` local y muestra que no consulta el historial persistido en Laravel. Falta endpoint de lectura autorizado e integración; afecta trazabilidad visible.
2. **Validación manual de interfaz:** el control automatizado de Chrome fue detenido por la herramienta al no poder confirmar la URL actual. Se verificó HTTP y compilación, pero no se completó recorrido visual autenticado de todos los roles.
3. **Correo de invitaciones:** el mailer local es SMTP, pero esta revisión no envió correos reales ni comprobó recepción, token vencido/usado y reenvío de cada rol fuera de las pruebas.
4. **Operación comercial:** el plan del centro actual sigue «sin suscripción registrada» hasta que plataforma lo asigne. Los precios de catálogo son los valores de demostración previos y deben aprobarse antes de mostrarlos como oferta contractual. Límites de usuarios/profesionales no se aplican automáticamente; por eso el catálogo inicial no promete cupos.
5. **Producción:** el entorno inspeccionado es `local` con `APP_DEBUG=true`, cola `sync` y sesión en archivo. Para despliegue externo, configurar infraestructura, respaldos de Core, cada base Center y archivos clínicos privados, HTTPS y una prueba de restauración.
6. **Escala:** reportes del paso 6 cargan citas de hasta 366 días en memoria del proceso para agregar; revisar paginación/SQL agregado si el volumen de cada centro crece. La política de retención de resultados privados sigue pendiente de decisión.

Esta revisión corresponde al único centro configurado en el entorno local. Un despliegue con varios centros debe repetir migraciones y pruebas por cada base Center.
