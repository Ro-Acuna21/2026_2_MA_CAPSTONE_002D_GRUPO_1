# MedSync Backend

Backend del proyecto **MedSync**, desarrollado con **Laravel** y conectado a una base de datos **PostgreSQL**.

Esta primera iteración está enfocada en implementar el flujo inicial de autenticación y persistencia de datos:

- Crear cuenta de paciente.
- Iniciar sesión.
- Cerrar sesión.
- Consultar usuario autenticado.
- Asociar usuarios a un centro médico.
- Guardar datos principales del paciente en PostgreSQL.

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
DB_DATABASE=medsync_bd
DB_USERNAME=postgres
DB_PASSWORD=tu_password
```

El archivo `.env` no se sube a GitHub.  
Cada integrante debe crear y configurar su propio `.env`.

---

## Base de datos

La fuente principal para crear y versionar la base de datos son las **migraciones de Laravel**, ubicadas en:

```text
medsync_backend/database/migrations/
```

Para crear las tablas y cargar los datos iniciales:

```bash
php artisan migrate
php artisan db:seed
```

El archivo SQL ubicado en la carpeta de base de datos se mantiene solo como respaldo documental del esquema principal de la Iteración 1.  
En caso de diferencias entre el SQL y las migraciones, se debe considerar como válida la estructura definida en las migraciones.

---

## Tablas principales de la Iteración 1

La estructura actual considera las siguientes tablas principales del sistema:

```text
medical_centers
users
health_insurances
patients
center_users
```

Estas tablas permiten registrar centros médicos, usuarios, pacientes, previsiones de salud y la relación entre un usuario y un centro médico.

No se incluyen todavía tablas de profesionales, especialidades, disponibilidad, reservas, reportes o suscripciones, ya que corresponden a próximas iteraciones del proyecto.

---

## Descripción de tablas principales

### `medical_centers`

Guarda los centros médicos registrados en MedSync.

En esta iteración se trabaja con un centro médico fijo de prueba:

```text
clinica-horizonte
```

---

### `users`

Guarda las credenciales de acceso de los usuarios.

Campos principales:

```text
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

El rol del usuario no se guarda en esta tabla.  
El rol se define mediante la tabla `center_users`.

---

### `health_insurances`

Guarda el catálogo de previsiones de salud utilizadas en el formulario de registro.

Valores iniciales:

```text
Fonasa
Isapre
Particular
Otra
```

---

### `patients`

Guarda los datos básicos del paciente dentro de un centro médico.

Campos principales:

```text
medical_center_id
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

El campo `user_id` puede ser `NULL`, ya que en futuras iteraciones recepción podría crear una ficha de paciente antes de que el paciente tenga cuenta de usuario.

---

### `center_users`

Relaciona un usuario con un centro médico y define su rol dentro de ese centro.

Relación principal:

```text
usuario + centro médico + rol
```

Roles considerados:

```text
ADMIN
RECEPCIONISTA
PROFESIONAL
PACIENTE
```

Para esta primera iteración, el flujo implementado se enfoca principalmente en el rol `PACIENTE`.

---

## Flujo actual de crear cuenta

```text
1. El paciente completa el formulario de registro en el frontend.
2. El frontend envía los datos al backend Laravel.
3. Laravel valida los datos recibidos.
4. Se crea el usuario en la tabla users.
5. Se crea o vincula la ficha del paciente en patients.
6. Se crea la relación entre usuario y centro en center_users.
7. Los datos quedan guardados en PostgreSQL.
```

---

## Flujo actual de iniciar sesión

```text
1. El usuario ingresa correo y contraseña.
2. Laravel valida las credenciales.
3. Si son correctas, se inicia sesión mediante Sanctum usando cookies.
4. El frontend puede consultar los datos del usuario autenticado mediante /api/v1/me.
```

---

## Endpoints implementados en la Iteración 1

```text
POST /api/register
POST /api/login
POST /api/logout
GET /api/v1/me
```

---

## Autenticación

La autenticación se realiza con **Laravel Sanctum** mediante cookies de sesión.

Antes de enviar solicitudes como login o registro, el frontend debe solicitar:

```text
GET /sanctum/csrf-cookie
```

Luego, las peticiones al backend deben enviarse con:

```text
credentials: 'include'
```

Esto permite que Laravel maneje correctamente la sesión del usuario autenticado.

---

## Seeders

Los seeders cargan datos iniciales necesarios para probar la Iteración 1:

```text
Clínica Horizonte
Fonasa
Isapre
Particular
Otra
```

Estos datos permiten probar el flujo de registro e inicio de sesión desde una base de datos vacía.

---

## SQL de respaldo

El archivo SQL de respaldo representa solamente las tablas principales implementadas en la Iteración 1.

Este archivo no reemplaza las migraciones de Laravel.  
Su objetivo es servir como apoyo documental o respaldo en caso de necesitar revisar la estructura principal de la base de datos.

No se incluyen tablas internas de Laravel como:

```text
cache
jobs
personal_access_tokens
migrations
```

Estas tablas son administradas por Laravel mediante sus propias migraciones.

---

## Consideraciones importantes

- La fuente principal de la base de datos son las migraciones de Laravel.
- El SQL se mantiene solo como respaldo documental.
- El archivo `.env` no debe subirse a GitHub.
- Después de clonar el proyecto, cada integrante debe ejecutar `composer install`.
- El centro médico se trabaja por ahora como centro fijo: `clinica-horizonte`.
- El rol se obtiene desde `center_users`, no desde `users`.
- La autenticación usa Sanctum con cookies/sesión.
- El frontend debe conectarse al backend mediante la API REST.

---

## Estado actual

```text
Registro de cuenta funcionando.
Inicio de sesión funcionando.
Logout funcionando.
Consulta de usuario autenticado funcionando.
Frontend conectado con backend Laravel.
Datos persistidos correctamente en PostgreSQL.
Migraciones y seeders creados para la Iteración 1.
SQL de respaldo actualizado según las migraciones actuales.
```

---

## Pendiente para próximas iteraciones

```text
Implementar roles y permisos con Spatie Permission.
Agregar gestión de profesionales.
Agregar gestión de especialidades.
Agregar disponibilidad horaria.
Agregar reservas/citas.
Implementar multi-centro real.
Agregar reportes administrativos.
Definir futuras funcionalidades de plataforma y suscripciones.
```
