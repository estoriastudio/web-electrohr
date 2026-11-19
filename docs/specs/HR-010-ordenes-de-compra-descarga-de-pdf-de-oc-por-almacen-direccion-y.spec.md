---
spec_id: HR-010
type: ext-rule
title: Descarga de PDF de OC por Almacén, Dirección y Pagos
module: Ordenes de Compra
module_key: ordenes_de_compra
status: implementado
implemented: 2026-11-19
author: Jorge Peña
reviewers: []
branch: master
created: 2026-11-19
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/purchase-order-payment-health-flow.md
  - docs/supplier-account-statement.md
open_questions: 0
---

# HR-010 · Ordenes de Compra — Descarga de PDF de OC por Almacén, Dirección y Pagos

**Módulo:** Ordenes de Compra (`ordenes_de_compra`) · **Tipo:** C3 · Cambio de regla

> ✓ Estado: **Implementado** (2026-11-19). Este spec describe código que ya existe: no lo vuelvas a implementar. Los cambios nuevos van en un spec de extensión (C1–C4).

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/purchase-order-payment-health-flow.md`
- `docs/supplier-account-statement.md`

## 1. Propósito

Permitir que los roles Almacén, Dirección y Pagos generen y descarguen el PDF de todas las órdenes de compra emitidas o autorizadas, sin importar quién sea el comprador asignado, mediante los controles y rutas actuales. Compras conserva el PDF solo de sus OC y el resto de las acciones sobre la OC no cambia.

**Flujo de interacción**

El usuario con rol Almacén, Dirección o Pagos (solo o combinado con otros roles) abre una OC emitida o autorizada, desde el listado o desde el detalle. Ve la opción "Descargar PDF" en el listado y el botón "Generar PDF" en el detalle, y obtiene el PDF con o sin anexos igual que Compras. No ve Editar, Cancelar, cierre ni los demás controles reservados al comprador de la OC; Dirección conserva además sus controles de autorización.

## 2. Módulo afectado

- **Módulo:** Ordenes de Compra
- **Usa Livewire:** no

**Comportamiento actual**

En la matriz (database/data/access_matrix.php, vista purchase_orders) las celdas ALM y PAG solo tienen view, y DIR tiene view y authorize; download lo tiene Compras con alcance own_buyer y admin. La ruta purchase_orders.pdf y las rutas de anexos exigen permission:purchase_orders.download, y PurchaseOrderController (downloadPdf, saveAnnex, downloadPdfWithAnnexes) autoriza con authorizeAccess($purchaseOrder, 'purchase_orders', 'download'). Las vistas (purchase_orders/show.blade.php y purchase_orders/utilities/_table.blade.php) muestran el PDF con hasFullAccessScope('purchase_orders', 'download') o con purchase_orders.download y comprador igual al usuario. Un usuario Almacén, Dirección o Pagos no tiene el permiso: no ve el botón y recibe 403 por URL directa. Un usuario Orden de compra + Almacén solo lo ve en las OC donde es el comprador. docs/roles-permissions-matrix.md indica que Pagos, Almacén y Dirección no descargan el PDF (decisión 2026-11-10).

**Archivos que se tocan**

- database/data/access_matrix.php: en purchase_orders, cambiar actions de las celdas ALM y PAG a ['view', 'download'] y la de DIR a ['view', 'authorize', 'download'], sin agregar scope a ninguna. Actualizar raw a 'V D' (ALM y PAG) y 'V D (A)' (DIR).
- routes/web.php: actualizar únicamente el comentario del grupo "PDF con anexos" que dice «solo Compras (sus OC) y admin»; conservar rutas y middleware.
- resources/views/purchase_orders/utilities/_table.blade.php: actualizar únicamente el comentario del PDF que dice «solo Compras (sus OC) y admin»; conservar la condición.
- docs/roles-permissions-matrix.md: reemplazar la regla «PDF de OC» y ajustar la descripción de purchase_orders.*, que hoy excluye a Almacén, Dirección y Pagos del PDF.

**Debe permanecer igual**

- Las condiciones Blade del botón "Generar PDF", del modal de anexos y de "Descargar PDF" del listado: hasFullAccessScope('purchase_orders', 'download') ya resulta verdadero con las nuevas celdas, por lo que no requieren cambio.
- PurchaseOrderController, sus validaciones, el contenido y el nombre de los PDF, y la condición de estatus emitida o autorizada.
- Todas las rutas, verbos, nombres y middleware; siguen exigiendo purchase_orders.download.
- El permiso de Compras: solo descarga el PDF de sus OC (own_buyer) y conserva todas sus acciones; los alcances de update, cancel y demás acciones de la OC siguen siendo de Compras sobre sus OC, de modo que Almacén, Dirección y Pagos no ganan Editar OC, Cancelar, cierre, cumplimientos de hitos ni solicitudes de cambio.
- La acción authorize de Dirección sobre OC, y las acciones de Dirección y Pagos en el resto de las vistas (autorizaciones, pagos, facturas, bandeja de Dirección).
- Los permisos de Gerente, Supervisor, Almacén Autoriza y proveedor (PRV), que no obtienen download.
- Los permisos de Almacén sobre recepciones (purchase_order_receipts), evidencias, facturas, SOLCOM y demás vistas.
- Los PDF de SOLCOM, SOLMAT y vales de material.
- No se crean roles, permisos nuevos, migraciones, modelos ni tablas.

**Riesgos**

- Los PDF con anexos usan el mismo permiso download: saveAnnex guarda la configuración de anexos de la OC. Almacén, Dirección y Pagos podrán guardar y modificar esa configuración en todas las OC al generar el PDF con anexos.
- El permiso purchase_orders.download es único: dárselo a estos roles habilita también purchase_orders.annex.save y purchase_orders.pdfWithAnnexes; no existe un permiso solo para el PDF simple.
- Una cuenta con Orden de compra + Almacén, Dirección o Pagos pasa a ver "Generar PDF" en todas las OC emitidas o autorizadas, aunque otro comprador las tenga asignadas, porque scopesFor devuelve alcance total si alguno de sus perfiles lo concede sin restricción.
- AccessMatrixSeeder sincroniza los permisos de todos los roles de la matriz y elimina permisos con punto que ya no están en ella, no solo el de este cambio. Revisar sus efectos y coordinar su ejecución por entorno; si HR-009 aún no se ha sembrado, el seeder también aplicará esos cambios.
- Ocultar o mostrar controles en Blade no cambia el permiso: hasta correr el seeder, los tres roles siguen sin el permiso en la base de datos.

## 3. Regla

**Actual**

Solo Compras (sobre sus OC) y admin descargan el PDF de una OC. Almacén, Pagos y Dirección no lo hacen, aunque consulten todas las OC.

**Nueva**

Los roles Almacén (ALM), Dirección (DIR) y Pagos (PAG) tienen la acción download sobre purchase_orders sin alcance, es decir, sobre todas las OC. Con ello pueden abrir la opción de PDF en el listado y el detalle de cualquier OC emitida o autorizada y generar el PDF con o sin anexos. El resto de las acciones sobre la OC no cambia. No se concede ninguna otra acción a estos roles.

**Dónde se aplica:** controller

**Casos afectados**

- Con rol Almacén, Dirección o Pagos solamente, el detalle de una OC emitida o autorizada muestra "Generar PDF" y el listado muestra "Descargar PDF"; GET purchase_orders.pdf devuelve el PDF, y POST purchase_orders.pdfWithAnnexes devuelve el PDF con anexos.
- Con cualquiera de esos tres roles, en una OC borrador, cerrada u otro estatus distinto de emitida o autorizada, el botón "Generar PDF" sigue sin mostrarse, igual que para Compras.
- Con roles Orden de compra + Almacén (o Dirección o Pagos), en una OC cuyo comprador es otro usuario, aparece "Generar PDF"; no aparecen Editar OC, Cancelar ni los controles de cumplimiento de hitos reservados al comprador.
- Con roles Orden de compra + Almacén (o Dirección o Pagos), en una OC propia, todos los controles de Compras siguen apareciendo.
- Con rol Almacén solamente, "Entregas" sigue apareciendo en OC autorizadas por purchase_order_receipts.register, sin cambios.
- Dirección conserva sus controles de autorización de OC y de solicitudes de cambio o cancelación, sin cambios.
- Un usuario con solo Orden de compra sigue descargando únicamente el PDF de sus OC; en la OC de otro comprador recibe 403 por URL directa.
- Gerente, Supervisor, Almacén Autoriza (sin otro rol que lo conceda) y proveedor siguen sin descargar el PDF y reciben 403 por URL directa.
- Admin conserva el acceso actual.

**Datos históricos**

No hay migraciones, backfill ni modificación de registros. El cambio aplica desde que se corre AccessMatrixSeeder en cada entorno, a todos los usuarios que ya tengan los roles Almacén, Dirección o Pagos.

## 4. Reglas de negocio

- Contexto adicional obligatorio: leer docs/roles-permissions-matrix.md, database/data/access_matrix.php, app/Services/AccessMatrix.php, database/seeders/AccessMatrixSeeder.php, routes/web.php, app/Http/Controllers/PurchaseOrderController.php (downloadPdf, saveAnnex, downloadPdfWithAnnexes), resources/views/purchase_orders/show.blade.php y resources/views/purchase_orders/utilities/_table.blade.php, además de las instrucciones aplicables de .github/instructions/.
- Cambiar únicamente las celdas ALM, DIR y PAG de purchase_orders: agregar download a actions (ALM y PAG: ['view', 'download']; DIR: ['view', 'authorize', 'download']), sin clave scope, y actualizar raw conservando el resto del texto ('V D', 'V D (A)', 'V D'). Conservar decision y demás claves de la celda DIR. No tocar las demás celdas.
- Sin scope en la celda, AccessMatrix::scopesFor($user, 'purchase_orders', 'download') devuelve alcance total para estos roles, y también para una cuenta que los combine con Compras. update, cancel y las demás acciones siguen saliendo solo de Compras con own_buyer.
- Mantener los middleware permission:purchase_orders.download y la autorización por registro authorizeAccess(..., 'download'); no agregar excepciones por rol en controllers ni vistas.
- Despliegue: después de revisar los efectos de sincronización del seeder, ejecutar php artisan db:seed --class=AccessMatrixSeeder en cada entorno autorizado. El seeder limpia la caché de permisos y sincroniza los roles de la matriz. La ejecución en un entorno real requiere autorización del operador.
- Actualizar solo los dos comentarios indicados; no modificar ninguna condición ni ruta.
- Implementación por capas: capa 1, matriz; capa 2, sin cambios sobre datos existentes (seeder por entorno); capa 3, comentarios y documentación. Detenerse tras cada capa para confirmación conforme a las instrucciones del proyecto.

## 5. Validaciones

_Convenciones de docs/controller-structure-and-notifications.md §4._

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- docs/roles-permissions-matrix.md, regla «PDF de OC» (decisión 2026-11-10): «solo con D de la matriz: Compras (sus OC) y admin. Pagos, Almacén y Dirección ya no descargan el PDF». Se sustituye: Almacén, Dirección y Pagos pasan a descargar el PDF de todas las OC, con y sin anexos. Compras conserva solo sus OC.
- La indicación automática del generador en Orden de implementación que dice «sin detenerte entre ellas» no aplica en este repositorio: debe solicitarse confirmación al terminar cada capa, conforme a .github/copilot-instructions.md. Esta excepción resuelve expresamente ese texto automático.

## 7. Preguntas abiertas

- [x] ¿Qué acción se concede a Almacén, Dirección y Pagos? → **Descargar el PDF de todas las OC.**
- [x] ¿Incluye el PDF con anexos y guardar la configuración de anexos? → **Sí, porque comparten el permiso purchase_orders.download y el botón "Generar PDF" abre el modal de anexos. Queda registrado en Riesgos para su revisión al aprobar el spec.**

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
- Conceder a Almacén, Dirección o Pagos otra acción sobre la OC (editar, cancelar, cierre, cumplimientos de hitos, solicitudes de cambio) o cambiar el alcance de Compras.
- Conceder el PDF a Gerente, Supervisor, Almacén Autoriza o proveedor.
- Crear un permiso distinto para el PDF simple o separar el PDF con anexos.
- Cambiar el contenido o formato de los PDF, o la condición de estatus emitida o autorizada.
- Añadir roles, permisos nuevos, migraciones, pruebas permanentes o paquetes.
- Ejecutar el seeder en una base de datos real sin autorización del operador.
- Implementar el cambio antes de aprobar el spec.
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

Implementa las capas en este orden sin detenerte entre ellas. Al terminar, lista los archivos creados o modificados por capa.

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en el controller: Los roles Almacén (ALM), Dirección (DIR) y Pagos (PAG) tienen la acción download sobre purchase_orders sin alcance, es decir, sobre todas las OC. Con ello pueden abrir la opción de PDF en el listado y el detalle de cualquier OC emitida o autorizada y generar el PDF con o sin anexos. El resto de las acciones sobre la OC no cambia. No se concede ninguna otra acción a estos roles.
- [ ] Caso: Con rol Almacén, Dirección o Pagos solamente, el detalle de una OC emitida o autorizada muestra "Generar PDF" y el listado muestra "Descargar PDF"; GET purchase_orders.pdf devuelve el PDF, y POST purchase_orders.pdfWithAnnexes devuelve el PDF con anexos.
- [ ] Caso: Con cualquiera de esos tres roles, en una OC borrador, cerrada u otro estatus distinto de emitida o autorizada, el botón "Generar PDF" sigue sin mostrarse, igual que para Compras.
- [ ] Caso: Con roles Orden de compra + Almacén (o Dirección o Pagos), en una OC cuyo comprador es otro usuario, aparece "Generar PDF"; no aparecen Editar OC, Cancelar ni los controles de cumplimiento de hitos reservados al comprador.
- [ ] Caso: Con roles Orden de compra + Almacén (o Dirección o Pagos), en una OC propia, todos los controles de Compras siguen apareciendo.
- [ ] Caso: Con rol Almacén solamente, "Entregas" sigue apareciendo en OC autorizadas por purchase_order_receipts.register, sin cambios.
- [ ] Caso: Dirección conserva sus controles de autorización de OC y de solicitudes de cambio o cancelación, sin cambios.
- [ ] Caso: Un usuario con solo Orden de compra sigue descargando únicamente el PDF de sus OC; en la OC de otro comprador recibe 403 por URL directa.
- [ ] Caso: Gerente, Supervisor, Almacén Autoriza (sin otro rol que lo conceda) y proveedor siguen sin descargar el PDF y reciben 403 por URL directa.
- [ ] Caso: Admin conserva el acceso actual.
- [ ] Datos históricos: No hay migraciones, backfill ni modificación de registros. El cambio aplica desde que se corre AccessMatrixSeeder en cada entorno, a todos los usuarios que ya tengan los roles Almacén, Dirección o Pagos.
- [ ] Permanece igual: Las condiciones Blade del botón "Generar PDF", del modal de anexos y de "Descargar PDF" del listado: hasFullAccessScope('purchase_orders', 'download') ya resulta verdadero con las nuevas celdas, por lo que no requieren cambio.
- [ ] Permanece igual: PurchaseOrderController, sus validaciones, el contenido y el nombre de los PDF, y la condición de estatus emitida o autorizada.
- [ ] Permanece igual: Todas las rutas, verbos, nombres y middleware; siguen exigiendo purchase_orders.download.
- [ ] Permanece igual: El permiso de Compras: solo descarga el PDF de sus OC (own_buyer) y conserva todas sus acciones; los alcances de update, cancel y demás acciones de la OC siguen siendo de Compras sobre sus OC, de modo que Almacén, Dirección y Pagos no ganan Editar OC, Cancelar, cierre, cumplimientos de hitos ni solicitudes de cambio.
- [ ] Permanece igual: La acción authorize de Dirección sobre OC, y las acciones de Dirección y Pagos en el resto de las vistas (autorizaciones, pagos, facturas, bandeja de Dirección).
- [ ] Permanece igual: Los permisos de Gerente, Supervisor, Almacén Autoriza y proveedor (PRV), que no obtienen download.
- [ ] Permanece igual: Los permisos de Almacén sobre recepciones (purchase_order_receipts), evidencias, facturas, SOLCOM y demás vistas.
- [ ] Permanece igual: Los PDF de SOLCOM, SOLMAT y vales de material.
- [ ] Permanece igual: No se crean roles, permisos nuevos, migraciones, modelos ni tablas.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-010]` en el mensaje.

---

_Generado con Estoria Spec Tool v4 · Estoria Studio_
