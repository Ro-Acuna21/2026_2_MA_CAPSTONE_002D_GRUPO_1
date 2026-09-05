# Clínica Horizonte — frontend

Frontend de gestión de agenda médica construido con **React, TypeScript, Tailwind CSS** y componentes con la convención de **shadcn/ui**. Funciona hoy con datos demo locales y está preparado para conectarse después a una API REST desarrollada en Laravel.

Incluye flujo SaaS de marca blanca: cada centro tiene su propia dirección, identidad visual, cuentas y datos aislados mediante `organizationId`. Los pacientes y trabajadores no ven qué otras instituciones utilizan la plataforma.

## Puesta en marcha

Requiere Node.js 20.19 o superior.

```bash
npm install
npm run dev
```

Abre [http://localhost:3000](http://localhost:3000). La demostración redirige al portal de Clínica Horizonte.

## Usuarios demo

La contraseña para todas las cuentas es `Demo2026!`.

| Rol | Correo |
| --- | --- |
| Superadministrador de plataforma | `superadmin@demo.cl` |
| Administrador | `admin@demo.cl` |
| Recepción | `recepcion@demo.cl` |
| Profesional | `camila.rojas@demo.cl` |
| Paciente | `paciente1@demo.cl` |

El segundo portal de demostración está en `/centro/centro-alameda/ingresar` y usa `admin.alameda@demo.cl`, `recepcion.alameda@demo.cl`, `emilia.silva@demo.cl` y `sofia@demo.cl`.

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

## Documentación para la integración

En la repo compartida, el modelo de datos necesario para reemplazar los datos demo está en `Fase 2/Evidencias Grupales/MedSync/REQUERIMIENTOS_BD.md`. Las tareas, reglas de seguridad y orden recomendado para Laravel están en `Fase 2/Evidencias Grupales/MedSync/INDICACIONES_BACKEND.md`.

## Comandos

```bash
npm run dev
npm run build
npm run test
npm run lint
```

Los datos demo se guardan en `localStorage`; la sesión, en `sessionStorage`. No se debe usar esta persistencia para datos reales o clínicos.

## Plataforma y centros médicos

El superadministrador utiliza el acceso separado `/plataforma/acceso` y entra a `/plataforma`: crea centros, cambia planes y estados de suscripción, habilita/deshabilita su acceso y asigna administradores. Los portales clínicos no muestran ni aceptan la cuenta de plataforma. El superadministrador no recibe agenda, pacientes ni documentos clínicos. Los estados de suscripción son informativos por ahora; deshabilitar un centro bloquea su acceso sin borrar sus datos.

El administrador del centro gestiona usuarios y roles, fichas profesionales, especialidades, prestaciones, horarios base, tipos de informes y reportes generales agregados. No accede a la agenda, las reservas, las fichas de pacientes ni sus resultados. Recepción registra fichas de pacientes y gestiona la agenda diaria, las reservas y sus cambios, sin asignar privilegios ni crear profesionales. Puede recibir explícitamente permiso de carga de borradores. Cada cuenta del centro pertenece únicamente a ese centro.

Crear una ficha no crea una cuenta. Para habilitar la ficha de un profesional, el administrador usa Usuarios y permisos, el mismo correo de la ficha y una contraseña inicial. Las cuentas de pacientes nacen desde el registro propio de cada portal. No se permite quitar el último administrador del centro sin asignar otro.

## Registro e informes del paciente

Cada centro publica `/centro/{slug}/ingresar` y `/centro/{slug}/crear-cuenta`. La dirección determina el centro automáticamente: no existe selector, cambio ni vinculación de clínicas. El registro solicita nombres, apellidos, RUT (módulo 11), nacimiento, teléfono chileno, correo, previsión, seguro complementario opcional y contraseña confirmada.

El mismo correo y RUT pueden registrarse por separado en dos centros. La unicidad se valida como `centro + correo` y `centro + RUT`; las cuentas, contraseñas, citas e informes permanecen independientes. No se implementa verificación de identidad ni envío de correo.

Cuando recepción creó previamente la ficha de un paciente, el paciente puede registrarse usando exactamente el mismo RUT y correo. El sistema vincula la nueva cuenta con esa ficha sin crear un paciente duplicado. Si solo coincide uno de los dos datos, debe corregirlo con recepción.

Los pacientes pueden cancelar o reprogramar una cita hasta 24 horas antes de su inicio. Recepción conserva la capacidad de gestionar la agenda. Una cita solo puede marcarse como atendida desde su hora de inicio y como inasistencia después de su hora de término.

`/resultados` muestra documentos publicados al paciente. El administrador define los tipos; el equipo autorizado carga borradores y solo el profesional responsable los publica. Los documentos pueden vincular una atención, visualizarse y descargarse. No hay agendamiento de exámenes. Formatos demo: PDF, PNG, JPEG y TXT, hasta 500 KB por archivo, sujetos a la capacidad del navegador.

Las contraseñas nuevas se guardan como derivaciones PBKDF2 con sal; esto no convierte la demo en autenticación segura de producción. Laravel/Sanctum debe reemplazar por completo las credenciales y la autorización locales. La migración local separa cuentas antiguas que estaban asociadas a varios centros y guarda la nueva estructura en la versión 4, sin borrar las copias anteriores.

## Verificación de los nuevos flujos

- `npm run test`: validación de registro, aislamiento de centros, roles, creación de cuentas y fichas, borrador/publicación/lectura de informes y navegación de plataforma.
- `npm run build`: TypeScript y bundle de producción.
- `npm run lint`: análisis estático.
- Prueba manual: entrar por `/plataforma/acceso`, desplegar Editar centro y suscripción; abrir `/centro/clinica-horizonte/ingresar` como administrador y crear un tipo de informe; entrar como Camila, cargar y publicar un resultado; entrar como Paciente 1 y visualizar el documento. Abrir `/centro/centro-alameda/ingresar` para verificar que muestra otras cuentas y datos. Usar exclusivamente archivos ficticios.

La implementación de tutores, pagos, restricciones automáticas por suscripción y la integración con backend quedan para etapas posteriores.
