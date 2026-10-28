# Respaldo SQL — MedSync

Este directorio contiene respaldos SQL de la estructura de base de datos utilizada actualmente en el proyecto MedSync.

MedSync utiliza PostgreSQL y trabaja con una arquitectura que separa la información general de la plataforma de los datos propios de cada centro médico.

Actualmente se utilizan dos bases de datos principales:

- `medsync_core`
- `medsync_clinica_horizonte`

La fuente principal para crear, modificar y versionar la estructura de las bases de datos son las migraciones de Laravel ubicadas en:

medsync_backend/database/migrations/

Por lo tanto, los archivos SQL almacenados en esta carpeta no reemplazan las migraciones de Laravel. Su objetivo es servir como respaldo documental de la estructura actual de las bases de datos y permitir revisar o recrear manualmente el esquema en caso de ser necesario.

---

## 1. Base de datos central — medsync_core

La base de datos `medsync_core` contiene la información general de la plataforma MedSync.

Entre sus principales tablas se encuentran:

- `users`
- `medical_centers`
- `medical_center_addresses`
- `center_users`

### users

Contiene las cuentas utilizadas para autenticarse en MedSync.

Los usuarios se almacenan de manera centralizada para evitar duplicar cuentas entre distintos centros médicos.

También permite identificar usuarios con permisos globales, como el rol:

- `SUPER_ADMIN`

### medical_centers

Contiene los centros médicos registrados en la plataforma.

Cada centro puede almacenar información como:

- nombre
- slug
- RUT
- correo
- teléfono
- nombre de la base de datos asociada
- estado

El campo `database_name` permite identificar qué base de datos corresponde a cada centro médico.

En una solicitud clínica, Laravel no toma el nombre de base desde la URL, headers ni payload. Después de autenticar, recupera el centro activo desde sesión, valida su estado y la membresía activa en `center_users`, y usa este campo para configurar la conexión clínica `center`. Esto permite que cada centro ya provisionado use su propia base sin rutas o controladores específicos.

### medical_center_addresses

Almacena las direcciones de los centros médicos de manera separada y normalizada.

### center_users

Relaciona una cuenta de `users` con un centro médico determinado.

Esta tabla permite indicar:

- a qué centro pertenece el usuario
- qué rol tiene dentro de ese centro
- si está relacionado con un paciente
- si está relacionado con un profesional
- si su acceso está activo

Actualmente se consideran roles como:

- `ADMIN`
- `RECEPCIONISTA`
- `PROFESIONAL`
- `PACIENTE`

### specialties y medical_center_specialties

El catálogo maestro de especialidades se almacena en `medsync_core.specialties`. El administrador de la plataforma mantiene este catálogo; `is_active` indica si la especialidad está disponible globalmente.

La tabla `medical_center_specialties` registra qué especialidades selecciona cada centro. Cada fila contiene el centro, la especialidad y un `is_active` propio del centro. La pareja centro-especialidad es única y ambas referencias tienen claves foráneas dentro de Core.

La migración de integración transforma y retira la tabla local por centro. No existe una clave foránea directa desde una base de centro a Core, porque son bases PostgreSQL independientes; cualquier ID central almacenado en ellas es una referencia lógica validada por la aplicación.

### Migración de datos pendiente de ejecución

El cambio final es retirar **`specialties` de cada base de centro** (por ejemplo, `medsync_clinica_horizonte.specialties`). No se deben eliminar `services`, `professional_specialty`, `appointments` ni las tablas nuevas de Core. Las columnas `services.specialty_id` y `professional_specialty.specialty_id` se conservan como referencias lógicas a `medsync_core.specialties.id`; no pueden tener una FK entre bases.

La rama `codex/integracion-especialidades-globales` implementa el backend y el ordenamiento de datos. Antes de aplicarla:

1. Respaldar la base Core y cada base Center.
2. Revisar los nombres duplicados locales que podrían representar conceptos diferentes. La migración se detiene cuando detecta duplicados con diferencias solo de mayúsculas o espacios.
3. Ejecutar la migración Laravel nueva desde el backend. Guarda el mapeo `center_id + local_specialty_id -> core_specialty_id`, actualiza referencias y elimina la tabla local en cada centro.
4. Comprobar que las prestaciones, asociaciones profesionales y reservas históricas siguen accesibles, y que cada centro conserva su estado de habilitación.

La tabla puente `medical_center_specialties` indica qué especialidad ofrece cada centro; la tabla global `specialties` no se elimina en esta transición. La migración retira explícitamente las restricciones locales y deja los IDs como referencias lógicas. Su `down()` vuelve a crear la tabla de cada centro desde el mapeo guardado.

El `SUPER_ADMIN` no necesita un registro en `center_users`, ya que corresponde a un rol global de la plataforma.

---

## 2. Base de datos del centro — medsync_clinica_horizonte

La base de datos `medsync_clinica_horizonte` contiene información administrativa propia de Clínica Horizonte.

Entre sus principales tablas se encuentran:

- `health_insurances`
- `patients`
- `patient_addresses`
- `professionals`

### health_insurances

Contiene el catálogo de previsiones de salud disponibles para los pacientes.

Actualmente se utilizan registros como:

- Fonasa
- Isapre
- Particular
- Otra

### patients

Contiene las fichas administrativas de los pacientes pertenecientes al centro médico.

Puede almacenar información como:

- nombre
- apellido
- RUT
- fecha de nacimiento
- correo electrónico
- teléfono
- previsión de salud
- consentimiento
- estado

La columna `user_id` permite relacionar la ficha del paciente con una cuenta existente en `medsync_core.users`.

El módulo Pacientes ahora lista, crea, consulta y edita fichas mediante la API autenticada de Laravel en la conexión clínica resuelta para el centro. Usa las columnas existentes de `patients`, la previsión en `health_insurances` y la dirección principal en `patient_addresses`. No requiere migración ni cambio al respaldo para este flujo. El correo de fichas vinculadas a una cuenta se mantiene fijo hasta implementar una actualización coordinada con Core.

La relación entre ambas bases de datos es lógica y es administrada por la aplicación, ya que PostgreSQL no utiliza claves foráneas directas entre bases de datos independientes.

### patient_addresses

Contiene las direcciones de los pacientes.

La dirección fue separada de la tabla `patients` para mantener una estructura más normalizada y permitir futuras ampliaciones.

### professionals

Contiene las fichas administrativas de los profesionales pertenecientes al centro médico.

Puede almacenar información como:

- nombre
- apellido
- RUT
- correo
- teléfono
- estado

La columna `user_id` puede ser nula.

Esto permite registrar un profesional administrativamente sin crear inmediatamente una cuenta para iniciar sesión.

Posteriormente, si se habilita acceso al profesional, se podrá relacionar su ficha con un usuario de `medsync_core`.

---

## 3. Relación entre ambas bases de datos

La estructura general utilizada actualmente es:

medsync_core
├── users
├── medical_centers
├── medical_center_addresses
├── center_users
└── tablas internas de Laravel

medsync_clinica_horizonte
├── health_insurances
├── patients
├── patient_addresses
├── professionals
└── migrations

La idea principal de esta arquitectura es centralizar la identidad y autenticación de los usuarios, pero mantener separados los datos operacionales pertenecientes a cada centro médico.

Por ejemplo:

Usuario
    ↓
medsync_core.users
    ↓
medsync_core.center_users
    ↓
Clínica Horizonte
    ↓
medsync_clinica_horizonte
    ↓
patient / professional

Actualmente la aplicación trabaja con Clínica Horizonte como primer centro funcional.

El login y registro públicos ya resuelven dinámicamente la conexión clínica mediante el centro activo en `medsync_core.medical_centers`; el navegador envía `center_slug`, nunca `database_name`. `GET /api/public/centers` publica solo identificador, nombre y slug de centros activos. En el entorno local puede estar aprovisionada solo `medsync_clinica_horizonte`. Este cambio de flujo no altera tablas ni datos existentes.

---

## 4. Tablas internas de Laravel

Además de las tablas principales del sistema, Laravel puede utilizar tablas internas como:

- `migrations`
- `personal_access_tokens`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

Estas tablas son administradas mediante las migraciones de Laravel.

No representan directamente entidades del negocio de MedSync.

---

## 5. Migraciones como fuente principal

La estructura oficial de las bases de datos se encuentra definida mediante migraciones de Laravel.

En caso de existir diferencias entre los respaldos SQL almacenados en esta carpeta y las migraciones del proyecto, se debe considerar como válida la estructura definida en:

medsync_backend/database/migrations/

Los respaldos SQL cumplen principalmente una función documental y de apoyo.

## Catálogo global de especialidades (actualización 28-10-2026)

La migración versionada `medsync_backend/database/migrations/core/2026_10_24_000001_create_specialty_catalog_tables.php` crea en **Core** `specialties(id, name, description, is_active, timestamps)` y `medical_center_specialties(id, medical_center_id, specialty_id, is_active, timestamps)`. La segunda tabla tiene claves foráneas hacia `medical_centers` y `specialties` de Core y una restricción única centro-especialidad. Esta es la ubicación adecuada para el catálogo común y las selecciones; un centro no debe administrar nombres globales ni consultar las selecciones de otro centro.

La nueva migración `medsync_backend/database/migrations/core/2026_10_28_000001_migrate_center_specialties_to_global_catalog.php` transforma las referencias de cada centro para que `services.specialty_id` y `professional_specialty.specialty_id` contengan IDs de `core.specialties`, elimina las claves foráneas locales y luego retira `center.specialties`. Conserva el mapeo y los datos de cada especialidad local en `core.medical_center_specialty_mappings`. La migración está preparada, pero aún no se ha ejecutado en las bases de datos del equipo.

Antes de aplicar la migración, respaldar Core y todas las bases de centros. Revisar nombres duplicados locales: la migración se detiene en ese centro y pide resolverlos antes de continuar. La operación es reversible usando la tabla de mapeo. Al ser bases PostgreSQL distintas, la referencia al ID Core es lógica y backend la valida; no crear una FK entre bases.

Pruebas necesarias antes de aplicar a datos reales: integridad de todos los IDs mapeados; dos centros que eligen subconjuntos distintos; activación/desactivación independiente por centro; rechazo de especialidad global inactiva; prestaciones y profesionales válidos solo si la selección está activa; citas históricas legibles; reservas y disponibilidad sin regresión. `center.availabilities` no cambia: la agrupación por profesional se realiza en React con `professional_id`; el bloque se desactiva con `is_active` y no se borra físicamente.

### Entrega pendiente para el responsable de BD

1. Verificar por entorno la aplicación de la migración Core del catálogo global y documentar respaldo, cantidad de filas y restricciones únicas/FK. No asumir que el respaldo SQL anterior contiene esas tablas.
2. Hacer respaldo de Core y de cada base Center antes de migrar. Aplicar la migración Laravel desde el backend para ejecutar el mapeo y retiro de la tabla local.
3. Después de migrar, comprobar que cada ID de servicio y vínculo profesional exista en `core.specialties`, y revisar reservas históricas y asociaciones activas por centro.

La corrección frontend de sesión caducada y carga de reservas no requiere cambios de esquema ni datos. En esta rama no se aplicaron migraciones, seeders ni escrituras SQL.
