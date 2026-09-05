# MedSync Backend

Backend del proyecto **MedSync**, desarrollado con **Laravel** y conectado a una base de datos **PostgreSQL**.

Esta primera etapa está enfocada en preparar la base para:

- Crear cuenta.
- Iniciar sesión.
- Asociar usuarios a un centro médico.
- Manejar roles por centro.
- Vincular usuarios con pacientes o profesionales.

---

## Requisitos

Antes de ejecutar el proyecto, cada integrante debe tener instalado:

- PHP
- Composer
- PostgreSQL
- Git

Verificar instalación:

```bash
php -v
composer -V
git --version
```

---

## Instalación

Clonar el repositorio y entrar al backend:

```bash
git clone URL_DEL_REPOSITORIO
cd medsync_backend
```

Instalar dependencias:

```bash
composer install
```

Crear archivo `.env`:

```powershell
Copy-Item .env.example .env
```

En Git Bash o Linux:

```bash
cp .env.example .env
```

Generar clave de Laravel:

```bash
php artisan key:generate
```

Limpiar configuración:

```bash
php artisan config:clear
```

---

## Configuración de la base de datos

En el archivo `.env`, configurar PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=MedSync
DB_USERNAME=postgres
DB_PASSWORD=tu_password
```

El archivo `.env` no se sube a GitHub.  
Cada integrante debe crear y configurar su propio `.env`.

---

## Base de datos

La estructura actual incluye estas tablas:

```text
medical_center
users
health_insurance
patient
professional
center_users
specialty
professional_specialty
availability
appointment
```

Para esta primera etapa, las tablas más importantes son:

```text
medical_center
users
patient
professional
center_users
```

---

## Tablas principales

### `medical_center`

Guarda los centros médicos.  
El campo `slug` permite identificar el centro desde la URL del frontend.

Ejemplo:

```text
/centro/clinica-horizonte/ingresar
```

Slug usado:

```text
clinica-horizonte
```

---

### `users`

Guarda los datos de acceso:

```text
name
email
password
status
remember_token
```

El rol del usuario no se guarda aquí.

---

### `patient`

Guarda la ficha del paciente dentro de un centro médico.

El campo `user_id` puede ser `NULL`, ya que recepción podría crear un paciente sin cuenta.  
Si el paciente se registra después, esa ficha puede vincularse a su usuario.

---

### `professional`

Guarda los datos de los profesionales.

El campo `user_id` también puede ser `NULL`, porque un profesional puede existir antes de tener acceso al sistema.

---

### `center_users`

Relaciona:

```text
usuario + centro médico + rol
```

Roles permitidos:

```text
ADMIN
RECEPCIONISTA
PROFESIONAL
PACIENTE
```

Los roles deben guardarse en mayúscula.

---

## Modelos Eloquent

Modelos principales creados o corregidos:

```text
User
MedicalCenter
HealthInsurance
Patient
Professional
CenterUser
Specialty
Availability
Appointment
```

Como las tablas actuales no tienen `created_at` ni `updated_at`, los modelos deben tener:

```php
public $timestamps = false;
```

Además, deben usar los nombres reales de las tablas:

```php
protected $table = 'users';
protected $table = 'medical_center';
protected $table = 'patient';
protected $table = 'professional';
protected $table = 'center_users';
```

---

## Relaciones probadas

Las relaciones principales fueron probadas con Laravel Tinker:

```text
Patient -> User
Patient -> MedicalCenter
Patient -> HealthInsurance

Professional -> User
Professional -> MedicalCenter

CenterUser -> User
CenterUser -> MedicalCenter
CenterUser -> Patient
CenterUser -> Professional

Appointment -> Patient
Appointment -> Professional
Appointment -> MedicalCenter
Appointment -> Specialty
```

---

## Flujo esperado para crear cuenta

```text
1. Recibir datos del formulario.
2. Obtener el slug del centro desde la URL.
3. Buscar el centro en medical_center.
4. Crear el usuario en users.
5. Revisar si ya existe una ficha de paciente con el mismo RUT y correo.
6. Si existe, vincularla al usuario.
7. Si no existe, crear una nueva ficha en patient.
8. Crear el registro en center_users con rol PACIENTE.
```

---

## Flujo esperado para iniciar sesión

```text
1. Recibir correo y contraseña.
2. Buscar usuario en users.
3. Validar contraseña.
4. Verificar que el usuario esté activo.
5. Buscar su relación en center_users.
6. Retornar usuario, centro y rol.
```

---

## Endpoints iniciales sugeridos

Para conectar el frontend con Laravel, se deberían implementar inicialmente:

```text
POST /api/register
POST /api/login
POST /api/logout
GET /api/v1/me
```

---

## Consideraciones importantes

- El centro debe buscarse por `slug`.
- El rol se obtiene desde `center_users`, no desde `users`.
- Los roles deben ir en mayúscula.
- Usar `user_id`, no `users_id`.
- No subir `.env` a GitHub.
- Después de clonar, ejecutar `composer install`.

---

## Estado actual

```text
Base de datos inicial creada.
Modelos principales corregidos.
Relaciones probadas con Tinker.
Centro de prueba configurado con slug clinica-horizonte.
Listo para comenzar login y registro.
```