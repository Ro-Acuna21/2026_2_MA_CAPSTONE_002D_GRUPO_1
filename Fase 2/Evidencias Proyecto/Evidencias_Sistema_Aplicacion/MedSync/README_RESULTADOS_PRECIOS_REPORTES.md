# Resultados médicos, precios y reportes (pasos 5 y 6)

## Resultados médicos

Las rutas `/api/v1/result-types` permiten a ADMIN crear, listar y activar/desactivar tipos documentales. ADMIN solo ve tipos, nunca resultados ni archivos. PROFESIONAL ve tipos activos y sus propios resultados. PACIENTE ve exclusivamente sus resultados publicados. RECEPCIONISTA y SUPER_ADMIN no acceden al contenido clínico.

`POST /api/v1/results` exige una cita `ATENDIDA` asignada al profesional autenticado, tipo activo, fecha de realización no futura y un archivo PDF, PNG, JPEG o TXT de hasta 5 MB. El paciente se deriva de la cita; el cliente no decide `patient_id` ni `professional_id`. El archivo se guarda en el disco Laravel `local` bajo `medical-results/{center_id}/` con nombre UUID, fuera del disco público. La respuesta omite `storage_path` y solo incluye metadatos del archivo. No se debe ejecutar `storage:link` para estos documentos.

`POST /api/v1/results/{id}/publish` solo admite un borrador con archivo y al profesional responsable. `POST /api/v1/results/{id}/void` requiere motivo y mantiene la evidencia privada; el paciente deja de ver el resultado anulado. `GET /api/v1/results/{id}/files/{file_id}` vuelve a verificar centro, propiedad, estado y vínculo del archivo: el profesional responsable puede descargar su resultado; el paciente dueño solo si está publicado. Las transiciones no borran físicamente archivos. La política de retención y respaldo debe definirse antes de producción con datos reales.

Recorrido de prueba: marcar una cita ficticia como atendida, entrar como su profesional, cargar un TXT ficticio, comprobar que el paciente todavía no lo ve, publicar, comprobar que solo el paciente de esa cita lo ve y descarga, anular con motivo y comprobar que desaparece de su portal. El test `ResultsPricesReportsTest` automatiza este flujo.

## Precios e indicadores

`service_prices` almacena importes en CLP y períodos inclusivos por prestación. ADMIN usa `GET/POST/PATCH/DELETE /api/v1/service-prices`; la API rechaza períodos superpuestos. Se puede modificar o eliminar un precio futuro; un precio abierto vigente solo puede cerrarse con `effective_to` desde hoy. Un precio histórico cerrado queda inmutable. Para cambiar una tarifa vigente, cerrar la anterior en su último día y crear la siguiente desde el día posterior.

`GET /api/v1/reports/summary?from=YYYY-MM-DD&to=YYYY-MM-DD[&service_id=...][&professional_id=...]` requiere ADMIN y un rango de hasta 366 días. Consulta exclusivamente la conexión `center` resuelta por la sesión. Cuenta citas por estado, mes, prestación y profesional. `estimated_revenue_clp` suma precios históricos vigentes en la fecha de citas `ATENDIDA`; `lost_estimate_clp` suma precios de citas `CANCELADA` y `NO_SHOW`; `average_ticket_clp` divide ingreso estimado por atenciones realizadas. Una cita sin tarifa vigente suma cero y se cuenta en `unpriced_appointments`. Son estimaciones basadas en agenda, no pagos ni facturación. El período y filtros se aplican a todos los indicadores y gráficos; no se retornan identificadores de pacientes.

El test de aislamiento crea dos bases clínicas: los precios y citas de la primera no aparecen en la segunda. Antes de un despliegue, respaldar Core y cada base Center; ejecutar migraciones Laravel (incluida `2026_10_31_000003_sync_results_prices_permissions.php` en Core) sobre el conjunto de bases aplicable y verificar permisos de membresías existentes. El almacenamiento privado debe incluirse en el respaldo.
