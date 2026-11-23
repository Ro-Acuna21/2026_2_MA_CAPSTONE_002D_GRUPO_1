# Cobros de pacientes y reportes

## Reglas de cobro

- El profesional responsable puede indicar una prestación sin deuda o generar directamente un cargo después de marcar la cita como `ATENDIDA`. Puede convertir su indicación pendiente en cargo sin crear una segunda fila. El monto y origen del precio quedan guardados.
- Administración o recepción aún puede confirmar indicaciones pendientes cuando corresponda. Una cita y prestación solo admiten una indicación/cargo.
- Un abono se asigna a uno o varios cargos del mismo paciente. Cada cargo muestra `PENDING`, `PARTIAL` o `PAID` según sus abonos activos; no se admiten abonos superiores al saldo. Solo administración puede anular un abono, con motivo registrado.
- Se puede anular una indicación con motivo. Un cargo con abonos asignados requiere anular primero esos abonos.
- Cada centro guarda sus indicaciones y abonos en su propia base. El reporte de cobros muestra únicamente el centro activo. `SUPER_ADMIN` no consulta datos clínicos ni financieros de centros.
- El saldo se calcula como **cargos confirmados menos abonos activos**. Una indicación pendiente nunca se suma al saldo. Este módulo no realiza pagos electrónicos ni emite documentos tributarios.

## Catálogo y precios base

`SUPER_ADMIN` administra `platform_services` y `platform_service_prices` en Core, sin abrir bases clínicas. Cada `ADMIN` habilita una prestación global como servicio local o vincula explícitamente un servicio existente. Los precios propios permanecen en `center.service_prices`; cuando no hay precio propio vigente, se aplica el base vigente de Core. No se emparejan servicios por nombre automáticamente. La edición posterior de un precio no cambia el monto copiado en un cargo.

## Reporte de plataforma

El panel de `SUPER_ADMIN` mantiene centros registrados, pacientes por centro, pacientes únicos e inscripciones multicentro. Las cifras de pacientes se basan en asociaciones activas de rol `PACIENTE` con ficha vinculada y cuenta activa. Una persona inscrita en dos centros cuenta una vez como paciente único y dos veces como inscripción. Las cuentas de plataforma se muestran por separado y no se clasifican automáticamente como pacientes.

Se retiró “Cuentas sin centro” del reporte principal: era un conteo de cuentas sin asociación, no de pacientes sin centro.

## Puesta en marcha

Antes de migrar datos existentes, respaldar Core y cada base de centro y consultar `scripts/audit_migration_status.php`; algunos centros históricos registran migraciones en Core. Aplicar las migraciones incrementales `2026_11_20_000001_create_platform_services.php` (Core) y `2026_11_20_000001_link_services_and_allocate_payments.php` (cada centro) con el procedimiento de migración reconciliado. La segunda asigna abonos históricos activos a los cargos más antiguos del paciente y aborta si encuentra un abono imposible de asignar; revisar ese centro sin descartar datos. No usar `migrate:fresh`. Las pruebas automatizadas usan SQLite temporal; falta verificar migración y flujo en PostgreSQL de prueba antes de aplicar a datos reales.

## Ajustes de cobros y reportes · 22-11-2026

El reporte de citas valora cada fecha con `EffectiveServicePrice`: usa precio propio y, si no hay, precio Core solo cuando el vínculo y categoría coinciden. Son estimaciones, no pagos recibidos; los cargos emitidos conservan su monto guardado. Los pagos exigen un UUID por operación y devuelven el mismo registro ante un reintento idéntico. Las migraciones de instalación nueva y actualización de datos representativos se probaron en PostgreSQL temporal y se limpiaron; sigue pendiente probar una copia representativa de los centros reales y concurrencia con dos conexiones. Ver `ESTABILIZACION_2026_11_22.md`.
