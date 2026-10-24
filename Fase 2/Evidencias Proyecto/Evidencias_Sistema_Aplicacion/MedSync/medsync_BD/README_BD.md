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

Las migraciones agregan estas tablas sin retirar todavía `specialties` de las bases de los centros. La tabla local y las referencias existentes de prestaciones y profesionales deben conservarse hasta que backend y datos se migren coordinadamente. No existe una clave foránea directa desde una base de centro a Core, porque son bases PostgreSQL independientes; cualquier ID central almacenado en ellas será una referencia lógica que deberá validar la aplicación.

### Implementación pendiente en backend y retiro de la tabla local

El cambio final es retirar **`specialties` de cada base de centro** (por ejemplo, `medsync_clinica_horizonte.specialties`). No se deben eliminar `services`, `professional_specialty`, `appointments` ni las tablas nuevas de Core. Las columnas `services.specialty_id` y `professional_specialty.specialty_id` se conservan como referencias lógicas a `medsync_core.specialties.id`; no pueden tener una FK entre bases.

El orden acordado para completar la transición es:

1. **Migrar el catálogo y la selección existente.** Consolidar en Core las especialidades locales sin duplicar el catálogo global y crear en `medical_center_specialties` una fila activa para cada especialidad que ya ofrece cada centro. Mantener un mapeo `center_id + local_specialty_id -> core_specialty_id`; no asumir que los IDs locales coinciden con los IDs centrales.
2. **Actualizar backend antes de borrar la tabla local.** Hugo debe cambiar las consultas/operaciones CRUD de especialidades para leer y administrar `core.specialties` y `core.medical_center_specialties`: el administrador de plataforma crea/desactiva filas del catálogo, y el administrador del centro activa/desactiva su relación. Al operar con prestaciones o profesionales, el backend debe comprobar que la especialidad existe y está activa globalmente y para el centro resuelto. Los modelos y relaciones Eloquent ya no deben consultar `specialties` por la conexión `center`.
3. **Actualizar las referencias locales.** Usando el mapeo, cambiar `services.specialty_id` y `professional_specialty.specialty_id` a los IDs de Core. Verificar que todas las filas tengan una especialidad central válida y que las citas sigan llegando a sus prestaciones mediante `appointments.service_id`.
4. **Desplegar y verificar el backend actualizado.** Probar lectura del catálogo, activación por centro, creación/listado de prestaciones, asociación de profesionales y reserva de citas en cada centro migrado.
5. **Retirar la tabla local mediante una nueva migración Laravel de `center`.** Primero quitar las claves foráneas locales hacia `specialties` (en `services.specialty_id` y `professional_specialty.specialty_id`); conservar esas columnas como referencias lógicas. Luego eliminar `specialties` de la base del centro. Esta migración debe ejecutarse solo después de los pasos anteriores y de verificar el backend actualizado.

La tabla puente `medical_center_specialties` es la que indica qué especialidad ofrece cada centro; la tabla global `specialties` no se elimina en esta transición. No usar `CASCADE` para borrar la tabla local: la migración debe retirar explícitamente las dos restricciones y mantener los IDs asociados.

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

## Observación Capstone: dependencias del catálogo global (24-10-2026)

La migración versionada `medsync_backend/database/migrations/core/2026_10_24_000001_create_specialty_catalog_tables.php` crea en **Core** `specialties(id, name, description, is_active, timestamps)` y `medical_center_specialties(id, medical_center_id, specialty_id, is_active, timestamps)`. La segunda tabla tiene claves foráneas hacia `medical_centers` y `specialties` de Core y una restricción única centro-especialidad. Esta es la ubicación adecuada para el catálogo común y las selecciones; un centro no debe administrar nombres globales ni consultar las selecciones de otro centro.

El esquema operacional actual conserva `center.specialties`; `center.services.specialty_id` y `center.professional_specialty.specialty_id` la referencian mediante claves foráneas. Las migraciones nuevas de Core no convierten automáticamente estas referencias. El respaldo Core fechado 15-10-2026 precede a la migración global y no prueba que las dos tablas estén aplicadas en la base en uso. Esta entrega no ejecuta SQL, migraciones ni seeders.

Para el compañero de BD: inventariar especialidades locales en **cada** centro; decidir correspondencias por concepto clínico, no solo por nombre o ID; registrar el mapeo `(medical_center_id, local_specialty_id, core_specialty_id)` y detectar duplicados/conflictos; respaldar antes de cualquier transformación. Poblar `core.medical_center_specialties` según las prestaciones ofrecidas y preservar tanto selecciones inactivas como historial. Coordinar con backend el cambio de lectura/autorización y solo después actualizar `services.specialty_id` y `professional_specialty.specialty_id`. Al ser bases PostgreSQL distintas, la referencia al ID Core será lógica y backend deberá validarla; no crear una FK entre bases ni borrar `center.specialties` mientras haya referencias locales. Diseñar una migración posterior, reversible y probada para retirar las FK y la tabla local cuando la transición esté verificada.

Pruebas necesarias antes de aplicar a datos reales: integridad de todos los IDs mapeados; dos centros que eligen subconjuntos distintos; activación/desactivación independiente por centro; rechazo de especialidad global inactiva; prestaciones y profesionales válidos solo si la selección está activa; citas históricas legibles; reservas y disponibilidad sin regresión. `center.availabilities` no cambia: la agrupación por profesional se realiza en React con `professional_id`; el bloque se desactiva con `is_active` y no se borra físicamente.

### Entrega pendiente para el responsable de BD

1. Verificar por entorno la aplicación de la migración Core del catálogo global y documentar respaldo, cantidad de filas y restricciones únicas/FK. No asumir que el respaldo SQL anterior contiene esas tablas.
2. Preparar un inventario por centro de `center.specialties`, `services.specialty_id` y `professional_specialty.specialty_id`; definir y revisar el mapeo hacia `core.specialties.id`. Resolver colisiones semánticas antes de poblar asociaciones.
3. Diseñar una migración de datos repetible y reversible que cree las selecciones en `core.medical_center_specialties` y preserve estados inactivos e historial. Coordinar la sustitución de referencias con backend; las bases Core y Center no pueden compartir FK PostgreSQL.
4. Registrar consultas de comprobación para referencias huérfanas, duplicados centro-especialidad, reservas y citas históricas. Retirar las FK y la tabla local únicamente cuando backend lea los IDs globales y las pruebas de dos centros y reservas pasen.

La corrección frontend de sesión caducada y carga de reservas no requiere cambios de esquema ni datos. En esta rama no se aplicaron migraciones, seeders ni escrituras SQL.
