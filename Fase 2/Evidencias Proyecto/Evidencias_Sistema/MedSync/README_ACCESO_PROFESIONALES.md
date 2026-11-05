# MedSync — Acceso e invitación de profesionales

## 1. Objetivo

Esta implementación incorpora el flujo completo para que un profesional registrado por un administrador pueda obtener acceso a MedSync, crear su contraseña mediante una invitación por correo electrónico e iniciar sesión con el rol `PROFESIONAL`.

El flujo separa la **ficha administrativa del profesional** de su **cuenta de acceso**. Registrar un profesional no crea automáticamente un usuario del sistema.

## 2. Flujo implementado

```text
Administrador registra profesional
        ↓
Professional.user_id = NULL
        ↓
Administrador pulsa "Habilitar acceso"
        ↓
Laravel crea o vincula User
        ↓
Se crea CenterUser con rol PROFESIONAL
        ↓
Professional.user_id queda vinculado
        ↓
Laravel genera un token temporal
        ↓
Se envía una invitación por correo
        ↓
Profesional abre /activar-cuenta
        ↓
Crea y confirma su contraseña
        ↓
Laravel valida y consume el token
        ↓
Profesional inicia sesión normalmente
        ↓
/api/v1/me devuelve role = PROFESIONAL
```

## 3. Cambios realizados en el backend

### 3.1. Modelo `User`

Archivo:

```text
app/Models/User.php
```

Se agregó `Notifiable` para permitir el envío de notificaciones por correo desde Laravel.

```php
use Illuminate\Notifications\Notifiable;

use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
```

El modelo continúa utilizando la conexión `core`.

### 3.2. Tabla `password_reset_tokens`

Se creó la migración:

```text
database/migrations/core/2026_09_28_203420_create_password_reset_tokens_table.php
```

La tabla se encuentra en `medsync_core` y almacena temporalmente los tokens utilizados para crear la contraseña de una cuenta invitada.

Campos principales:

- `email`
- `token`
- `created_at`

El token se elimina cuando se utiliza correctamente.

### 3.3. Configuración del Password Broker

Archivo:

```text
config/auth.php
```

Se configuró explícitamente el password broker para trabajar con la conexión `core` y la tabla `password_reset_tokens`.

El enlace de activación tiene una duración de 60 minutos.

### 3.4. URL del frontend

Archivo:

```text
config/app.php
```

Se agregó:

```php
'frontend_url' => env(
    'FRONTEND_URL',
    'http://localhost:3000'
),
```

Esto permite que Laravel genere enlaces hacia la aplicación React.

Ejemplo:

```text
http://localhost:3000/activar-cuenta?token=...&email=...
```

### 3.5. Servicio de acceso profesional

Archivo:

```text
app/Services/Professionals/ProfessionalAccessService.php
```

Responsabilidades principales:

- validar que el profesional esté activo;
- exigir un correo electrónico;
- impedir habilitar dos veces la misma ficha;
- buscar una cuenta global existente por correo;
- crear un `User` si no existe;
- crear un `CenterUser` con rol `PROFESIONAL`;
- vincular `professional.user_id`;
- generar un token temporal;
- enviar la invitación por correo;
- permitir reenviar una invitación mientras la cuenta no haya sido activada.

La lógica utiliza la base central para `users` y `center_users`, mientras que la ficha `professional` continúa almacenada en la base de datos del centro.

### 3.6. Controlador de acceso profesional

Archivo:

```text
app/Http/Controllers/Api/ProfessionalAccessController.php
```

Endpoints implementados:

```http
POST /api/v1/professionals/{professional}/enable-access
POST /api/v1/professionals/{professional}/resend-invitation
```

Ambos endpoints requieren autenticación mediante Sanctum y un usuario con rol `ADMIN`.

### 3.7. Notificación de invitación

Archivo:

```text
app/Notifications/ProfessionalInvitationNotification.php
```

Laravel envía un correo con:

- nombre del profesional;
- nombre del centro médico;
- aviso de habilitación de acceso;
- botón `Crear mi contraseña`;
- enlace con token y correo;
- aviso de expiración de 60 minutos.

### 3.8. Activación de cuenta

Archivos:

```text
app/Http/Controllers/Api/AccountActivationController.php
app/Http/Requests/ActivateAccountRequest.php
```

Endpoint:

```http
POST /api/auth/activate-account
```

Payload esperado:

```json
{
  "email": "profesional@correo.com",
  "token": "TOKEN_RECIBIDO",
  "password": "NuevaClave123",
  "password_confirmation": "NuevaClave123"
}
```

La contraseña debe cumplir:

- mínimo 8 caracteres;
- al menos una letra minúscula;
- al menos una letra mayúscula;
- al menos un número;
- confirmación coincidente.

Cuando la activación es correcta:

- se establece la contraseña;
- se completa `email_verified_at`;
- se consume el token de activación;
- el profesional puede utilizar el login normal de MedSync.

`remember_token` no se utiliza para este flujo y permanece `NULL`.

### 3.9. Rutas backend

Archivo:

```text
routes/api.php
```

Ruta pública:

```http
POST /api/auth/activate-account
```

Rutas protegidas:

```http
POST /api/v1/professionals/{professional}/enable-access
POST /api/v1/professionals/{professional}/resend-invitation
```

La activación es pública porque el profesional todavía no ha iniciado sesión.

## 4. Cambios realizados en el frontend

### 4.1. Servicio HTTP

Archivo:

```text
src/services/http.ts
```

Se agregó soporte para:

```text
POST /api/auth/activate-account
POST /api/v1/professionals/{id}/enable-access
```

Las peticiones reutilizan la infraestructura existente:

- `credentials: "include"`;
- Sanctum;
- cookie `XSRF-TOKEN`;
- header `X-XSRF-TOKEN`;
- manejo centralizado de errores mediante `ApiError`.

### 4.2. Página de activación

Archivo:

```text
src/pages/activate-account-page.tsx
```

Se creó la pantalla:

```text
/activar-cuenta
```

La página:

- lee `token` desde la URL;
- lee `email` desde la URL;
- solicita nueva contraseña;
- solicita confirmación;
- valida requisitos mínimos;
- llama al endpoint de activación;
- muestra errores del backend;
- informa cuando la cuenta fue activada correctamente;
- permite continuar hacia el login.

### 4.3. Ruta React

Archivo:

```text
src/App.tsx
```

Se agregó la ruta pública:

```tsx
<Route
  path="/activar-cuenta"
  element={<ActivateAccountPage />}
/>
```

### 4.4. Administración de profesionales

Archivo:

```text
src/pages/admin-page.tsx
```

Se agregó una sección de **Acceso al sistema** dentro de cada ficha profesional.

Profesional sin cuenta:

```text
Este profesional todavía no tiene una cuenta de acceso.

[ Habilitar acceso ]
```

Profesional con cuenta:

```text
Este profesional ya tiene una cuenta vinculada a MedSync.

Acceso habilitado
```

La página consulta:

```http
GET /api/v1/professionals
```

y utiliza `user_id` para determinar si la ficha tiene una cuenta vinculada.

Después de habilitar el acceso, el estado se actualiza inmediatamente en React sin necesidad de recargar la página.

### 4.5. Confirmación con shadcn/ui

Se reemplazó `window.confirm()` por un `AlertDialog` de shadcn/ui.

Archivo agregado:

```text
src/components/ui/alert-dialog.tsx
```

También se exportó `buttonVariants` desde:

```text
src/components/ui/button.tsx
```

para permitir que `AlertDialog` reutilice los estilos de botones existentes.

### 4.6. Mensajes de interfaz

Se utiliza `sonner` para notificaciones visuales de éxito y error.

## 5. Envío de correo real

Inicialmente se utilizó:

```env
MAIL_MAILER=log
```

Esto escribía las invitaciones en:

```text
storage/logs/laravel.log
```

Después se configuró SMTP para enviar correos reales mediante una cuenta dedicada de MedSync.

Ejemplo de configuración local:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=correo-medsync@gmail.com
MAIL_PASSWORD=CONTRASENA_DE_APLICACION
MAIL_FROM_ADDRESS=correo-medsync@gmail.com
MAIL_FROM_NAME="MedSync"

FRONTEND_URL=http://localhost:3000
```

### Importante

La contraseña SMTP nunca debe almacenarse en GitHub.

El archivo `.env` contiene las credenciales reales y debe permanecer fuera del repositorio.

En `.env.example` se puede documentar la estructura sin incluir secretos.

## 6. Diferencia entre tokens y cookies

### `password_reset_tokens`

Se utiliza durante la invitación y activación.

```text
correo
→ enlace con token
→ crear contraseña
→ token consumido
```

Permite expiración, uso único y validación segura.

### `laravel_session`

Mantiene la sesión del usuario después de iniciar sesión.

### `XSRF-TOKEN`

Protege las peticiones de la SPA frente a CSRF.

### `remember_token`

Corresponde al mecanismo tradicional de Laravel para la opción **Recordarme**.

MedSync actualmente no utiliza esa funcionalidad, por lo que `remember_token` permanece `NULL`.

## 7. Pruebas realizadas

Se verificó manualmente el flujo completo:

1. Registrar profesional desde Administración.
2. Confirmar que `professional.user_id` sea `NULL`.
3. Mostrar botón `Habilitar acceso`.
4. Confirmar la operación mediante `AlertDialog`.
5. Crear o vincular el usuario.
6. Crear `center_users` con rol `PROFESIONAL`.
7. Vincular `professional.user_id`.
8. Generar token de activación.
9. Enviar correo real mediante SMTP.
10. Recibir el correo en una bandeja real.
11. Abrir `Crear mi contraseña`.
12. Acceder a `/activar-cuenta`.
13. Crear contraseña.
14. Consumir el token.
15. Iniciar sesión con la cuenta profesional.
16. Consultar `GET /api/v1/me`.

Resultado esperado y comprobado:

```text
HTTP 200
role = PROFESIONAL
patient = null
professional = ficha vinculada
```

## 8. Consideraciones de seguridad

- Las contraseñas se almacenan mediante el cast `hashed` de Laravel.
- La contraseña SMTP no se versiona.
- Los tokens de activación son temporales.
- El token se elimina después de ser utilizado.
- Solo un `ADMIN` puede habilitar acceso a profesionales.
- La activación de cuenta no requiere una sesión previa.
- La autenticación normal continúa utilizando Laravel Sanctum con cookies/sesiones.
- `remember_token` no participa en el flujo actual.

## 9. Archivos principales modificados

### Backend

```text
app/Models/User.php
app/Http/Controllers/Api/ProfessionalAccessController.php
app/Http/Controllers/Api/AccountActivationController.php
app/Http/Requests/ActivateAccountRequest.php
app/Notifications/ProfessionalInvitationNotification.php
app/Services/Professionals/ProfessionalAccessService.php
config/app.php
config/auth.php
routes/api.php
database/migrations/core/2026_09_28_203420_create_password_reset_tokens_table.php
.env.example
```

### Frontend

```text
src/App.tsx
src/services/http.ts
src/pages/activate-account-page.tsx
src/pages/admin-page.tsx
src/components/ui/alert-dialog.tsx
src/components/ui/button.tsx
package.json
package-lock.json
```

## 10. Resultado final

El acceso del profesional ya no requiere intervención manual en la base de datos ni llamadas desde la consola del navegador.

Flujo del administrador:

```text
Registrar profesional
        ↓
Habilitar acceso
        ↓
Invitación enviada
```

Flujo del profesional:

```text
Recibir correo
        ↓
Crear contraseña
        ↓
Iniciar sesión
        ↓
Acceder a MedSync como PROFESIONAL
```

Con esto queda implementado y probado el flujo completo de **habilitación, invitación, activación e inicio de sesión de profesionales** en MedSync.
