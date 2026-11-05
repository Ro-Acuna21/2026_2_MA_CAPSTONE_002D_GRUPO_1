# MedSync

MedSync es una plataforma de gestión clínica para varios centros. Una cuenta de plataforma administra centros, planes y suscripciones; cada centro trabaja con su propia base clínica PostgreSQL. Laravel resuelve el centro desde la sesión autenticada y aplica los permisos en el servidor.

## Componentes

| Componente | Tecnología | Ubicación |
| --- | --- | --- |
| Interfaz web | React, TypeScript, Vite | `Fase 2/Evidencias Proyecto/Evidencias_Sistema/MedSync/medsync_frontend` |
| API y autenticación | Laravel, Sanctum, Spatie Permission | `Fase 2/Evidencias Proyecto/Evidencias_Sistema/MedSync/medsync_backend` |
| Datos | PostgreSQL: Core y una base clínica por centro | Configuración en el backend |

Core conserva centros, usuarios globales, membresías, catálogo de planes y suscripciones. Cada base clínica conserva pacientes, profesionales, prestaciones, disponibilidad, citas, resultados médicos, precios e historial. `SUPER_ADMIN` administra la plataforma sin acceso a datos clínicos; ADMIN, recepción, profesional y paciente usan permisos del centro activo.

## Funciones principales

- Directorio público de centros, selección explícita de centro, registro y acceso contextual.
- Administración de centros, personal, especialidades, prestaciones y horarios.
- Reservas de pacientes y recepción, agenda, reprogramación y consulta del historial de citas.
- Resultados médicos con documentos privados, borrador, publicación, anulación y descarga autorizada.
- Tarifas con vigencia e historial, indicadores clínicos y reportes comerciales por centro.
- Catálogo de planes, suscripciones y pagos informados manualmente. La interfaz muestra el estado persistido; MedSync no procesa cobros bancarios.

## Accesos web

| Ruta | Uso |
| --- | --- |
| `/` y `/centros` | Sitio y directorio públicos |
| `/ingresar` | Selección de centro |
| `/centro/{slug}/ingresar` | Acceso clínico del centro |
| `/plataforma/acceso` | Acceso de administración de plataforma |

## Ejecución local

Se requieren PHP 8.3 o superior con `pdo_pgsql`, Composer, PostgreSQL y Node.js. En `medsync_backend`, instalar dependencias con `composer install`, copiar `.env.example` a `.env`, configurar las conexiones Core y Center, y ejecutar `php artisan key:generate`, `php artisan migrate` y `php artisan db:seed`. Iniciar la API con `php artisan serve`.

En `medsync_frontend`, ejecutar `npm install` y `npm run dev`. La interfaz se abre en `http://localhost:3000` y, por defecto, llama a la API en `http://localhost:8000`; `VITE_API_URL` permite cambiar esa dirección.

La configuración de bases, migraciones y centros existentes se explica en [README del backend](Fase%202/Evidencias%20Proyecto/Evidencias_Sistema/MedSync/medsync_backend/README.md). El [contrato de API](Fase%202/Evidencias%20Proyecto/Evidencias_Sistema/MedSync/medsync_frontend/docs/api-contract.md) y la [matriz de roles](Fase%202/Evidencias%20Proyecto/Evidencias_Sistema/MedSync/medsync_backend/docs/MATRIZ_ACCESO_ROLES.md) detallan rutas y permisos. Para ejecutar las pruebas: `php artisan test` en el backend y `npm test` en el frontend.
