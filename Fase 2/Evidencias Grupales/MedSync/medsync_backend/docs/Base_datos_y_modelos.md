# MedSync - Base de Datos

Este README resume la estructura de base de datos implementada en **MedSync**, incluyendo conexiones, migraciones, modelos, seeders, roles y pasos para levantar el proyecto.

---

## 1. Objetivo

Se reorganizó la base de datos para separar la información central de MedSync de la información propia de un centro médico.

Actualmente la implementación trabaja con:

```text
medsync_core
medsync_clinica_horizonte
```

Importante: por ahora **no está automatizado el manejo de múltiples bases de datos por centro médico**. La conexión `center` está configurada manualmente para trabajar con una base de datos de centro de prueba.

---

## 2. Arquitectura actual

El proyecto usa dos conexiones de base de datos:

```text
core   = Base central de MedSync.
center = Base del centro médico.
```

La base central es:

```text
medsync_core
```

La base de centro configurada actualmente es:

```text
medsync_clinica_horizonte
```

En el archivo `.env`, la base del centro se define con:

```env
DB_CENTER_DATABASE=medsync_clinica_horizonte
```

---

## 3. Qué se dejó preparado

Aunque actualmente se trabaja con una sola base de datos de centro, se dejó preparada la estructura para que más adelante el backend pueda manejar varios centros médicos.

Para esto, en `medical_centers` se agregó el campo:

```text
database_name
```

Ejemplo:

```text
Centro médico: Clínica Horizonte
Slug: clinica-horizonte
Database name: medsync_clinica_horizonte
```

A futuro, el backend debería usar el `slug` del centro para obtener el `database_name` y cambiar dinámicamente la conexión `center`.

---

## 4. Qué falta automatizar

Queda pendiente para backend:

```text
Crear automáticamente una base de datos al registrar un nuevo centro médico.
Ejecutar migraciones en la nueva base de datos del centro.
Cambiar dinámicamente la conexión center según el centro seleccionado.
Usar el slug del centro para identificar qué base de datos corresponde.
```

Por ahora, la conexión `center` apunta manualmente a:

```text
medsync_clinica_horizonte
```

---

## 5. Estructura de bases de datos

### `medsync_core`

Contiene la información central de MedSync:

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

### `medsync_clinica_horizonte`

Contiene la información propia del centro médico de prueba:

```text
health_insurances
patients
professionals
migrations
```

---

## 6. Configuración en Laravel

Se configuraron dos conexiones en:

```text
config/database.php
```

Conexiones:

```text
core
center
```

La conexión por defecto quedó como:

```php
'default' => env('DB_CONNECTION', 'core'),
```

Configuración esperada en `.env`:

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

Si PostgreSQL tiene contraseña:

```env
DB_PASSWORD=tu_contraseña
```

---

## 7. Migraciones creadas

Las migraciones se organizaron en dos carpetas:

```text
database/migrations/core
database/migrations/center
```

### Migraciones de `core`

```text
database/migrations/core
├── 0001_01_01_000001_create_cache_table.php
├── 0001_01_01_000002_create_jobs_table.php
├── 2026_09_04_164340_create_personal_access_tokens_table.php
├── 2026_09_06_225034_create_medical_centers_table.php
├── 2026_09_06_225035_create_users_table.php
├── 2026_09_06_225036_create_center_users_table.php
└── 2026_09_12_232029_add_system_role_to_users_table.php
```

Estas migraciones usan:

```php
Schema::connection('core')
```

### Migraciones de `center`

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

## 8. Tablas principales

### `users`

Tabla central de usuarios del sistema.

Campos principales:

```text
id
name
email
password
system_role
email_verified_at
is_active
remember_token
created_at
updated_at
deleted_at
```

El campo `system_role` permite identificar roles globales de MedSync, como:

```text
SUPER_ADMIN
```

---

### `medical_centers`

Tabla central de centros médicos.

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

El campo `database_name` indica la base de datos asociada al centro médico.

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

Roles actuales:

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

Los campos `patient_id` y `professional_id` no tienen foreign key directa porque apuntan a registros ubicados en la base de datos del centro.

---

### `health_insurances`

Tabla del centro médico para previsiones de salud.

Registros base:

```text
Fonasa
Isapre
Particular
Otra
```

---

### `patients`

Tabla del centro médico para pacientes.

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

No tiene `medical_center_id`, porque la tabla ya está dentro de la base de datos propia del centro.

---

### `professionals`

Tabla del centro médico para profesionales.

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

No tiene `medical_center_id`, porque ya pertenece a la base de datos del centro.

---

## 9. Roles implementados

Los roles se separaron en dos niveles:

```text
Rol global de MedSync.
Rol dentro de un centro médico.
```

### Rol global

El rol global se maneja en:

```text
medsync_core.users.system_role
```

Actualmente se usa:

```text
SUPER_ADMIN
```

Ejemplo:

```text
email: admin@medsync.cl
system_role: SUPER_ADMIN
```

El `SUPER_ADMIN` administra la plataforma MedSync completa y no se registra en `center_users`.

### Roles por centro

Los roles por centro se manejan en:

```text
medsync_core.center_users.role
```

Roles actuales:

```text
ADMIN
RECEPCIONISTA
PROFESIONAL
PACIENTE
```

La base de datos solo almacena el rol. Los permisos específicos deben implementarse desde el backend.

---

## 10. Modelos creados o modificados

Los modelos se organizaron según la conexión que utilizan.

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

Usan:

```php
protected $connection = 'core';
```

Modelos:

```text
App\Models\User
App\Models\Core\MedicalCenter
App\Models\Core\CenterUser
```

En `User` se agregó:

```text
system_role
```

También se agregó el método:

```php
public function isSuperAdmin(): bool
{
    return $this->system_role === 'SUPER_ADMIN';
}
```

### Modelos de `center`

Usan:

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

## 11. Relaciones principales

### Usuario con centro médico

```text
users 1 ─── N center_users
medical_centers 1 ─── N center_users
```

Esto permite que un usuario tenga un rol dentro de un centro médico.

### Centro médico con pacientes y profesionales

Como se usa una base de datos por centro, `patients` y `professionals` no necesitan `medical_center_id`.

La relación se entiende mediante:

```text
medical_centers.database_name = medsync_clinica_horizonte
```

Entonces:

```text
medsync_clinica_horizonte.patients       = pacientes de Clínica Horizonte
medsync_clinica_horizonte.professionals  = profesionales de Clínica Horizonte
```

### Paciente con previsión

```text
health_insurances 1 ─── N patients
```

### Paciente con profesional

Actualmente no existe una relación directa entre paciente y profesional.

Esa relación se implementará más adelante mediante reservas o citas:

```text
patients 1 ─── N appointments N ─── 1 professionals
```

---

## 12. Seeders creados

### `SuperAdminSeeder`

Crea el administrador global:

```text
name: Admin MedSync
email: admin@medsync.cl
system_role: SUPER_ADMIN
is_active: true
```

### `CoreMedicalCenterSeeder`

Crea el centro médico de prueba:

```text
name: Clínica Horizonte
slug: clinica-horizonte
database_name: medsync_clinica_horizonte
```

### `CenterHealthInsuranceSeeder`

Crea previsiones de salud:

```text
Fonasa
Isapre
Particular
Otra
```

### `DemoCenterUserSeeder`

Crea datos de prueba:

```text
Usuario paciente.
Paciente.
Usuario profesional.
Profesional.
Relación center_users para paciente.
Relación center_users para profesional.
```

---

## 13. Cómo levantar el proyecto después de clonarlo

### 1. Clonar repositorio

```powershell
git clone URL_DEL_REPOSITORIO
```

Entrar al backend:

```powershell
cd "Fase 2\Evidencias Grupales\MedSync\medsync_backend"
```

### 2. Instalar dependencias

```powershell
composer install
```

### 3. Crear `.env`

```powershell
copy .env.example .env
```

Configurar las variables de base de datos indicadas en este README.

### 4. Generar clave

```powershell
php artisan key:generate
```

### 5. Crear bases de datos en PostgreSQL

```sql
CREATE DATABASE medsync_core;
CREATE DATABASE medsync_clinica_horizonte;
```

### 6. Limpiar caché

```powershell
php artisan optimize:clear
```

### 7. Ejecutar migraciones

Primero `core`:

```powershell
php artisan migrate --database=core --path=database/migrations/core
```

Luego `center`:

```powershell
php artisan migrate --database=center --path=database/migrations/center
```

### 8. Ejecutar seeders

```powershell
php artisan db:seed
```

### 9. Levantar servidor

```powershell
php artisan serve
```

URL local:

```text
http://127.0.0.1:8000
```

---

## 14. Cuando otro integrante haga `git pull`

Después de actualizar el proyecto, ejecutar:

```powershell
php artisan migrate --database=core --path=database/migrations/core
php artisan migrate --database=center --path=database/migrations/center
php artisan db:seed
```

Laravel solo ejecutará las migraciones pendientes.

---

## 15. Reiniciar base de datos en ambiente local

Si hay errores por tablas antiguas o datos de prueba, se puede reiniciar la estructura:

```powershell
php artisan migrate:fresh --database=core --path=database/migrations/core
php artisan migrate:fresh --database=center --path=database/migrations/center
php artisan db:seed
```

Importante:

```text
migrate:fresh elimina las tablas y las vuelve a crear.
Solo usar en ambiente local o de pruebas.
No usar en producción.
```

---

## 16. Comandos de prueba con Tinker

Entrar a Tinker:

```powershell
php artisan tinker
```

### Ver bases configuradas

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

### Ver conexiones de modelos

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

### Ver admin global

```php
\App\Models\User::where('email', 'admin@medsync.cl')->first();
```

### Probar `isSuperAdmin()`

```php
\App\Models\User::where('email', 'admin@medsync.cl')->first()->isSuperAdmin();
```

Resultado esperado:

```php
true
```

### Ver conteos

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

Resultado esperado:

```php
[
    "core_users" => 3,
    "core_medical_centers" => 1,
    "core_center_users" => 2,
    "center_health_insurances" => 4,
    "center_patients" => 1,
    "center_professionals" => 1,
]
```

---

## 17. Consideraciones para backend

El backend debe adaptar login y registro a esta arquitectura.

Flujo recomendado:

```text
1. Recibir el slug del centro desde la URL.
2. Buscar el centro en medsync_core.medical_centers.
3. Leer el database_name del centro.
4. Configurar la conexión center usando ese database_name.
5. Crear o consultar el usuario en medsync_core.users.
6. Crear o consultar paciente/profesional en la base del centro.
7. Crear o consultar la relación en medsync_core.center_users.
```

Ejemplo:

```text
/api/centro/clinica-horizonte/register
/api/centro/clinica-horizonte/login
```

El campo `system_role` no debe enviarse desde el formulario público de registro. Para usuarios normales debe quedar en `null`.

---

## 18. Cómo agregar migraciones y modelos en el futuro

Regla principal:

```text
Si la tabla pertenece a la plataforma MedSync → va en core.
Si la tabla pertenece a un centro médico → va en center.
```

### Crear migración en `core`

```powershell
php artisan make:migration create_nombre_tabla_table --path=database/migrations/core
```

La migración debe usar:

```php
Schema::connection('core')
```

El modelo debe ir en:

```text
app/Models/Core
```

### Crear migración en `center`

```powershell
php artisan make:migration create_nombre_tabla_table --path=database/migrations/center
```

La migración debe usar:

```php
Schema::connection('center')
```

El modelo debe ir en:

```text
app/Models/Center
```

---

## 19. Estado actual

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

Esta entrega deja preparada una estructura inicial para separar la información central de MedSync de la información propia del centro médico.

Actualmente está enfocada en una sola base de datos de centro configurada manualmente, pero queda preparada para que en una siguiente iteración el backend pueda automatizar la creación y selección de bases de datos por centro médico.
