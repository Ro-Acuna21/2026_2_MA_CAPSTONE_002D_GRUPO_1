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

La selección dinámica de diferentes bases de datos de centros médicos será implementada progresivamente en futuras iteraciones.

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
