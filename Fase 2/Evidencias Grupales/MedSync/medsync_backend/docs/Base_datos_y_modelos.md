# MedSync - Base de Datos

Este README explica los cambios realizados en la base de datos del proyecto **MedSync**, incluyendo la estructura definida, migraciones, modelos, seeders, relaciones principales y pasos para levantar el proyecto después de clonarlo.

---

## 1. Objetivo de los cambios

Se reorganizó la base de datos para preparar MedSync para una arquitectura tipo SaaS, donde el sistema pueda trabajar con varios centros médicos.

Para esto se separó la información en dos tipos de base de datos:

```text
core   = Base de datos central de MedSync
center = Base de datos propia de cada centro médico
```

Actualmente se configuraron estas bases:

```text
medsync_core
medsync_clinica_horizonte
```

La idea es que `medsync_core` guarde la información central de la plataforma, mientras que `medsync_clinica_horizonte` guarde los datos propios del centro médico.

---

## 2. Estructura de bases de datos

### Base de datos `medsync_core`

Contiene las tablas centrales del sistema:

```text
users
medical_centers
center_users
personal_access_tokens
cache
cache_locks
jobs
job_batches
failed_jobs
migrations
```

### Base de datos `medsync_clinica_horizonte`

Contiene las tablas propias del centro médico:

```text
health_insurances
patients
professionals
migrations
```

---

## 3. Configuración realizada en Laravel

Se agregaron dos conexiones en el archivo:

```text
config/database.php
```

Las conexiones configuradas fueron:

```text
core
center
```

La conexión por defecto quedó como:

```php
'default' => env('DB_CONNECTION', 'core'),
```

En el archivo `.env` se configuraron las variables de base de datos:

```env
DB_CONNECTION=core
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=medsync_core
DB_CORE_DATABASE=medsync_core
DB_CENTER_DATABASE=medsync_clinica_horizonte
DB_USERNAME=postgres
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Si PostgreSQL tiene contraseña, completar:

```env
DB_PASSWORD=tu_contraseña
```

---

## 4. Migraciones organizadas

Las migraciones se separaron en dos carpetas:

```text
database/migrations/core
database/migrations/center
```

### Migraciones de `core`

Estas migraciones crean las tablas centrales de MedSync:

```text
database/migrations/core
├── 0001_01_01_000001_create_cache_table.php
├── 0001_01_01_000002_create_jobs_table.php
├── 2026_09_04_164340_create_personal_access_tokens_table.php
├── 2026_09_06_225034_create_medical_centers_table.php
├── 2026_09_06_225035_create_users_table.php
└── 2026_09_06_225036_create_center_users_table.php
```

Estas migraciones usan:

```php
Schema::connection('core')
```

### Migraciones de `center`

Estas migraciones crean las tablas propias del centro médico:

```text
database/migrations/center
├── 2026_09_06_225040_create_health_insurances_table.php
├── 2026_09_06_225045_create_patients_table.php
└── 2026_09_12_212901_create_professionals_table.php
```

Estas migraciones usan:

```php
Schema::connection('center')
```

---

## 5. Tablas principales

### `users`

Tabla central donde se almacenan las cuentas de usuario del sistema.

Campos principales:

```text
id
name
email
password
email_verified_at
is_active
remember_token
created_at
updated_at
deleted_at
```

Esta tabla está en `medsync_core`.

---

### `medical_centers`

Tabla central donde se almacenan los centros médicos registrados en MedSync.

Campos principales:

```text
id
name
slug
database_name
rut
address
phone
email
is_active
created_at
updated_at
```

El campo `database_name` indica a qué base de datos pertenece el centro médico.

Ejemplo:

```text
name: Clínica Horizonte
slug: clinica-horizonte
database_name: medsync_clinica_horizonte
```

---

### `center_users`

Tabla central que relaciona usuarios con centros médicos y roles.

Campos principales:

```text
id
medical_center_id
user_id
role
patient_id
professional_id
is_active
created_at
updated_at
```

Roles definidos:

```text
ADMIN
RECEPCIONISTA
PROFESIONAL
PACIENTE
```

Esta tabla permite saber qué rol tiene un usuario dentro de un centro médico.

Ejemplo:

```text
user_id = 1
medical_center_id = 1
role = PACIENTE
patient_id = 1
professional_id = null
```

Esto significa que el usuario pertenece al centro médico con rol de paciente.

Los campos `patient_id` y `professional_id` no tienen foreign key directa, porque apuntan a registros que están en la base de datos del centro.

---

### `personal_access_tokens`

Tabla utilizada por Laravel Sanctum cuando se trabaja con autenticación mediante tokens.

Se dejó en `core`, porque la autenticación pertenece al sistema central.

---

### `health_insurances`

Tabla del centro médico donde se almacenan las previsiones de salud.

Registros creados por seeder:

```text
Fonasa
Isapre
Particular
Otra
```

---

### `patients`

Tabla del centro médico donde se almacenan los pacientes.

Campos principales:

```text
id
user_id
health_insurance_id
first_name
last_name
rut
birth_date
email
phone
address
medical_insurance
consent_at
consent_version
is_active
created_at
updated_at
deleted_at
```

Importante:

```text
patients no tiene medical_center_id
```

Esto es intencional, porque la tabla ya está dentro de la base de datos propia del centro médico.

Por ejemplo, si un paciente está en:

```text
medsync_clinica_horizonte.patients
```

entonces pertenece a Clínica Horizonte.

---

### `professionals`

Tabla del centro médico donde se almacenan los profesionales.

Campos principales:

```text
id
user_id
first_name
last_name
rut
email
phone
is_active
created_at
updated_at
deleted_at
```

Al igual que `patients`, no necesita `medical_center_id`, porque ya pertenece a la base de datos del centro.

---

## 6. Modelos creados o modificados

Los modelos se organizaron según la base de datos que utilizan.

```text
app/Models
├── User.php
├── Core
│   ├── MedicalCenter.php
│   └── CenterUser.php
└── Center
    ├── HealthInsurance.php
    ├── Patient.php
    └── Professional.php
```

### Modelos de `core`

Estos modelos usan:

```php
protected $connection = 'core';
```

Modelos:

```text
App\Models\User
App\Models\Core\MedicalCenter
App\Models\Core\CenterUser
```

### Modelos de `center`

Estos modelos usan:

```php
protected $connection = 'center';
```

Modelos:

```text
App\Models\Center\HealthInsurance
App\Models\Center\Patient
App\Models\Center\Professional
```

---

## 7. Relaciones principales

### Usuario con centro médico

La relación se maneja mediante `center_users`.

```text
users 1 ─── N center_users
medical_centers 1 ─── N center_users
```

Esto permite que un usuario tenga un rol dentro de un centro médico.

---

### Centro médico con pacientes y profesionales

Como se usa una base de datos por centro, los pacientes y profesionales no necesitan `medical_center_id`.

La relación se entiende por la base de datos del centro:

```text
medical_centers.database_name = medsync_clinica_horizonte
```

Entonces:

```text
medsync_clinica_horizonte.patients       = pacientes de Clínica Horizonte
medsync_clinica_horizonte.professionals  = profesionales de Clínica Horizonte
```

---

### Paciente con previsión

Un paciente puede tener una previsión de salud.

```text
health_insurances 1 ─── N patients
```

---

### Paciente con profesional

Actualmente no existe una tabla directa entre paciente y profesional.

Esto queda para una siguiente iteración, cuando se implemente agenda o reservas.

La relación futura debería ser mediante una tabla de citas o reservas:

```text
patients 1 ─── N appointments N ─── 1 professionals
```

Esto permitirá que:

```text
Un paciente pueda atenderse con varios profesionales.
Un profesional pueda atender a varios pacientes.
```

---

## 8. Seeders creados

Se crearon seeders para cargar datos base y datos de prueba.

### `CoreMedicalCenterSeeder`

Crea el centro médico inicial en `medsync_core.medical_centers`.

Datos principales:

```text
name: Clínica Horizonte
slug: clinica-horizonte
database_name: medsync_clinica_horizonte
```

### `CenterHealthInsuranceSeeder`

Crea las previsiones de salud en la base del centro:

```text
Fonasa
Isapre
Particular
Otra
```

### `DemoCenterUserSeeder`

Crea datos de prueba para validar las relaciones principales:

```text
Usuario paciente
Paciente
Usuario profesional
Profesional
Relación center_users para paciente
Relación center_users para profesional
```

Este seeder permite comprobar que:

```text
users se crea en core
patients se crea en center
professionals se crea en center
center_users relaciona usuario, centro y rol
```

---

## 9. Cómo levantar el proyecto después de clonarlo

### 1. Clonar el repositorio

```powershell
git clone URL_DEL_REPOSITORIO
```

Entrar a la carpeta del backend:

```powershell
cd "Fase 2\Evidencias Grupales\MedSync\medsync_backend"
```

### 2. Instalar dependencias

```powershell
composer install
```

### 3. Crear archivo `.env`

Si no existe `.env`, copiarlo desde `.env.example`:

```powershell
copy .env.example .env
```

Luego configurar las variables de base de datos:

```env
DB_CONNECTION=core
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=medsync_core
DB_CORE_DATABASE=medsync_core
DB_CENTER_DATABASE=medsync_clinica_horizonte
DB_USERNAME=postgres
DB_PASSWORD=
```

### 4. Generar clave de Laravel

```powershell
php artisan key:generate
```

### 5. Crear bases de datos en PostgreSQL

Desde pgAdmin o psql:

```sql
CREATE DATABASE medsync_core;
CREATE DATABASE medsync_clinica_horizonte;
```

### 6. Limpiar caché de Laravel

```powershell
php artisan optimize:clear
```

Nota:

Si aparece un error indicando que la tabla `cache` no existe, ejecutar primero las migraciones de `core` y luego volver a ejecutar `optimize:clear`.

### 7. Ejecutar migraciones de `core`

```powershell
php artisan migrate --database=core --path=database/migrations/core
```

### 8. Ejecutar migraciones de `center`

```powershell
php artisan migrate --database=center --path=database/migrations/center
```

### 9. Ejecutar seeders

```powershell
php artisan db:seed
```

### 10. Levantar servidor Laravel

```powershell
php artisan serve
```

El backend quedará disponible en:

```text
http://127.0.0.1:8000
```

---

## 10. Comandos útiles de prueba

Entrar a Tinker:

```powershell
php artisan tinker
```

### Ver bases de datos configuradas

```php
[
    'core' => DB::connection('core')->getDatabaseName(),
    'center' => DB::connection('center')->getDatabaseName(),
];
```

Resultado esperado:

```php
[
    "core" => "medsync_core",
    "center" => "medsync_clinica_horizonte",
]
```

### Ver conexión de modelos

```php
[
    'User' => (new \App\Models\User)->getConnectionName(),
    'MedicalCenter' => (new \App\Models\Core\MedicalCenter)->getConnectionName(),
    'CenterUser' => (new \App\Models\Core\CenterUser)->getConnectionName(),
    'HealthInsurance' => (new \App\Models\Center\HealthInsurance)->getConnectionName(),
    'Patient' => (new \App\Models\Center\Patient)->getConnectionName(),
    'Professional' => (new \App\Models\Center\Professional)->getConnectionName(),
];
```

Resultado esperado:

```php
[
    "User" => "core",
    "MedicalCenter" => "core",
    "CenterUser" => "core",
    "HealthInsurance" => "center",
    "Patient" => "center",
    "Professional" => "center",
]
```

### Ver tablas creadas

```php
[
    'core_tables' => collect(DB::connection('core')->select("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        ORDER BY table_name
    "))->pluck('table_name'),

    'center_tables' => collect(DB::connection('center')->select("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        ORDER BY table_name
    "))->pluck('table_name'),
];
```

Resultado esperado para `core`:

```text
cache
cache_locks
center_users
failed_jobs
job_batches
jobs
medical_centers
migrations
personal_access_tokens
users
```

Resultado esperado para `center`:

```text
health_insurances
migrations
patients
professionals
```

### Ver conteos de datos

```php
[
    'core_users' => \App\Models\User::count(),
    'core_medical_centers' => \App\Models\Core\MedicalCenter::count(),
    'core_center_users' => \App\Models\Core\CenterUser::count(),
    'center_health_insurances' => \App\Models\Center\HealthInsurance::count(),
    'center_patients' => \App\Models\Center\Patient::count(),
    'center_professionals' => \App\Models\Center\Professional::count(),
];
```

Resultado esperado aproximado después de ejecutar seeders:

```php
[
    "core_users" => 2,
    "core_medical_centers" => 1,
    "core_center_users" => 2,
    "center_health_insurances" => 4,
    "center_patients" => 1,
    "center_professionals" => 1,
]
```

---

## 11. Validaciones comprobadas

Se realizaron pruebas con Tinker y se comprobó que:

```text
Los modelos apuntan a la conexión correcta.
Las tablas están en la base de datos correspondiente.
patients no tiene medical_center_id.
center_users relaciona usuario, centro y rol.
No se permite duplicar el RUT de un paciente.
No se permite duplicar la relación del mismo usuario con el mismo centro.
No se permite asignar una previsión inexistente a un paciente.
```

---

## Cómo agregar nuevas migraciones y modelos en el futuro

Para mantener el proyecto ordenado, se debe respetar la separación entre `core` y `center`.

La regla principal es:

````text
Si la tabla pertenece a la plataforma MedSync → va en core.
Si la tabla pertenece a un centro médico → va en center.

## 12. Consideraciones para backend

La lógica de login, registro y cierre de sesión debe adaptarse a esta arquitectura.

El flujo recomendado para el backend es:

```text
1. Recibir el slug del centro desde la URL.
2. Buscar el centro en medsync_core.medical_centers.
3. Leer el database_name del centro.
4. Configurar la conexión center usando ese database_name.
5. Crear o consultar el usuario en medsync_core.users.
6. Crear o consultar paciente/profesional en la base del centro.
7. Crear o consultar la relación en medsync_core.center_users.
````

Ejemplo de rutas futuras:

```text
/api/centro/clinica-horizonte/register
/api/centro/clinica-horizonte/login
```

---

## 13. Estado actual

Estado actual de `core`:

```text
users
medical_centers
center_users
personal_access_tokens
cache
jobs
```

Estado actual de `center`:

```text
health_insurances
patients
professionals
```

Esta entrega deja preparada la base de datos para trabajar con varios centros médicos, separando la información central de MedSync de la información propia de cada centro.
