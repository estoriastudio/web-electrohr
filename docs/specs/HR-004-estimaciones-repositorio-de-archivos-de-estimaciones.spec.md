---
spec_id: HR-004
type: ext-subresource
title: Repositorio de archivos de estimaciones
module: Estimaciones
module_key: estimaciones
status: aprobado
author: Jorge Peña
reviewers: [Mariam]
branch: master
created: 2026-11-03
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/ui-kit-design-system.md
  - docs/ui-kit-table-structure.md
open_questions: 0
---

# HR-004 · Estimaciones — Repositorio de archivos de estimaciones

**Módulo:** Estimaciones (`estimaciones`) · **Tipo:** C4 · Sub-recurso

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`

## 1. Propósito

Convertir los archivos de las estimaciones de un proyecto en un repositorio: en lugar de un espacio fijo por documento (un solo archivo por tipo), una estimación puede tener varios archivos de cada tipo (varias facturas, varias notas de crédito, etc.), cargándolos en lote y clasificándolos solo por tipo.

**Flujo de interacción**

En el detalle del proyecto, bloque Estimaciones, el usuario (admin, Proyectos o quien hoy gestiona archivos, no Engineer) abre "Gestionar archivos" de una estimación. El modal muestra el repositorio de la estimación: los archivos ya cargados agrupados por tipo (nombre original, fecha, quién lo cargó, descargar y eliminar) y un formulario de carga con un selector de tipo y un campo de archivos con selección múltiple. Elige el tipo, selecciona uno o varios archivos y guarda; todos quedan clasificados con ese tipo y el modal se recarga con la lista actualizada. Para cargar otro tipo repite el paso. En la tabla de estimaciones, la columna de archivos muestra un ícono por tipo con el número de archivos; su menú lista los archivos del tipo para descargar o eliminar y la opción "Cargar" abre el modal con ese tipo preseleccionado.

## 2. Módulo afectado

- **Módulo:** Estimaciones
- **Usa Livewire:** no

**Comportamiento actual**

Hoy project_estimates tiene 6 columnas de ruta (invoice_pdf_path, invoice_xml_path, credit_note_pdf_path, credit_note_xml_path, spei_receipt_path, liquidation_slip_path), por lo que cada estimación admite un solo archivo por tipo. El modal "Archivos de estimación" (estimates/_documents_modal.blade.php) tiene un campo de archivo por columna; cargar de nuevo reemplaza y borra el anterior de S3 (ProjectEstimateController::updateDocuments/storeDocuments con DOCUMENTS). La tabla de estimaciones muestra 6 íconos fijos con descarga y eliminación por columna. El formulario de editar estimación (estimates/_form.blade.php) tiene el checkbox "Adjuntar boleta de liquidación" que guarda liquidation_slip_path. Eliminar una estimación borra de S3 las rutas de las 6 columnas. Rutas: projects.estimates.documents.update (PUT), .destroy (DELETE por tipo) y .download (GET por tipo).

**Archivos que se tocan**

- database/migrations/2026_11_03_000000_create_project_estimate_files_table.php (nueva, vía make:model -mc: crea project_estimate_files y project_estimate_invoices, copia las rutas de las columnas viejas (PDF y XML de factura como una sola factura; el resto como archivos) y elimina esas 6 columnas de project_estimates; down recrea las columnas y restaura una ruta por tipo)
- app/Models/ProjectEstimateInvoice.php (nuevo: factura = PDF + XML con folio, fecha y fecha de pago)
- app/Http/Controllers/ProjectEstimateInvoiceController.php (nuevo: store con lectura del UUID del XML, download por formato y destroy)
- routes/web.php (projects.estimates.invoices.store, .download y .destroy)
- app/Models/ProjectEstimateFile.php (nuevo: TYPES con etiqueta, ícono y extensiones; relaciones)
- app/Models/ProjectEstimate.php (relación files; se quitan las 6 rutas de $fillable)
- app/Http/Controllers/ProjectEstimateFileController.php (nuevo: store, download, destroy)
- app/Http/Controllers/ProjectEstimateController.php (se eliminan DOCUMENTS, updateDocuments, destroyDocument, download, storeDocuments, documentValidationRules y el manejo de liquidation_slip en validatedData; destroy borra de S3 los archivos del repositorio; store deja de aceptar archivos)
- app/Http/Controllers/ProjectController.php (with('estimates.files') en show; solo la carga de la relación)
- routes/web.php (projects.estimates.files.store, .download, .destroy; se retiran projects.estimates.documents.*)
- resources/views/estimates/_documents_modal.blade.php (modal de repositorio)
- resources/views/projects/_estimates.blade.php (columna de archivos, preselección de tipo, se quita el JS de la boleta)
- resources/views/estimates/_form.blade.php (se quita el bloque de la boleta de liquidación)

**Debe permanecer igual**

- El flujo de crear y editar una estimación (montos, deducciones, retenciones, fechas, tipo, estatus, notas) y el cálculo de importes del modelo.
- El acceso del rol Engineer (Mis proyectos): sigue sin ver ni gestionar archivos de estimaciones.
- El disco s3 y la carpeta projects/{project_id}/estimates/{estimate_id} para los archivos nuevos; los archivos ya almacenados no se mueven.
- Las notificaciones de ProjectEstimate al cargar y eliminar archivos y al eliminar la estimación.
- Los documentos del checklist del proyecto (HR-003), convenios y los archivos de pagos (payments.spei_receipt_path), que son independientes de estas columnas.

**Riesgos**

- La migración elimina columnas con datos: debe copiar primero y dejar down que restaure al menos un archivo por tipo; conviene probarla en una copia de la base antes de correrla en producción.
- Antes de quitar las columnas hay que confirmar con grep que ningún otro código lee invoice_*_path, credit_note_*_path, spei_receipt_path o liquidation_slip_path de project_estimates (hoy solo ProjectEstimateController, ProjectEstimate y las vistas de estimaciones; payments.spei_receipt_path es otra tabla).
- Si falla el guardado de uno de varios archivos en S3, no deben quedar archivos huérfanos ni filas a medias: se guarda dentro de una transacción y, ante error, se borran de S3 los ya subidos (mismo patrón que deleteStoredPaths).
- post_max_size y upload_max_filesize del servidor limitan el lote: un lote grande puede exceder el límite y llegar vacío; el controller debe responder con un error claro en lugar de fallar en silencio.
- Eliminar la estimación debe borrar de S3 todos los archivos del repositorio antes de perder las filas (las filas se eliminan en cascada).

## 3. Sub-recurso

- **Modelo padre:** `ProjectEstimate`
- **Rutas:** anidadas shallow
- **Ubicación:** sección/tabla en show del padre
- **Render:** Blade
- **Operaciones:** create, delete, show

### ProjectEstimateFile — tabla `project_estimate_files`

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `project_estimate_id` | foreignId | no | FK → project_estimates.id, cascade on delete |
| `file_type` | string | no | credit_note_pdf, credit_note_xml, spei_receipt, liquidation_slip, other · catálogo en constantes del modelo (TYPES con etiqueta, ícono y extensiones permitidas; Factura vive en ProjectEstimateInvoice) · indexado junto con project_estimate_id |
| `file_path` | string | no | ruta en el disco s3 |
| `original_name` | string | no | nombre original del archivo, usado como nombre de descarga y para mostrarlo en el repositorio |
| `uploaded_by` | foreignId | sí | FK → users.id, null on delete (null en archivos migrados) |

Relaciones:
- `belongsTo` → ProjectEstimate (`project_estimate_id`)
- `belongsTo` → User (`uploaded_by`)

## 4. Reglas de negocio

- Los archivos de una estimación son un repositorio: la relación estimación → archivos es 1:N y cada archivo se clasifica con un solo tipo del catálogo ProjectEstimateFile::TYPES.
- Tipos de archivo (ProjectEstimateFile::TYPES): credit_note_pdf (Nota de crédito PDF), credit_note_xml (Nota de crédito XML), spei_receipt (Comprobante SPEI), liquidation_slip (Boleta de liquidación) y other (Otro). "Factura" es un tipo aparte que une PDF + XML en una sola factura con datos propios (ver reglas de Factura) y se guarda en ProjectEstimateInvoice.
- Formatos permitidos por tipo: credit_note_pdf solo pdf; credit_note_xml solo xml; spei_receipt y liquidation_slip pdf, jpg, jpeg y png; other pdf, xml, jpg, jpeg y png. Máximo 10 MB por archivo, sin límite de cantidad.
- Factura: cada factura exige su PDF y su XML (CFDI timbrado), Folio, Fecha y Fecha de pago, todos obligatorios. El Folio es el folio fiscal (UUID) del nodo TimbreFiscalDigital del XML, como en "Subir factura" de la orden de compra: se autocompleta al elegir el XML, no se captura a mano y el servidor lo vuelve a leer del XML (si el XML no tiene UUID válido o difiere del enviado, se rechaza). Fecha es la fecha de emisión (se precarga del atributo Fecha del Comprobante y es editable) y Fecha de pago es la fecha esperada de pago (captura manual).
- Factura: se pueden cargar varias facturas en una misma operación con "Agregar otra factura" (un bloque PDF + XML + fechas por factura). Si cualquiera falla, no se guarda ninguna. No se permite repetir un folio dentro de la misma estimación.
- Factura: eliminar una factura borra su fila y sus dos archivos de S3; el menú del ícono de la tabla muestra folio y enlaces PDF y XML. El modal de archivos no lista lo ya cargado: la consulta, descarga y eliminación se hacen desde los menús de los íconos de la tabla, que se mueven al body con posición fija al abrirse (patrón de la tabla de SOLMAT) para exceder el borde de la tabla sin recortarse.
- Cargar siempre agrega. No existe reemplazo: para cambiar un archivo se elimina y se carga el nuevo.
- Una carga lleva un solo tipo y uno o varios archivos; si cualquier archivo falla la validación, no se guarda ninguno (todo o nada).
- El archivo se guarda en S3 con un nombre único (uuid) y su nombre original se conserva en original_name para mostrarlo y descargarlo.
- Eliminar un archivo borra su fila y su objeto en S3 tras confirmar en el navegador; eliminar la estimación borra todos sus archivos de S3.
- La boleta de liquidación deja de ser un adjunto del formulario de edición y pasa a ser un tipo más del repositorio.
- El ícono de cada tipo en la tabla indica cuántos archivos hay (verde con contador) y el menú permite descargar o eliminar cada uno.
- Solo los roles que hoy gestionan archivos de estimaciones pueden cargar, descargar y eliminar; el controller lo valida (403), no solo se ocultan botones.
- Notificación ProjectEstimate al cargar (con la cantidad de archivos) y al eliminar un archivo, con el mismo formato que las actuales.

## 5. Validaciones

| Campo | Regla |
|---|---|
| `file_type` | requerido, in: credit_note_pdf, credit_note_xml, spei_receipt, liquidation_slip, other (Factura usa su propia acción projects.estimates.invoices.store) |
| `invoices.*.pdf_file` | requerido, file, mimes:pdf, máx 10240 KB |
| `invoices.*.xml_file` | requerido, file, extensión .xml, máx 10240 KB; debe contener un UUID válido en TimbreFiscalDigital |
| `invoices.*.folio` | opcional en el envío (readonly, autocompletado); el servidor usa el UUID del XML y rechaza si el enviado difiere; único dentro de la estimación y del lote |
| `invoices.*.invoice_date / payment_date` | requeridos, date |
| `files` | requerido, array, mínimo 1 |
| `files.*` | file, máx 10240 KB, mimes según file_type (ver regla de formatos por tipo) |
| `estimate / estimateFile (ruta)` | el archivo de las rutas download y destroy debe pertenecer a la estimación de la ruta; de lo contrario 404 |

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Las rutas del repositorio cuelgan de la estimación sin anidarse bajo el proyecto (projects.estimates.files.*), igual que las rutas actuales de documentos de estimaciones.

## 7. Preguntas abiertas

- [x] ¿Qué pasará con los elementos ya registrados en el sistema (archivos ya cargados en estimaciones)? → **Decidido por el equipo: se migran a la nueva tabla project_estimate_files, uno por cada ruta existente, en su tipo equivalente, y las 6 columnas viejas se eliminan en la misma migración. Los archivos en S3 no se mueven.**
- [x] ¿Qué tipos tiene el repositorio? → **Los actuales sin Factura PDF/XML (Nota de crédito PDF, Nota de crédito XML, Comprobante SPEI, Boleta de liquidación) más "Otro"; "Factura" pasa a ser un tipo unificado que exige PDF + XML y captura Folio, Fecha y Fecha de pago (decidido por el equipo). Las notas de crédito conservan PDF y XML por separado; por confirmar si también deben unificarse.**
- [x] ¿Cómo se capturan los datos de la factura? → **Decidido por el equipo: Folio, Fecha y Fecha de pago, todos obligatorios. Fecha = fecha de emisión de la factura; Fecha de pago = fecha esperada de pago. El Folio se obtiene del XML (folio fiscal UUID), como en el modal "Subir factura" de la orden de compra. Una fila por factura (PDF + XML) y se pueden agregar varias en la misma carga.**
- [x] ¿Qué pasa con las facturas ya cargadas (PDF y XML de las columnas viejas)? → **Se migran como una sola factura por estimación (PDF + XML) con folio = invoice_number y fecha = invoice_date de la estimación; la fecha de pago queda vacía (null) hasta que se edite. Por confirmar.**
- [x] ¿Qué formatos y límites se permiten? → **PDF, XML, JPG y PNG, máx. 10 MB por archivo, sin límite de cantidad. Se asumió que los tipos "PDF" aceptan solo pdf y los "XML" solo xml, y que SPEI, Boleta y Otro aceptan imágenes; por confirmar.**
- [x] ¿Qué pasa con el checkbox "Adjuntar boleta de liquidación" del formulario de edición? → **Decidido por el equipo: se quita del formulario; la boleta se carga solo desde el repositorio.**
- [x] ¿Cómo es la carga? → **Decidido por el equipo: modal con selector de tipo y selección múltiple de archivos (varios del mismo tipo a la vez), con recarga de página al terminar (sin AJAX).**

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
- Carga por chunks o barra de progreso para archivos grandes.
- Vista previa de PDF o XML dentro del sistema.
- Descarga de todos los archivos de una estimación en ZIP.
- Subcategorías, etiquetas o notas por archivo.
- Archivos de estimaciones para el rol Engineer (Mis proyectos).
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Migración del hijo
2. Modelo hijo y relación en el padre
3. Controller y rutas anidadas
4. Integración en la vista del padre

Detente al terminar cada capa y lista los archivos creados o modificados.

## 10. Criterios de aceptación

- [ ] La migración de `project_estimate_files` tiene `foreignId` hacia `project_estimate` con `constrained()`.
- [ ] `ProjectEstimate` declara `hasMany(ProjectEstimateFile)` y `ProjectEstimateFile` declara `belongsTo(ProjectEstimate)`.
- [ ] Rutas anidadas shallow: index/store bajo el padre; show/edit/update/destroy por ID del hijo.
- [ ] El hijo se muestra como sección/tabla dentro de show del padre.
- [ ] Operaciones disponibles sobre el hijo: create, delete, show.
- [ ] Permanece igual: El flujo de crear y editar una estimación (montos, deducciones, retenciones, fechas, tipo, estatus, notas) y el cálculo de importes del modelo.
- [ ] Permanece igual: El acceso del rol Engineer (Mis proyectos): sigue sin ver ni gestionar archivos de estimaciones.
- [ ] Permanece igual: El disco s3 y la carpeta projects/{project_id}/estimates/{estimate_id} para los archivos nuevos; los archivos ya almacenados no se mueven.
- [ ] Permanece igual: Las notificaciones de ProjectEstimate al cargar y eliminar archivos y al eliminar la estimación.
- [ ] Permanece igual: Los documentos del checklist del proyecto (HR-003), convenios y los archivos de pagos (payments.spei_receipt_path), que son independientes de estas columnas.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-004]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
