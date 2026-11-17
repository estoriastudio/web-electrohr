---
spec_id: HR-006
type: ext-rule
title: Autorización parcial, pagos de más y abonos entre hitos
module: Hitos
module_key: milestones
status: borrador
author: Jorge Peña
reviewers: [Mariam]
branch: satellite
created: 2026-11-16
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
open_questions: 0
---

# HR-006 · Hitos — Autorización parcial, pagos de más y abonos entre hitos

**Módulo:** Hitos (`milestones`) · **Tipo:** C3 · Cambio de regla

> ⚠ Estado: **Borrador**. No implementar hasta que el estado sea "aprobado".

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`

## 1. Propósito

Que quien autoriza decida al autorizar cuánto se paga de cada PAY y que el sistema mantenga cuadrada la caja de cada hito: genera solo un PAY por el resto, cubre diferencias mínimas por tolerancia y, cuando se autoriza o se paga más que el saldo de un hito, abona el excedente a los hitos siguientes y rehace sus PAY pendientes por el saldo. Las reglas salen del «Simulador de caja de hitos» (artifact de Estoria, OC-1024 de ejemplo).

**Flujo de interacción**

OC con 3 hitos de $100.00, $80.00 y $200.00; cada hito nace con su PAY por el 100 %. Hito 1: Admin abre Autorizar en un PAY, el monto viene precargado con $100.00 y teclea $80.00; antes de confirmar ve qué hará el sistema. El PAY queda autorizado por $80.00 y el sistema genera un PAY por $20.00 por autorizar, con el vencimiento y el checklist del hito. Pagos registra el SPEI por $80.00 (importe precargado con lo autorizado). Admin autoriza el PAY de $20.00 y Pagos lo paga: el hito 1 queda pagado completo. Hito 2: Admin autoriza $50.00 del PAY de $80.00 (se genera un PAY por $30.00) y Pagos paga $50.00; Admin autoriza los $30.00 y Pagos registra un SPEI por $50.00, $20.00 de más. El hito 2 queda cubierto, los $20.00 se abonan al hito 3 y su PAY de $200.00 queda sustituido por uno de $180.00. Admin autoriza los $180.00 y Pagos los paga. Al final salieron del banco $380.00, igual al monto de la OC, y el saldo por pagar es $0.00. En el detalle de la OC cada hito muestra su caja: monto, cubierto, saldo por pagar y el desglose pagado · abono recibido · tolerancia · autorizado sin pagar · por autorizar.

## 2. Módulo afectado

- **Módulo:** Hitos
- **Controllers:** `PurchaseOrderMilestoneController`, `PaymentController`
- **Modelos:** `PurchaseOrderMilestone` (`purchase_order_milestones`), `Payment` (`payments`)
- **Usa Livewire:** no

**Comportamiento actual**

Al crear un hito se genera su pago inicial por el 100 % (`por_autorizar`, `payments.payment_date` = `due_date` del hito). Autorizar es solo un cambio de estatus, sin monto: `PaymentController@update` (individual, desde el detalle de la OC y la tabla de pagos; admin desde el detalle de la OC se salta el checklist con `$skipChecklist`, línea 757), `@swipe` (modo interactivo), `@authorizeMultiple` y `PurchaseOrderController@approveWithPayments` ponen `autorizado` y `checklist_verified_at` tras `PurchaseOrderMilestoneChecklist::ensureComplete` y `PurchaseOrderPaymentSequenceValidator::ensureStatusSequence`. Para pagar solo una parte hay que dividir antes el pago con `PaymentController@store` (`existing_payment_amount`), y solo si el hito tiene un único pago (`$payments->count() === 1`) y sigue `por_autorizar`. Registrar pagado (`@update` con SPEI, en dos lugares, y `@markMultiplePaidWithSpei`) no captura importe: el SPEI solo se adjunta, se pone `pagado` y se incrementa `covered_amount` del hito con `payments.amount`; `@destroy` lo decrementa con el mismo `amount`. `PurchaseOrderMilestone::ensurePaymentTotalWithinAmount` impide que la suma de pagos comprometidos (`Payment::COMMITTED_STATUSES`) supere `effective_amount`, así que no se puede autorizar ni pagar más que el hito. `PurchaseOrder::AMOUNT_TOLERANCE` ($0.50) solo se usa en `is_complete`. No existen abonos entre hitos, PAY sustituidos ni saldo a favor con el proveedor. Estatus de pago: `por_autorizar`, `autorizado`, `pagado`, `rechazado`, `pospuesto` (ENUM de MySQL, ampliado con `ALTER TABLE ... MODIFY`). Suman `payments.amount` de los pagos `pagado`: `PurchaseOrder::saldo_cubierto` (lo usa `PurchaseOrderEconomicSummary`), `PurchaseOrderClosureEvaluator`, `SupplierAccountStatementService` y `PaidPaymentExport`. `MilestoneEventStatus` cuenta pagos `pagado` y lee `covered_amount`; `MilestoneAmountAdjuster` suma pagos comprometidos; `PurchaseOrder::syncPercentageMilestonePayments` toma como monto bloqueado los pagos con `status != 'por_autorizar'`. `AdminController`, `SupplierPortalController`, `SupplierController` y `PurchaseOrderController` (línea 145) filtran hitos pendientes con `covered_amount < value`. El reporte `purchase-orders:health` es un closure en `routes/console.php` (línea 125) y, como el validador de secuencia, toma el pago de menor id de cada hito como pago inicial.

**Archivos que se tocan**

- database/migrations (nueva): `payments` agrega `sustituido` al ENUM de `status` y las columnas `original_amount`, `paid_amount`, `applied_amount`, `origin`, `source_payment_id`, `replaced_by_payment_id`
- database/migrations (nueva): tabla `purchase_order_milestone_credits` (abonos entre hitos)
- database/migrations (nueva): `purchase_orders.supplier_credit_amount`
- app/Models/Payment.php, app/Models/PurchaseOrderMilestone.php, app/Models/PurchaseOrder.php (`saldo_cubierto`, `syncPercentageMilestonePayments` y el filtro `covered_amount < value` de la línea 252) y app/Models/PurchaseOrderMilestoneCredit.php (nuevo)
- app/Services/MilestonePaymentService.php (nuevo): autorizar con monto, registrar pagado con importe, abonar, recalcular los PAY pendientes de un hito y simular la vista previa
- app/Http/Controllers/PaymentController.php (update, incluida la rama que hoy reemplaza el SPEI de un pago `pagado`, que se elimina; swipe, authorizeMultiple, markMultiplePaidWithSpei, store, destroy, requestReactivation y `preview` nuevo)
- app/Http/Controllers/PurchaseOrderController.php (approveWithPayments, show y filtro de la línea 145)
- app/Http/Controllers/PurchaseOrderMilestoneController.php (PAY inicial al crear un hito y al editar un hito legado, líneas 155 y 259: `origin` = `initial`)
- app/Http/Controllers/AdminController.php, SupplierPortalController.php y SupplierController.php (filtros `covered_amount < value`)
- routes/web.php (`payments.preview`) y routes/console.php (closure `purchase-orders:health` y backfill de pagos iniciales de la línea 103)
- app/Services/PurchaseOrderPaymentSequenceValidator.php, PurchaseOrderClosureEvaluator.php, MilestoneEventStatus.php, PurchaseOrderEconomicSummary.php, SupplierAccountStatementService.php y MilestoneAmountAdjuster.php (`createPayment`: `origin` = `rest`)
- resources/views/purchase_orders/show.blade.php (caja por hito, modal Autorizar con monto y vista previa, modal Registrar pagado con importe)
- resources/views/payments/utilities/table.blade.php, resources/views/payments/por_pagar.blade.php e index.blade.php (mismos modales y chips de estatus)
- app/Exports/PaidPaymentExport.php (importe pagado = `paid_amount`)
- resources/views/payments/paid.blade.php (importe pagado) y resources/views/payments/utilities/swipable_cards.blade.php (chip `sustituido` e importe mostrado)
- resources/views/supplier_portal/account_statement.blade.php (estatus por hito y chip `sustituido`)

**Debe permanecer igual**

- Al crear un hito se genera su PAY inicial por el 100 %, `por_autorizar`, con fecha = `due_date`.
- El checklist del hito (`PurchaseOrderMilestoneChecklist::ensureComplete`) se valida antes de autorizar cualquier PAY normal, también el PAY del resto; los PAY de abono lo validan al registrarse pagados.
- La secuencia de estatus y fechas entre hitos (`PurchaseOrderPaymentSequenceValidator`), con el ajuste de qué cuenta como pago inicial y la regla general de pago en orden de hitos.
- El límite de pagos por hito: la suma de pagos comprometidos no supera `effective_amount` (con `amount` ya ajustado al autorizado), salvo la excepción de tolerancia: `ensurePaymentTotalWithinAmount` admite hasta $0.50 sobre el monto del hito.
- Autorizar en bloque, el modo interactivo (swipe) y Autorizar OC con pagos siguen autorizando el 100 % de cada PAY.
- El SPEI múltiple (`markMultiplePaidWithSpei`) registra cada pago por lo autorizado (`paid_amount` = `amount`).
- Dividir el pago inicial (`payments.store`) y Modificar monto del hito siguen disponibles; «único pago del hito» cuenta solo los PAY no sustituidos.
- Rechazar, posponer y solicitar reactivación siguen igual para los PAY que no están bloqueados (ver Bloqueos en Reglas de negocio); comprobante SPEI obligatorio para registrar pagado.
- Bloqueo de OC cerrada (`PurchaseOrder::ensureOpen`) y transacción con bloqueo OC → hitos → pagos.
- Regularización documental y la regla de una factura por hito: la factura es por el importe total del hito y los abonos no participan.

**Riesgos**

- Riesgo aceptado: la migración amplía el ENUM de `payments.status` con `ALTER TABLE ... MODIFY`, que bloquea la tabla `payments` mientras corre; se despliega en un horario sin operación de pagos.
- Riesgo aceptado: Pagos pierde eliminar y reemplazar el comprobante SPEI de pagos `pagado`, también en pagos anteriores al despliegue; solo admin los corrige.
- Riesgo aceptado: las OCs que hoy están fuera de orden quedan bloqueadas para autorizar y pagar hasta regularizarse en la base de datos.

## 3. Regla

**Actual**

Un PAY se autoriza y se paga siempre por su `amount` completo. Pagar solo una parte exige dividir el pago antes de autorizarlo. La suma de pagos de un hito nunca puede superar su monto, así que no se puede autorizar ni pagar de más, y una diferencia de centavos deja el hito con saldo o con un PAY pendiente.

**Nueva**

Al autorizar un PAY individual (admin) se captura el monto (precargado al 100 %). Si es menor, el PAY se autoriza por ese monto y el sistema genera un PAY por el resto, salvo que el resto quepa en la tolerancia de $0.50 por hito. Si es mayor que el saldo del hito, el PAY se autoriza por el saldo y el excedente se convierte en PAY autorizados de abono en los hitos siguientes. Al registrar pagado (Pagos) se captura el importe del SPEI (precargado con lo autorizado): un pago de más cubre el hito hasta su monto y abona el excedente a los hitos siguientes; lo que ya no cabe en ningún hito de la OC pasa a saldo a favor con el proveedor, que se guarda en la OC y se refleja en sus indicadores económicos. Después de cada movimiento, los PAY pendientes de un hito que ya no caben en su saldo se sustituyen por un solo PAY por el saldo.

**Dónde se aplica:** controller

**Casos afectados**

- Guion completo del flujo (OC de $100, $80 y $200, con el checklist completo en los tres hitos): al terminar, salió del banco $380.00, cada hito tiene saldo $0.00, la OC no tiene PAY por autorizar ni autorizados y los PAY sustituidos no cuentan para cerrarla.
- Hito 1 ($100, PAY de $100 sin pagos): autorizar $80.00 deja el PAY autorizado por $80.00 con `original_amount` $100.00 y genera un PAY por $20.00 `por_autorizar`, `origin` = rest, con la fecha de vencimiento del hito.
- Autorizar $99.60 del PAY de $100.00: no se genera PAY; al pagarse los $99.60 el hito queda pagado completo (los $0.40 se cubren por tolerancia).
- Registrar un SPEI por $79.70 de un PAY autorizado por $80.00: se acepta y los $0.30 se cubren por tolerancia. Un SPEI por $79.00 se rechaza con «Pagar menos de lo autorizado no está permitido: el monto se decide al autorizar.»
- Hito 2 ($80) con $50 pagados y un PAY de $30 autorizado, y el hito 3 con checklist completo: un SPEI por $50.00 aplica $30.00 al hito 2 (queda pagado completo), abona $20.00 al hito 3 y su PAY de $200.00 pasa a `sustituido` por un PAY nuevo de $180.00 `por_autorizar`.
- Autorizar $250.00 en el PAY de $100.00 del hito 1 (OC sin pagos, hitos 2 y 3 con checklist completo): el PAY queda autorizado por $100.00; se genera un PAY autorizado de abono por $80.00 en el hito 2 (su PAY pendiente de $80.00 pasa a `sustituido` porque el hito ya quedó cubierto) y otro por $70.00 en el hito 3 (su PAY de $200.00 pasa a `sustituido` por uno de $130.00).
- Autorizar más que el saldo del hito y de los siguientes regresa «Lo más que se puede autorizar es $X: el saldo de este hito y de los siguientes.» ($X ya incluye los $0.50 de tolerancia) En el último hito: «No hay hito siguiente con saldo donde abonar el excedente. Lo más que se puede autorizar es $X, el saldo de este hito.»
- Un SPEI de más en el último hito, o con un excedente mayor que el saldo libre de los hitos siguientes: se abona hasta llenar los hitos siguientes y el resto se suma a `purchase_orders.supplier_credit_amount`; los indicadores económicos de la OC lo muestran como «Saldo a favor».
- Autorizar en bloque o por swipe un PAY de $80.00: queda autorizado por $80.00 sin capturar monto y no se genera nada más.
- Pagos no puede regresar a otro estatus, cambiar de importe, reemplazar el comprobante SPEI ni eliminar un pago `pagado`: responde «Este pago ya se registró con SPEI y no se puede modificar ni eliminar.» Admin sí puede corregirlo.
- Autorizar $80.40 en el PAY de $80.00 de un hito de $80 sin pagos: se acepta como excepción de tolerancia, sin abono al siguiente hito.
- Un solo SPEI registra pagados el PAY de $100.00 del hito 1 y su PAY de abono autorizado de $80.00 del hito 2 (selección múltiple en Por pagar), siempre que el hito 2 tenga su checklist completo.
- La vista previa de autorizar $250.00 en el PAY del hito 1 lista los PAY de abono y las sustituciones del caso anterior sin guardar nada.
- Con el hito 1 sin pagar completo, registrar pagado un PAY de abono autorizado del hito 2 se rechaza con «No se puede registrar pagado: el Hito 1 aún no está pagado completo.»; con el hito 1 pagado pero sin entrega confirmada en el hito 2, se rechaza por el checklist.
- Admin elimina el SPEI de $50.00 del hito 2 que aplicó $30.00 y abonó $20.00 al hito 3 (con el PAY de $180.00 del hito 3 ya pagado): el hito 2 baja $30.00 de cubierto y recibe un PAY `por_autorizar` por $30.00; el abono de $20.00 se elimina y el hito 3 recibe un PAY `por_autorizar` por $20.00; todo en una sola operación.
- Autorizar $250.00 en el PAY del hito 1 con el hito 2 sin entrega confirmada: se rechaza con el máximo permitido ($100.50), porque el excedente no puede pasar al hito 2 ni saltarlo.
- Con el hito 1 sin aprobar (un PAY por autorizar), autorizar el PAY del hito 2 se rechaza con «No se puede autorizar: el Hito 1 aún no está aprobado.», también en autorizar en bloque.
- Admin intenta eliminar un pago `pagado` del hito 1 que al autorizarse de más generó PAY de abono en los hitos 2 y 3: se rechaza con «Este pago generó abonos autorizados en PAY-##### y no se puede corregir.»

**Datos históricos**

Los pagos existentes no cambian: `original_amount`, `paid_amount`, `applied_amount`, `origin`, `source_payment_id` y `replaced_by_payment_id` quedan vacíos; un `paid_amount` o `applied_amount` vacío se lee como `amount`. No se recalcula `covered_amount` de ningún hito ni se generan abonos ni sustituciones sobre pagos anteriores al despliegue. Para hitos con datos anteriores, el saldo libre se calcula con el `covered_amount` guardado. Los excesos históricos que reporta `php artisan purchase-orders:health` siguen igual. Las OCs existentes quedan con `supplier_credit_amount` en 0. Excepción: la corrección de admin de un pago `pagado` (también de uno anterior al despliegue) sí puede generar PAY de reposición (`origin` = `replacement`) en los hitos afectados.

## 4. Reglas de negocio

- Autorizar con monto es tarea de admin y solo en la autorización individual (detalle de la OC y tabla de pagos). El modal muestra el monto precargado con el 100 % del PAY y, antes de confirmar, una vista previa de lo que hará el sistema (PAY que genera, sustituye o abona). La vista previa la calcula el servidor: `GET /pagos/{payment}/vista-previa` (`payments.preview`, parámetros `accion` = autorizar|pagar y `monto`) usa los mismos cálculos de `MilestonePaymentService` sin guardar nada: no bloquea tablas (`lockForUpdate`), no genera folios, no llama a `NotificationService` y no valida el comprobante SPEI. Con `accion=autorizar` solo la usa admin; con `accion=pagar`, admin y Pagos. Responde JSON con los movimientos o el error, incluidos los de las reglas de autorización y pago en orden de hitos. Sin vista previa válida no se confirma.
- Monto menor que el PAY: `amount` pasa a ser lo autorizado y `original_amount` guarda el monto anterior. Se genera un PAY `por_autorizar` por el resto con `origin` = `rest`, `source_payment_id` = el PAY autorizado, `payment_date` = `due_date` del hito (si el hito no tiene `due_date`, el `payment_date` del PAY autorizado) y el mismo checklist.
- Tolerancia: después de cada operación, si la diferencia del hito (monto efectivo − cubierto − autorizado sin pagar − por autorizar) queda entre $0.01 y $0.50 (`PurchaseOrder::AMOUNT_TOLERANCE`), no se genera PAY por ella; esa diferencia no sube `covered_amount` y el hito se lee completo con `is_complete`, como hoy.
- Excedente dentro de tolerancia: autorizar o pagar hasta $0.50 más de lo que le falta al hito se acepta como excepción de las validaciones; no genera abono ni saldo a favor y se aplica al mismo hito (`applied_amount` y `covered_amount` pueden superar el monto del hito hasta por $0.50). La tolerancia se pasa como parámetro a `ensurePaymentTotalWithinAmount` solo desde autorizar con monto y registrar pagado; `payments.store`, la reactivación, la edición de hitos y Modificar monto siguen sin tolerancia, como hoy.
- Saldo libre de un hito = monto efectivo − cubierto − autorizado sin pagar. Cubierto = pagado aplicado + abonos recibidos. Si el resultado es negativo (excesos históricos), se toma como $0.00.
- Monto mayor que el saldo libre del hito: el PAY se autoriza por el saldo libre y el excedente se reparte, en el orden de los hitos, como PAY `autorizado` con `origin` = `credit` (abono autorizado) en los hitos siguientes, hasta el saldo libre de cada uno. Si el excedente supera la suma de saldos libres siguientes, se rechaza con el máximo permitido.
- Registrar pagado con importe es tarea de Pagos (y admin): en el registro individual se captura el importe del SPEI, precargado con lo autorizado, en `paid_amount`; `applied_amount` guarda lo que se aplicó al hito del PAY. Un importe menor solo se acepta si la diferencia que deja en el hito cabe en la tolerancia. No hay tope para un importe mayor: el modal muestra una alerta con lo que hará el sistema (hitos que reciben abono y monto que pasa a saldo a favor) y pide confirmar.
- Pago de más: al hito se le aplica hasta su saldo (`covered_amount` sube `applied_amount`, no el SPEI completo). El excedente se abona a los hitos siguientes, en orden y hasta su saldo libre: cada abono es un registro en `purchase_order_milestone_credits` (hito destino, PAY de origen, monto, usuario) y sube el `covered_amount` del hito destino.
- `covered_amount` solo se mueve con `applied_amount` y con los abonos recibidos. Donde hoy se usa `payments.amount` para incrementarlo (`update`, dos lugares, y `markMultiplePaidWithSpei`) se usa `applied_amount`.
- Saldo a favor (RF-20): lo que un pago de más ya no puede abonar a ningún hito de la OC se suma a `purchase_orders.supplier_credit_amount` y se refleja en los indicadores económicos de la OC (`PurchaseOrderEconomicSummary`). Solo aplica a pagos de más: autorizar de más sigue rechazándose cuando el excedente no cabe en los hitos siguientes. El saldo a favor que generó un pago se calcula como `paid_amount` − `applied_amount` − abonos que generó; es lo que se revierte si admin corrige ese pago.
- Dinero pagado en reportes: `PurchaseOrder::saldo_cubierto` (pagado del resumen económico), el estado de cuenta del proveedor, el cierre de la OC y el exportable de Pagados usan el importe del SPEI (`paid_amount`; si está vacío, `amount`) en lugar de `payments.amount`. La cobertura de cada hito usa `covered_amount` (aplicado + abonos recibidos), así un adelanto no se cuenta dos veces.
- Estado de cuenta del proveedor al cotejar facturas: los totales de pagado usan `paid_amount`, pero al repartir pagos contra las facturas de cada hito (`SupplierAccountStatementService::calculateOrder`) se usa lo aplicado a ese hito (`applied_amount`) más los abonos que recibió, no el SPEI completo. Así un pago de más no marca «Por conciliar» la factura del hito de origen. El monto programado de cada hito (`scheduled`) suma sus PAY no `sustituido` ni `rechazado` más los abonos que recibió. Un abono se coteja contra las facturas del hito destino como parte del pago de origen y se muestra con el folio de ese PAY.
- Regularización documental sin cambios: la factura es por el importe total del hito y los abonos no participan; Regularización documental y la dimensión Facturación de `MilestoneEventStatus` siguen calculándose como hoy.
- Recalcular pendientes: después de cada autorización, pago o abono, y también después de rechazar, eliminar o reactivar un PAY `por_autorizar` o `pospuesto`, si la suma de PAY `por_autorizar` y `pospuesto` de un hito supera su saldo libre, esos PAY pasan a `sustituido` (`replaced_by_payment_id`) y se genera uno solo por el saldo libre (`origin` = `replacement`). Si el saldo libre es $0.00 se sustituyen sin generar otro; si es de $0.50 o menos, se cubre por tolerancia. El PAY nuevo queda `por_autorizar`.
- `origin` de los PAY que ya se crean hoy: `initial` para el PAY inicial del hito (alta del hito, edición de un hito legado sin pagos y backfill de `routes/console.php`); `rest` para el PAY que generan dividir el pago inicial (`payments.store`) y Modificar monto (`MilestoneAmountAdjuster::createPayment`).
- Portal de proveedores: el estatus que se muestra por hito sale de su pago inicial (misma regla que la secuencia: menor id, sin `sustituido` ni abonos); los PAY `sustituido` no se muestran.
- `pospuesto` opera igual que `por_autorizar` en todo este spec: se autoriza con monto, cuenta como pendiente al recalcular y se sustituye igual.
- Estatus `sustituido`: no está en `Payment::COMMITTED_STATUSES`, no cuenta para la secuencia, el cierre, el checklist, los exportables ni el estado de cuenta, no se reactiva, no se puede autorizar ni pagar, y se muestra tachado con «Sustituido por PAY-#####» o el motivo («El hito ya quedó cubierto», «Diferencia cubierta por tolerancia»). En `SupplierAccountStatementService::calculateOrder` no entra en el monto programado (`scheduled`). Tampoco cuenta como monto bloqueado en `PurchaseOrder::syncPercentageMilestonePayments` ni en `MilestoneAmountAdjuster`, y el reporte `purchase-orders:health` lo excluye. Mensaje si se intenta operar: «Este PAY fue sustituido y ya no se puede autorizar ni pagar.»
- Pago inicial para la secuencia entre hitos: el PAY de menor id del hito que no esté `sustituido` y cuyo `origin` no sea `credit`.
- Los PAY de abono autorizado (`origin` = `credit`) se pagan como cualquier PAY autorizado y muestran el chip «Abono» con el PAY de origen.
- Abonos y requisitos documentales: los saldos que pasan a otros hitos cumplen los mismos requisitos que cualquier pago. Un PAY de abono autorizado (`origin` = `credit`) solo se crea en un hito que ya puede autorizarse (checklist completo según su tipo y hito anterior aprobado), y para registrarlo pagado se valida otra vez el checklist (`PurchaseOrderMilestoneChecklist::ensureComplete`) y la regla de pago en orden de hitos. Un abono de un pago de más solo entra a un hito con el checklist completo y con el hito anterior cubierto. Admin conserva su excepción de checklist desde el detalle de la OC.
- Regla general de pago en orden de hitos: no se puede registrar ni reportar como pagado un saldo de un hito si el hito anterior (por `id`) no está pagado completo (`is_complete`). Aplica a todo PAY (normal, resto, sustitución o abono) y a los abonos de un pago de más, que solo pasan a un hito cuando el anterior quedó cubierto. En el SPEI múltiple la regla se evalúa por OC, juntando los pagos seleccionados de cada una: se permite si el resultado deja cada hito anterior pagado completo. Mensaje: «No se puede registrar pagado: el Hito N aún no está pagado completo.» Esta regla se valida en `PurchaseOrderPaymentSequenceValidator` y es más estricta que la secuencia actual, que solo compara pagos iniciales.
- Regla general de autorización en orden de hitos: no se puede autorizar un PAY de un hito si el hito anterior (por `id`) no está aprobado: todos sus PAY no `sustituido` ni `rechazado` están `autorizado` o `pagado` y su saldo libre es $0.00 (o cabe en la tolerancia). Además se autoriza en orden documental: el hito debe tener completo su checklist según su tipo (con la excepción de admin desde el detalle de la OC). Aplica a todo PAY (normal, resto, sustitución o abono) y a autorizar en bloque, swipe y Autorizar OC con pagos, que se evalúan juntos por OC. Mensaje: «No se puede autorizar: el Hito N aún no está aprobado.» Se valida en `PurchaseOrderPaymentSequenceValidator`.
- Las reglas de orden (autorizar y pagar) aplican a todos los tipos de hito, también a anticipos y a hitos con plazo de crédito propio (`MilestoneCreditTerms`). No tienen excepción para OCs existentes que hoy están fuera de orden: esas OCs quedan bloqueadas hasta regularizarse en la base de datos.
- Admin conserva sus excepciones actuales: la de autorizar sin checklist desde el detalle de la OC (`$skipChecklist`) aplica también a los PAY de resto y de sustitución.
- Autorizar en bloque, swipe y Autorizar OC con pagos autorizan el 100 % de cada PAY. Un solo comprobante SPEI puede registrar pagado un PAY junto con sus PAY de abono autorizados (selección múltiple en Por pagar, como hoy); en el SPEI múltiple `paid_amount` = `applied_amount` = `amount` de cada PAY. La captura de montos distintos solo es individual.
- Bloqueos: en cuanto un PAY se registra `pagado` con SPEI queda bloqueado para todos excepto admin: no cambia de estatus ni de importe, no se reemplaza su comprobante SPEI y `destroy` lo rechaza con «Este pago ya se registró con SPEI y no se puede modificar ni eliminar.» Admin puede corregir un pago `pagado`: reemplazar el comprobante SPEI, cambiar el importe pagado, regresarlo a `autorizado` o eliminarlo. Un PAY `autorizado` que ya generó resto, abonos o sustituciones tampoco se puede regresar a `por_autorizar`, rechazar, posponer ni eliminar; el mensaje cita los hitos y PAY afectados.
- Corrección de admin con saldo movido: si admin elimina un pago `pagado`, baja su importe pagado o lo regresa a `autorizado`, la misma transacción revierte sus efectos: baja `covered_amount` del hito por lo aplicado, elimina o reduce los abonos que generó (empezando por el último hito) y su saldo a favor. Cada hito que queda con saldo libre sin PAY pendiente que lo cubra recibe un PAY `por_autorizar` por ese saldo (`origin` = `replacement`), para que la caja siga cuadrada. Cada reversión registra NotificationService. La corrección usa `payments.update` (con `paid_amount` y, si se reemplaza, `spei_receipt_file`) y `payments.destroy`; ambas acciones sobre un pago `pagado` solo las ejecuta admin y responden 403 a Pagos. Bajar el importe pagado se trata como revertir el pago y volver a aplicarlo con el importe nuevo, con las mismas reglas de aplicado, abonos, tolerancia y saldo a favor.
- Admin no puede eliminar, bajar el importe ni regresar a `autorizado` un pago `pagado` que al autorizarse de más generó PAY de abono (`origin` = `credit`), estén autorizados o pagados. Mensaje: «Este pago generó abonos autorizados en PAY-##### y no se puede corregir.»
- Cambios de comportamiento respecto a hoy: rechazar o posponer un PAY `autorizado` que ya generó resto, abonos o sustituciones queda bloqueado; `destroy` y el reemplazo del comprobante SPEI de un pago `pagado` quedan solo para admin (también en pagos anteriores al despliegue); Pagos ya no los ve en el detalle de la OC ni en Por pagar.
- Caja por hito en el detalle de la OC: monto del hito, cubierto, saldo por pagar, barra y desglose (pagado · abono recibido · tolerancia · autorizado sin pagar · por autorizar), y en cada PAY su origen («Resto de PAY-#####», «Abono del excedente de PAY-#####», «Saldo del hito; sustituye a PAY-#####»). Encabezado de la OC con: monto, salió del banco (suma de `paid_amount`), cubierto, saldo por pagar y saldo a favor. Si el PAY de origen se eliminó (`source_payment_id` queda vacío), el texto dice «Resto de un PAY eliminado» o «Abono de un PAY eliminado».
- Cada acción del sistema (generar resto, abonar, sustituir, cubrir por tolerancia, saldo a favor) registra NotificationService con `type` = Payment y un texto con folios y montos, como la lista de Movimientos del simulador.
- Toda la operación (autorizar o registrar pagado más sus efectos en otros hitos) corre en una sola transacción. Bloquea la OC, después todos sus hitos en orden de `id` y después sus pagos; si algo falla no se guarda nada. Los excedentes se reparten entre los hitos siguientes en orden de `id` ascendente, sin saltar ninguno: el reparto se detiene en el primer hito que no puede recibirlo (saldo libre $0.00 sin estar cubierto, o checklist incompleto). Si el excedente no cabe hasta ese punto, la operación se rechaza con el máximo permitido; el saldo a favor solo se genera cuando todos los hitos siguientes ya quedaron cubiertos.
- Otros puntos que deben considerar abonos y sustituciones: `ensurePaymentTotalWithinAmount` (alta y reactivación) cuenta los abonos recibidos como cubierto. Los filtros de hitos pendientes con `covered_amount < value` (AdminController, SupplierPortalController, SupplierController, PurchaseOrderController línea 143 y `PurchaseOrder` línea 252) se quedan en SQL, porque se usan para ordenar (`withMin`), contar y limitar: cambian a `covered_amount < value - 0.50` para que un hito cubierto por tolerancia no aparezca como pendiente. Los hitos porcentuales siguen como hoy.
- Rendimiento: la caja por hito del detalle de la OC carga pagos y abonos con eager loading (sin consultas por hito) y las sumas de `paid_amount` se hacen en la consulta.
- Esquema: en `payments`, `original_amount`, `paid_amount` y `applied_amount` son `decimal(15,2)` nullable; `origin` es `string(20)` nullable con valores `initial`, `rest`, `credit`, `replacement` (vacío en pagos anteriores); `source_payment_id` y `replaced_by_payment_id` son `foreignId` nullable a `payments` con `nullOnDelete`. `status` sigue como ENUM de MySQL y se amplía con `ALTER TABLE ... MODIFY` como en `2026_08_05_000001_add_pospuesto_status_to_payments_table.php` (en SQLite no hace nada); su `down()` se detiene con un error si existen pagos `sustituido`. `purchase_order_milestone_credits`: `id`, `purchase_order_id` (foreignId, `cascadeOnDelete`), `milestone_id` destino (foreignId a `purchase_order_milestones`, `cascadeOnDelete`), `source_payment_id` (foreignId a `payments`, `restrictOnDelete`), `amount` `decimal(15,2)`, `user_id` (foreignId nullable a `users`, `nullOnDelete`), timestamps, sin soft deletes. `purchase_orders.supplier_credit_amount` `decimal(15,2)` default 0.
- Orden de implementación real (sustituye al de la sección «Orden de implementación», que es genérico para un cambio de regla): 1) migraciones; 2) modelos: casts, fillable y relaciones (`credits()`); 3) `MilestonePaymentService`; 4) controllers y rutas (`payments.preview`); 5) servicios de reportes, cierre, estado de cuenta, exportables y `purchase-orders:health`; 6) vistas. Detente al terminar cada capa y lista los archivos.

## 5. Validaciones

| Campo | Regla |
|---|---|
| `authorized_amount` | PaymentController@update al autorizar un PAY individual (admin): requerido, numeric, min:0.01, decimal:0,2; no mayor al saldo libre del hito más el de los hitos siguientes (más $0.50 de tolerancia) |
| `paid_amount` | PaymentController@update al registrar pagado individual (Pagos y admin): requerido junto con `spei_receipt_file`, numeric, min:0.01, decimal:0,2; si es menor que lo autorizado, la diferencia que deja en el hito no puede pasar de $0.50; sin tope superior |
| `spei_receipt_file` | Igual que hoy: requerido para registrar pagado, mimes:pdf,jpg,jpeg,png,webp, máx 10240 KB |
| `payments.status` | Agrega `sustituido`; no se acepta como valor de entrada en ninguna ruta |
| `monto, accion` | `payments.preview`: `accion` requerido, in:autorizar,pagar; `monto` requerido, numeric, min:0.01, decimal:0,2 |
| `payments.preview` | `accion=autorizar`: solo admin; `accion=pagar`: admin y Pagos (403 al resto) |
| `paid_amount (corrección)` | PaymentController@update sobre un pago `pagado`: solo admin; numeric, min:0.01, decimal:0,2; 403 a cualquier otro rol |
| `spei_receipt_file (corrección)` | PaymentController@update sobre un pago `pagado`: solo admin; mimes:pdf,jpg,jpeg,png,webp, máx 10240 KB; 403 a cualquier otro rol |
| `payments.destroy` | Sobre un pago `pagado`: solo admin; 403 a Pagos |

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- `docs/purchase-order-payment-health-flow.md` (Checklist de comprobación por hito): el checklist del hito también se valida al registrar pagado un PAY de abono y antes de abonar un pago de más a un hito.
- `docs/purchase-order-payment-health-flow.md` (Modelo de datos y Secuencia de estatus): el pago inicial de un hito es el PAY de menor id que no esté `sustituido` ni sea abono (`origin` = `credit`), no simplemente el de menor id.
- `docs/purchase-order-payment-health-flow.md` (Secuencia de estatus): además de comparar pagos iniciales, ningún PAY de un hito se autoriza si el hito anterior no está aprobado ni se registra pagado si el hito anterior no está pagado completo, para todos los tipos de hito.

## 7. Preguntas abiertas

- [x] Abonos y checklist/secuencia: un abono (por autorizar o pagar de más) cubre un hito siguiente que puede no tener el checklist completo (entrega confirmada o factura) y puede autorizarse antes que el pago inicial de un hito anterior. ¿Se permite como excepción, igual que en el simulador (el abono funciona como un anticipo), o solo se abona a hitos con checklist completo? → **Ajuste final: no hay excepción. Los abonos se autorizan y se pagan en orden documental y de tipo de hito, como cualquier pago (solo admin conserva su excepción de checklist).**
- [x] Saldo a favor (RF-20): ¿dónde se registra el excedente que no cabe en ningún hito y cómo se usa después? Propuesta: en este spec solo se guarda en la OC (`purchase_orders.supplier_credit_amount`) y se muestra en el detalle de la OC y en el estado de cuenta del proveedor; aplicarlo a otra OC o pedir la devolución va en otro spec. → **Sí se registra: lo que un pago de más ya no puede abonar a ningún hito pasa a saldo a favor, en la columna nueva `purchase_orders.supplier_credit_amount`, y se refleja en los indicadores económicos de la OC.**
- [x] Importes con pago de más: cuando un SPEI paga más de lo que se aplica al hito, ¿qué importe usa cada lugar que hoy suma `payments.amount` de los pagos `pagado`: el importe del SPEI (`paid_amount`) o lo aplicado al hito? Lugares: resumen económico de la OC (`PurchaseOrderEconomicSummary`), estado de cuenta del proveedor (`SupplierAccountStatementService`), cierre de la OC (`PurchaseOrderClosureEvaluator`) y exportables de Pagados y Por pagar. → **El importe del SPEI (`paid_amount`) en el estado de cuenta del proveedor, el cierre de la OC y los exportables de Pagados y Por pagar. Los pagos anteriores sin `paid_amount` se leen como `amount`. La cobertura de cada hito sigue saliendo de `covered_amount` (aplicado + abonos), para no contar dos veces un adelanto.**
- [x] Abonos y regularización documental: un hito cubierto total o parcialmente por un abono, sin factura aceptada ligada, ¿debe aparecer en Regularización documental (`payments.regularization`) como hoy aparece un pago `pagado` sin factura? → **No. La factura es por el importe total del hito; los abonos no se mezclan con ella y la regularización documental sigue igual que hoy.**
- [x] `PurchaseOrder::saldo_cubierto` (lo que el resumen económico muestra como pagado): ¿usa el importe del SPEI (`paid_amount`), como el estado de cuenta, o lo aplicado a los hitos más abonos (`covered_amount`)? El encabezado de la OC ya muestra «salió del banco» y «cubierto» por separado. → **Usa el importe del SPEI (`paid_amount`; si está vacío, `amount`).**
- [x] Bloqueo de un PAY pagado: hoy se puede reemplazar el comprobante SPEI de un pago `pagado`. ¿El bloqueo también impide reemplazar el comprobante, o solo el estatus, el importe y eliminar? → **Sí: el bloqueo también impide reemplazar el comprobante SPEI.**
- [x] Rol Dirección: en esta rama no existe; `payments.update` está en `role:admin|Pagos` y solo admin autoriza. ¿El spec dice solo «admin», o deja «admin/Dirección» porque el rol llega con el trabajo de la matriz de roles? → **Solo admin.**
- [x] Estado de cuenta al cotejar facturas: `SupplierAccountStatementService` reparte cada pago contra las facturas de su propio hito. Un SPEI de $50 con $30 aplicados al hito 2 y $20 abonados al hito 3 se cotejaría completo contra la factura del hito 2 y marcaría «Por conciliar» sin error real. Opción A: totales con `paid_amount` y, al cotejar facturas, lo aplicado a cada hito más sus abonos. Opción B: dejarlo así y aceptar esas marcas. → **Opción A: totales con `paid_amount`; al cotejar facturas, lo aplicado a cada hito más sus abonos.**
- [x] Corrección de un SPEI mal capturado: con `destroy` y el reemplazo del comprobante bloqueados para todo pago `pagado` (también los anteriores), no queda una vía en el sistema. ¿Admin conserva una vía de corrección por su excepción, o se corrige fuera del sistema? → **Admin corrige: puede reemplazar el comprobante, cambiar el importe pagado o eliminar un pago `pagado`.**
- [x] Abonos pagados antes que el hito 1: la excepción de abonos permite autorizar y pagar PAY de abono en hitos siguientes antes de que el hito anterior esté pagado. ¿Se anota como riesgo aceptado o basta con la regla? → **No se permite: los abonos ya autorizados deben cumplir los requisitos documentales de su hito (entrega y factura) y la regla general impide registrar pagado un saldo de un hito si el anterior no está pagado completo.**
- [x] Corrección de admin sobre un pago que ya generó abonos o saldo a favor: si admin elimina o baja el importe de ese pago, ¿la corrección revierte en la misma operación los abonos, el saldo a favor y las sustituciones que generó, o admin solo puede corregir pagos que no movieron saldo? → **Sí, se revierten en la misma operación.**
- [x] Reparto de excedentes y regla de orden: si el hito siguiente tiene un PAY autorizado sin pagar, su saldo libre es 0 pero no está cubierto. ¿El excedente se detiene en ese hito o lo salta? Saltarlo contradice la regla de pago en orden de hitos. → **No se salta ningún hito: el reparto se detiene en el primer hito que no puede recibir el excedente y, si no cabe, la operación se rechaza. Se autoriza en orden documental y de tipo de hito: un pago futuro no se autoriza hasta que el hito anterior esté aprobado.**
- [x] Estatus de un pago `pagado`: ¿admin puede regresarlo a `autorizado`, o solo corregir el importe, reemplazar el comprobante o eliminarlo? → **Sí, admin puede regresarlo a `autorizado`; se revierten sus efectos igual que en la corrección.**
- [x] PAY de abono de un pago corregido: si admin elimina un `pagado` que al autorizarse de más generó PAY de abono (`origin` = `credit`), ¿qué pasa con esos PAY si siguen autorizados y qué pasa si ya están pagados? → **No se permite el flujo: un pago que generó PAY de abono al autorizarse de más no se puede eliminar, bajar de importe ni regresar a autorizado.**
- [x] Alcance de la regla de orden: ¿aplica también a hitos de anticipo y a hitos con plazo de crédito propio (`MilestoneCreditTerms`)? → **Sí, aplica a todos los tipos de hito, también anticipos y hitos con plazo de crédito propio.**
- [x] OCs existentes fuera de orden: las que hoy reporta `purchase-orders:health` se bloquearían al pagar sus PAY pendientes con la regla de orden. ¿Admin las desbloquea con su excepción o se necesita otro plan? → **Se bloquean; se regularizan directamente en la base de datos, fuera del sistema.**
- [x] Riesgos: ¿se anotan como riesgos aceptados el bloqueo de la tabla `payments` durante la migración del ENUM y el cambio visible para Pagos (pierde eliminar y reemplazar SPEI de pagos `pagado`)? → **Sí, se anotan como riesgos aceptados.**

## 8. Fuera de alcance

- Rutas API (routes/api.php)
- Unit / Feature tests
- Factories y seeders
- Form Requests
- Observers / Event listeners
- Jobs y colas
- Policies / Gates
- Clases Mail / Notification de Laravel
- API Resources / Transformers
- Instalar paquetes no listados en el perfil
- Cambios a vite.config.js / package.json
- Aplicar el saldo a favor a otra OC o registrar su devolución
- Captura de monto en Autorizar en bloque, swipe, Autorizar OC con pagos o SPEI múltiple
- Recalcular `covered_amount` o generar abonos sobre pagos anteriores al despliegue
- Cambiar la tolerancia de $0.50 por hito
- Artisan commands nuevos: solo se actualiza el closure `purchase-orders:health` de routes/console.php
- Deshacer abonos o sustituciones fuera de la corrección de admin de un pago `pagado`
- Corregir los filtros de hitos pendientes para hitos porcentuales (`covered_amount < value` compara contra el porcentaje)
- Regularizar las OCs existentes que están fuera de orden (las reporta `purchase-orders:health`): se hace en la base de datos
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

Detente al terminar cada capa y lista los archivos creados o modificados.

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en el controller: Al autorizar un PAY individual (admin) se captura el monto (precargado al 100 %). Si es menor, el PAY se autoriza por ese monto y el sistema genera un PAY por el resto, salvo que el resto quepa en la tolerancia de $0.50 por hito. Si es mayor que el saldo del hito, el PAY se autoriza por el saldo y el excedente se convierte en PAY autorizados de abono en los hitos siguientes. Al registrar pagado (Pagos) se captura el importe del SPEI (precargado con lo autorizado): un pago de más cubre el hito hasta su monto y abona el excedente a los hitos siguientes; lo que ya no cabe en ningún hito de la OC pasa a saldo a favor con el proveedor, que se guarda en la OC y se refleja en sus indicadores económicos. Después de cada movimiento, los PAY pendientes de un hito que ya no caben en su saldo se sustituyen por un solo PAY por el saldo.
- [ ] Caso: Guion completo del flujo (OC de $100, $80 y $200, con el checklist completo en los tres hitos): al terminar, salió del banco $380.00, cada hito tiene saldo $0.00, la OC no tiene PAY por autorizar ni autorizados y los PAY sustituidos no cuentan para cerrarla.
- [ ] Caso: Hito 1 ($100, PAY de $100 sin pagos): autorizar $80.00 deja el PAY autorizado por $80.00 con `original_amount` $100.00 y genera un PAY por $20.00 `por_autorizar`, `origin` = rest, con la fecha de vencimiento del hito.
- [ ] Caso: Autorizar $99.60 del PAY de $100.00: no se genera PAY; al pagarse los $99.60 el hito queda pagado completo (los $0.40 se cubren por tolerancia).
- [ ] Caso: Registrar un SPEI por $79.70 de un PAY autorizado por $80.00: se acepta y los $0.30 se cubren por tolerancia. Un SPEI por $79.00 se rechaza con «Pagar menos de lo autorizado no está permitido: el monto se decide al autorizar.»
- [ ] Caso: Hito 2 ($80) con $50 pagados y un PAY de $30 autorizado, y el hito 3 con checklist completo: un SPEI por $50.00 aplica $30.00 al hito 2 (queda pagado completo), abona $20.00 al hito 3 y su PAY de $200.00 pasa a `sustituido` por un PAY nuevo de $180.00 `por_autorizar`.
- [ ] Caso: Autorizar $250.00 en el PAY de $100.00 del hito 1 (OC sin pagos, hitos 2 y 3 con checklist completo): el PAY queda autorizado por $100.00; se genera un PAY autorizado de abono por $80.00 en el hito 2 (su PAY pendiente de $80.00 pasa a `sustituido` porque el hito ya quedó cubierto) y otro por $70.00 en el hito 3 (su PAY de $200.00 pasa a `sustituido` por uno de $130.00).
- [ ] Caso: Autorizar más que el saldo del hito y de los siguientes regresa «Lo más que se puede autorizar es $X: el saldo de este hito y de los siguientes.» ($X ya incluye los $0.50 de tolerancia) En el último hito: «No hay hito siguiente con saldo donde abonar el excedente. Lo más que se puede autorizar es $X, el saldo de este hito.»
- [ ] Caso: Un SPEI de más en el último hito, o con un excedente mayor que el saldo libre de los hitos siguientes: se abona hasta llenar los hitos siguientes y el resto se suma a `purchase_orders.supplier_credit_amount`; los indicadores económicos de la OC lo muestran como «Saldo a favor».
- [ ] Caso: Autorizar en bloque o por swipe un PAY de $80.00: queda autorizado por $80.00 sin capturar monto y no se genera nada más.
- [ ] Caso: Pagos no puede regresar a otro estatus, cambiar de importe, reemplazar el comprobante SPEI ni eliminar un pago `pagado`: responde «Este pago ya se registró con SPEI y no se puede modificar ni eliminar.» Admin sí puede corregirlo.
- [ ] Caso: Autorizar $80.40 en el PAY de $80.00 de un hito de $80 sin pagos: se acepta como excepción de tolerancia, sin abono al siguiente hito.
- [ ] Caso: Un solo SPEI registra pagados el PAY de $100.00 del hito 1 y su PAY de abono autorizado de $80.00 del hito 2 (selección múltiple en Por pagar), siempre que el hito 2 tenga su checklist completo.
- [ ] Caso: La vista previa de autorizar $250.00 en el PAY del hito 1 lista los PAY de abono y las sustituciones del caso anterior sin guardar nada.
- [ ] Caso: Con el hito 1 sin pagar completo, registrar pagado un PAY de abono autorizado del hito 2 se rechaza con «No se puede registrar pagado: el Hito 1 aún no está pagado completo.»; con el hito 1 pagado pero sin entrega confirmada en el hito 2, se rechaza por el checklist.
- [ ] Caso: Admin elimina el SPEI de $50.00 del hito 2 que aplicó $30.00 y abonó $20.00 al hito 3 (con el PAY de $180.00 del hito 3 ya pagado): el hito 2 baja $30.00 de cubierto y recibe un PAY `por_autorizar` por $30.00; el abono de $20.00 se elimina y el hito 3 recibe un PAY `por_autorizar` por $20.00; todo en una sola operación.
- [ ] Caso: Autorizar $250.00 en el PAY del hito 1 con el hito 2 sin entrega confirmada: se rechaza con el máximo permitido ($100.50), porque el excedente no puede pasar al hito 2 ni saltarlo.
- [ ] Caso: Con el hito 1 sin aprobar (un PAY por autorizar), autorizar el PAY del hito 2 se rechaza con «No se puede autorizar: el Hito 1 aún no está aprobado.», también en autorizar en bloque.
- [ ] Caso: Admin intenta eliminar un pago `pagado` del hito 1 que al autorizarse de más generó PAY de abono en los hitos 2 y 3: se rechaza con «Este pago generó abonos autorizados en PAY-##### y no se puede corregir.»
- [ ] Datos históricos: Los pagos existentes no cambian: `original_amount`, `paid_amount`, `applied_amount`, `origin`, `source_payment_id` y `replaced_by_payment_id` quedan vacíos; un `paid_amount` o `applied_amount` vacío se lee como `amount`. No se recalcula `covered_amount` de ningún hito ni se generan abonos ni sustituciones sobre pagos anteriores al despliegue. Para hitos con datos anteriores, el saldo libre se calcula con el `covered_amount` guardado. Los excesos históricos que reporta `php artisan purchase-orders:health` siguen igual. Las OCs existentes quedan con `supplier_credit_amount` en 0. Excepción: la corrección de admin de un pago `pagado` (también de uno anterior al despliegue) sí puede generar PAY de reposición (`origin` = `replacement`) en los hitos afectados.
- [ ] Permanece igual: Al crear un hito se genera su PAY inicial por el 100 %, `por_autorizar`, con fecha = `due_date`.
- [ ] Permanece igual: El checklist del hito (`PurchaseOrderMilestoneChecklist::ensureComplete`) se valida antes de autorizar cualquier PAY normal, también el PAY del resto; los PAY de abono lo validan al registrarse pagados.
- [ ] Permanece igual: La secuencia de estatus y fechas entre hitos (`PurchaseOrderPaymentSequenceValidator`), con el ajuste de qué cuenta como pago inicial y la regla general de pago en orden de hitos.
- [ ] Permanece igual: El límite de pagos por hito: la suma de pagos comprometidos no supera `effective_amount` (con `amount` ya ajustado al autorizado), salvo la excepción de tolerancia: `ensurePaymentTotalWithinAmount` admite hasta $0.50 sobre el monto del hito.
- [ ] Permanece igual: Autorizar en bloque, el modo interactivo (swipe) y Autorizar OC con pagos siguen autorizando el 100 % de cada PAY.
- [ ] Permanece igual: El SPEI múltiple (`markMultiplePaidWithSpei`) registra cada pago por lo autorizado (`paid_amount` = `amount`).
- [ ] Permanece igual: Dividir el pago inicial (`payments.store`) y Modificar monto del hito siguen disponibles; «único pago del hito» cuenta solo los PAY no sustituidos.
- [ ] Permanece igual: Rechazar, posponer y solicitar reactivación siguen igual para los PAY que no están bloqueados (ver Bloqueos en Reglas de negocio); comprobante SPEI obligatorio para registrar pagado.
- [ ] Permanece igual: Bloqueo de OC cerrada (`PurchaseOrder::ensureOpen`) y transacción con bloqueo OC → hitos → pagos.
- [ ] Permanece igual: Regularización documental y la regla de una factura por hito: la factura es por el importe total del hito y los abonos no participan.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-006]` en el mensaje.

---

_Generado con Estoria Spec Tool v4 · Estoria Studio_
