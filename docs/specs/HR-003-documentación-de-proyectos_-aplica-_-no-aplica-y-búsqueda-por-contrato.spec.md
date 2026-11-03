---
spec_id: HR-003
type: new-module
variant: crud
module: Documentación de proyectos: Aplica / No aplica y búsqueda por contrato
status: aprobado
author: Jorge Peña
reviewers: [Mariam]
branch: master
created: 2026-11-02
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/ui-kit-design-system.md
  - docs/ui-kit-table-structure.md
open_questions: 0
---

# HR-003 · Documentación de proyectos: Aplica / No aplica y búsqueda por contrato — Módulo nuevo (CRUD)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`

## 1. Propósito

Permitir marcar cada documento del checklist documental de un proyecto como "No aplica" para que el contador de documentos del listado de Proyectos (y el de cada categoría en el detalle) solo cuente los documentos que sí aplican, eliminando el ruido visual del semáforo causado por documentos que ese proyecto nunca va a tener. Además, permitir buscar proyectos por número de contrato desde el buscador del listado.

**Flujo de interacción**

En el detalle del proyecto, tabla "Documentación del Proyecto", un usuario admin o Proyectos ve en cada documento sin archivo el botón "No aplica" junto a "Subir". Al pulsarlo se envía una petición AJAX, sin recargar la página, y el documento queda marcado como No aplica: la fila se actualiza en el lugar (conservando la categoría expandida) con el badge gris "No aplica", el punto del semáforo en estilo neutro, sin el botón "Subir" y con "Marcar como aplica" para revertirlo; el contador de la categoría se actualiza en el mismo momento y se muestra un aviso en la tarjeta del checklist. El contador de la categoría pasa de {subidos}/{total} a {subidos}/{total que aplican}. En el listado de Proyectos, la columna Docs muestra un badge por categoría con ese mismo conteo; si todos los documentos de una categoría son No aplica, el badge de esa categoría no se muestra. En el buscador del listado el usuario puede escribir un número de contrato (completo o parcial) y ver los proyectos que tengan una obra con ese contrato.

## 2. Modelos

### ProjectDocument — tabla `project_documents`

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `not_applicable` | boolean | no | default false · true = el documento "No aplica" para ese proyecto · se agrega con migración nueva ALTER (no se edita 2026_06_15_220000_create_project_documents_table) · incluir en $fillable y en $casts como boolean |

## 3. Rutas

- **Prefijo:** `/proyectos/{project}/documentos/{docType}`
- **Nombres de ruta:** `proyectos.{project}.documentos.{docType}.*`
- **Middleware:** `role:admin|Proyectos|Solmat (grupo existente de proyectos); la acción exige admin o Proyectos en el controller, igual que los botones de subida en la vista`

## 4. Vistas

Carpeta: `resources/views/proyectos/{project}/documentos/{docType}` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `projects/show (checklist Documentación del Proyecto, fila de documento).blade.php` | Blade | Documento Aplica sin archivo: punto gris, "Sin archivo", botones "Subir" y "No aplica" (este último solo admin\|Proyectos, en un form PATCH enviado por AJAX (fetch) con not_applicable=1, sin confirmación). Documento Aplica con archivo: igual que hoy (punto verde, ver, "Reemplazar"); NO se muestra el botón "No aplica". Documento No aplica: punto neutro (borde gris, sin relleno) con title "{documento}: No aplica", badge gris "No aplica" en la columna Archivo, fecha "—", sin botón "Subir" y con botón "Marcar como aplica" (form PATCH con not_applicable=0). Los modales de subida se generan para todos los documentos (también los No aplica) para que "Subir" funcione tras marcar como Aplica sin recargar; el botón "Subir" solo aparece en documentos Aplica. La fila y el badge de categoría se renderizan con parciales (projects/_document_row y projects/_document_category_badge) que usan tanto la vista como la respuesta AJAX. |
| `projects/show (checklist, encabezado de categoría).blade.php` | Blade | El badge de la categoría muestra {subidos}/{aplican}. Es verde cuando subidos = aplican y aplican > 0; secundario en otro caso. Si aplican = 0 (todos No aplica) el badge no se muestra. El contador se obtiene del helper del modelo Project, no se recalcula en la vista. |
| `projects/index (barra de búsqueda).blade.php` | Blade | El input search existente conserva su comportamiento y cambia el placeholder a "Buscar por nombre, cliente o número de contrato…". Al buscar un número de contrato se listan los proyectos que tengan al menos una obra con ese contrato (coincidencia parcial). El botón de limpiar y la paginación con withQueryString no cambian. |
| `projects/index (columna Docs).blade.php` | Blade | Por cada categoría se muestra el badge con {subidos}/{aplican} y el color actual (verde completo, amarillo parcial, gris en 0). Si aplican = 0 el badge de la categoría no se renderiza. El tooltip indica "{Categoría}: {subidos}/{aplican} documentos" y, si hay alguno, " · {n} no aplican". Usa la relación documents ya cargada con ->with('documents'), sin consultas adicionales por proyecto. |

**Búsqueda / filtros:** En el listado de Proyectos, el buscador existente (parámetro search) también busca por número de contrato (project_works.contract_number) de las obras del proyecto, además de nombre y cliente

## 5. Reglas de negocio

- La condición No aplica se guarda por proyecto y documento en project_documents.not_applicable (boolean, default false). El catálogo de documentos no cambia.
- Solo un documento sin archivo (file_path nulo) puede marcarse como No aplica. Un documento con archivo no se puede marcar y el botón no se muestra.
- Un documento No aplica no admite subida de archivos: el backend rechaza uploadDocument, initChunkUpload y finalizeChunkUpload. Para subir, primero se debe usar "Marcar como aplica"; no se reactiva automáticamente.
- Los documentos No aplica no cuentan en el denominador ni en el numerador del conteo de su categoría: el conteo es {subidos}/{que aplican}, con subidos = documentos Aplica con file_path.
- Color del badge de categoría: verde si subidos = aplican (y aplican > 0), amarillo si subidos > 0 y menor a aplican, gris si subidos = 0. Con aplican = 0 el badge se oculta.
- El mismo cálculo alimenta el listado, el encabezado de categoría del detalle y Project::hasAllDocuments(); vive en un helper de Project (documentCategoryProgress) para no duplicar lógica en las vistas.
- No se captura motivo ni se registra quién lo marcó: la acción solo cambia el flag.
- Solo admin y Proyectos pueden marcar o revertir. La acción valida el rol en el controller (403) además del ocultamiento de botones en la vista.
- Búsqueda por contrato: el número de contrato vive en project_works.contract_number (no en projects). El filtro search del listado agrega, con OR dentro del mismo grupo que nombre y cliente, una condición orWhereHas('works', contract_number like %search%). Sin join para no duplicar proyectos ni romper la paginación ni el addSelect de project_value.
- La acción projects.document.applicability es AJAX: el controller responde JSON {message, not_applicable, category_key, row_html, badge_html} con la fila y el badge ya renderizados desde los parciales; el JS reemplaza la fila (conservando su estado expandido), actualiza el badge de la categoría y muestra un alert en la tarjeta, sin recargar. Errores: 422 con message (documento con archivo, validación) y 403/404; el JS los muestra como alert de error y rehabilita el botón. Se evitan doble clics mientras la petición está en curso.
- Mensajes (alert en la tarjeta del checklist, no flash de sesión): éxito "Documento marcado como No aplica." / "Documento marcado como Aplica."; error "No se puede marcar como No aplica un documento que ya tiene archivo." y "Este documento está marcado como No aplica. Márcalo como Aplica para subir un archivo."

## 6. Validaciones

| Campo | Regla |
|---|---|
| `not_applicable` | requerido, boolean |
| `search (listado)` | opcional, string; se aplica trim; coincidencia parcial (like) contra projects.name, projects.client_name y project_works.contract_number |
| `docType (ruta)` | debe existir en ProjectDocument::allDocTypes(), de lo contrario abort(404) |
| `estado del documento (not_applicable = 1)` | el registro no debe tener file_path; si lo tiene, 422 JSON con message y sin cambios |
| `estado del documento (subida)` | uploadDocument, initChunkUpload y finalizeChunkUpload rechazan si el registro existe con not_applicable = true |

## 7. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- El spec es de extensión sobre ProjectController y solo la acción nueva usa PATCH; el resto de rutas de documentos siguen en POST como hasta hoy.
- La acción projects.document.applicability responde JSON (AJAX) en lugar de redirect con flash, porque el checklist se actualiza sin recargar la página.

## 8. Preguntas abiertas

- [x] ¿Qué pasará con los elementos ya registrados en el sistema (proyectos, documentos ya subidos, contadores actuales)? → **Nada cambia. La columna nueva tiene default false, así que todos los documentos existentes quedan como Aplica y los contadores actuales siguen iguales; no hay backfill. Los proyectos solo cambian su conteo cuando un usuario marca un documento como No aplica. Por confirmar.**
- [x] ¿Qué pasa si se intenta marcar No aplica un documento que ya tiene archivo? → **No se permite: el botón no aparece y el backend lo rechaza. Decidido por el equipo.**
- [x] ¿Se pide motivo al marcar No aplica? → **No. Decidido por el equipo: solo cambia el flag, sin motivo ni auditoría.**
- [x] ¿Qué se muestra cuando una categoría completa es No aplica? → **El badge de esa categoría se oculta en el listado y en el detalle. Decidido por el equipo.**
- [x] ¿Qué pasa al subir un archivo a un documento No aplica? → **Se bloquea la subida hasta que se marque manualmente como Aplica. Decidido por el equipo.**
- [x] ¿Dónde está el número de contrato y qué significa buscarlo en el listado de proyectos? → **El número de contrato es un campo de cada obra (project_works.contract_number), no del proyecto. Un proyecto puede tener varias obras, por lo que el proyecto aparece si alguna de sus obras coincide. No se busca en convenios (no tienen número de contrato). Decidido por el equipo: basta con el número de contrato de las obras.**
- [x] ¿Quién puede marcar Aplica / No aplica? → **admin y Proyectos, igual que subir documentos. Decidido por el equipo.**

## 9. Fuera de alcance

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
- Historial o auditoría de quién y cuándo marcó No aplica.
- Marcar No aplica desde el listado de Proyectos (solo se hace en el detalle).
- Marcar categorías completas como No aplica en una sola acción.
- Cambios al catálogo de documentos (ProjectDocument::CATEGORIES).
- Búsqueda por contrato en otras vistas (obras, órdenes de compra) o filtros adicionales en el listado.
- Modificar archivos protegidos del perfil del proyecto

## 10. Orden de implementación

1. Migraciones (en orden de FKs)
2. Modelos: fillable, casts, relaciones
3. Controller(s) y rutas en routes/web.php
4. Vistas Blade y entrada en el menú de navegación

Detente al terminar cada capa y lista los archivos creados o modificados.

## 11. Criterios de aceptación

- [ ] `php artisan migrate` corre sin errores; las migraciones se crean en orden de dependencia de FKs.
- [ ] `ProjectDocument` declara `$fillable`, `$casts`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: En el listado de Proyectos, el buscador existente (parámetro search) también busca por número de contrato (project_works.contract_number) de las obras del proyecto, además de nombre y cliente.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.

---

_Generado con Estoria Spec Tool v2 · Estoria Studio_
