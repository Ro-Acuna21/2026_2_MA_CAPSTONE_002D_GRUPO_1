# MediSync — frontend

Frontend de gestión de agenda médica construido con **React, TypeScript, Tailwind CSS** y componentes con la convención de **shadcn/ui**. Algunos módulos siguen usando datos demo locales; autenticación, registro y parte de las reservas se conectan a Laravel.

Incluye flujo SaaS de marca blanca: cada centro tiene su propia dirección, identidad visual, cuentas y datos aislados mediante `organizationId`. Los pacientes y trabajadores no ven qué otras instituciones utilizan la plataforma.

## Puesta en marcha

Requiere Node.js 20.19 o superior.

```bash
npm install
npm run dev
```

Abre [http://localhost:3000](http://localhost:3000). La raíz es una landing pública; no redirige a ningún centro.

## Navegación pública y acceso contextual

El selector público de login y registro usa `GET /api/public/centers` como fuente de centros activos. `src/data/public-centers.ts` conserva textos e información comercial estática para las vistas públicas; no determina qué centro puede recibir un registro.

| Ruta | Propósito |
| --- | --- |
| `/` | Landing comercial de MediSync. |
| `/centros` | Directorio de centros asociados. |
| `/centros/{slug}` | Micrositio público reutilizable de cada centro. |
| `/ingresar` | Busca o selecciona el centro antes de cualquier autenticación. |
| `/centro/{slug}/ingresar` | Login contextual; React entrega el slug al backend. |
| `/crear-cuenta` | Selector de centro para continuar al registro contextual. |
| `/plataforma/acceso` | Acceso separado y exclusivo de `SUPER_ADMIN`. |

Los accesos genéricos nunca asumen Clínica Horizonte ni otro centro. Los botones dentro de un micrositio sí llevan directamente a su propio `/centro/{slug}/ingresar`.

El formulario de `/centro/{slug}/crear-cuenta` envía ese `slug` como `center_slug` a Laravel y lo reutiliza para el login posterior. Si Core no publica el centro, no se ofrece en el selector. Los centros estáticos del directorio comercial pueden diferir de los centros aprovisionados en Core; mantenerlos sincronizados queda pendiente.

## Usuarios del dataset local de demostración

Estas cuentas y la contraseña `Demo2026!` pertenecen al dataset local de módulos aún no integrados. **No son las credenciales del login Laravel**. Para probar el acceso real de Clínica Horizonte, consultar las cuentas de los seeders en `../medsync_backend/README.md`.

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

La interfaz concentra las solicitudes Laravel en `src/services/http.ts`, con `credentials: include`, CSRF y errores de validación. `src/services/clinic-api.ts` conserva contratos de módulos pendientes de integración. El proveedor de estado combina datos reales de autenticación, pacientes, reservas y horarios con datos locales de los módulos demo.

## Integración pendiente con Laravel

1. Copia `.env.example` como `.env`.
2. Define `VITE_API_URL` con el origen de Laravel.
3. Implementa los endpoints marcados como pendientes en `docs/api-contract.md` sin reemplazar los que ya funcionan.
4. Sustituye los datos locales solo en los módulos aún pendientes.
5. Mantén en Laravel la autorización real con middleware/policies. Los permisos del frontend solo controlan la interfaz; Spatie Permission aún no está instalado en este checkout.

## Documentación para la integración

En la repo compartida, el modelo de datos necesario para reemplazar los datos demo está en `Fase 2/Evidencias Proyecto/Evidencias_Sistema_Aplicacion/MedSync/REQUERIMIENTOS_BD.md`. Las tareas, reglas de seguridad y orden recomendado para Laravel están en `Fase 2/Evidencias Proyecto/Evidencias_Sistema_Aplicacion/MedSync/INDICACIONES_BACKEND.md`.

## Comandos

```bash
npm run dev
npm run build
npm run test
npm run lint
```

Algunos módulos demo conservan datos en `localStorage`. La autenticación real usa la cookie de sesión de Laravel/Sanctum; `sessionStorage` solo recuerda el identificador visual del centro seleccionado. Las reservas reales y los datos clínicos integrados no deben guardarse en almacenamiento local.

## Plataforma y centros médicos

El superadministrador utiliza el acceso separado `/plataforma/acceso` y entra a `/plataforma`: crea centros, cambia planes y estados de suscripción, habilita/deshabilita su acceso y asigna administradores. Los portales clínicos no muestran ni aceptan la cuenta de plataforma. El superadministrador no recibe agenda, pacientes ni documentos clínicos. Los estados de suscripción son informativos por ahora; deshabilitar un centro bloquea su acceso sin borrar sus datos.

El administrador del centro gestiona usuarios y roles, fichas profesionales, la selección de especialidades de su centro, prestaciones, horarios base, tipos de informes y reportes generales agregados. No accede a la agenda, las reservas, las fichas de pacientes ni sus resultados. Recepción registra fichas de pacientes y gestiona la agenda diaria, las reservas y sus cambios, sin asignar privilegios ni crear profesionales. Puede recibir explícitamente permiso de carga de borradores. Cada cuenta del centro pertenece únicamente a ese centro.

Crear una ficha no crea una cuenta. Para habilitar la ficha de un profesional, el administrador usa Usuarios y permisos, el mismo correo de la ficha y una contraseña inicial. Las cuentas de pacientes nacen desde el registro propio de cada portal. No se permite quitar el último administrador del centro sin asignar otro.

## Registro e informes del paciente

Cada centro publica `/centro/{slug}/ingresar` y `/centro/{slug}/crear-cuenta`. La dirección determina el contexto clínico después de que la persona lo selecciona explícitamente desde `/ingresar` o `/crear-cuenta`; dentro del portal no existe cambio ni vinculación libre de clínicas. El registro solicita nombres, apellidos, RUT (módulo 11), nacimiento, teléfono chileno, correo, previsión, seguro complementario opcional y contraseña confirmada.

El mismo correo y RUT pueden registrarse por separado en dos centros. La unicidad se valida como `centro + correo` y `centro + RUT`; las cuentas, contraseñas, citas e informes permanecen independientes. No se implementa verificación de identidad ni envío de correo.

Cuando recepción creó previamente la ficha de un paciente, el paciente puede registrarse usando exactamente el mismo RUT y correo. El sistema vincula la nueva cuenta con esa ficha sin crear un paciente duplicado. Si solo coincide uno de los dos datos, debe corregirlo con recepción.

El módulo Pacientes de recepción carga listado y detalle desde `GET /api/v1/patients` y `GET /api/v1/patients/{id}`. Alta y edición usan `POST` y `PATCH`; el perfil del paciente consulta y edita solo su propia ficha. Estas operaciones persisten en PostgreSQL del centro resuelto por Laravel y no guardan fichas en `localStorage`. El correo de una ficha vinculada a una cuenta queda de solo lectura mientras no exista un flujo coordinado para cambiar también el correo de acceso en Core. Reservas mantiene la selección de pacientes activos mediante el mismo listado.

Los pacientes pueden cancelar o reprogramar una cita hasta 24 horas antes de su inicio. Recepción conserva la capacidad de gestionar la agenda. Una cita solo puede marcarse como atendida desde su hora de inicio y como inasistencia después de su hora de término.

`/resultados` muestra documentos publicados al paciente. El administrador define los tipos; el equipo autorizado carga borradores y solo el profesional responsable los publica. Los documentos pueden vincular una atención, visualizarse y descargarse. No hay agendamiento de exámenes. Formatos demo: PDF, PNG, JPEG y TXT, hasta 500 KB por archivo, sujetos a la capacidad del navegador.

Los módulos demo heredados conservan derivaciones PBKDF2 locales, que no sustituyen la autenticación ni autorización de Laravel/Sanctum. El acceso clínico real usa las cuentas y sesiones del backend. La migración local de datos demo separa cuentas antiguas asociadas a varios centros y guarda la nueva estructura en la versión 4, sin borrar las copias anteriores.

## Verificación de los nuevos flujos

- `npm run test`: validación de registro, aislamiento de centros, roles, creación de cuentas y fichas, borrador/publicación/lectura de informes y navegación de plataforma.
- `npm run build`: TypeScript y bundle de producción.
- `npm run lint`: análisis estático.
- Prueba manual: entrar por `/plataforma/acceso`, desplegar Editar centro y suscripción; abrir `/centro/clinica-horizonte/ingresar` como administrador y crear un tipo de informe; entrar como Camila, cargar y publicar un resultado; entrar como Paciente 1 y visualizar el documento. Abrir `/centro/centro-alameda/ingresar` para verificar que muestra otras cuentas y datos. Usar exclusivamente archivos ficticios.

La implementación de tutores, pagos, restricciones automáticas por suscripción y la integración de los módulos que aún usan datos demo quedan para etapas posteriores.

## Observaciones de la segunda evaluación: horarios y especialidades

En `/centro/{centerSlug}/administracion`, `AvailabilitySection` (en `src/pages/admin-page.tsx`) agrupa los bloques de `GET /api/v1/availabilities` por profesional. Cada profesional aparece una vez y despliega sus días, horas y estados. Hay carga, error con reintento y estado vacío. Crear y editar reutilizan `POST/PATCH /api/v1/availabilities`; el botón Desactivar/Reactivar usa `PATCH` con `is_active`. Laravel aún no ofrece eliminación física de horarios; se conserva el bloque para no perder su configuración. La API mantiene la numeración ISO de días 1–7 y el formulario convierte domingo a 7.

La misma administración incorpora `CenterSpecialtyCatalog`, una pantalla de selección del catálogo global. `/plataforma/especialidades` muestra la administración global solo a `SUPER_ADMIN` y figura en su menú. `src/services/specialty-catalog-api.ts` contiene **contratos propuestos**, separados de `src/services/http.ts`, que sigue representando la API existente de especialidades locales. Las rutas propuestas todavía no existen: ambas pantallas muestran un error controlado y permiten reintentar; no presentan datos demo ni confirman cambios sin respuesta del servidor. El centro ya no muestra formularios para crear o editar especialidades locales. Las prestaciones existentes siguen usando los IDs locales hasta que backend y BD migren sus referencias.

| Pantalla | Rol | API | Estado |
| --- | --- | --- | --- |
| Horarios agrupados, `/centro/{centerSlug}/administracion` | `ADMIN` | `GET/POST/PATCH /api/v1/availabilities` | Frontend integrado con API existente; desactivación sustituye a borrado físico |
| Catálogo global, `/plataforma/especialidades` | `SUPER_ADMIN` | `GET/POST/PATCH /api/v1/platform/specialties` | Interfaz preparada; backend pendiente |
| Selección del centro, `/centro/{centerSlug}/administracion` | `ADMIN` | `GET /api/v1/center/specialties`, `PUT /api/v1/center/specialties/{id}` | Interfaz preparada; backend y migración de IDs pendientes |

Los JSON, permisos y códigos de error están en `docs/api-contract.md`. La UI oculta acciones de otros roles, pero la autorización efectiva debe aplicarse en Laravel. Ningún control del navegador decide el centro: la asociación se resuelve desde la sesión autenticada. La verificación automática de esta entrega cubre componentes y servicios simulados; la integración real de especialidades requiere los endpoints propuestos y datos Core disponibles.

### Reserva del paciente y sesión vencida

`/centro/{centerSlug}/reservar` carga prestaciones desde `GET /api/v1/services`, profesionales desde `GET /api/v1/services/{id}/professionals` y horas desde `GET /api/v1/appointments/available-slots`. La pantalla ahora distingue la carga, el catálogo vacío y el error de catálogo con opción de reintentar. Si cualquier solicitud Laravel responde `401`, el estado de usuario y centro se limpia y la ruta clínica vuelve al acceso contextual; así no queda visible una sesión local antigua con selectores vacíos. Reingresar recupera el catálogo. La disponibilidad puede ser legítimamente vacía en días sin bloques activos; en el centro de prueba los bloques configurados están de lunes a viernes.

Verificación de esta rama: `npm run test -- --run` (44 pruebas), `npm run lint` (sin errores; una advertencia heredada en `button.tsx`) y `npm run build` (correcto; advertencia de tamaño del bundle). La API global de especialidades continúa pendiente y su ausencia se muestra como error controlado, sin datos ficticios.
