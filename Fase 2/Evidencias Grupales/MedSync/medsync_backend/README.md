# MedSync — Backend (Laravel)

Backend del proyecto **MedSync** (Duoc UC, Capstone). API REST hecha con **Laravel 13**, **PostgreSQL** y **Laravel Sanctum** (autenticación por sesión/cookies, no tokens), pensada para conectarse al frontend en `medsync_frontend` (React + Vite).

> Este README reemplaza al genérico de Laravel y a `docs/Base_datos_y_modelos.md`, que quedó desactualizado (esquema antiguo, sin timestamps, nombres de tabla en singular). Este documento describe **lo que realmente existe hoy** en el código.

---

## 1. Estado actual (Iteración 1)

Iteración 1 = **registro de cuenta + login**, funcionando de punta a punta (frontend → backend → Postgres), para un **solo centro médico fijo**: "Clínica Horizonte" (`slug: clinica-horizonte`).

⚠️ **Arquitectura de base de datos cambió a mitad de esta iteración** (trabajo de Roberto): ya no es una sola base de datos. Ahora hay **dos conexiones/bases separadas**:

- **`core`** (base `medsync_core`): datos globales, compartidos entre todos los centros — `users`, `medical_centers`, `medical_center_addresses`, `center_users`.
- **`center`** (base `medsync_clinica_horizonte` en esta iteración): datos propios de un centro médico — `patients`, `patient_addresses`, `professionals`, `health_insurances`.

Los modelos ahora están namespaced según a qué base pertenecen: `App\Models\Core\*` (`MedicalCenter`, `CenterUser`, `MedicalCenterAddress`) y `App\Models\Center\*` (`Patient`, `Professional`, `HealthInsurance`, `PatientAddress`). `App\Models\User` se mantiene sin namespace, vive en `core`. Ver `docs/Base_datos_y_modelos.md` (actualizado por Roberto) para el detalle completo del diseño.

Lo que ya funciona:

- Migraciones y modelos para `medical_centers`, `users`, `center_users`, `patients`, `health_insurances`, `professionals`, `medical_center_addresses`, `patient_addresses`, repartidos entre las dos bases.
- Registro de pacientes con validación de RUT chileno (módulo 11), teléfono chileno, previsión de salud, contraseña y consentimiento.
- Vinculación automática cuando recepción ya creó una ficha de paciente antes de que este cree su cuenta (mismo RUT **y** correo).
- Login / logout / usuario autenticado (`/api/v1/me`) vía **cookies de sesión de Sanctum** (no Bearer token).
- CORS y Sanctum configurados para el frontend real (`http://localhost:3000`).
- El frontend (`register-page.tsx`, `login-page.tsx`) ya llama a esta API real además de su mock local, solo para el centro `clinica-horizonte`.
- Tests automatizados (Pest) para registro y login.

⚠️ **Riesgo conocido, pendiente de decidir en equipo** (ver detalle en sección 11): al usar dos bases físicas separadas, una sola transacción de Laravel ya no puede cubrir una operación que escribe en ambas a la vez. Esto afecta el registro de pacientes.

Lo que **todavía no existe** (ver sección 10, próximas iteraciones): roles con Spatie Permission, multi-centro real (hoy sigue fijo a `clinica-horizonte`), especialidades, agenda/citas, resultados médicos, plataforma/suscripciones.

---

## 2. Requisitos previos

Cada integrante necesita instalado:

- PHP 8.3+ (el proyecto se desarrolló con PHP 8.5)
- Composer
- PostgreSQL (con un usuario y una base de datos creados)
- Node.js 20.19+ y npm (para `medsync_frontend`)
- Git

Verificar:

```bash
php -v
composer -V
node -v
git --version
```

---

## 3. Instalación del backend

```bash
git clone <URL_DEL_REPOSITORIO>
cd medsync_backend
composer install
```

Crear tu `.env` local (no se sube a Git):

```bash
# Windows PowerShell
Copy-Item .env.example .env

# Git Bash / Linux / Mac
cp .env.example .env
```

Editar en `.env` los datos de tu PostgreSQL local. **Importante:** ahora son dos bases de datos, no una — `DB_CONNECTION` debe ser `core` (no `pgsql`), y hacen falta las variables `DB_CORE_DATABASE` / `DB_CENTER_DATABASE`:

```env
DB_CONNECTION=core
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=medsync_core
DB_CORE_DATABASE=medsync_core
DB_CENTER_DATABASE=medsync_clinica_horizonte
DB_USERNAME=postgres
DB_PASSWORD=tu_password
```

Si vienes de una versión anterior del `.env` (con `DB_CONNECTION=pgsql` y `DB_DATABASE=medsync_bd`), **actualízalo a mano** — el `.env` no se sube a Git, así que `git pull` nunca lo toca por ti.

Crear las **dos bases de datos vacías** en Postgres antes de migrar (desde pgAdmin o `psql`):

```sql
CREATE DATABASE medsync_core;
CREATE DATABASE medsync_clinica_horizonte;
```

Generar la clave de la app y correr migraciones + seeders:

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Un solo `php artisan migrate` llena las dos bases: cada migración especifica su propia conexión (`Schema::connection('core')` o `Schema::connection('center')`) y `app/Providers/AppServiceProvider.php` registra ambas carpetas (`database/migrations/core` y `database/migrations/center`) con `loadMigrationsFrom()`.

`db:seed` corre, en este orden, `SuperAdminSeeder` → `CoreMedicalCenterSeeder` → `CenterHealthInsuranceSeeder` → `DemoCenterUserSeeder`, y crea automáticamente:

- Un usuario super administrador.
- El centro médico fijo "Clínica Horizonte".
- El catálogo de previsiones Fonasa / Isapre / Particular / Otra.
- Un paciente y un profesional de prueba (`paciente.prueba@test.cl` / `profesional.prueba@test.cl`, contraseña `Password123`).

Levantar el servidor:

```bash
php artisan serve
```

Por defecto queda en `http://localhost:8000`.

### Variables de entorno relevantes

| Variable | Para qué sirve | Valor esperado en desarrollo |
| --- | --- | --- |
| `DB_*` | Conexión a tu Postgres local | Ver arriba |
| `APP_URL` | URL pública del backend | `http://localhost:8000` |
| `SANCTUM_STATEFUL_DOMAINS` | Qué orígenes pueden autenticarse por cookie | No hace falta definirla: el valor por defecto en `config/sanctum.php` ya incluye `localhost:3000` (el puerto del frontend). Solo agrégala si el frontend corre en otro puerto/dominio. |
| `SESSION_DOMAIN` | Dominio de la cookie de sesión | Déjala en blanco/null para desarrollo local (funciona entre `localhost:3000` y `localhost:8000`). |

---

## 4. Instalación del frontend

```bash
cd medsync_frontend
npm install
npm run dev
```

Queda disponible en `http://localhost:3000`. La variable `VITE_API_URL` (opcional, en `medsync_frontend/.env`) apunta al backend; si no existe, usa `http://localhost:8000` por defecto — no hace falta crearla si tu backend corre en ese puerto.

### Problemas comunes en Windows

- **`npm : No se puede cargar el archivo ... npm.ps1 porque la ejecución de scripts está deshabilitada`**: PowerShell bloquea scripts por defecto. Solución permanente (una sola vez, no requiere administrador):
  ```powershell
  Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
  ```
  o como alternativa rápida, usar `npm.cmd run dev` en vez de `npm run dev`.
- **`"vite" no se reconoce como un comando`**: faltó correr `npm install` antes de `npm run dev`.

---

## 5. Probar que el registro llega a la base de datos

1. Con el backend (`php artisan serve`) y el frontend (`npm run dev`) corriendo, abre `http://localhost:3000/centro/clinica-horizonte/crear-cuenta`.
2. Llena el formulario y presiona "Crear mi cuenta".
3. Deberías ver **dos** notificaciones: la del mock local ("Cuenta creada...") y una segunda que confirma el guardado real: **"Guardado en la base de datos real (Laravel + Postgres)"**. Si en cambio aparece una notificación roja "Backend real: ...", revisa la sección de errores comunes (punto 8).

Para confirmar el dato en la base, cualquiera de estas tres opciones:

Ojo: como ahora son dos bases, `patients` vive en `medsync_clinica_horizonte` y `users`/`center_users` viven en `medsync_core`.

**a) Tinker (más rápido, no necesita nada adicional):**
```bash
php artisan tinker
```
```php
\App\Models\Center\Patient::latest()->first();
\App\Models\User::latest()->first();
\App\Models\Core\CenterUser::latest()->first();
```

**b) psql (una conexión por base):**
```bash
psql -U postgres -h 127.0.0.1 -d medsync_core
```
```sql
SELECT id, name, email FROM users ORDER BY id DESC LIMIT 5;
SELECT * FROM center_users ORDER BY id DESC LIMIT 5;
```
```bash
psql -U postgres -h 127.0.0.1 -d medsync_clinica_horizonte
```
```sql
SELECT id, first_name, last_name, rut, email FROM patients ORDER BY id DESC LIMIT 5;
```

**c) pgAdmin 4:** conectar a `localhost:5432` → expandir **ambas** bases (`medsync_core` y `medsync_clinica_horizonte`) → Schemas → public → Tables → clic derecho en la tabla que corresponda → View/Edit Data → All Rows.

---

## 6. Correr las pruebas automatizadas

Backend (Pest, corre contra una base SQLite en memoria, no toca tu Postgres):
```bash
php artisan test
```

Frontend:
```bash
npm run test    # pruebas unitarias/integración (Vitest)
npm run build   # compila TypeScript + bundle de producción
npm run lint    # análisis estático
```

---

## 7. Contrato real de la API (para Denise / frontend)

⚠️ `medsync_frontend/docs/api-contract.md` todavía no refleja esto exactamente — hay que actualizarlo. Mientras tanto, este es el contrato que **realmente** implementa el backend.

Todas las peticiones deben ir con `credentials: 'include'`. Antes de `POST /api/login` o `POST /api/register`, el frontend debe pedir `GET /sanctum/csrf-cookie` y reenviar el valor de la cookie `XSRF-TOKEN` en el header `X-XSRF-TOKEN` (ya implementado en `src/services/http.ts`).

### `POST /api/register`

```json
{
  "first_name": "Juan",
  "last_name": "Pérez",
  "rut": "12.345.678-5",
  "birth_date": "1990-05-10",
  "email": "juan@correo.cl",
  "phone": "+56 9 1234 5678",
  "health_insurance": "Fonasa",
  "medical_insurance": null,
  "address": null,
  "password": "Password123",
  "password_confirmation": "Password123",
  "consent": true
}
```

- `health_insurance` debe ser exactamente uno de: `Fonasa`, `Isapre`, `Particular`, `Otra`.
- `rut` acepta con o sin puntos/guión; se valida con módulo 11.
- `phone` acepta con o sin `+56`, espacios, guiones o paréntesis.
- Respuesta exitosa: `201` con `{ "data": { "id", "name", "email", "role": "PACIENTE", "medical_center", "patient", "professional" } }`.
- Errores de validación: `422` con el formato estándar de Laravel `{ "message": "...", "errors": { "campo": ["..."] } }`.

### `POST /api/login`
```json
{ "email": "juan@correo.cl", "password": "Password123" }
```
Respuesta: `200` con `{ "data": { ...mismo formato que register... } }`. Establece la cookie de sesión.

### `POST /api/logout`
Sin body. Requiere estar autenticado. Cierra la sesión.

### `GET /api/v1/me`
Requiere estar autenticado. Devuelve `{ "data": { ...mismo formato... } }` con el usuario actual.

---

## 8. Errores comunes

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| Toast "Backend real: No se pudo conectar..." | `php artisan serve` no está corriendo | Levantarlo en el puerto 8000 |
| Error 419 / CSRF token mismatch | Falta pedir `/sanctum/csrf-cookie` antes del POST | Ya está resuelto en `http.ts`; si vuelve a pasar, revisar que `credentials: 'include'` siga presente |
| Error CORS en la consola del navegador | El frontend corre en un puerto distinto a 3000 | Agregar ese origen a `allowed_origins` en `config/cors.php` |
| "El centro médico no está configurado" | No se corrió `php artisan db:seed` | Correr `php artisan db:seed` |
| "Previsión no reconocida" | Igual que el anterior (falta `HealthInsuranceSeeder`) | `php artisan db:seed` |
| Error 500 crudo en vez de mensaje de validación | Dos registros llegaron casi simultáneamente con el mismo RUT/correo (choque con el índice único de Postgres) | Conocido, pendiente de pulir (ver sección 9) |
| `php artisan migrate` dice "Nothing to migrate" | Laravel solo revisa `database/migrations` en la raíz por defecto; con la base dividida, las migraciones están en `database/migrations/core` y `database/migrations/center` | Ya está resuelto en `app/Providers/AppServiceProvider.php` con `loadMigrationsFrom([...])`. Si vuelve a pasar, revisa que ese archivo no se haya pisado. |
| `SQLSTATE[42P01]: ... no existe la relación «medical_centers»` (o similar) al migrar | El `.env` sigue apuntando a la conexión/base vieja (`DB_CONNECTION=pgsql`, `DB_DATABASE=medsync_bd`) en vez de `core` + `medsync_core`/`medsync_clinica_horizonte`, o la caché de config quedó desactualizada | Revisar el bloque `DB_*` del `.env` (sección 3) y correr `php artisan config:clear` |
| `php artisan migrate:status` dice "Ran" en migraciones cuyas tablas no existen | Quedó un estado inconsistente de un intento anterior (bases a medio borrar, o corridas contra una conexión equivocada) | Borrar ambas bases con `DROP DATABASE nombre WITH (FORCE);` (Postgres 13+; cierra primero cualquier pestaña de Query Tool abierta sobre esa base), recrearlas vacías y volver a `migrate` + `db:seed` |
| Error de registro: "El RUT o el correo ya están asociados a otra ficha..." aunque la tabla `patients` no tiene esa fila | El registro "mock" del frontend (guardado en `localStorage` del navegador) tiene un intento anterior guardado y falla antes de llegar al backend real | Botón "Restablecer datos demo" en el login, o borrar `localStorage` del sitio en las herramientas de desarrollador (F12 → Application → Local Storage) |

---

## 9. Nota sobre `medsync_BD/medsync_schema.sql` y `docs/Base_datos_y_modelos.md`

`medsync_BD/medsync_schema.sql` sigue **sin ser la fuente de verdad**: nada lo ejecuta ni lo referencia. El esquema real que existe hoy en Postgres lo crean y versionan las migraciones de Laravel en `medsync_backend/database/migrations/core/` y `database/migrations/center/`.

Roberto agregó `medsync_backend/docs/Base_datos_y_modelos.md`, que **sí está al día** con el diseño de dos bases (core/center) — léelo junto con la sección 1 de este README para el detalle completo del modelo de datos.

Recomendación: no correr `medsync_schema.sql` contra una base que ya tiene las migraciones aplicadas (crearía conflictos). Si se quiere mantener, lo ideal es actualizarlo para que refleje el esquema actual o marcarlo como archivo histórico.

---

## 10. Próximas iteraciones necesarias

Orden recomendado según `INDICACIONES_BACKEND.md` y `REQUERIMIENTOS_BD.md`, ajustado a lo que ya se avanzó:

### Iteración 2 — Roles y permisos
- Instalar `spatie/laravel-permission`, publicar sus migraciones.
- Seeder de roles: `ADMIN`, `RECEPCIONISTA`, `PROFESIONAL`, `PACIENTE` (por centro) y `SUPER_ADMIN` (global).
- Middlewares/policies que verifiquen rol + centro en cada request (no confiar en lo que muestra React).

### Iteración 3 — Catálogos y fichas del centro
- Migraciones y CRUD para `professionals`, `specialties`, `services`, `professional_specialty`.
- Ajustar esos modelos (hoy usan el diseño viejo: tabla en singular, sin `is_active`/timestamps reales) igual que se hizo con `MedicalCenter`, `Patient`, `CenterUser`.
- Únicos compuestos por centro (RUT y correo de profesionales).

### Iteración 4 — Agenda y disponibilidad
- `availabilities` (horarios recurrentes) y `appointments` (horas como `TIME`, no `DATE`).
- Endpoint `GET /api/v1/slots` calculando disponibilidad real en servidor.
- Reglas: cancelar/reprogramar hasta 24h antes, `ATENDIDA` solo desde la hora de inicio, `NO_SHOW` solo después de la hora de término, sin solapamientos (transaccional).
- `appointment_history` para trazabilidad de cambios.

### Iteración 5 — Resultados médicos
- `result_types` y `medical_results` (estados `DRAFT` / `PUBLISHED` / `VOIDED`).
- `medical_result_files` con almacenamiento privado (disco de Laravel, nunca base64/`data:` en producción).
- Solo el profesional responsable publica; el paciente solo ve sus propios resultados publicados.

### Iteración 6 — Multi-centro real y plataforma
- Resolver el centro por dominio/subdominio (o slug) en middleware, en vez del slug fijo `clinica-horizonte` actual.
- Rol `SUPER_ADMIN` global, gestión de centros/planes/suscripciones (`medical_centers.plan`, `subscription_status`, tablas `plans`/`subscriptions` opcionales).
- Aislamiento estricto: ninguna consulta puede devolver datos de otro centro.

### Pendientes transversales (aplican en cualquier iteración)
- Actualizar `medsync_frontend/docs/api-contract.md` cada vez que cambie un endpoint (ver sección 7 de este documento).
- Feature tests de aislamiento entre centros, permisos por rol, y los que pide `INDICACIONES_BACKEND.md`.
- `audit_logs` si se necesita trazabilidad de cambios administrativos.
- Decidir antes de producción: comportamiento de suscripciones vencidas/suspendidas, alcance de recepción sobre resultados, formatos y tamaños definitivos de archivos clínicos.

---

## 11. Detalle de cambios de esta iteración (para revisión de Roberto y Denise)

Esta sección lista, archivo por archivo, todo lo que se modificó o se creó para dejar funcionando el registro y login reales. Sirve como changelog técnico para que ambos sepan exactamente qué cambió antes de tocar esos mismos archivos.

### Cambios más recientes — rama `feature/bd-por-centro`

Roberto rediseñó la base de datos para separar los datos "globales" (`core`) de los datos propios de cada centro médico (`center`, una base física por centro). Esto significó: mover los modelos a `App\Models\Core\*` / `App\Models\Center\*`, dividir `database/migrations/` en `migrations/core/` y `migrations/center/`, agregar `medical_center_addresses` y `patient_addresses` (antes la dirección era un solo campo de texto), quitar el campo `medical_center_id` de `patients` (ya no hace falta: la base entera ya es de ese centro), y agregar los seeders `SuperAdminSeeder`, `CoreMedicalCenterSeeder`, `CenterHealthInsuranceSeeder`, `DemoCenterUserSeeder`.

Sobre eso, se hicieron estos ajustes para dejar el registro/login funcionando de nuevo:

- **`app/Http/Controllers/Api/AuthController.php`** — seguía importando los modelos desde el namespace viejo (`App\Models\CenterUser`, `App\Models\HealthInsurance`, `App\Models\MedicalCenter`, `App\Models\Patient`), que ya no existen ahí; esto rompía `/api/register`, `/api/login` y `/api/v1/me` con "Class not found". Se corrigieron los `use` a `App\Models\Core\CenterUser`, `App\Models\Center\HealthInsurance`, `App\Models\Core\MedicalCenter`, `App\Models\Center\Patient`. Además, se quitó `medical_center_id` de la búsqueda de paciente duplicado y de `Patient::create(...)`, porque esa columna ya no existe en la tabla `patients` (sigue existiendo y usándose en `CenterUser`, que sí vive en `core` y sí necesita saber a qué centro pertenece cada usuario).
- **`app/Providers/AppServiceProvider.php`** — estaba vacío; Laravel no sabía que las migraciones ahora viven en subcarpetas (`migrations/core/`, `migrations/center/`) y por eso `php artisan migrate` no encontraba nada. Se agregó `loadMigrationsFrom([...])` en `boot()` registrando ambas rutas.
- **`.env`** (local de cada integrante, no se sube a Git) — necesita actualizarse a mano con `DB_CONNECTION=core`, `DB_CORE_DATABASE=medsync_core` y `DB_CENTER_DATABASE=medsync_clinica_horizonte` (ver sección 3). Si tu `.env` es de antes de esta rama, todavía va a tener `DB_CONNECTION=pgsql` y `DB_DATABASE=medsync_bd`, que ya no aplican.

⚠️ **Punto pendiente de conversar en equipo — transacción entre las dos bases:**

En `AuthController::register()`, el bloque `DB::transaction(function () { ... })` envuelve la creación del `User` (conexión `core`) y del `CenterUser` (conexión `core`), pero **no** la del `Patient`, que vive en la conexión `center` — es decir, en otra base de datos física. `DB::transaction()` sin especificar conexión usa la conexión por defecto (`core`) únicamente; no puede cubrir una escritura en `center` al mismo tiempo, porque son dos conexiones/transacciones de Postgres completamente independientes.

En la práctica, esto significa que si el registro falla **después** de crear el `Patient` pero **antes** de terminar de crear el `User` o el `CenterUser` (por ejemplo, por un error de validación tardío o una caída de conexión), el `Patient` ya creado en `center` **no se revierte**, y queda una ficha de paciente huérfana, sin usuario asociado.

No es un bug de sintaxis — es una limitación real de tener dos bases físicas separadas, y hay que decidir en equipo cómo manejarlo, por ejemplo: revertir el `Patient` manualmente en un `catch` si falla el resto (transacción compensatoria), aceptar el riesgo por ahora dado que Iteración 1 es de bajo volumen/uso académico, o buscar otro patrón. Por ahora se dejó **sin corregir**, documentado aquí para que se discuta antes de construir más funcionalidad sobre este mismo flujo (por ejemplo, en Iteración 3 al agregar el registro de profesionales, que tendrá el mismo problema).

### Base de datos y backend (cambios de la iteración anterior, registro/login inicial)

**Modelos corregidos** (tenían el diseño viejo: nombre de tabla en singular, campo `status` en vez de `is_active`, `$timestamps = false` aunque la migración sí trae timestamps):
- `app/Models/MedicalCenter.php` → tabla `medical_centers`, campo `is_active`, timestamps habilitados.
- `app/Models/User.php` → agregado `SoftDeletes`, `email_verified_at` como `datetime`, `is_active` en vez de `status`.
- `app/Models/Patient.php` → tabla `patients` (antes `patient`), agregado `SoftDeletes`, campos `medical_insurance`, `consent_at`, `consent_version`.
- `app/Models/CenterUser.php` → `is_active` en vez de `status`, timestamps habilitados.
- `app/Models/HealthInsurance.php` → tabla `health_insurances` (antes `health_insurance`, singular), `is_active`, timestamps.

**Migraciones nuevas:**
- `database/migrations/2026_09_06_230000_create_health_insurances_table.php` — catálogo de previsiones (Fonasa/Isapre/Particular/Otra), no existía antes. Necesaria porque el formulario de registro la pide como campo obligatorio.

**Seeders:**
- `database/seeders/MedicalCenterSeeder.php` (nuevo) — crea "Clínica Horizonte" con `firstOrCreate` por `slug`.
- `database/seeders/HealthInsuranceSeeder.php` (nuevo) — crea las 4 previsiones fijas.
- `database/seeders/DatabaseSeeder.php` — se agregó el `$this->call([...])` a los dos seeders anteriores.

**Reglas de validación:**
- `app/Rules/ValidRut.php` (nuevo) — valida RUT chileno con dígito verificador (módulo 11), sin depender de ningún paquete externo.

**Controlador y rutas:**
- `app/Http/Controllers/Api/AuthController.php` (nuevo) — `register`, `login`, `logout`, `me`. Usa autenticación por sesión/cookies (`Auth::attempt`, `session()->regenerate()`), no tokens Bearer.
- `routes/api.php` (nuevo) — no existía ningún archivo de rutas de API en el proyecto.

**Configuración:**
- `bootstrap/app.php` — se agregó `api: __DIR__.'/../routes/api.php'` al routing y `$middleware->statefulApi()` para que Sanctum maneje cookies/CSRF en rutas `/api/*`.
- `config/cors.php` — `allowed_origins` ahora incluye `http://localhost:3000` (antes tenía `localhost:5173`, puerto que el frontend real no usa) y `supports_credentials` pasó a `true` (obligatorio para que el navegador mande la cookie de sesión).

**Tests:**
- `tests/Pest.php` — se habilitó `RefreshDatabase` (estaba comentado; sin eso las pruebas no tenían tablas donde escribir).
- `tests/Feature/Auth/RegisterTest.php` y `tests/Feature/Auth/LoginTest.php` (nuevos) — registro exitoso, RUT inválido, correo duplicado en el mismo centro, login correcto/incorrecto, `/api/v1/me`, logout.

### Frontend

Se tocaron solo 3 archivos, sin modificar `src/state/clinic-store.tsx` (el proveedor mock que usa el resto de la app) para no interferir con el trabajo de Denise ahí ni con sus tests existentes:

- `src/services/http.ts` — le faltaba adjuntar el header `X-XSRF-TOKEN` (leído de la cookie `XSRF-TOKEN`) en cada request; sin eso Laravel rechaza todo con error 419. Se agregó esa lógica más una función `sanctum.register(payload)` para llamar a `POST /api/register`.
- `src/pages/register-page.tsx` — el flujo de registro sigue guardando en el mock exactamente igual que antes; **además**, solo cuando el centro es `clinica-horizonte`, llama a `sanctum.register(...)` con los datos del formulario para guardarlos también en el backend real. Si esa llamada falla, se muestra un toast de aviso pero no se interrumpe el flujo mock.
- `src/pages/login-page.tsx` — mismo criterio: al iniciar sesión en `clinica-horizonte`, además del login mock intenta silenciosamente `sanctum.login(...)` contra el backend real (falla esperable con las cuentas demo que no existen en Postgres, solo funciona con cuentas creadas vía `/crear-cuenta`).
- `src/components/app-layout.tsx` — el botón "Cerrar sesión" ahora también llama a `sanctum.logout()` contra el backend real (antes solo hacía el logout del mock; por eso no aparecía nada en la consola de `php artisan serve` al cerrar sesión).

Ningún test existente de frontend (`App.test.tsx`, `clinic-store.test.tsx`) ejecuta estos formularios, así que no deberían verse afectados — de todas formas, correr `npm run test` para confirmarlo.

---

## 12. Estructura del proyecto

```text
app/
  Http/Controllers/Api/   Controladores de la API (AuthController, ...)
  Models/
    Core/                 Modelos de la base "core" (MedicalCenter, CenterUser, MedicalCenterAddress)
    Center/                Modelos de la base "center" (Patient, Professional, HealthInsurance, PatientAddress)
    User.php              Vive en "core", sin namespace
  Providers/
    AppServiceProvider.php  Registra las carpetas de migraciones de core/ y center/
  Rules/                  Reglas de validación propias (ValidRut)
database/
  migrations/
    core/                 Migraciones de la base "core" (medsync_core)
    center/                Migraciones de la base "center" (medsync_clinica_horizonte)
  seeders/                Datos iniciales (super admin, centro fijo, previsiones, paciente/profesional demo)
docs/
  Base_datos_y_modelos.md  Diseño detallado del modelo de datos core/center (Roberto)
routes/
  api.php                 Rutas de la API (/api/*)
tests/
  Feature/Auth/           Tests de registro, login, logout, me
```
