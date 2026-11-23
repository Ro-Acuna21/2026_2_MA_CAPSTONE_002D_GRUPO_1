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
- Se completó un recorrido de navegador y una prueba de pagos concurrentes sobre copias de las bases locales, descritos abajo. Esto no sustituye una revisión visual de todos los roles y centros ni una actualización con copias de producción.

## Verificación adicional con copias locales · 22-11-2026

Se copiaron `medsync_core` y `medsync_clinica_horizonte` con `pg_dump`/`pg_restore` a dos bases `medsync_audit_*`. Antes de iniciar la API se cambió **solo en la copia de Core** la referencia del centro para que apuntara a la copia clínica. Un marcador temporal en el nombre del centro confirmó por HTTP que ambos procesos de API usaban las copias. En ellas se aplicó la migración Core pendiente y las seis migraciones Center pendientes mediante el registro legado Core que detecta `scripts/audit_migration_status.php`. La auditoría posterior no mostró pendientes. Los recuentos de pacientes (3), profesionales (9), prestaciones (5), citas (3), precios locales (0) y resultados (0) se mantuvieron antes y después. Esta prueba demuestra compatibilidad con el esquema y los datos **locales** observados; no representa datos de otros centros.

Con Playwright contra la API y React de las copias pasaron tres recorridos: creación de cuenta y reserva persistida, rechazo de una fecha sin disponibilidad, y reprogramación con historial. El caso de inscripción multicentro del archivo E2E no se ejecutó porque la copia solo contenía un centro. También se comprobó desde la interfaz que SUPER_ADMIN crea una prestación global, ADMIN clasifica una prestación antigua y la vincula, un precio global histórico de $15.000 aparece en el reporte estimado de una cita atendida, y el cambio de categoría global vinculado se rechaza con HTTP 422. Una primera comprobación de clasificación consultó la base antes de terminar el `PATCH`; se repitió esperando la respuesta HTTP 200 y se confirmó el valor persistido.

La concurrencia se probó con **dos procesos Laravel** conectados a la misma copia PostgreSQL y dos sesiones de ADMIN. Dos POST simultáneos de $4.000 con la misma clave devolvieron 201 y 200 con el mismo ID; dos POST simultáneos de $3.000 con claves diferentes devolvieron 201 y 201. Quedaron tres pagos, $10.000 abonados y saldo cero; un sobrepago adicional se rechazó con HTTP 422. El primer intento devolvió 419 porque el inicio de sesión renovó el token CSRF; se repitió con el token actualizado. No se alteraron las bases originales: después de las pruebas Core conservaba su nombre de centro, sus tablas anteriores y el centro original conservaba 3 citas. Se detuvieron los tres servidores de prueba, se eliminaron las dos copias PostgreSQL y las capturas temporales, y no quedaron bases `medsync_audit_*`.

## Demostración sugerida

Desde el backend ejecutar `php artisan serve`; desde `medsync_frontend`, `npm run dev`. Usar las cuentas y datos de demostración preparados por el equipo en un entorno aislado. Mostrar acceso de SUPER_ADMIN y catálogo global; ADMIN clasifica una prestación antigua, vincula una de la misma categoría y define precio local; PROFESIONAL registra un examen compartido en una cita atendida; RECEPCIONISTA registra un abono y comprueba que el mismo reintento devuelve el pago original; PACIENTE consulta saldo. Finalmente mostrar que el reporte indica estimaciones y que los cargos históricos conservan su importe.
