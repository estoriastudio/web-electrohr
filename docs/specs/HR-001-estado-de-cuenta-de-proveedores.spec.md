---
spec_id: HR-001
type: ext-action
module: Estado de cuenta de proveedores
target: Estado de cuenta de proveedores: exportación de PDFs de facturas y seguimiento de faltantes.
status: implementado
author: Jorge Peña
reviewers: [Mariam]
branch: master
created: 2026-10-30
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/ui-kit-design-system.md
  - docs/ui-kit-table-structure.md
open_questions: 0
---

# HR-001 · Estado de cuenta de proveedores — Nueva acción

> ⚠ Estado: **Borrador**. No implementar hasta que el estado sea "aprobado".

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`

## 1. Propósito

Descargar los PDFs de facturas del estado de cuenta en Facturas-SAHR2.0.zip, seleccionando su estatus financiero y reportando faltantes sin impedir la descarga de los disponibles.

**Flujo de interacción**

Desde Exportar Documentos, junto a Exportar Excel, seleccionar el estatus del estado de cuenta en un modal. Descargar ZIP muestra un loader y bloquea controles y cierre durante la solicitud. Se descargan los PDFs disponibles y después se abre un modal de resultado con resumen y faltantes. Ver resultado de exportación permite reabrirlo mientras se conserve la página cargada.

## 2. Módulo afectado

- **Módulo:** Estado de cuenta de proveedores: exportación de PDFs de facturas y seguimiento de faltantes.
- **Usa Livewire:** no

**Comportamiento actual**

El estado de cuenta ya dispone de exportación a Excel y de estatus financieros calculados por SupplierAccountStatementService.

**Archivos que se tocan**

- app/Http/Controllers/SupplierAccountStatementController.php
- routes/web.php
- resources/views/suppliers/account_statement.blade.php
- docs/controller-structure-and-notifications.md

**Debe permanecer igual**

- Exportación a Excel, filtros, paginación, totales y cálculos financieros existentes.
- Carga, descarga individual, estatus y vinculación de las facturas.
- Roles autorizados para consultar el estado de cuenta.
- Modelos, tablas, configuración de discos y archivos protegidos.
- Registros financieros y archivos originales; sin notificaciones de alta, cambio o eliminación por esta acción de solo lectura.

**Riesgos**

- Las filas de pagos sin factura no aportan PDFs; seleccionar sin_factura puede no producir documentos.
- Los PDFs pueden no tener ruta registrada o no existir en el disco. Deben aparecer en el reporte sin bloquear los disponibles.
- El reporte no es un historial permanente: su consulta consume los datos de sesión y su reapertura depende de conservar la página cargada.
- El folio de OC no debe interpretarse como una ruta: sustituir separadores y evitar los nombres . y ...

## 3. Acción

- **Controller:** `SupplierAccountStatementController`
- **Método:** `exportDocuments()`
- **Ruta:** `GET /proveedores/estado-cuenta/exportar-documentos` → `suppliers.account_statement.export_documents`
- **Respuesta:** descarga de archivo
- **Quién puede ejecutarla:** Usuarios autenticados con rol admin, Pagos u Orden de compra.
- **Disparador en la UI:** Botón Exportar Documentos junto a Exportar Excel; formulario modal con selección de estatus y botón Descargar ZIP.
- **Notificación:** no

**Entrada esperada**

status requerido, definido en SupplierAccountStatementService::STATUSES; export_id requerido, UUID.

## 4. Reglas de negocio

- Usar SupplierAccountStatementService::STATUSES y las etiquetas visibles del filtro principal, no el estatus de la OC.
- Seleccionar solamente facturas identificadas por las filas del estatus elegido, sin agregar las demás facturas de la misma OC.
- Exportar todos los registros del estatus elegido, independientemente de paginación y demás filtros de la página.
- Nombre exacto del ZIP en servidor y navegador: Facturas-SAHR2.0.zip.
- Carpetas nombradas solamente con el folio de OC, sin sufijo de ID. Sustituir / y barra invertida por guiones; usar ID si falta folio o este es . o ...
- Ruta de cada PDF dentro del ZIP: {folio_OC}/Factura-{id_factura}-{nombre_archivo}, utilizando el nombre base del archivo.
- Incluir exclusivamente PDFs de facturas, no XML, evidencias, notas de crédito ni SPEI.
- Reutilizar Storage::disk() y ZipArchive, sin nuevas dependencias, discos ni copias permanentes.
- Omitir y reportar facturas sin file_path y archivos inexistentes; incluir los PDFs disponibles.
- El reporte muestra OC, factura, motivo y enlace a invoices.show, insertando los datos como texto y no como HTML.
- El modal de resultado muestra cantidades de PDFs incluidos y faltantes, con scroll interno.
- Sin PDFs disponibles: HTTP 422 con reporte de faltantes, sin generar ZIP vacío. Sin facturas: HTTP 422 con mensaje.
- Los errores de creación, lectura, incorporación de documentos y cierre del ZIP no se presentan como una descarga exitosa.
- Guardar {included, missing} en sesión bajo statement_document_exports.{export_id} para descargas exitosas.
- GET /proveedores/estado-cuenta/exportar-documentos/{export}/faltantes, nombre suppliers.account_statement.export_documents.report, acción documentExportReport: UUID restringido en ruta, consulta de una sola vez en la misma sesión y HTTP 404 si no existe.
- Usar los mismos middleware auth y role:admin|Pagos|Orden de compra para descarga y reporte.
- Eliminar el archivo temporal después del envío o ante un fallo de preparación.

## 5. Validaciones

| Campo | Regla |
|---|---|
| `status` | required; Rule::in(array_keys(SupplierAccountStatementService::STATUSES)) |
| `export_id` | required; uuid |
| `export` | UUID restringido mediante whereUuid en la ruta del reporte. |

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Se utiliza el disco predeterminado para mantener consistencia con la carga de facturas, en vez de fijar un disco diferente.
- Errores y reportes se devuelven como JSON para el flujo asíncrono, sin redirect con flash.

## 7. Preguntas abiertas

_Ninguna._

## 8. Fuera de alcance

- Rutas API (routes/api.php)
- Unit / Feature tests
- Factories y seeders
- Form Requests
- Observers / Event listeners
- Jobs y colas
- Artisan commands
- Policies / Gates
- Clases Mail / Notification de Laravel
- API Resources / Transformers
- Instalar paquetes no listados en el perfil
- Cambios a vite.config.js / package.json
- Exportación de otros tipos de documento.
- Corregir, recuperar o volver a cargar automáticamente PDFs faltantes.
- Historial permanente de exportaciones o persistencia del reporte al recargar la página.
- Colas, jobs, nuevas dependencias, rutas API, migraciones y modificaciones a archivos protegidos.
- Nuevos tests permanentes, factories o seeders.
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Ruta
2. Método en el controller
3. Disparador en la vista

Detente al terminar cada capa y lista los archivos creados o modificados.

## 10. Criterios de aceptación

- [ ] Existe la ruta `GET /proveedores/estado-cuenta/exportar-documentos` con nombre `suppliers.account_statement.export_documents` dentro del grupo de rutas existente.
- [ ] `SupplierAccountStatementController::exportDocuments()` existe con return type `BinaryFileResponse/StreamedResponse`; los métodos existentes no cambian.
- [ ] La entrada se valida con `$request->validate()`: status requerido, definido en SupplierAccountStatementService::STATUSES; export_id requerido, UUID.
- [ ] Solo pueden ejecutarla: Usuarios autenticados con rol admin, Pagos u Orden de compra..
- [ ] La acción se dispara desde: Botón Exportar Documentos junto a Exportar Excel; formulario modal con selección de estatus y botón Descargar ZIP..
- [ ] Permanece igual: Exportación a Excel, filtros, paginación, totales y cálculos financieros existentes.
- [ ] Permanece igual: Carga, descarga individual, estatus y vinculación de las facturas.
- [ ] Permanece igual: Roles autorizados para consultar el estado de cuenta.
- [ ] Permanece igual: Modelos, tablas, configuración de discos y archivos protegidos.
- [ ] Permanece igual: Registros financieros y archivos originales; sin notificaciones de alta, cambio o eliminación por esta acción de solo lectura.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-001]` en el mensaje.

---

_Generado con Estoria Spec Tool v2 · Estoria Studio_
