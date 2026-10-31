# MedSync — frontend

**Integrantes del equipo:** seguir primero [`../README_EQUIPO.md`](../README_EQUIPO.md) para actualizar dependencias, bases y variables locales. Este README conserva secciones de diseño y evaluaciones anteriores; la guía de equipo describe la instalación vigente.

Frontend clínico y comercial construido con **React, TypeScript, Tailwind CSS** y componentes con la convención de **shadcn/ui**. Autenticación, pacientes, agenda, reservas, resultados, precios, reportes y suscripciones usan la API Laravel/PostgreSQL. Persisten utilidades demo heredadas para funciones no integradas; no sustituyen la autorización del backend.

Incluye flujo SaaS de marca blanca: cada centro tiene su propia dirección, identidad visual, cuentas y datos aislados mediante `organizationId`. Los pacientes y trabajadores no ven qué otras instituciones utilizan la plataforma.

## Puesta en marcha

Requiere Node.js 20.19 o superior.

```bash
npm install
npm run dev
```

Abre [http://localhost:3000](http://localhost:3000). La raíz es una landing pública; no redirige a ningún centro.

## Navegación pública y acceso contextual

El selector, directorio y portada públicos usan `GET /api/public/centers` como fuente de centros activos. `src/data/public-centers.ts` solo enriquece con textos editoriales a centros conocidos; no determina qué centro puede recibir un registro.

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

El formulario de `/centro/{slug}/crear-cuenta` envía ese `slug` como `center_slug` a Laravel y lo reutiliza para el login posterior. Si Core no publica el centro, no se ofrece en el selector. Los centros nuevos aprovisionados y activos aparecen en el directorio y pueden recibir registro sin editar un arreglo estático.

## Cuentas para el entorno local

El acceso web vigente autentica contra Laravel; las cuentas antiguas de `src/data/mock-data.ts` no sirven para iniciar sesión. Las cuentas de demostración se crean con los seeders del backend y se describen en [`../medsync_backend/README.md`](../medsync_backend/README.md). Cada integrante debe usar su propia base local y no compartir credenciales de `.env`.

## Arquitectura

```text
src/
  components/       Componentes compartidos y UI estilo shadcn
  data/             Dataset de demostración
  domain/           Tipos y permisos del negocio
  pages/            Pantallas organizadas por ruta
  services/         Cliente HTTP y contrato de la API Laravel
  state/            Estado de sesión y datos de interfaz
  styles/           Tema global de Tailwind
```

La interfaz concentra las solicitudes Laravel en `src/services/http.ts` y servicios específicos, con `credentials: include`, CSRF y errores de validación. El estado conserva algunas utilidades demo antiguas; los flujos clínicos integrados usan respuestas de Laravel.

## Conexión con Laravel

`VITE_API_URL` apunta al origen del backend; si se omite, se usa el mismo host del navegador en el puerto 8000. En desarrollo, usa `localhost` en ambos servicios o `127.0.0.1` en ambos para que Sanctum lea la cookie CSRF. Laravel aplica permisos Spatie con Teams por centro; ocultar un botón en React no concede ni reemplaza autorización.

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

Crear una ficha profesional no crea una cuenta. El ADMIN habilita su acceso con el correo de la ficha; la persona nueva recibe una invitación para establecer su contraseña. Las cuentas de pacientes nacen desde el registro contextual del centro. No se permite quitar el último administrador del centro sin asignar otro.

## Registro e informes del paciente

Cada centro publica `/centro/{slug}/ingresar` y `/centro/{slug}/crear-cuenta`. La dirección determina el contexto clínico después de que la persona lo selecciona explícitamente desde `/ingresar` o `/crear-cuenta`; dentro del portal no existe cambio ni vinculación libre de clínicas. El registro solicita nombres, apellidos, RUT (módulo 11), nacimiento, teléfono chileno, correo, previsión, seguro complementario opcional y contraseña confirmada.

La cuenta de usuario es global en Core y puede tener membresías en distintos centros. Cada centro conserva su propia ficha y datos clínicos; un acceso a otro centro no copia pacientes, citas ni informes. Las invitaciones de personal usan SMTP configurado en el backend.

Cuando recepción creó previamente la ficha de un paciente, el paciente puede registrarse usando exactamente el mismo RUT y correo. El sistema vincula la nueva cuenta con esa ficha sin crear un paciente duplicado. Si solo coincide uno de los dos datos, debe corregirlo con recepción.

El módulo Pacientes de recepción carga listado y detalle desde `GET /api/v1/patients` y `GET /api/v1/patients/{id}`. Alta y edición usan `POST` y `PATCH`; el perfil del paciente consulta y edita solo su propia ficha. Estas operaciones persisten en PostgreSQL del centro resuelto por Laravel y no guardan fichas en `localStorage`. El correo de una ficha vinculada a una cuenta queda de solo lectura mientras no exista un flujo coordinado para cambiar también el correo de acceso en Core. Reservas mantiene la selección de pacientes activos mediante el mismo listado.

Los pacientes pueden cancelar o reprogramar una cita hasta 24 horas antes de su inicio. Recepción conserva la capacidad de gestionar la agenda. Una cita solo puede marcarse como atendida desde su hora de inicio y como inasistencia después de su hora de término.

`/resultados` muestra documentos publicados al paciente. El administrador define los tipos; el profesional responsable carga, publica y puede anular sus informes. Los documentos se almacenan de forma privada y se descargan solo con autorización. La API acepta PDF, PNG, JPEG y TXT de hasta 5 MB por archivo; no hay agendamiento de exámenes.

Los módulos demo heredados conservan derivaciones PBKDF2 locales, que no sustituyen la autenticación ni autorización de Laravel/Sanctum. El acceso clínico real usa las cuentas y sesiones del backend. La migración local de datos demo separa cuentas antiguas asociadas a varios centros y guarda la nueva estructura en la versión 4, sin borrar las copias anteriores.

## Verificación de los nuevos flujos

- `npm test`: pruebas de registro, aislamiento, pacientes, estado de sesión y navegación. Las pruebas de permisos y documentos clínicos también se ejecutan en Laravel.
- `npm run build`: TypeScript y bundle de producción.
- `npm run lint`: análisis estático.
- Prueba manual: entrar por `/plataforma/acceso` para revisar planes y suscripciones; luego usar las cuentas Laravel del centro para recorrer administración, agenda, reserva, pacientes, resultados y reportes. Para publicar un documento se requiere una cuenta profesional habilitada y una cita `ATENDIDA` asignada a ella. Usar exclusivamente archivos ficticios.

Los tutores, el cobro bancario integrado y las restricciones automáticas por estado de suscripción no forman parte de la implementación actual. Los pagos pueden registrarse manualmente en Core.

## Observaciones de la segunda evaluación: horarios y especialidades

En `/centro/{centerSlug}/administracion`, `AvailabilitySection` (en `src/pages/admin-page.tsx`) agrupa los bloques de `GET /api/v1/availabilities` por profesional. Cada profesional aparece una vez y despliega sus días, horas y estados. Hay carga, error con reintento y estado vacío. Crear y editar reutilizan `POST/PATCH /api/v1/availabilities`; el botón Desactivar/Reactivar usa `PATCH` con `is_active`. Laravel aún no ofrece eliminación física de horarios; se conserva el bloque para no perder su configuración. La API mantiene la numeración ISO de días 1–7 y el formulario convierte domingo a 7.

La misma administración incorpora `CenterSpecialtyCatalog`, una pantalla de selección del catálogo global. `/plataforma/especialidades` permite a `SUPER_ADMIN` administrar el catálogo; el ADMIN selecciona las especialidades de su centro. `src/services/specialty-catalog-api.ts` consulta las rutas Laravel vigentes. La migración de IDs globales debe constar como aplicada en la base de cada integrante antes de usar prestaciones y reservas.

| Pantalla | Rol | API | Estado |
| --- | --- | --- | --- |
| Horarios agrupados, `/centro/{centerSlug}/administracion` | `ADMIN` | `GET/POST/PATCH /api/v1/availabilities` | Frontend integrado con API existente; desactivación sustituye a borrado físico |
| Catálogo global, `/plataforma/especialidades` | `SUPER_ADMIN` | `GET/POST/PATCH /api/v1/platform/specialties` | Integrado |
| Selección del centro, `/centro/{centerSlug}/administracion` | `ADMIN` | `GET /api/v1/center/specialties`, `PUT /api/v1/center/specialties/{id}` | Integrado; requiere migración local aplicada |

Los JSON, permisos y códigos de error están en `docs/api-contract.md`. La UI oculta acciones de otros roles, pero Laravel aplica la autorización efectiva. Ningún control del navegador decide el centro: la asociación se resuelve desde la sesión autenticada.

### Reserva del paciente y sesión vencida

`/centro/{centerSlug}/reservar` carga prestaciones desde `GET /api/v1/services`, profesionales desde `GET /api/v1/services/{id}/professionals` y horas desde `GET /api/v1/appointments/available-slots`. La pantalla ahora distingue la carga, el catálogo vacío y el error de catálogo con opción de reintentar. Si cualquier solicitud Laravel responde `401`, el estado de usuario y centro se limpia y la ruta clínica vuelve al acceso contextual; así no queda visible una sesión local antigua con selectores vacíos. Reingresar recupera el catálogo. La disponibilidad puede ser legítimamente vacía en días sin bloques activos; en el centro de prueba los bloques configurados están de lunes a viernes.

Verificación del estado integrado al 31-10-2026: 48 pruebas frontend y 54 pruebas Laravel pasaron; `npm run lint` no tuvo errores (una advertencia heredada en `button.tsx`) y `npm run build` terminó correctamente. Cada integrante debe repetir las pruebas después de actualizar su entorno.

## Centros reales y cuenta existente en otro centro

La pantalla `/plataforma` consulta Core para listar, crear, editar, habilitar y deshabilitar centros; muestra si la base clínica está lista y permite reintentar un fallo. Los datos de plan y suscripción son estados guardados en Core; no hay cobro automático. Los centros activos aparecen en portada y directorio a través de la API pública.

Si el login en un centro reconoce una cuenta global sin membresía allí, la ruta `/centro/{slug}/crear-ficha` muestra el correo autenticado y solicita los datos de una ficha local con consentimiento. Cancelar cierra la sesión sin crear registros. La ficha usa la misma cuenta global; los datos clínicos de otros centros no se muestran ni copian. Si un RUT o correo ya existe en ese centro, se requiere verificación por recepción.

### Administración de accesos

`/plataforma` asigna ADMIN al centro seleccionado; la administración del centro asigna recepción. La interfaz consulta y escribe membresías en Laravel, muestra invitaciones pendientes y permite desactivar, reactivar y reenviar. No solicita contraseña inicial: el usuario nuevo la crea desde el correo. Una cuenta existente mantiene su contraseña.

### Permisos visibles y cambio de rol

El ADMIN ve los permisos fijos que responde `GET /api/v1/center/permissions`; no se presentan como asignaciones editables. La plataforma lista al personal de cada centro y puede cambiar ADMIN/RECEPCIONISTA sobre la misma membresía. El frontend mantiene reglas locales para pantallas demo aún no integradas; esas reglas no conceden autorización en Laravel. El backend comprueba rol, propiedad y centro activo en cada petición.

## Pasos 5 y 6 · 31-10-2026
Resultados, precios y reportes (pasos 5 y 6): ver `README_RESULTADOS_PRECIOS_REPORTES.md` en la raíz MedSync. API de archivos privados, permisos por centro, vigencia de tarifas e indicadores estimados; aplicar la migración Core de permisos y respaldar almacenamiento local privado.

## Paso 7 y revisión final · 31-10-2026
Planes, suscripciones y pagos manuales se guardan en Core; plataforma y centro leen el mismo estado. No hay cobro integrado ni afirmación automática de «pago al día». La revisión integral y sus pendientes están en `README_SUSCRIPCIONES_Y_REVISION_FINAL.md` de la raíz MedSync.

El historial de `/centro/{centerSlug}/citas/{id}` consulta `GET /api/v1/appointments/{id}/history` y muestra eventos persistidos con estados de carga y error. Laravel comprueba la propiedad de la cita y no entrega motivos internos al paciente.
