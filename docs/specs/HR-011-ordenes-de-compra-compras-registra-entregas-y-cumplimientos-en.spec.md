---
spec_id: HR-011
type: ext-rule
title: Compras registra entregas y cumplimientos en sus OC
module: Ordenes de Compra
module_key: ordenes_de_compra
extends: HR-002
status: aprobado
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

# HR-011 · Ordenes de Compra — Compras registra entregas y cumplimientos en sus OC

**Módulo:** Ordenes de Compra (`ordenes_de_compra`) · **Tipo:** C3 · Cambio de regla · **Extiende:** HR-002

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/purchase-order-payment-health-flow.md`
- `docs/supplier-account-statement.md`

## 1. Propósito

Permitir que el rol Orden de compra (COM) registre entregas físicas y cumplimientos de hito (Estimación, Documental y Anticipo) en las OC donde es el comprador asignado, desde el botón "Entregas" del detalle de la OC. Hoy recibe 403 al registrar porque la matriz de acceso no le da los permisos que exigen las rutas, aunque HR-002 y docs/checklist-de-hitos-autorizacion-de-pagos.md dicen que Compras registra en sus OC. Los controles, rutas y controllers actuales no cambian.

**Flujo de interacción**

El comprador abre una OC autorizada y propia, pulsa "Entregas", elige el tipo de cumplimiento (Recepción física, Estimación o avance, Documental o Anticipo), selecciona el hito y adjunta la evidencia, y registra. El sistema guarda la entrega o el cumplimiento sin 403. También puede confirmar o revocar la entrega de un hito de su OC y usar "Buscar OCs" del menú, que lista solo sus OC. En una OC cuyo comprador es otro usuario, y que no ve por otro rol, sigue sin poder registrar.

## 2. Módulo afectado

- **Módulo:** Ordenes de Compra
- **Usa Livewire:** no

**Comportamiento actual**

En database/data/access_matrix.php el rol COM solo tiene view en purchase_order_receipts y en milestone_validations tiene view, create y update, sin authorize. Las rutas purchase_orders.deliveries.store y purchase_orders.delivery_report exigen permission:purchase_order_receipts.register; purchase_orders.fulfillments.store y purchase_orders.milestones.confirm_delivery exigen permission:milestone_validations.authorize. Un usuario solo con Orden de compra no tiene ninguno y no ve el botón "Entregas" (resources/views/purchase_orders/show.blade.php: $canRecordDeliveries). Un usuario que además tiene otro rol que concede el permiso con alcance assigned_projects (por ejemplo Supervisor) sí ve el botón, pero PurchaseOrderController::storeDelivery llama authorizeAccess($purchaseOrder, 'purchase_order_receipts', 'register'); el alcance resultante es solo assigned_projects, así que en una OC propia fuera de sus proyectos recibe 403. AccessMatrix::scopesFor une los alcances de todos sus perfiles. docs/roles-permissions-matrix.md (decisión 2026-11-11) dice que Compras ya no valida hitos y que registrar entregas pide R de Almacén, Gerente y Supervisor, lo que contradice a HR-002 y al checklist de hitos.

**Archivos que se tocan**

- database/data/access_matrix.php: en purchase_order_receipts, celda COM, cambiar actions a ['view', 'register'], agregar 'action_scopes' => ['register' => 'own_buyer'] y actualizar raw a 'V R (sus OC)'. En milestone_validations, celda COM, cambiar actions a ['view', 'create', 'update', 'authorize'], agregar 'action_scopes' => ['authorize' => 'own_buyer'] y actualizar raw a 'V C E A (sus OC)'. Agregar en ambas celdas una clave decision que cite HR-011 y que restaura HR-002. No tocar las demás celdas.
- routes/web.php: actualizar únicamente los comentarios de los grupos de purchase_order_receipts.register (reportar entregas y entregas por concepto) y de milestone_validations.authorize, que hoy listan solo Almacén, Gerente y Supervisor, para incluir a Compras en sus OC; conservar rutas y middleware.
- docs/roles-permissions-matrix.md: reemplazar las reglas de Recepción de OC y Validación de hitos que excluyen a Compras («Compras ya no valida»; registrar entregas pide R de Almacén, Gerente y Supervisor) y agregar la decisión 2026-11-19 al historial.
- docs/checklist-de-hitos-autorizacion-de-pagos.md: solo verificar que lo que dice sobre quién registra la entrega siga coincidiendo; no debería requerir cambios.

**Debe permanecer igual**

- Todas las rutas, verbos, nombres y middleware; siguen exigiendo purchase_order_receipts.register o milestone_validations.authorize.
- PurchaseOrderController (storeDelivery, deliveryReport, confirmMilestoneDelivery), PurchaseOrderMilestoneFulfillmentController, sus validaciones, notificaciones y mensajes: ya limitan por alcance con authorizeAccess y visibleTo.
- resources/views/purchase_orders/show.blade.php y delivery_report.blade.php: $canRecordDeliveries y $canRecordFulfillment (comprador con purchase_orders.update) ya resultan verdaderos para el comprador con los permisos nuevos, por lo que no requieren cambio.
- Las reglas de HR-002: un hito se cumple por un solo tipo, solo se registra en OC autorizada y abierta, el recálculo del plazo de crédito y las notificaciones.
- Los permisos de Almacén, Gerente, Supervisor, Dirección, Pagos, Almacén Autoriza, proveedor y admin sobre purchase_order_receipts y milestone_validations.
- El alcance de COM sobre purchase_orders (own_buyer) y todas sus demás acciones.
- Revertir una entrega (purchase_orders.deliveries.revert) sigue siendo solo de admin.
- No se crean roles, permisos nuevos, migraciones, modelos ni tablas.

**Riesgos**

- milestone_validations.authorize es un solo permiso: dárselo a COM habilita también confirmar y revocar la entrega de un hito (purchase_orders.milestones.confirm_delivery). Confirmarla valida el hito y habilita el pago según el checklist, por lo que el comprador de la OC puede validar y no solo registrar. Esto revierte la decisión 2026-11-11 de docs/roles-permissions-matrix.md («Compras ya no valida»); revisarlo al aprobar el spec.
- El alcance own_buyer lo aplican los controllers con authorizeAccess; la ruta solo exige el permiso. Un comprador cuya OC no es suya recibe 403 por URL directa, pero ve en el menú "Buscar OCs" aunque la lista salga vacía.
- PurchaseOrderMilestoneFulfillmentController autoriza con authorizeAccess($purchaseOrder, 'purchase_orders') (ver la OC), no con el alcance de milestone_validations. Si otro rol que ve la OC enviara un cumplimiento no físico por URL directa, el controller lo aceptaría, aunque la vista solo ofrece esos tipos al comprador. Es el comportamiento actual y este spec no lo cambia.
- Una cuenta con Orden de compra + Supervisor (u otro rol con alcance assigned_projects) suma alcances: puede registrar en sus OC y en las de sus proyectos. El botón "Entregas" se sigue mostrando en cualquier OC que la cuenta ve, y el 403 aparece al enviar si ninguno de sus alcances cubre esa OC.
- AccessMatrixSeeder sincroniza los permisos de todos los roles de la matriz y elimina permisos con punto que ya no están en ella, no solo el de este cambio. Revisar sus efectos y coordinar su ejecución por entorno.
- Ocultar o mostrar controles en Blade no cambia el permiso: hasta correr el seeder, el rol Orden de compra sigue sin los permisos en la base de datos y el 403 persiste.

## 3. Regla

**Actual**

Solo Almacén (sobre todas las OC), Gerente y Supervisor (sobre las OC de sus proyectos) y admin pueden registrar entregas físicas y cumplimientos de hito. El comprador de la OC (rol Orden de compra) no tiene los permisos y recibe 403 al registrar; si tiene además el rol Supervisor, el alcance de ese rol no incluye sus OC fuera de sus proyectos.

**Nueva**

El rol Orden de compra (COM) obtiene register en purchase_order_receipts y authorize en milestone_validations, ambos con alcance own_buyer: puede registrar entregas físicas, registrar cumplimientos de hito, confirmar y revocar la entrega de un hito y consultar "Buscar OCs" únicamente en las OC donde es el comprador asignado. Una cuenta con varios roles suma los alcances de todos sus perfiles. Registrar sigue pidiendo OC autorizada y abierta. No se concede ninguna otra acción a COM y los demás roles no cambian.

**Dónde se aplica:** controller

**Casos afectados**

- Con solo el rol Orden de compra, en una OC autorizada donde es el comprador, aparece "Entregas"; registrar una Recepción física con hito y evidencia guarda la entrega sin 403.
- Con solo el rol Orden de compra, en una OC autorizada propia, registrar Estimación o avance, Documental o Anticipo guarda el cumplimiento y deja el hito verificado, sin 403.
- Con solo el rol Orden de compra, en una OC propia, confirmar y revocar la entrega de un hito funciona.
- Con solo el rol Orden de compra, "Buscar OCs" aparece en el menú y busca únicamente entre sus OC.
- Con solo el rol Orden de compra, en una OC de otro comprador, POST purchase_orders.deliveries.store, purchase_orders.fulfillments.store y purchase_orders.milestones.confirm_delivery devuelven 403, igual que ver la OC.
- Con Orden de compra + Supervisor (caso reportado), en una OC propia fuera de sus proyectos, registrar entrega física y cumplimientos funciona sin 403.
- Con Orden de compra + Supervisor, en una OC de otro comprador dentro de sus proyectos, registra como hoy; en una OC de otro comprador fuera de sus proyectos recibe 403.
- En una OC que no está autorizada, o que está cerrada, registrar sigue devolviendo el mensaje de error actual.
- Almacén, Gerente, Supervisor, Dirección, Pagos, Almacén Autoriza y proveedor conservan sus permisos actuales sobre entregas y validación de hitos.
- Admin conserva el acceso actual, incluida la reversión de entregas.

**Datos históricos**

No hay migraciones, backfill ni modificación de registros. El cambio aplica desde que se corre AccessMatrixSeeder en cada entorno, a todos los usuarios que ya tengan el rol Orden de compra. Las entregas y cumplimientos ya registrados no cambian.

## 4. Reglas de negocio

- Contexto adicional obligatorio: leer docs/specs/HR-002-ordenes-de-compra-cumplimiento-de-hitos.spec.md, docs/roles-permissions-matrix.md, docs/checklist-de-hitos-autorizacion-de-pagos.md, database/data/access_matrix.php (encabezado, que documenta action_scopes), app/Services/AccessMatrix.php, app/Models/Concerns/HasAccessScope.php, app/Models/PurchaseOrder.php (applyAccessScope), database/seeders/AccessMatrixSeeder.php, routes/web.php, app/Http/Controllers/PurchaseOrderController.php (storeDelivery, deliveryReport, confirmMilestoneDelivery) y PurchaseOrderMilestoneFulfillmentController.php, además de las instrucciones aplicables de .github/instructions/.
- Cambiar únicamente la celda COM de purchase_order_receipts y la celda COM de milestone_validations. Usar action_scopes para que el alcance own_buyer aplique solo a register y a authorize, y que view, create y update conserven el alcance que tienen. No tocar las demás celdas.
- Con action_scopes, AccessMatrix::scopesFor($user, 'purchase_order_receipts', 'register') devuelve own_buyer para COM, y PurchaseOrder::applyAccessScope ya sabe resolverlo (buyer_id = usuario). Con varios roles se unen los alcances.
- Mantener los middleware permission:purchase_order_receipts.register y permission:milestone_validations.authorize y la autorización por registro de los controllers; no agregar excepciones por rol en controllers, rutas ni vistas.
- Despliegue: después de revisar los efectos de sincronización del seeder, ejecutar php artisan db:seed --class=AccessMatrixSeeder en cada entorno autorizado. El seeder limpia la caché de permisos y sincroniza los roles de la matriz. La ejecución en un entorno real requiere autorización del operador.
- Actualizar solo los comentarios de ruta indicados; no modificar rutas ni middleware.
- Implementación por capas: capa 1, matriz; capa 2, sin cambios sobre datos existentes (seeder por entorno); capa 3, comentarios y documentación. Detenerse tras cada capa para confirmación conforme a las instrucciones del proyecto.

## 5. Validaciones

_Convenciones de docs/controller-structure-and-notifications.md §4._

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- docs/roles-permissions-matrix.md, regla de Validación de hitos (decisión 2026-11-11): «Compras ya no valida». Se sustituye: Compras (rol Orden de compra) registra el cumplimiento y confirma o revoca la entrega solo en las OC donde es el comprador asignado.
- docs/roles-permissions-matrix.md, regla de Recepción de OC: «registrar entregas y "Buscar OCs" piden R (Almacén; Gerente y Supervisor en sus proyectos)». Se sustituye: Compras también tiene R, limitado a sus OC (own_buyer).
- La indicación automática del generador en Orden de implementación que dice «sin detenerte entre ellas» no aplica en este repositorio: debe solicitarse confirmación al terminar cada capa, conforme a .github/copilot-instructions.md. Esta excepción resuelve expresamente ese texto automático.

## 7. Preguntas abiertas

- [x] ¿Qué acciones se le conceden a Compras en sus OC? → **Registrar entregas físicas y cumplimientos de hito (Estimación, Documental y Anticipo), con alcance own_buyer (decisión del equipo, 2026-11-19).**
- [x] ¿Incluye confirmar y revocar la entrega de un hito? → **Sí, porque comparte el permiso milestone_validations.authorize con registrar el cumplimiento y la ruta de confirmar. Queda registrado en Riesgos para su revisión al aprobar el spec.**

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
- Cambiar rutas, controllers, validaciones o vistas de entregas y cumplimientos.
- Separar registrar el cumplimiento de confirmar o revocar la entrega en permisos distintos, o crear permisos nuevos.
- Restringir en PurchaseOrderMilestoneFulfillmentController los tipos no físicos al comprador de la OC.
- Ocultar el botón "Entregas" en las OC donde la cuenta no tiene alcance para registrar.
- Conceder a COM acciones de entregas o validación sobre OC de otros compradores, o a otros roles acciones nuevas.
- Añadir roles, migraciones, pruebas permanentes o paquetes.
- Ejecutar el seeder en una base de datos real sin autorización del operador.
- Implementar el cambio antes de aprobar el spec.
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

Implementa las capas en este orden sin detenerte entre ellas. Al terminar, lista los archivos creados o modificados por capa.

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en el controller: El rol Orden de compra (COM) obtiene register en purchase_order_receipts y authorize en milestone_validations, ambos con alcance own_buyer: puede registrar entregas físicas, registrar cumplimientos de hito, confirmar y revocar la entrega de un hito y consultar "Buscar OCs" únicamente en las OC donde es el comprador asignado. Una cuenta con varios roles suma los alcances de todos sus perfiles. Registrar sigue pidiendo OC autorizada y abierta. No se concede ninguna otra acción a COM y los demás roles no cambian.
- [ ] Caso: Con solo el rol Orden de compra, en una OC autorizada donde es el comprador, aparece "Entregas"; registrar una Recepción física con hito y evidencia guarda la entrega sin 403.
- [ ] Caso: Con solo el rol Orden de compra, en una OC autorizada propia, registrar Estimación o avance, Documental o Anticipo guarda el cumplimiento y deja el hito verificado, sin 403.
- [ ] Caso: Con solo el rol Orden de compra, en una OC propia, confirmar y revocar la entrega de un hito funciona.
- [ ] Caso: Con solo el rol Orden de compra, "Buscar OCs" aparece en el menú y busca únicamente entre sus OC.
- [ ] Caso: Con solo el rol Orden de compra, en una OC de otro comprador, POST purchase_orders.deliveries.store, purchase_orders.fulfillments.store y purchase_orders.milestones.confirm_delivery devuelven 403, igual que ver la OC.
- [ ] Caso: Con Orden de compra + Supervisor (caso reportado), en una OC propia fuera de sus proyectos, registrar entrega física y cumplimientos funciona sin 403.
- [ ] Caso: Con Orden de compra + Supervisor, en una OC de otro comprador dentro de sus proyectos, registra como hoy; en una OC de otro comprador fuera de sus proyectos recibe 403.
- [ ] Caso: En una OC que no está autorizada, o que está cerrada, registrar sigue devolviendo el mensaje de error actual.
- [ ] Caso: Almacén, Gerente, Supervisor, Dirección, Pagos, Almacén Autoriza y proveedor conservan sus permisos actuales sobre entregas y validación de hitos.
- [ ] Caso: Admin conserva el acceso actual, incluida la reversión de entregas.
- [ ] Datos históricos: No hay migraciones, backfill ni modificación de registros. El cambio aplica desde que se corre AccessMatrixSeeder en cada entorno, a todos los usuarios que ya tengan el rol Orden de compra. Las entregas y cumplimientos ya registrados no cambian.
- [ ] Permanece igual: Todas las rutas, verbos, nombres y middleware; siguen exigiendo purchase_order_receipts.register o milestone_validations.authorize.
- [ ] Permanece igual: PurchaseOrderController (storeDelivery, deliveryReport, confirmMilestoneDelivery), PurchaseOrderMilestoneFulfillmentController, sus validaciones, notificaciones y mensajes: ya limitan por alcance con authorizeAccess y visibleTo.
- [ ] Permanece igual: resources/views/purchase_orders/show.blade.php y delivery_report.blade.php: $canRecordDeliveries y $canRecordFulfillment (comprador con purchase_orders.update) ya resultan verdaderos para el comprador con los permisos nuevos, por lo que no requieren cambio.
- [ ] Permanece igual: Las reglas de HR-002: un hito se cumple por un solo tipo, solo se registra en OC autorizada y abierta, el recálculo del plazo de crédito y las notificaciones.
- [ ] Permanece igual: Los permisos de Almacén, Gerente, Supervisor, Dirección, Pagos, Almacén Autoriza, proveedor y admin sobre purchase_order_receipts y milestone_validations.
- [ ] Permanece igual: El alcance de COM sobre purchase_orders (own_buyer) y todas sus demás acciones.
- [ ] Permanece igual: Revertir una entrega (purchase_orders.deliveries.revert) sigue siendo solo de admin.
- [ ] Permanece igual: No se crean roles, permisos nuevos, migraciones, modelos ni tablas.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-011]` en el mensaje.

---

_Generado con Estoria Spec Tool v4 · Estoria Studio_
