---
spec_id: HR-002
type: ext-subresource
module: Cumplimiento de hitos
target: Órdenes de compra → Entregas y panel Checklist del hito (purchase_order_milestones): checklist de autorización de pagos, estado del evento y cierre de la OC
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

# HR-002 · Cumplimiento de hitos — Sub-recurso

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`

## 1. Propósito

Permitir cumplir un hito de una orden de compra por distintos tipos de cumplimiento (Recepción física, Estimación o avance, Documental o Anticipo), elegidos al registrar en Entregas y no como configuración del hito, de modo que el hito quede verificado cuando se cumple la condición propia de ese tipo sin exigir recepción física cuando el acuerdo con el proveedor no la contempla.

**Flujo de interacción**

En el detalle de la OC, el usuario abre Entregas y el primer paso es elegir el tipo entre 4 tarjetas (Recepción física, Estimación o avance, Documental, Anticipo), como el selector de tipo de movimiento de Inventarios (Nueva entrada); al elegir una tarjeta se muestra el formulario de ese tipo. Recepción física: aparece el selector de conceptos con cantidades, el hito y la evidencia, y se registra la entrega como hoy; después se confirma la entrega completa del hito en su panel. Estimación o avance, Documental y Anticipo: no hay selector de conceptos, solo se pide el hito y la evidencia (Estimación y Documental añaden un comentario opcional); con hito y evidencia capturados se desbloquea el botón "Validar cumplimiento", que guarda la evidencia y marca el hito como verificado. Para revocar se usa el mismo botón de revocar confirmación del panel del hito.

## 2. Módulo afectado

- **Módulo:** Órdenes de compra → Entregas y panel Checklist del hito (purchase_order_milestones): checklist de autorización de pagos, estado del evento y cierre de la OC
- **Usa Livewire:** no

**Comportamiento actual**

Hoy solo existe el cumplimiento por recepción física: el modal Entregas pide siempre los conceptos con cantidades, el hito y la evidencia (purchase_order_item_deliveries, ligada a un hito) y después un usuario confirma "entrega completa del hito" (purchase_order_milestones.delivery_confirmed_at / delivery_confirmed_by). Esa confirmación alimenta: el checklist de autorización de pagos (PurchaseOrderMilestoneChecklist, requisito "Entrega confirmada" para todo hito que no sea anticipo), la dimensión "Cumplimiento" del estado del evento (MilestoneEventStatus, que exige confirmación incluso en anticipos), el inicio del plazo de crédito "cumplimiento validado" (MilestoneCreditTerms) y el cierre de la OC (PurchaseOrderClosureEvaluator: eventos cerrados + entrega completa de conceptos). Un hito sin entrega física posible (anticipo, estimación, entregable documental) no tiene cómo cumplirse salvo registrando una entrega ficticia de conceptos.

**Archivos que se tocan**

- database/migrations/2026_10_30_000000_create_purchase_order_milestone_fulfillments_table.php (nueva, vía make:model -mc)
- app/Models/PurchaseOrderMilestoneFulfillment.php (nuevo)
- app/Models/PurchaseOrderMilestone.php (relación fulfillments y helper del tipo con el que se cumplió)
- app/Http/Controllers/PurchaseOrderMilestoneFulfillmentController.php (nuevo: solo store)
- app/Http/Controllers/PurchaseOrderController.php (registro de entregas rechaza hitos cumplidos por registro; confirmMilestoneDelivery al revocar elimina los registros; revertDelivery solo limpia la verificación si no quedan entregas ni registros)
- app/Http/Controllers/PurchaseOrderEvidenceController.php (no eliminar evidencia ligada a un cumplimiento vigente)
- app/Services/PurchaseOrderClosureEvaluator.php (requisito "Entrega completa")
- routes/web.php
- resources/views/purchase_orders/show.blade.php (modal Entregas y panel del hito)
- docs/checklist-de-hitos-autorizacion-de-pagos.md
- docs/purchase-order-payment-health-flow.md

**Debe permanecer igual**

- Los formularios de crear, editar y Modificar monto de hitos: el tipo de cumplimiento NO es un campo del hito y no se agregan columnas a purchase_order_milestones.
- El flujo de Recepción física: tabla de conceptos con cantidades, evidencia, purchase_orders.deliveries.store, "Confirmar entrega completa del hito" (requiere al menos una entrega ligada) y revertir entrega (solo Admin).
- Las columnas delivery_confirmed_at y delivery_confirmed_by: pasan a significar "cumplimiento verificado" para cualquier tipo, sin renombrarse; la ruta purchase_orders.milestones.confirm_delivery no cambia.
- El checklist de hitos is_advance: no agrega requisitos que bloqueen autorizar su pago (los anticipos siguen autorizándose desde el inicio).
- Las reglas de factura aceptada, plazo de crédito, orden y fechas entre hitos, montos, pagos (registrar, dividir, rechazar, autorizar) y regularización documental.
- El cierre de la OC y la reapertura, salvo el requisito "Entrega completa" descrito en las reglas.
- Los hitos, entregas, confirmaciones y OCs cerradas ya registradas.
- Los archivos protegidos del perfil del proyecto.

**Riesgos**

- show.blade.php es muy grande (≈3,400 líneas) y concentra el modal Entregas y el panel del hito: cambiar solo esos bloques.
- El modal Entregas ya valida hito y evidencia como obligatorios con required nativo; al ocultar la tabla de conceptos y cambiar de acción por tipo hay que deshabilitar los inputs ocultos para no enviar campos de otra acción ni disparar validaciones del tipo equivocado.
- Sin tipo guardado en el hito, el tipo de cumplimiento se deduce (entregas ligadas = física; registro = su tipo). Se debe impedir mezclar ambos en un mismo hito para que la deducción sea única.
- OCs cuyos hitos se cumplen solo por registro nunca marcan sus conceptos como entregados; sin ajuste no podrían cerrarse.
- Todos los tipos se registran como Entrega: marcan "Entrega confirmada" del checklist y habilitan el Cumplimiento del estado del evento sin tocar esos servicios, porque ambos leen delivery_confirmed_at. Un hito verificado por Estimación, Documental o Anticipo se ve igual que uno recibido físicamente salvo por el tipo que muestra su panel.
- Eliminar una evidencia desde Evidencias podría dejar un cumplimiento sin documento; se bloquea mientras el registro esté vigente.
- Revocar la confirmación de un hito cumplido por registro elimina el registro; el archivo se conserva en las evidencias de la OC.

## 3. Sub-recurso

- **Modelo padre:** `PurchaseOrderMilestone`
- **Rutas:** anidadas shallow
- **Ubicación:** sección/tabla en show del padre
- **Render:** Blade
- **Operaciones:** create

### PurchaseOrderMilestoneFulfillment — tabla `purchase_order_milestone_fulfillments`

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `purchase_order_milestone_id` | foreignId | no | FK → purchase_order_milestones.id, cascade on delete |
| `fulfillment_type` | string | no | estimation, documentary, advance · constantes en el modelo (la recepción física no genera registro: usa purchase_order_item_deliveries) |
| `evidence_id` | foreignId | sí | FK → purchase_order_evidences.id, null on delete |
| `comment` | text | sí | solo se captura en Estimación o avance y Documental |
| `recorded_by` | foreignId | sí | FK → users.id, null on delete |
| `recorded_at` | timestamp | no |  |

Relaciones:
- `belongsTo` → PurchaseOrderMilestone (`purchase_order_milestone_id`)
- `belongsTo` → PurchaseOrderEvidence (`evidence_id`)
- `belongsTo` → User (`recorded_by`)

## 4. Reglas de negocio

- El tipo de cumplimiento NO se configura en el hito. Se elige en el primer paso del modal Entregas con 4 tarjetas (patrón del selector de tipo de movimiento de Inventarios, stocks/entries/index.blade.php): Recepción física, Estimación o avance, Documental o Anticipo (constantes en PurchaseOrderMilestoneFulfillment; la física no genera registro propio).
- Recepción física: sin cambios. Se muestra el selector de conceptos (tabla con cantidades); pide hito y evidencia; registra con purchase_orders.deliveries.store y el hito se verifica con "Confirmar entrega completa del hito", que exige haber registrado antes al menos una entrega ligada.
- Estimación o avance, Documental y Anticipo: no muestran selector de conceptos ni piden cantidades; se pide hito y evidencia, y en Estimación y Documental además un comentario opcional (Anticipo no lleva comentario). Con hito y evidencia capturados se habilita "Validar cumplimiento" (el comentario no condiciona el botón); al enviarlo se guarda la evidencia, se crea el registro y el hito queda verificado en el mismo paso (delivery_confirmed_at y delivery_confirmed_by).
- Qué evidencia corresponde: Estimación = estimación firmada o visto bueno de la obra; Documental = acta o documento aprobado; Anticipo = hoja de autorización firmada por Dirección. El formulario indica cuál según el tipo.
- Un hito se cumple por un solo tipo: se rechaza registrar un cumplimiento no físico en un hito con entregas ligadas, con otro registro o ya verificado, y se rechaza registrar una entrega física en un hito con registro de cumplimiento.
- El tipo con el que se cumplió un hito se deduce: con entregas ligadas es Recepción física; con registro es el tipo del registro; si no, queda sin tipo. Se muestra en el panel del hito.
- Revocar: se reutiliza "Revocar confirmación de entrega" (purchase_orders.milestones.confirm_delivery). Si el hito se cumplió por registro, además elimina el registro y conserva el archivo en las evidencias de la OC. Los permisos para revocar son los que ya aplica confirmMilestoneDelivery, sin cambios.
- revertDelivery solo limpia la verificación cuando el hito se queda sin entregas y sin registros.
- Toda validación de cumplimiento recalcula el plazo de crédito (MilestoneCreditTerms::apply), igual que hoy al confirmar la entrega.
- Quién registra: Recepción física = Almacén (rol Inventario), Compras (rol Orden de compra con buyer_id de la OC) y admin; se conserva además el acceso actual de Solmat. Estimación, Documental y Anticipo = Compras (el comprador de la OC) y admin; los demás roles no ven esos tipos en el selector y el controller rechaza el registro.
- Selector de hito: se muestran todos los hitos de la OC, como hoy, independientemente del tipo de entrega elegido y de si el hito es anticipo (is_advance). Las restricciones de exclusividad se validan en el controller.
- Registrar requiere OC autorizada y abierta (ensureOpen), igual que las entregas.
- Checklist (PurchaseOrderMilestoneChecklist): sin cambios. Todo tipo se registra como Entrega, así que al validar el cumplimiento se marca el requisito "Entrega confirmada" (delivery_confirmed_at). El texto no se renombra. Los hitos is_advance siguen sin requisitos bloqueantes para autorizar su pago; no se valida requisito alguno sobre la hoja de Dirección.
- Estado del evento (MilestoneEventStatus): sin cambios. La dimensión Cumplimiento sigue siendo delivery_confirmed_at !== null, por lo que se verifica al validar cualquier tipo de entrega.
- Cierre de la OC (PurchaseOrderClosureEvaluator): el requisito "Entrega completa" se cumple como hoy (todos los conceptos entregados) o cuando la OC no tiene ninguna entrega física registrada y todos sus hitos tienen un registro de cumplimiento.
- No se eliminan evidencias ligadas a un cumplimiento vigente desde PurchaseOrderEvidenceController::destroy.
- Nueva tabla purchase_order_milestone_fulfillments (modelo PurchaseOrderMilestoneFulfillment, columnas en la sección del modelo de este spec) con unique(purchase_order_milestone_id); sin soft deletes; se crea con `php artisan make:model PurchaseOrderMilestoneFulfillment -mc` (controller solo con store, sin resource).
- Ruta nueva dentro del grupo de OC existente: POST /ordenes-de-compra/{purchase_order}/cumplimientos → purchase_orders.fulfillments.store. Cuelga de la OC y el hito viaja en el cuerpo de la petición, igual que purchase_orders.deliveries.store; el rol (admin|Solmat|Orden de compra) y el buyer_id se verifican en el controller. No hay ruta de destroy: revocar usa la ruta existente.
- Notificaciones (NotificationService, type PurchaseOrder, model_action update) al validar y al revocar un cumplimiento, indicando tipo, hito y OC.
- Mensajes flash con las llaves success, error y info; textos visibles en español.

## 5. Validaciones

| Campo | Regla |
|---|---|
| `fulfillment_type` | requerido, in:estimation,documentary,advance (la física usa deliveries.store) |
| `milestone_id` | requerido, exists en purchase_order_milestones de la OC de la ruta; sin entregas ligadas, sin registro previo y sin delivery_confirmed_at; sin restricción por is_advance ni por tipo |
| `comment` | opcional, string, máx 1000; solo se guarda con estimation y documentary (con advance se ignora) |
| `fulfillment_evidence` | requerido, file, mimes:pdf,jpg,jpeg,png,webp, máx 10240 KB |
| `milestone_id (entrega física)` | además de las reglas actuales, el hito no puede tener un registro de cumplimiento |

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Las rutas del sub-recurso no se anidan bajo el hito: la ruta cuelga de la OC (purchase_orders.fulfillments.store) y el hito viaja en el cuerpo de la petición, igual que purchase_orders.deliveries.store, porque el hito se elige en el modal Entregas.

## 7. Preguntas abiertas

- [x] ¿Qué pasará con los elementos ya registrados en el sistema (hitos, entregas, confirmaciones, OCs cerradas)? → **Propuesta: nada cambia. Al no agregar columnas a los hitos no hay migración de datos ni backfill. Los hitos, entregas, confirmaciones (delivery_confirmed_at/by), checklist, vencimientos de crédito, estado del evento y OCs cerradas siguen igual. Los hitos ya cumplidos se muestran como Recepción física si tienen entregas ligadas; los verificados sin entregas (OCs entregadas antes del checklist) quedan verificados y sin tipo. Los anticipos legados siguen cumpliéndose con una entrega física y confirmación, como hoy, o se pueden cumplir con el tipo Anticipo si aún no tienen entregas ni confirmación. Por confirmar.**
- [x] ¿"Anticipo" como tipo de cumplimiento y la casilla "Esto es un anticipo" (is_advance, que ya rige el pago sin checklist) son lo mismo? → **Resuelto por el cambio de flujo: el tipo ya no es un dato del hito. is_advance conserva solo su significado actual (pago sin checklist) y el selector de hito del modal Entregas muestra todos los hitos para cualquier tipo.**
- [x] ¿Cómo se cierra el evento de un hito Anticipo? Propuesta: el anticipo se liga a un hito de la misma OC (advance_for_milestone_id, requerido) y queda verificado cuando existe la hoja de Dirección y ese hito está verificado. Alternativas: (B) confirmarlo manualmente con la entrega física como hoy; (C) que cualquier verificación posterior de la OC lo cierre. ¿Un anticipo respalda un solo hito o varios? → **Se debe confirmar manualmente el cumplimiento. Al verificar el cumplimiento se puede adjuntar el requeriemiento a excepción de Recepción Fisica que requiere se registre primero la entrega para luego confirmarla.**
- [x] El cierre de la OC exige "Entrega completa" de todos sus conceptos. En OCs con hitos Documental, Estimación sin cantidades o Anticipo los conceptos nunca se marcan entregados. Propuesta: ese requisito se considera cumplido cuando la OC no tiene ningún hito de Recepción física, y en OCs mixtas se mantiene como hoy. ¿Se acepta o prefieres otra regla? → **Se acepta**
- [x] En Estimación o avance, ¿las cantidades opcionales deben sumar a la entrega de los conceptos o solo quedar como dato informativo? → **Resuelto por el cambio de flujo: los tipos distintos de Recepción física no capturan cantidades ni conceptos, solo hito y evidencia.**
- [x] Quién registra: Recepción física = Almacén (rol Inventario) o el comprador; Estimación, Documental y Anticipo = el comprador (con aval de obra en estimación). Propuesta: además de admin y Solmat, solo el comprador de la OC puede registrar los tres tipos no físicos y Inventario no. ¿Se confirma? → **Según la tabla de tipos: Recepción física = Almacén (Inventario), Compras y Admin; Estimación o avance, Documental y Anticipo = Compras (el comprador de la OC) y Admin. Solmat conserva su acceso actual a Recepción física y no registra los tipos no físicos.**
- [x] Selector de hito por tipo: ¿Anticipo solo debe mostrar hitos marcados como anticipo y Estimación y Documental excluirlos? → **No. Se muestran todos los hitos como ahora, independientemente del tipo de entrega.**
- [x] Estimación o avance y Documental piden Comentario en la tabla de tipos. ¿Debe ser obligatorio o basta con hito y evidencia para habilitar "Validar cumplimiento"? → **Opcional: el comentario se captura en Estimación y Documental (no en Anticipo) pero no condiciona el botón, que sigue habilitándose con hito y evidencia como se definió en el flujo. Por confirmar.**
- [x] ¿La hoja de autorización de Dirección debe ser requisito para autorizar el pago del anticipo? → **No se valida requisito. El pago del anticipo sigue autorizándose sin checklist, como hoy.**

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
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Migración del hijo
2. Modelo hijo y relación en el padre
3. Controller y rutas anidadas
4. Integración en la vista del padre

Detente al terminar cada capa y lista los archivos creados o modificados.

## 10. Criterios de aceptación

- [ ] La migración de `purchase_order_milestone_fulfillments` tiene `foreignId` hacia `purchase_order_milestone` con `constrained()`.
- [ ] `PurchaseOrderMilestone` declara `hasMany(PurchaseOrderMilestoneFulfillment)` y `PurchaseOrderMilestoneFulfillment` declara `belongsTo(PurchaseOrderMilestone)`.
- [ ] Rutas anidadas shallow: index/store bajo el padre; show/edit/update/destroy por ID del hijo.
- [ ] El hijo se muestra como sección/tabla dentro de show del padre.
- [ ] Operaciones disponibles sobre el hijo: create.
- [ ] Las mutaciones del hijo registran NotificationService con `type` = PurchaseOrder (notificación sobre la OC, `model_action` = update).
- [ ] Permanece igual: Los formularios de crear, editar y Modificar monto de hitos: el tipo de cumplimiento NO es un campo del hito y no se agregan columnas a purchase_order_milestones.
- [ ] Permanece igual: El flujo de Recepción física: tabla de conceptos con cantidades, evidencia, purchase_orders.deliveries.store, "Confirmar entrega completa del hito" (requiere al menos una entrega ligada) y revertir entrega (solo Admin).
- [ ] Permanece igual: Las columnas delivery_confirmed_at y delivery_confirmed_by: pasan a significar "cumplimiento verificado" para cualquier tipo, sin renombrarse; la ruta purchase_orders.milestones.confirm_delivery no cambia.
- [ ] Permanece igual: El checklist de hitos is_advance: no agrega requisitos que bloqueen autorizar su pago (los anticipos siguen autorizándose desde el inicio).
- [ ] Permanece igual: Las reglas de factura aceptada, plazo de crédito, orden y fechas entre hitos, montos, pagos (registrar, dividir, rechazar, autorizar) y regularización documental.
- [ ] Permanece igual: El cierre de la OC y la reapertura, salvo el requisito "Entrega completa" descrito en las reglas.
- [ ] Permanece igual: Los hitos, entregas, confirmaciones y OCs cerradas ya registradas.
- [ ] Permanece igual: Los archivos protegidos del perfil del proyecto.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-002]` en el mensaje.

---

_Generado con Estoria Spec Tool v2 · Estoria Studio_
