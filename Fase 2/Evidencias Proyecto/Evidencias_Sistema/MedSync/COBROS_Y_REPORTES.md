# Cobros de pacientes y reportes

## Reglas de cobro

- El profesional responsable puede indicar una prestación con precio vigente después de marcar la cita como `ATENDIDA`. La indicación guarda el precio del centro en ese momento y no genera deuda.
- Administración o recepción confirma el cargo cuando corresponda cobrar la prestación. La confirmación convierte la indicación en deuda del paciente en ese centro.
- Un abono disminuye el saldo pendiente; no se admiten abonos superiores al saldo. Solo administración puede anular un abono, con motivo registrado.
- Se puede anular una indicación con motivo. Un cargo no puede anularse si los abonos activos superarían los cargos restantes.
- Cada centro guarda sus indicaciones y abonos en su propia base. El reporte de cobros muestra únicamente el centro activo. `SUPER_ADMIN` no consulta datos clínicos ni financieros de centros.
- El saldo se calcula como **cargos confirmados menos abonos activos**. Una indicación pendiente nunca se suma al saldo. Este módulo no realiza pagos electrónicos ni emite documentos tributarios.

## Reporte de plataforma

El panel de `SUPER_ADMIN` mantiene centros registrados, pacientes por centro, pacientes únicos e inscripciones multicentro. Las cifras de pacientes se basan en asociaciones activas de rol `PACIENTE` con ficha vinculada y cuenta activa. Una persona inscrita en dos centros cuenta una vez como paciente único y dos veces como inscripción. Las cuentas de plataforma se muestran por separado y no se clasifican automáticamente como pacientes.

Se retiró “Cuentas sin centro” del reporte principal: era un conteo de cuentas sin asociación, no de pacientes sin centro.

## Puesta en marcha

Antes de migrar datos existentes, respaldar Core y cada base de centro. Ejecutar las migraciones incrementales con el procedimiento habitual de aprovisionamiento de centros; la nueva migración `2026_11_14_000001_create_patient_billing_tables.php` crea dos tablas por centro. No usar `migrate:fresh` en bases con datos. Las pruebas automatizadas usan SQLite temporal; queda pendiente verificar la migración y el flujo de cobro en PostgreSQL real antes del despliegue.
