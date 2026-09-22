# MedSync — Cambios de Integración Frontend y Backend

Este documento explica los cambios realizados durante esta iteración para avanzar en la integración entre el frontend React, el backend Laravel y PostgreSQL.

> Importante: la configuración de Laravel, las rutas originales de autenticación, Sanctum y la separación de las bases `core` y `center` ya existían previamente en el proyecto.  
> Este documento no las presenta como cambios nuevos. Solo se mencionan en la sección de instalación porque son necesarias para levantar el proyecto.

Los cambios de esta iteración se enfocaron principalmente en:

- Dejar el login del frontend utilizando la autenticación real de Laravel.
- Dejar Crear Cuenta sin depender de usuarios/pacientes mock.
- Recuperar la sesión real desde Laravel al recargar la aplicación.
- Conectar el listado de profesionales con PostgreSQL.
- Conectar la creación de profesionales con PostgreSQL.
- Mantener los mocks únicamente en módulos que todavía no tienen integración backend, como agenda, reservas, disponibilidad y especialidades.

---

# 1. Cómo levantar el proyecto

## Requisitos

Se necesita tener instalado:

```text
PHP
Composer
PostgreSQL
Node.js
npm
Git
```

Se puede comprobar con:

```powershell
php -v
composer -V
node -v
npm -v
git --version
```

---

## Backend

Entrar a la carpeta del backend:

```powershell
cd medsync_backend
```

Instalar dependencias si corresponde:

```powershell
composer install
```

Si es una instalación nueva y no existe `.env`:

```powershell
Copy-Item .env.example .env
```

Generar la clave de Laravel si todavía no existe:

```powershell
php artisan key:generate
```

El `.env` debe tener configuradas las bases utilizadas actualmente por el proyecto:

```env
DB_CONNECTION=core

DB_HOST=127.0.0.1
DB_PORT=5432

DB_DATABASE=medsync_core
DB_CORE_DATABASE=medsync_core
DB_CENTER_DATABASE=medsync_clinica_horizonte

DB_USERNAME=postgres
DB_PASSWORD=TU_PASSWORD_POSTGRES

SESSION_DRIVER=file

APP_URL=http://localhost:8000
```

Para Sanctum también debe estar permitido el frontend, por ejemplo:

```env
SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173,localhost:3000,127.0.0.1:3000
```

Cada integrante debe utilizar su propia contraseña de PostgreSQL.

No subir el archivo `.env` al repositorio.

---

## Migraciones

Si se está configurando el proyecto por primera vez, ejecutar las migraciones correspondientes al proyecto.

Para `core`:

```powershell
php artisan migrate --database=core --path=database/migrations/core
```

Para `center`:

```powershell
php artisan migrate --database=center --path=database/migrations/center
```

Si corresponde, ejecutar también:

```powershell
php artisan db:seed
```

Después:

```powershell
php artisan optimize:clear
```

> No utilizar `migrate:fresh` si existen datos locales que se quieran conservar.

---

## Levantar Laravel

```powershell
php artisan serve
```

El backend debería quedar disponible normalmente en:

```text
http://localhost:8000
```

---

## Frontend

Abrir otra terminal y entrar a:

```powershell
cd medsync_frontend
```

Instalar dependencias:

```powershell
npm install
```

Levantar Vite:

```powershell
npm run dev
```

Por ejemplo:

```text
http://localhost:5173
```

---

# 2. Cambios realizados en el Frontend

Los cambios del frontend se concentraron principalmente en:

```text
src/services/http.ts
src/state/clinic-store.tsx
src/pages/register-page.tsx
```

El objetivo fue dejar de utilizar datos mock para autenticación, registro de cuentas y creación/listado de profesionales.

---

# 3. `src/state/clinic-store.tsx`

Este archivo tuvo varios cambios importantes.

Antes, gran parte del sistema utilizaba:

```text
createMockData()
allData.users
localStorage
sessionStorage
```

para simular usuarios y sesiones.

En esta iteración se modificó específicamente la parte de autenticación y profesionales.

Los mocks siguen existiendo porque otros módulos todavía los necesitan.

---

## 3.1 Usuario autenticado

Antes el usuario autenticado dependía de los usuarios guardados dentro de:

```text
allData.users
```

Ahora el usuario autenticado se mantiene directamente en estado:

```ts
const [user, setUser] = useState<User | null>(null);
```

### ¿Por qué se cambió?

Porque tener un usuario dentro de `localStorage` no significa que exista una sesión válida en Laravel.

La fuente real de autenticación ahora es el backend.

---

## 3.2 `applyBackendUser()`

Se agregó una función:

```ts
applyBackendUser();
```

Su función es convertir la respuesta que entrega Laravel al formato `User` que actualmente utiliza el frontend.

Laravel puede devolver:

```text
id
name
email
role
medical_center
patient
professional
```

mientras que el frontend trabaja con:

```text
id
name
email
role
permissions
organizationIds
memberships
patientId
professionalId
```

Por eso se necesita una adaptación entre ambas estructuras.

### ¿Por qué se hizo?

Para conectar Laravel sin tener que modificar inmediatamente todas las interfaces y componentes existentes del frontend.

---

## 3.3 Recuperación de sesión

Se agregó una comprobación de sesión al cargar la aplicación.

Se ejecuta:

```ts
sanctum.me();
```

que consulta:

```text
GET /api/v1/me
```

El flujo queda:

```text
React inicia
    ↓
sanctum.me()
    ↓
Laravel revisa la sesión
    ↓
Laravel devuelve el usuario
    ↓
applyBackendUser()
```

### ¿Por qué se hizo?

Antes el frontend podía depender de datos del navegador para considerar que existía una sesión.

Ahora Laravel confirma realmente si el usuario sigue autenticado.

Esto también permite mantener correctamente la sesión después de recargar la página.

---

## 3.4 `authLoading`

Se agregó:

```ts
authLoading;
```

Mientras se consulta `/api/v1/me`:

```text
authLoading = true
```

Cuando Laravel responde:

```text
authLoading = false
```

### ¿Por qué?

Para evitar que React redirija al usuario al login antes de que Laravel termine de comprobar si ya existe una sesión activa.

---

## 3.5 Login real

La función:

```ts
login();
```

fue ajustada para dejar de buscar las credenciales dentro de:

```text
allData.users
```

Ahora utiliza:

```ts
const response = await sanctum.login(email, password);
```

Después utiliza:

```ts
applyBackendUser(response.data);
```

El flujo es:

```text
Formulario login
      ↓
ClinicProvider.login()
      ↓
sanctum.login()
      ↓
Laravel
      ↓
respuesta real
      ↓
applyBackendUser()
```

### ¿Por qué?

Para eliminar la autenticación mediante usuarios demo/mock y utilizar la sesión real de Laravel.

---

## 3.6 Validación de portal y centro

Dentro del nuevo `login()` también se agregaron comprobaciones.

Por ejemplo:

```text
SUPER_ADMIN
```

no debe acceder mediante el portal normal de un centro.

Del mismo modo, un usuario de centro no debe acceder mediante el portal de administración global.

También se comprueba que el centro que devuelve Laravel corresponda al centro que se está intentando abrir en el frontend.

### ¿Por qué?

Para evitar que una sesión válida sea utilizada desde un portal o centro que no corresponde.

---

## 3.7 Logout real

La función:

```ts
logout();
```

ahora llama:

```ts
await sanctum.logout();
```

y posteriormente limpia:

```text
user
organizationId
sessionStorage
```

### ¿Por qué?

Porque cerrar sesión no debería consistir solamente en eliminar datos del navegador.

Laravel también debe destruir la sesión del servidor.

---

# 4. Crear Cuenta sin Mock

Uno de los cambios principales fue dejar el flujo de Crear Cuenta independiente del registro mock.

Anteriormente existía un flujo que podía crear localmente:

```text
User mock
Patient mock
Membership mock
```

dentro de los datos utilizados por `ClinicProvider`.

Eso ya no se utiliza para el formulario actual de Crear Cuenta.

---

# 5. `src/pages/register-page.tsx`

Este archivo fue modificado para realizar el registro directamente contra Laravel.

Actualmente ya NO obtiene:

```ts
register;
```

desde:

```ts
useClinic();
```

para crear la cuenta.

Ahora utiliza:

```ts
registerWithBackend(values);
```

---

## 5.1 `registerWithBackend()`

Se agregó:

```ts
async function registerWithBackend(values: Record<string, string>);
```

Esta función ejecuta:

```ts
sanctum.register({
  first_name: values.firstName.trim(),
  last_name: values.lastName.trim(),
  rut: values.rut,
  birth_date: values.birthDate,
  email: values.email.trim(),
  phone: values.phone,
  health_insurance: values.healthInsurance,
  medical_insurance: values.medicalInsurance || null,
  address: values.address || null,
  password: values.password,
  password_confirmation: values.confirmation,
  consent: values.consent === "on",
});
```

### ¿Por qué?

Para que Crear Cuenta utilice directamente el registro que ya existía en Laravel y no cree otra cuenta paralela dentro del mock.

El flujo actual es:

```text
Formulario
    ↓
registerWithBackend()
    ↓
sanctum.register()
    ↓
Laravel
    ↓
PostgreSQL
```

---

## 5.2 Eliminación del registro mock del formulario

Antes se podía llamar a:

```ts
useClinic().register();
```

y después sincronizar el mismo registro con Laravel.

Eso podía dejar dos representaciones diferentes de una misma cuenta:

```text
Paciente mock
+
Paciente PostgreSQL
```

Ese flujo fue eliminado del formulario.

Ahora:

```text
Crear Cuenta
→ solo Laravel/PostgreSQL
```

### ¿Por qué?

Para evitar duplicaciones y evitar que el frontend considere creada una cuenta que Laravel haya rechazado.

---

## 5.3 Manejo de errores

Se agregó:

```ts
firstApiError();
```

para obtener un mensaje útil desde los errores de validación de Laravel.

Ejemplo:

```ts
function firstApiError(error: ApiError) {
  const firstFieldMessage = Object.values(error.errors ?? {})
    .flat()
    .find(Boolean);

  return firstFieldMessage ?? error.message;
}
```

También se utiliza:

```ts
throw error;
```

cuando el registro falla.

### ¿Por qué?

Si Laravel devuelve un error, por ejemplo:

```text
422 Unprocessable Content
```

el formulario debe detenerse.

No debe:

```text
mostrar éxito
navegar al portal
intentar continuar el proceso
```

---

## 5.4 Login automático después de Crear Cuenta

Después de registrar correctamente al paciente se ejecuta:

```ts
const loggedIn = await login(
  values.email.trim(),
  values.password,
  "CENTER",
  center.id,
);
```

Si funciona:

```text
Crear cuenta
    ↓
Login automático
    ↓
Portal
```

Si por alguna razón el login automático falla, la cuenta igualmente quedó creada y se envía al usuario a:

```text
/ingresar
```

### ¿Por qué?

Para mejorar el flujo del usuario sin mezclar nuevamente la cuenta con datos mock.

---

## 5.5 `authLoading` en RegisterPage

La pantalla también utiliza:

```ts
authLoading;
```

para esperar a que Laravel determine si ya existe una sesión.

### ¿Por qué?

Para impedir que un usuario que ya está autenticado llegue momentáneamente al formulario de registro mientras `/api/v1/me` todavía está respondiendo.

---

# 6. `src/services/http.ts`

En esta iteración este archivo fue ampliado principalmente para conectar profesionales.

La autenticación mediante `sanctum` ya existía en el proyecto y NO se considera un cambio nuevo de esta iteración.

Se agregaron los tipos relacionados con profesionales:

```ts
ProfessionalPayload;
BackendProfessional;
ProfessionalResponse;
ProfessionalsResponse;
```

---

## 6.1 `ProfessionalPayload`

Representa los datos que React envía a Laravel:

```ts
export interface ProfessionalPayload {
  first_name: string;
  last_name: string;
  rut: string;
  email: string;
  phone: string;
  is_active?: boolean;
}
```

---

## 6.2 `BackendProfessional`

Representa la estructura que devuelve Laravel:

```ts
export interface BackendProfessional {
  id: number;
  user_id: number | null;
  first_name: string;
  last_name: string;
  rut: string;
  email: string;
  phone: string;
  is_active: boolean;
}
```

### ¿Por qué se agregaron estos tipos?

Porque la estructura del profesional en Laravel no es exactamente igual a la utilizada actualmente por los componentes React.

TypeScript necesita conocer ambas estructuras.

---

## 6.3 `professionalApi`

Se agregó:

```ts
export const professionalApi = {
  list: () => apiRequest<ProfessionalsResponse>("/api/v1/professionals"),

  create: (payload: ProfessionalPayload) =>
    apiRequest<ProfessionalResponse>("/api/v1/professionals", {
      method: "POST",
      body: JSON.stringify(payload),
    }),
};
```

### ¿Por qué?

Para centralizar la comunicación de profesionales con Laravel en el servicio HTTP, en vez de realizar `fetch()` directamente dentro de los componentes.

---

# 7. Profesionales en `clinic-store.tsx`

Además de autenticación, `clinic-store.tsx` fue modificado para comenzar a utilizar profesionales reales.

---

## 7.1 `saveProfessional()` antes

Anteriormente la función terminaba creando un objeto frontend y guardándolo mediante:

```ts
commit(...)
```

Por lo tanto el profesional solamente existía en los datos mock/localStorage.

---

## 7.2 `saveProfessional()` ahora

La función pasó a ser:

```ts
async;
```

y utiliza:

```ts
await professionalApi.create(...)
```

Ejemplo:

```ts
const response = await professionalApi.create({
  first_name: firstName,
  last_name: lastName,
  rut: input.rut,
  email: input.email.trim().toLowerCase(),
  phone: input.phone,
  is_active: input.active,
});
```

### ¿Por qué?

Para que cuando el ADMIN cree un profesional este quede almacenado realmente en PostgreSQL y no solamente en `localStorage`.

---

## 7.3 Conversión de nombre

Actualmente el formulario frontend trabaja con:

```text
name
```

por ejemplo:

```text
Camila Rojas
```

mientras el backend trabaja con:

```text
first_name
last_name
```

Por eso temporalmente se agregó:

```ts
const nameParts = input.name.trim().split(/\s+/);

const firstName = nameParts.shift() ?? "";

const lastName = nameParts.join(" ");
```

### ¿Por qué?

Para conectar el backend sin tener que modificar todavía todos los componentes que esperan un único campo `name`.

---

## 7.4 Adaptación de respuesta

Laravel devuelve:

```text
first_name
last_name
```

pero el frontend sigue esperando:

```text
name
```

Por eso se vuelve a transformar:

```ts
name: `${created.first_name} ${created.last_name}`;
```

---

## 7.5 Edición todavía no conectada

Se agregó una protección temporal:

```ts
if (id) {
  throw new Error(
    "La edición de profesionales todavía no está conectada al backend.",
  );
}
```

### ¿Por qué?

Porque en esta iteración se conectó solamente:

```text
Crear profesional
Listar profesionales
```

No se implementó todavía:

```text
Editar profesional
```

Esto evita que una pantalla parezca guardar una edición cuando en realidad PostgreSQL no fue modificado.

---

# 8. Carga de profesionales reales

También se agregó en `clinic-store.tsx` una carga de profesionales utilizando:

```ts
professionalApi.list();
```

Cuando existe:

```text
usuario autenticado
+
centro seleccionado
+
role = ADMIN
```

el frontend consulta:

```text
GET /api/v1/professionals
```

y convierte la respuesta de Laravel al formato del frontend.

### ¿Por qué?

Para que después de actualizar la página los profesionales no dependan únicamente de lo que quedó guardado en `localStorage`.

Los registros vuelven a obtenerse desde PostgreSQL.

---

# 9. Cambios realizados en el Backend

En el backend, durante esta iteración el cambio principal fue agregar la API para gestionar profesionales.

No se considera parte de esta iteración:

```text
/api/login
/api/register
/api/logout
/api/v1/me
Sanctum
separación core/center
```

porque esas partes ya existían.

---

# 10. `app/Http/Controllers/Api/ProfessionalController.php`

Se agregó este controlador para manejar los profesionales reales desde el panel ADMIN.

Se implementaron:

```text
index()
store()
```

---

## 10.1 `index()`

Se utiliza para obtener los profesionales almacenados en la base de datos.

Ejemplo conceptual:

```php
$professionals = Professional::query()
    ->orderBy('first_name')
    ->orderBy('last_name')
    ->get();
```

Luego devuelve:

```json
{
  "data": []
}
```

### ¿Por qué?

El frontend necesitaba dejar de obtener la lista de profesionales solamente desde los datos mock.

---

## 10.2 `store()`

Se agregó para registrar profesionales desde el panel ADMIN.

Recibe:

```text
first_name
last_name
rut
email
phone
is_active
```

Se normaliza el teléfono y se validan los datos.

También se utiliza la regla existente:

```php
new ValidRut()
```

para validar el RUT.

### ¿Por qué?

Para que la creación de profesionales llegue realmente a PostgreSQL.

---

## 10.3 Validación de duplicados

Antes de crear el registro se comprueba si ya existe otro profesional con el mismo:

```text
RUT
```

o:

```text
correo
```

en el centro actual.

### ¿Por qué?

Para evitar fichas duplicadas.

---

## 10.4 Permiso ADMIN

Se agregó una validación para comprobar que el usuario que intenta administrar profesionales tenga una relación activa:

```text
role = ADMIN
```

Ejemplo conceptual:

```php
$centerUser = $request->user()
    ->centerUsers()
    ->where('role', 'ADMIN')
    ->where('is_active', true)
    ->first();
```

Si no existe:

```text
403 Forbidden
```

### ¿Por qué?

No basta con ocultar el botón en React.

La autorización también debe validarse en Laravel.

---

## 10.5 `user_id = null`

Cuando se crea la ficha:

```php
'user_id' => null,
```

Esto fue intencional.

Crear la ficha profesional no significa darle automáticamente una cuenta para iniciar sesión.

Actualmente:

```text
ADMIN crea profesional
        ↓
professionals
        ↓
user_id = null
```

Más adelante se puede implementar el proceso:

```text
Dar acceso
```

que relacionará la ficha profesional con una cuenta `users`.

---

# 11. `routes/api.php`

Este archivo YA existía y las rutas de autenticación YA existían.

En esta iteración solamente se agregaron las rutas correspondientes a profesionales:

```php
Route::get(
    '/v1/professionals',
    [ProfessionalController::class, 'index']
);

Route::post(
    '/v1/professionals',
    [ProfessionalController::class, 'store']
);
```

Ambas quedaron dentro del grupo que ya utilizaba:

```php
auth:sanctum
```

### ¿Por qué?

Para exponer al frontend únicamente las operaciones nuevas de profesionales aprovechando la autenticación que el proyecto ya tenía.

---

# 12. `app/Models/Center/Professional.php`

Este archivo se revisó durante la implementación.

No fue necesario modificar su configuración principal porque ya utilizaba:

```php
protected $connection = 'center';

protected $table = 'professionals';
```

y ya contenía los campos requeridos en:

```php
$fillable
```

Por lo tanto:

```text
Professional.php
```

NO debe considerarse un archivo nuevo de esta iteración.

Solo se confirmó que podía ser utilizado por `ProfessionalController`.

---

# 13. Qué NO modificamos en esta iteración

Para evitar confusión, estos elementos ya existían previamente:

```text
POST /api/login
POST /api/register
POST /api/logout
GET /api/v1/me

Laravel Sanctum

Conexión core
Conexión center

medsync_core
medsync_clinica_horizonte

center_users

Roles existentes

Estructura general de autenticación del backend
```

La integración frontend fue adaptada para utilizarlos, pero no fueron creados nuevamente.

---

# 14. Qué sigue utilizando Mock

Los mocks no fueron eliminados completamente de:

```text
clinic-store.tsx
```

porque otros módulos todavía dependen de ellos.

Actualmente:

```text
Login                      REAL
Crear cuenta               REAL
Sesión                      REAL

Listar profesionales        REAL
Crear profesional           REAL

Agenda                      MOCK
Reservas                    MOCK
Disponibilidad              MOCK
Especialidades              MOCK
Prestaciones                MOCK
Resultados                  MOCK
Parte de pacientes/perfil   MOCK
Gestión de accesos          MOCK
```

### ¿Por qué no se eliminaron todos?

Porque hacerlo ahora rompería las pantallas que todavía no tienen endpoints ni persistencia real.

La migración se está realizando por módulos.

---

# 15. Resumen de archivos modificados

## Frontend

```text
src/services/http.ts
```

Se agregó:

```text
Tipos para profesionales
professionalApi.list()
professionalApi.create()
```

---

```text
src/state/clinic-store.tsx
```

Se modificó:

```text
Autenticación del usuario usando Laravel
Recuperación de sesión
authLoading
applyBackendUser()
login real
logout real
carga de profesionales reales
saveProfessional() conectado al backend
```

Los mocks permanecen para los módulos no migrados.

---

```text
src/pages/register-page.tsx
```

Se modificó:

```text
Eliminación del register mock del formulario
registerWithBackend()
manejo de errores de Laravel
firstApiError()
login automático después del registro
authLoading
```

---

## Backend

```text
app/Http/Controllers/Api/ProfessionalController.php
```

Archivo agregado:

```text
GET profesionales
POST profesionales
validaciones
validación ADMIN
detección de duplicados
```

---

```text
routes/api.php
```

Solamente se agregaron:

```text
GET /api/v1/professionals
POST /api/v1/professionals
```

Las rutas de autenticación ya existían.

---

# 16. Pruebas realizadas

Para revisar las rutas:

```powershell
php artisan route:list --path=professionals
```

Se comprobó que aparecieran:

```text
GET|HEAD  api/v1/professionals
POST      api/v1/professionals
```

Durante las pruebas del ADMIN también se comprobó:

```text
Login                         200
GET profesionales             200
```

Al realizar una petición con un RUT inválido Laravel respondió:

```text
422 Unprocessable Content
```

confirmando que las validaciones del backend estaban funcionando.

---

# 17. Comprobar profesionales

Desde Tinker:

```powershell
php artisan tinker
```

Ver profesionales:

```php
\App\Models\Center\Professional::all();
```

Ver último registro:

```php
\App\Models\Center\Professional::latest('id')->first();
```

---

# 18. Compilar Frontend

Después de los cambios se recomienda ejecutar:

```powershell
npm run build
```

Esto comprueba que TypeScript y Vite puedan compilar correctamente el frontend antes de subir la rama.

---

# 19. Estado al finalizar esta iteración

```text
Autenticación frontend con Laravel        ✅
Recuperación de sesión                    ✅
Logout conectado                          ✅

Crear cuenta sin mock                     ✅
Login automático después de registro      ✅

Listar profesionales reales               ✅
Crear profesional real                    ✅
Validación ADMIN                          ✅

Editar profesional                        Pendiente
Especialidad del profesional              Pendiente

Agenda                                    Mock
Reservas                                  Mock
Disponibilidad                            Mock
Especialidades                            Mock
Prestaciones                              Mock
```

---

# 20. Próximos pasos

Los siguientes módulos pueden ir migrándose de la misma forma:

```text
Especialidades
Relación profesional-especialidad
Disponibilidad
Prestaciones
Agenda
Reservas
Gestión de acceso profesional
```

La idea es mantener:

```text
Frontend existente
      ↓
reemplazar cada operación mock
      ↓
API Laravel
      ↓
PostgreSQL
```

sin eliminar de golpe funcionalidades que todavía dependen de los datos demo.
