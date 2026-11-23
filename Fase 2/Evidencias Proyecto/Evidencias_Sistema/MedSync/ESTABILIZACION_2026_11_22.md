# Estabilización para revisión — 22-11-2026

Rama: `codex/medsync-estabilizacion-final`, basada en `codex/medsync-prestaciones-fechas-cobros`. No se hizo merge ni push. No se migraron las bases originales.

## Reglas aplicadas

- Una prestación local vinculada debe conservar la categoría de su prestación global. El cambio global se rechaza mientras cualquier centro tenga un vínculo; el cambio local se valida y se serializa con el registro global. Una prestación antigua sin categoría puede clasificarse mediante la edición de ADMIN, sin inferirla del nombre. Las prestaciones nuevas requieren categoría.
- Las categorías son `CONSULTATION`, `EXAM` y `DIAGNOSIS`. `DIAGNOSIS` describe una **prestación diagnóstica cobrable**, no un diagnóstico clínico de la ficha. Registrar un resultado clínico no crea un cargo.
- Solo la especialidad de la cita puede registrar una prestación, salvo un `EXAM` marcado explícitamente `is_shared_exam`. El administrador controla esta opción y el backend valida la operación.
- Una vigencia de precio puede comenzar en el pasado para completar tarifas históricas. Los intervalos son inclusivos y no pueden solaparse. El monto e inicio de un precio ya vigente o pasado no se modifican; se permite cerrar su intervalo. Los precios futuros pueden editarse o eliminarse. Las fechas comerciales usan `America/Santiago`; los cargos guardan una copia del importe y origen, y nunca se recalculan por cambiar tarifas.
- Los reportes calculan **ingresos estimados** según la fecha de la cita, primero con precio local y después con tarifa global si el vínculo es válido. No representan pagos recibidos y no cambian los cargos emitidos.
- Cada operación de pago exige un UUID `idempotency_key`. Repetir la misma clave y carga útil devuelve el pago original; usar la clave con otros datos se rechaza. Dos abonos distintos usan claves distintas. El bloqueo por paciente y el índice único por paciente/clave protegen reintentos concurrentes.

## Migraciones y reversión

- `2026_11_22_000002_add_shared_exam_to_services.php`: agrega booleano con valor inicial `false`. La reversión se niega si existe una prestación marcada como compartida.
- `2026_11_22_000003_add_payment_idempotency.php`: agrega clave y hash opcionales para no alterar pagos antiguos, con índice único por paciente y clave. La reversión se niega si ya hay pagos con clave.
- Para aplicar en un entorno real, respaldar Core y **cada** centro y reconciliar primero el historial de migraciones con `scripts/audit_migration_status.php`: algunos centros antiguos registraron migraciones en Core. Solo después, usar el procedimiento de despliegue del equipo para las migraciones incrementales de Core y de **cada** base de centro. Git no migra las bases. No ejecutar migraciones contra originales antes de la revisión.
- Se probó instalación nueva de Core y centro en dos bases PostgreSQL temporales y actualización de un centro temporal desde el esquema anterior con una prestación sin categoría y un pago histórico. La actualización conservó el pago y la categoría nula, agregó el índice único y dejó el examen compartido desactivado. Se eliminaron todas las bases temporales. Las pruebas de Laravel usan SQLite temporal para los escenarios funcionales. Falta comprobar datos y migraciones en copias de los centros reales antes del despliegue.

## Verificación y límites

- `php artisan test --compact`: 96 pruebas, 96 aprobadas. Ejecutar nuevamente antes de integrar.
- `npm test -- --run`: 71/71; `npm run lint`: aprobado; `npm run build`: aprobado con aviso de paquete de 586 kB.
- La comprobación PostgreSQL de instalación nueva terminó con `core_migrate_exit=0`, `center_migrate_exit=0`, y presencia de servicios, pagos, asignaciones y columnas nuevas. La comprobación de actualización terminó con `upgrade_exit=0`, categoría antigua `NULL`, pago antiguo intacto e índice único presente. Las tres bases temporales se eliminaron.
- La verificación automatizada no sustituye un recorrido visual completo ni una prueba de concurrencia real con dos conexiones PostgreSQL. Esos puntos deben revisarse con copias aisladas antes del despliegue.

## Demostración sugerida

Desde el backend ejecutar `php artisan serve`; desde `medsync_frontend`, `npm run dev`. Usar las cuentas y datos de demostración preparados por el equipo en un entorno aislado. Mostrar acceso de SUPER_ADMIN y catálogo global; ADMIN clasifica una prestación antigua, vincula una de la misma categoría y define precio local; PROFESIONAL registra un examen compartido en una cita atendida; RECEPCIONISTA registra un abono y comprueba que el mismo reintento devuelve el pago original; PACIENTE consulta saldo. Finalmente mostrar que el reporte indica estimaciones y que los cargos históricos conservan su importe.
