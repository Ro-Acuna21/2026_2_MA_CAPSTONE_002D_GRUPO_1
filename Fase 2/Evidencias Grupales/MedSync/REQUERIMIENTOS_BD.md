# Requerimientos de base de datos — MedSync

Este documento define el modelo objetivo para PostgreSQL y Laravel. Parte del esquema inicial de `medsync_BD/medsync_schema.sql`, pero lo amplía para el SaaS multi-centro y para los flujos que el frontend ya muestra. No usar datos clínicos reales en desarrollo.

## Reglas transversales

- Usar `id` como UUID o `bigint` de Laravel de forma consistente en todas las tablas, además de `created_at` y `updated_at`.
- Todas las tablas clínicas y administrativas del centro llevan `medical_center_id`; las consultas deben filtrarlo siempre.
- Correo y RUT no son únicos globalmente: deben ser únicos dentro de un centro. La misma persona puede atenderse en dos centros sin que uno conozca al otro.
- Usar `deleted_at` cuando se necesite conservar historial. No borrar citas, resultados ni auditoría físicamente.
- Los valores enumerados se implementan como `CHECK` de PostgreSQL o enums validados por Laravel.

## Tablas de plataforma

| Tabla | Atributos requeridos | Claves y reglas |
| --- | --- | --- |
| `medical_centers` | `id`, `name`, `slug`, `rut`, `address`, `phone`, `email`, `plan`, `subscription_status`, `subscription_ends_at`, `is_active`, timestamps | `slug` único; `rut` único para el centro. `plan`: `STARTER` o `PRO`. `subscription_status`: `TRIAL`, `ACTIVE`, `EXPIRED`, `SUSPENDED`. |
| `users` | `id`, `name`, `email`, `password`, `email_verified_at`, `is_active`, `remember_token`, timestamps, `deleted_at` | El correo no debe tener un índice único global si una cuenta se repite en distintos centros. La contraseña se almacena solo con hash de Laravel. |
| `center_users` | `id`, `medical_center_id`, `user_id`, `role`, `patient_id` opcional, `professional_id` opcional, `is_active`, timestamps | Único compuesto `(medical_center_id, user_id)`. `role`: `ADMIN`, `RECEPCIONISTA`, `PROFESIONAL` o `PACIENTE`. El superadministrador es global mediante Spatie y no necesita pertenecer a un centro. |
| `plans` opcional | `id`, `name`, `code`, `description`, `is_active`, timestamps | Permite sustituir el enum de plan si se administrarán planes desde plataforma. |
| `subscriptions` opcional | `id`, `medical_center_id`, `plan_id`, `status`, `started_at`, `ends_at`, `notes`, timestamps | Mantiene historial de cambios; el estado actual puede derivarse desde el último registro. |

## Catálogos y personas del centro

| Tabla | Atributos requeridos | Claves y reglas |
| --- | --- | --- |
| `health_insurances` | `id`, `name`, `type`, `is_active`, timestamps | Catálogo global o por centro. `type`: `FONASA`, `ISAPRE`, `PARTICULAR`, `OTHER`. |
| `patients` | `id`, `medical_center_id`, `user_id` opcional, `first_name`, `last_name`, `rut`, `birth_date`, `email`, `phone`, `address` opcional, `health_insurance_id` opcional, `medical_insurance` opcional, `consent_at`, `is_active`, timestamps, `deleted_at` | Únicos compuestos `(medical_center_id, rut)` y `(medical_center_id, email)`. `user_id` se completa al registrar o vincular una cuenta. Crear ficha no crea obligatoriamente un usuario. |
| `professionals` | `id`, `medical_center_id`, `user_id` opcional, `first_name`, `last_name`, `rut`, `email`, `phone`, `description` opcional, `is_active`, timestamps, `deleted_at` | Únicos compuestos por centro para RUT y correo. El usuario se vincula al habilitar acceso profesional. |
| `specialties` | `id`, `medical_center_id`, `name`, `description` opcional, `is_active`, timestamps | Único compuesto `(medical_center_id, name)`. |
| `professional_specialty` | `professional_id`, `specialty_id`, timestamps | Clave única compuesta; ambas relaciones deben corresponder al mismo centro. |
| `services` | `id`, `medical_center_id`, `specialty_id`, `name`, `duration_minutes`, `is_active`, timestamps | Duración positiva y especialidad del mismo centro. Una prestación no es un tipo de resultado. |

## Agenda y atención

| Tabla | Atributos requeridos | Claves y reglas |
| --- | --- | --- |
| `availabilities` | `id`, `medical_center_id`, `professional_id`, `weekday`, `start_time`, `end_time`, `is_active`, timestamps | `weekday` entre 1 y 7; `start_time < end_time`; profesional del mismo centro. |
| `appointments` | `id`, `medical_center_id`, `patient_id`, `professional_id`, `specialty_id`, `service_id`, `appointment_date`, `start_time`, `end_time`, `status`, `source`, `note` opcional, `overbook`, `created_by`, timestamps, `deleted_at` | Horas deben ser `TIME`, no `DATE`. `status`: `PENDIENTE`, `CONFIRMADA`, `ATENDIDA`, `CANCELADA`, `NO_SHOW`; `source`: `WEB`, `RECEPCION`, `DEMO`. Validar que paciente, profesional, especialidad y prestación pertenezcan al centro. |
| `appointment_history` | `id`, `appointment_id`, `actor_user_id`, `event_type`, `previous_status` opcional, `new_status` opcional, `old_date` opcional, `old_start_time` opcional, `new_date` opcional, `new_start_time` opcional, `created_at` | Inmutable. `event_type`: `CREACION`, `CAMBIO_ESTADO`, `REPROGRAMACION`, `CANCELACION`. |

## Informes y resultados médicos

| Tabla | Atributos requeridos | Claves y reglas |
| --- | --- | --- |
| `result_types` | `id`, `medical_center_id`, `name`, `description` opcional, `is_active`, timestamps | Único compuesto `(medical_center_id, name)`. Ejemplos: informe ADOS, biopsia, informe de atención. |
| `medical_results` | `id`, `medical_center_id`, `patient_id`, `professional_id`, `result_type_id`, `appointment_id` opcional, `performed_at`, `status`, `published_at` opcional, `created_by`, `published_by` opcional, timestamps, `deleted_at` | `status`: `DRAFT` o `PUBLISHED`. El profesional responsable publica; el paciente solo puede leer sus propios registros publicados. |
| `medical_result_files` | `id`, `medical_result_id`, `disk`, `storage_path`, `original_filename`, `mime_type`, `size_bytes`, `checksum` opcional, `uploaded_by`, timestamps | Archivos privados; no guardar base64 ni contenido binario en la tabla. Validar PDF, PNG, JPEG o TXT y el tamaño acordado. |

## Spatie Permission y auditoría

Spatie crea `roles`, `permissions`, `model_has_roles`, `model_has_permissions` y `role_has_permissions`. Definir los roles globales `SUPER_ADMIN` y los roles por centro `ADMIN`, `RECEPCIONISTA`, `PROFESIONAL`, `PACIENTE`. Si se requiere registrar cambios fuera de citas, agregar `audit_logs` con `id`, `medical_center_id` opcional, `actor_user_id`, `action`, `auditable_type`, `auditable_id`, `before` JSONB opcional, `after` JSONB opcional, `ip_address` opcional y `created_at`.

## Índices mínimos

- `medical_centers(slug)` y `medical_centers(is_active, subscription_status)`.
- `center_users(medical_center_id, user_id)` único y `center_users(medical_center_id, role)`.
- `patients(medical_center_id, rut)` y `patients(medical_center_id, email)` únicos.
- `professionals(medical_center_id, rut)` y `professionals(medical_center_id, email)` únicos.
- `appointments(medical_center_id, appointment_date, professional_id, start_time)` para agenda y detección de solapamientos.
- `medical_results(medical_center_id, patient_id, status, performed_at)` y `medical_result_files(medical_result_id)`.

La base de datos debe añadir una restricción o validación transaccional que impida reservas solapadas para un profesional, excepto cuando `overbook` haya sido autorizado explícitamente.
