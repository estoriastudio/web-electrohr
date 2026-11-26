---
spec_id: HR-013
type: ext-rule
title: Órdenes de compra y pagos permiten OC atemporales
module: Ordenes de Compra
module_key: ordenes_de_compra
extends: HR-012
status: aprobado
author: 
reviewers: []
branch: 
created: 2026-11-26
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/purchase-order-payment-health-flow.md
  - docs/supplier-account-statement.md
open_questions: 0
---

# HR-013 · Ordenes de Compra — Órdenes de compra y pagos permiten OC atemporales

**Módulo:** Ordenes de Compra (`ordenes_de_compra`) · **Tipo:** C3 · Cambio de regla · **Extiende:** HR-012

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos (si ya los leíste en esta conversación y no cambiaron, no los vuelvas a leer). Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos, fuera de alcance)
- `docs/controller-structure-and-notifications.md`
- `docs/purchase-order-payment-health-flow.md`
- `docs/supplier-account-statement.md`

## 1. Propósito

Permitir que Admin y Dirección marquen una OC como Atemporal para omitir las reglas de secuencia de autorización, estatus/pago y fechas entre sus hitos, sin omitir el checklist de autorización.

**Flujo de interacción**

Admin o Dirección edita la OC y activa OC Atemporal. Dirección puede editar el formulario general de la OC, incluso si está autorizada, pero esta excepción no le permite emitir, archivar, cerrar, cancelar, reasignar compradores ni ejecutar otras acciones. En una OC Atemporal se procesan sus pagos sin comparar el avance o las fechas de un hito contra los demás; cada autorización sigue requiriendo el checklist de su propio hito.

## 2. Módulo afectado

- **Módulo:** Ordenes de Compra
- **Usa Livewire:** no

**Comportamiento actual**

La edición general corresponde a Admin y al comprador asignado en sus OC, pero Dirección no puede editar el formulario. La regla de secuencia compara estatus y fechas de los pagos iniciales entre hitos de la misma OC y bloquea avances fuera de orden. Los pagos también deben cumplir el checklist independiente del hito para ser autorizados.

**Archivos que se tocan**

- database/migrations/: nueva migración para purchase_orders.is_atemporal con default false.
- app/Models/PurchaseOrder.php: fillable y cast booleano.
- app/Http/Controllers/PurchaseOrderController.php: autorización acotada de Dirección para edit/update, validación y persistencia de is_atemporal solo por Admin/Dirección; preservar auditoría y demás campos.
- routes/web.php: permitir Dirección únicamente en rutas edit/update; conservar restringidas las demás rutas actualmente agrupadas bajo purchase_orders.update.
- resources/views/purchase_orders/edit.blade.php: switch OC Atemporal, visible solo para Admin y Dirección.
- app/Services/PurchaseOrderPaymentSequenceValidator.php: no aplicar secuencias para OCs marcadas, en todos sus seis métodos públicos.
- routes/console.php: excluir solo las alertas de secuencia de OCs atemporales del reporte purchase-orders:health; mantener los avisos no secuenciales.
- tests/Unit/PurchaseOrderPaymentSequenceValidatorTest.php y tests/Feature/: cobertura para ambos valores de is_atemporal, permisos de edición y checklist.
- docs/roles-permissions-matrix.md: documentar el alcance de edición de Dirección sin concederle otras acciones de purchase_orders.update.
- docs/purchase-order-payment-health-flow.md, docs/checklist-de-hitos-autorizacion-de-pagos.md y docs/manual/milestones.md: describir la excepción a secuencia y confirmar que el checklist continúa aplicando.

**Debe permanecer igual**

- El checklist de autorización por hito, su visibilidad, sus comprobaciones, sus marcas de verificación y sus bloqueos permanecen iguales para todas las OCs, incluidas las atemporales.
- Para OCs no atemporales se conservan todas las reglas de secuencia y mensajes actuales.
- La nueva columna es false por defecto para registros existentes; no hay backfill a true.
- Solo Admin y Dirección pueden cambiar is_atemporal; los usuarios con permisos ordinarios de edición no pueden cambiarlo mediante una solicitud directa.
- Dirección recibe acceso únicamente a edit/update del formulario general, incluso para una OC autorizada; no recibe permisos para emitir, archivar, cerrar, cancelar, reasignar comprador ni otras acciones actualmente bajo purchase_orders.update.
- Los controles de saldo, límites por hito, asignación de excedentes, pagos parciales, transacciones, bloqueos y cierre de OC no cambian.

**Riesgos**

- La excepción a HR-012 y a los documentos vigentes debe ser explícita: puede haber pagos autorizados, pagados o fechas fuera de orden dentro de una OC marcada.
- El checklist no es una regla de secuencia y no debe omitirse junto con ella.
- Añadir purchase_orders.update al rol Dirección le daría acceso a más rutas de las solicitadas; el acceso debe limitarse a edit/update.
- Un usuario sin autorización podría intentar cambiar is_atemporal enviando el campo directamente; el controller debe rechazar ese intento y no confiar en la vista.
- El reporte purchase-orders:health no debe clasificar como anomalías las secuencias permitidas por la marca; los pagos rechazados y otros hallazgos no secuenciales siguen reportándose.

## 3. Regla

**Actual**

Todas las OCs aplican las reglas de secuencia de estatus, autorización y fechas entre hitos. El checklist de autorización es una regla independiente y se exige también a Admin.

**Nueva**

Cuando is_atemporal=true, omitir todas las validaciones de secuencia entre hitos: ensureStatusSequence, ensureAuthorizationOrder, ensurePaymentOrder, ensurePaymentDateSequence, ensureNewInitialPaymentDate y ensureExistingMilestoneFirstPaymentDate. Mantener el checklist, los permisos de autorización y todas las demás validaciones. Cuando is_atemporal=false, conservar íntegra la regla actual.

**Dónde se aplica:** undefined

**Casos afectados**

- Una OC no atemporal conserva el bloqueo de autorización cuando un hito previo no está aprobado.
- Una OC atemporal permite autorizar un pago de un hito posterior aunque los hitos previos no estén pagados, si el checklist del hito seleccionado está completo.
- Una OC atemporal permite pagar, cambiar estatus, reactivar pagos y modificar/crear fechas de hitos sin comparación de secuencia entre hitos.
- Una OC atemporal con checklist incompleto continúa rechazando la autorización y muestra el motivo del checklist.
- Admin puede activar y desactivar el switch; Dirección también puede hacerlo en OC pendiente, emitida y autorizada.
- Dirección puede editar el formulario general de una OC, incluida una autorizada, pero obtiene 403/no acceso en emisión, archivo, cierre, cancelación, asignación de comprador y otras acciones no incluidas.
- Un comprador u otro usuario que puede editar campos generales no ve el switch; una solicitud directa para cambiar is_atemporal es rechazada y no cambia el valor.
- Una actualización ordinaria de un usuario sin permiso para el switch conserva el valor persistido si no envía el campo.
- Todas las OCs existentes se comportan como no atemporales después de la migración.
- Los pagos de OCs atemporales que no cumplen el checklist siguen rechazados; se conservan permisos y demás validaciones.
- El reporte purchase-orders:health no genera alertas de orden, estatus ni fechas para una OC atemporal; conserva los avisos de pagos rechazados y pagos iniciales faltantes.

**Datos históricos**

Sin backfill de comportamiento ni modificación de pagos. La columna nueva se crea con default false; solo las OCs marcadas explícitamente por Admin o Dirección omiten secuencias.

## 4. Reglas de negocio

- Contexto obligatorio: docs/specs/_project.json, docs/controller-structure-and-notifications.md, docs/purchase-order-payment-health-flow.md, docs/checklist-de-hitos-autorizacion-de-pagos.md, docs/roles-permissions-matrix.md, database/data/access_matrix.php (celda purchase_orders), routes/web.php, routes/console.php (purchase-orders:health), app/Http/Controllers/PurchaseOrderController.php (edit/update), app/Models/PurchaseOrder.php, app/Services/PurchaseOrderPaymentSequenceValidator.php, app/Services/MilestonePaymentService.php, app/Services/MilestoneAmountAdjuster.php, app/Http/Controllers/PaymentController.php (todos los puntos de mutación que invocan el validador), app/Http/Controllers/PurchaseOrderMilestoneController.php y las instrucciones aplicables de .github/instructions/.
- Este spec extiende HR-012 y modifica únicamente la regla de secuencia para OCs con is_atemporal=true. HR-012 y la guía de pagos permanecen vigentes para OCs no atemporales. También declara la excepción de acceso a edit/update de Dirección, sin conceder otras acciones.
- Agregar a purchase_orders la columna booleana is_atemporal con default false y cast booleano en el modelo. No cambiar registros históricos a true.
- Centralizar el comportamiento en los métodos del PurchaseOrderPaymentSequenceValidator para que las seis validaciones de secuencia omitan la regla únicamente cuando la OC está marcada. Mantener todos los callers y validaciones no secuenciales.
- No saltar PurchaseOrderMilestoneChecklist ni aceptar pagos cuyo hito no cumple los requisitos. Mantener las comprobaciones en autorización individual, múltiple, interactiva y autorización de OC.
- El controller debe verificar el rol Admin/Dirección antes de persistir is_atemporal; la protección debe aplicar también a solicitudes directas. Usuarios sin permiso no pueden cambiarlo y el campo no debe ser asignable por mass assignment general.
- Dirección podrá acceder a edit/update de cualquier OC, incluidas autorizadas, pero no a otras rutas agrupadas por purchase_orders.update. No agregar purchase_orders.update a la matriz de Dirección si habilita acciones fuera del alcance.
- La edición de Dirección conserva validaciones y restricciones de campos actuales salvo la excepción explícita que permite editar una OC autorizada; no permite cambiar el estatus de la OC.
- Actualizar la matriz/documentación de roles para reflejar con precisión la edición de Dirección y mantener la diferencia entre edición general, autorización de OC y operaciones de emisión/cierre.
- Describir la nueva regla en docs y manuales. La frase actual que dice que la secuencia aplica a todas las OCs debe especificar la excepción; la regla del checklist de autorización no cambia.
- Actualizar solo el reporte purchase-orders:health definido en routes/console.php para no informar como anomalías permitidas las alertas de orden, estatus y fechas de OCs atemporales. Conservar alertas de pagos rechazados y pagos iniciales faltantes. Se habilita expresamente Artisan commands en Fuera de alcance solo para este reporte.
- Incluir pruebas de unidad para cada método de secuencia con OC normal y atemporal, y pruebas de autorización de edición/toggle y preservación del checklist. Fuera de alcance: Unit / Feature tests se habilita expresamente para este spec.
- Antes de implementar, el spec debe estar aprobado y tener open_questions=0. No desplegar ni ejecutar migraciones en entornos reales como parte de este trabajo.

## 5. Validaciones

| Campo | Regla |
|---|---|
| `is_atemporal` | nullable\|boolean; procesar solo cuando el usuario tiene rol admin o Dirección |

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- HR-012 y docs/purchase-order-payment-health-flow.md: las secuencias de estatus, autorización y fechas entre hitos no aplican si purchase_orders.is_atemporal=true; permanecen vigentes para las demás OCs.
- docs/checklist-de-hitos-autorizacion-de-pagos.md y docs/purchase-order-payment-health-flow.md: OC Atemporal no constituye excepción al checklist; este sigue siendo obligatorio.
- docs/roles-permissions-matrix.md: Dirección puede editar el formulario general mediante edit/update, incluidas OCs autorizadas, sin adquirir otras acciones que hoy comparten purchase_orders.update.

## 7. Preguntas abiertas

- [x] ¿El reporte purchase-orders:health excluye las secuencias de OCs atemporales o las conserva como alertas informativas? → **Las excluye de las alertas de orden, estatus y fechas; conserva alertas no secuenciales, como pagos rechazados y pagos iniciales faltantes.**

## 8. Fuera de alcance

Aplica la lista `oos` de `docs/specs/_project.json`, salvo lo que este spec sí incluye.

**Este spec sí incluye:**

- Unit / Feature tests
- Artisan commands

**Además, fuera de alcance en este spec:**

- Omitir o relajar checklist, permisos de autorización, límites de monto, controles de saldo, requisitos de facturas/entregas u otras reglas no secuenciales.
- Agregar al rol Dirección permisos para emitir, archivar, cerrar, cancelar, asignar compradores o ejecutar otras acciones fuera de edit/update.
- Cambiar la secuencia para OCs que no tengan is_atemporal=true.
- Marcar OCs existentes como atemporales por migración/backfill.

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en undefined: Cuando is_atemporal=true, omitir todas las validaciones de secuencia entre hitos: ensureStatusSequence, ensureAuthorizationOrder, ensurePaymentOrder, ensurePaymentDateSequence, ensureNewInitialPaymentDate y ensureExistingMilestoneFirstPaymentDate. Mantener el checklist, los permisos de autorización y todas las demás validaciones. Cuando is_atemporal=false, conservar íntegra la regla actual.
- [ ] Caso: Una OC no atemporal conserva el bloqueo de autorización cuando un hito previo no está aprobado.
- [ ] Caso: Una OC atemporal permite autorizar un pago de un hito posterior aunque los hitos previos no estén pagados, si el checklist del hito seleccionado está completo.
- [ ] Caso: Una OC atemporal permite pagar, cambiar estatus, reactivar pagos y modificar/crear fechas de hitos sin comparación de secuencia entre hitos.
- [ ] Caso: Una OC atemporal con checklist incompleto continúa rechazando la autorización y muestra el motivo del checklist.
- [ ] Caso: Admin puede activar y desactivar el switch; Dirección también puede hacerlo en OC pendiente, emitida y autorizada.
- [ ] Caso: Dirección puede editar el formulario general de una OC, incluida una autorizada, pero obtiene 403/no acceso en emisión, archivo, cierre, cancelación, asignación de comprador y otras acciones no incluidas.
- [ ] Caso: Un comprador u otro usuario que puede editar campos generales no ve el switch; una solicitud directa para cambiar is_atemporal es rechazada y no cambia el valor.
- [ ] Caso: Una actualización ordinaria de un usuario sin permiso para el switch conserva el valor persistido si no envía el campo.
- [ ] Caso: Todas las OCs existentes se comportan como no atemporales después de la migración.
- [ ] Caso: Los pagos de OCs atemporales que no cumplen el checklist siguen rechazados; se conservan permisos y demás validaciones.
- [ ] Caso: El reporte purchase-orders:health no genera alertas de orden, estatus ni fechas para una OC atemporal; conserva los avisos de pagos rechazados y pagos iniciales faltantes.
- [ ] Datos históricos: Sin backfill de comportamiento ni modificación de pagos. La columna nueva se crea con default false; solo las OCs marcadas explícitamente por Admin o Dirección omiten secuencias.
- [ ] Permanece igual: El checklist de autorización por hito, su visibilidad, sus comprobaciones, sus marcas de verificación y sus bloqueos permanecen iguales para todas las OCs, incluidas las atemporales.
- [ ] Permanece igual: Para OCs no atemporales se conservan todas las reglas de secuencia y mensajes actuales.
- [ ] Permanece igual: La nueva columna es false por defecto para registros existentes; no hay backfill a true.
- [ ] Permanece igual: Solo Admin y Dirección pueden cambiar is_atemporal; los usuarios con permisos ordinarios de edición no pueden cambiarlo mediante una solicitud directa.
- [ ] Permanece igual: Dirección recibe acceso únicamente a edit/update del formulario general, incluso para una OC autorizada; no recibe permisos para emitir, archivar, cerrar, cancelar, reasignar comprador ni otras acciones actualmente bajo purchase_orders.update.
- [ ] Permanece igual: Los controles de saldo, límites por hito, asignación de excedentes, pagos parciales, transacciones, bloqueos y cierre de OC no cambian.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-013]` en el mensaje.

---

_Generado con Estoria Spec Tool v4.1 · Estoria Studio_
