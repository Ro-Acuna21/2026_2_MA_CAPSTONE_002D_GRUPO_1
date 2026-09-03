# Clínica Horizonte — frontend

Frontend de gestión de agenda médica construido con **React, TypeScript, Tailwind CSS** y componentes con la convención de **shadcn/ui**. Funciona hoy con datos demo locales y está preparado para conectarse después a una API REST desarrollada en Laravel.

Incluye flujo SaaS multiempresa: credenciales personales, selección de organización, cambio de empresa y aislamiento de los datos demo mediante `organizationId`.

## Puesta en marcha

Requiere Node.js 20.19 o superior.

```bash
npm install
npm run dev
```

Abre [http://localhost:3000](http://localhost:3000).

## Usuarios demo

La contraseña para todas las cuentas es `Demo2026!`.

| Rol | Correo |
| --- | --- |
| Administrador | `admin@demo.cl` |
| Recepción | `recepcion@demo.cl` |
| Profesional | `camila.rojas@demo.cl` |
| Paciente | `paciente1@demo.cl` |

## Arquitectura

```text
src/
  components/       Componentes compartidos y UI estilo shadcn
  data/             Dataset de demostración
  domain/           Tipos y permisos del negocio
  pages/            Pantallas organizadas por ruta
  services/         Cliente HTTP y contrato de la API Laravel
  state/            Proveedor de datos mock intercambiable
  styles/           Tema global de Tailwind
```

La interfaz no acopla las pantallas a `fetch`, rutas HTTP ni modelos Eloquent. `src/services/clinic-api.ts` concentra el contrato remoto y `src/services/http.ts` configura las solicitudes con `credentials: include`, CSRF y errores de validación para Laravel Sanctum.

## Conectar el backend Laravel

1. Copia `.env.example` como `.env`.
2. Define `VITE_API_URL` con el origen de Laravel.
3. Implementa los endpoints descritos en `docs/api-contract.md`.
4. Sustituye el proveedor mock por llamadas a `clinicApi` o crea un `ApiClinicProvider`.
5. Mantén en Laravel la autorización real con policies/middleware y Spatie Permission. Los permisos del frontend solo controlan la interfaz.

## Comandos

```bash
npm run dev
npm run build
npm run test
npm run lint
```

Los datos demo se guardan en `localStorage`; la sesión, en `sessionStorage`. No se debe usar esta persistencia para datos reales o clínicos.
