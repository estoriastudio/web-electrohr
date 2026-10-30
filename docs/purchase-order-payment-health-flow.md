# Flujo de salud de Ordenes de Compra, hitos y pagos

## Objetivo

Este documento describe el flujo operativo de una Orden de Compra (OC) y las reglas que preservan la consistencia entre sus hitos y pagos.

Tambien documenta el comando `php artisan purchase-orders:health`, que detecta registros historicos que incumplen estas reglas. Las validaciones de aplicacion previenen que se creen nuevas inconsistencias; el reporte no modifica datos existentes.

## Modelo de datos y alcance

La revision siempre se hace dentro de una sola OC. No se comparan hitos ni pagos de ordenes distintas.

```text
purchase_orders
  └── purchase_order_milestones (purchase_order_id)
        └── payments (milestone_id)
```

- Una OC puede tener varios hitos.
- Un hito puede tener varios pagos, por ejemplo cuando se divide su pago inicial.
- Para la salud de una OC, los hitos se ordenan por `purchase_order_milestones.id` ascendente.
- Dentro de cada hito, los pagos se ordenan por `payments.id` ascendente.
- El pago inicial es el pago con menor `id` de un hito. Solo ese pago participa en las reglas de secuencia entre hitos.
- El alcance operativo y del reporte son OCs activas: se excluyen las archivadas (`archived_at`) y las eliminadas lógicamente.

## Flujo de la OC

### Estados de la OC

```text
pendiente -> emitida -> autorizada
```

- `pendiente`: OC creada y editable.
- `emitida`: OC enviada para autorizacion.
- `autorizada`: OC habilitada para registrar y procesar pagos.

Solo un administrador puede autorizar una OC. Las OCs autorizadas restringen su edicion; las OCs de destajo mantienen las excepciones operativas definidas en los controladores.

### Alta y modificacion de hitos

Al crear un hito se genera un pago inicial con:

- Monto igual al importe efectivo del hito.
- Fecha de pago igual a `due_date` del hito.
- Estatus `por_autorizar`.

Al editar un hito:

- Las condiciones, descripcion y plazo de un hito existente se editan solo por Direccion (`admin`). El formulario general no permite cambiar el importe: se debe usar **Modificar monto**.
- Si ya tiene pagos pendientes, se ajustan sus montos y la fecha de esos pagos conforme a la fecha del hito.
- Si es un hito legado sin pagos, se crea su pago inicial durante la actualizacion.
- Antes de crear o modificar un pago inicial, se valida que su fecha conserve el orden de fechas dentro de la OC.

### Modificar monto y generar una parcialidad

- Disponible para `admin` o el comprador asignado a la OC (`Orden de compra` y `buyer_id` igual al usuario), tambien en OCs autorizadas. Una OC cerrada no admite ajustes.
- El hito no puede tener ningun pago `autorizado` o `pagado`. El nuevo monto debe ser positivo, no menor que lo facturado aceptado vinculado (`net_scope`, o `amount` si no existe alcance liquido), ni que `covered_amount`.
- La suma de los hitos no puede superar el total neto de la OC. El importe se guarda como `fijo`, incluso si antes era un porcentaje. Si la conversion alteraria por redondeo el importe de otro hito porcentual, se rechaza para que Direccion revise la distribucion, sin cambios parciales.
- La descripcion, modalidad, dias de credito y origen del plazo del hito editado no cambian.
- Si disminuye el importe, se genera siempre una parcialidad por la diferencia, con su pago inicial `por_autorizar`; la suma de hitos se conserva. El comprador puede definir las condiciones del **nuevo** hito, precargadas con las del origen. No se copian facturas, cumplimiento ni fechas base.
- Los pagos pendientes y pospuestos del hito original se redistribuyen proporcionalmente; los rechazados permanecen como historial y no consumen saldo.
- Cada ajuste deja una bitacora persistente: usuario, fecha, monto anterior/nuevo, tipo/valor anterior y motivo obligatorio. `split_from_milestone_id` identifica el origen de la parcialidad.
- El despliegue requiere la migracion `2026_10_27_000000_add_milestone_amount_adjustments.php`; no modifica automaticamente las vinculaciones historicas de facturas.

### Una factura por hito

Las nuevas vinculaciones permiten una sola factura por hito y un solo hito por factura, tanto al cargar como al aceptar la factura. Un hito ocupado no se ofrece para otra factura, incluso si la vinculada esta rechazada. Aceptar otra vez la misma factura con su mismo hito es valido. Los datos historicos multi-hito no se borran ni se redistribuyen automaticamente.

### Flujo de pagos

Los estatus operativos de pago son:

```text
por_autorizar / pospuesto -> autorizado -> pagado
```

- `por_autorizar`: pago pendiente de autorizacion.
- `pospuesto`: pago pendiente temporalmente diferido; tiene el mismo nivel de avance que `por_autorizar` para la regla de secuencia.
- `autorizado`: pago aprobado para pago.
- `pagado`: pago finalizado; incrementa `covered_amount` del hito.
- `rechazado`: pago que requiere revision. Se permite para conservar el flujo de solicitud de reactivacion, pero el reporte lo informa como alerta.

Los pagos se pueden crear desde un hito autorizado, dividir cuando el pago inicial aun esta por autorizar, autorizar individualmente o en bloque, pagar con comprobante SPEI individual o multiple, posponer, rechazar y solicitar su reactivacion.

**Limite de pagos por hito**: la suma de `amount` de todos sus pagos `por_autorizar`, `autorizado`, `pagado` y `pospuesto` debe ser menor o igual a `effective_amount`. Los rechazados se excluyen para poder reemplazarlos. Al reactivarlos (solicitud, cambio por admin o deshacer mediante swipe) se vuelve a validar el saldo. No se suma `covered_amount` nuevamente: los pagos pagados ya estan incluidos. El total exacto esta permitido y un exceso de $0.01 se rechaza sin guardar cambios parciales.

Las altas y reactivaciones se validan en servidor bajo transaccion, bloqueando OC, hito y pagos en ese orden. El formulario muestra el mismo saldo. Los excesos historicos no se corrigen ni se eliminan automaticamente; no se permiten nuevas altas/reactivaciones que mantengan el exceso.

## Checklist de comprobacion por hito

Antes de que un pago pueda autorizarse, su hito debe cumplir un checklist. Admin solo verifica el checklist y autoriza; Pagos recibe pagos que ya cumplieron la regla.

| Tipo de hito | Requisitos |
|---|---|
| Anticipo (`is_advance`) | Ninguno |
| Contado | Entrega confirmada |
| Credito, inicio del plazo = validacion de factura (valor de hitos legados) | Entrega confirmada + al menos una factura `aceptada` ligada al hito (`invoice_milestone`) |
| Credito, inicio del plazo = cumplimiento validado | Entrega confirmada; la factura no es requisito para autorizar (se regulariza despues) |

- **Entrega confirmada**: `delivery_confirmed_at/by` significa "cumplimiento verificado" para cualquier tipo. El tipo se elige en el modal Entregas y no se guarda en el hito; se deduce: con entregas ligadas (`purchase_order_item_deliveries.milestone_id`) es Recepcion fisica; con un registro en `purchase_order_milestone_fulfillments` (unique por hito; tipos `estimation`, `documentary`, `advance`) es el tipo del registro; si no, queda sin tipo (`PurchaseOrderMilestone::fulfillment_type`). Recepcion fisica: con al menos una entrega ligada, un usuario con permiso de entregas confirma "entrega completa para este hito". Estimacion, Documental y Anticipo: `POST /ordenes-de-compra/{purchase_order}/cumplimientos` (`purchase_orders.fulfillments.store`, comprador de la OC y admin) guarda la evidencia, crea el registro y verifica el hito en el mismo paso. Un hito se cumple por un solo tipo (no se mezclan entregas y registro). Revocar (`confirmMilestoneDelivery`) elimina el registro y conserva el archivo; no se puede eliminar desde Evidencias la evidencia de un registro vigente. Revertir la ultima entrega ligada limpia la confirmacion solo si el hito tampoco tiene registro. Los hitos de OCs ya `entregado` al desplegar quedaron confirmados por la migracion.
- **Bloqueo**: `PurchaseOrderMilestoneChecklist::ensureComplete` se invoca en `PaymentController::swipe`, `update` (a `autorizado`), `authorizeMultiple` y `PurchaseOrderController::approveWithPayments`. Al autorizar se guarda `payments.checklist_verified_at`, visible en la bandeja Por pagar.
- **Visibilidad**: detalle de la OC (panel por hito), `payments.index` (badges, autorizar deshabilitado y filtro), modo interactivo (solo muestra pagos con checklist completo), `milestones.index` (columna y filtro).
- La seleccion persistida para autorizar en bloque descarta pagos con checklist pendiente.

## Estado del evento, regularizacion documental y resumen economico

Cada hito se trata como un evento de pago con tres dimensiones independientes (`MilestoneEventStatus`):

| Dimension | Completa cuando |
|---|---|
| Cumplimiento | La entrega del hito esta confirmada (tambien en anticipos) |
| Facturacion | Hay al menos una factura `aceptada` ligada al hito |
| Pago | El hito esta cubierto (`covered_amount` >= monto); `partial` si hay pagos parciales |

El evento esta **cerrado** cuando las tres estan completas; si no, se muestra "Abierto – pendiente de ..." con lo que falta. Se ve en el detalle de la OC y en el listado de hitos.

- **Regularizacion documental**: un pago `pagado` cuyo hito no tiene factura aceptada. `payments.regularization` (admin, Pagos, Recepcion, Orden de compra; Compras solo ve sus OCs) lista esos pagos y su antiguedad. Al aceptar la primera factura de un hito, los pagos ya pagados quedan ligados a ella (`payments.regularization_invoice_id`, `regularized_at`) sin crear pagos ni importes nuevos; rechazar o eliminar la factura libera esa vinculacion. Los pagos historicos sin factura aparecen en la lista hasta que se acepte una factura ligada a su hito.
- **Resumen economico** (`PurchaseOrderEconomicSummary`, detalle de la OC): importe total, facturado (solo aceptadas, neto de notas de credito), pendiente de facturar, pagado, pendiente de pagar y facturado sin pagar.

## Plazo de credito

Los hitos a credito (no anticipo) definen `credit_start` (`invoice_validation` | `fulfillment_validated`) y `credit_days`. `MilestoneCreditTerms` calcula el vencimiento = fecha base + dias:

- Base `invoice_validation`: la primera fecha de aceptacion (`purchase_order_invoices.accepted_at`) de una factura aceptada ligada al hito.
- Base `fulfillment_validated`: `delivery_confirmed_at` del hito.
- Se recalcula al aceptar una factura (`PurchaseOrderInvoiceController::updateStatus`), al confirmar la entrega (`confirmMilestoneDelivery`) y al editar el hito. Actualiza `due_date` y la fecha de los pagos `por_autorizar` / `pospuesto`.
- Cada hito calcula su vencimiento desde su propia base, aun si resulta anterior al de un hito previo; el recalculo automatico no se bloquea por la secuencia de fechas de otros hitos.
- Antes de que exista la fecha base, `due_date` es la fecha estimada capturada. Los hitos a credito existentes quedaron con `invoice_validation` y sin dias, por lo que conservan su fecha manual.

## Cierre de la OC

El cierre es un estatus propio (`purchase_orders.closed_at`, `closed_by`, `closure_note`), independiente de `status` (`emitida|pendiente|autorizada`) para no alterar las restricciones de edicion de OCs autorizadas. Una OC cerrada se muestra con la etiqueta "Cerrada", tiene la bandeja "Cerradas" en el listado y queda bloqueada.

`PurchaseOrderClosureEvaluator` decide si una OC puede cerrarse. Requiere todo lo siguiente:

| Requisito | Regla |
|---|---|
| OC autorizada | `status = autorizada` |
| Entrega completa | `delivery_status = entregado` (una OC sin conceptos cumple), o la OC no tiene entregas fisicas y todos sus hitos tienen un registro de cumplimiento |
| Todos los eventos cerrados | Existe al menos un hito y cada uno tiene cumplimiento, facturacion y pago completos (`MilestoneEventStatus`); los anticipos tambien requieren entrega confirmada |
| Pagos completos | Todos los pagos estan `pagado`, cada hito esta cubierto y los hitos cubren el total de la OC |
| Importe facturado completo | Las facturas `aceptadas` (netas de notas de credito) cubren el total de la OC, con tolerancia de $0.50 |
| Sin regularizaciones documentales | Ningun pago `pagado` en hitos sin factura aceptada y ninguna factura `en_proceso` |

- **Cerrar** (`PurchaseOrderController::close`): admin o el comprador de la OC; revalida los requisitos dentro de una transaccion y registra quien y cuando cerro, con nota opcional.
- **Reabrir** (`PurchaseOrderController::reopen`): solo admin, con motivo obligatorio (queda en notificaciones).
- **Bloqueo**: `PurchaseOrder::ensureOpen()` rechaza cambios en la OC, sus conceptos, hitos, pagos, entregas y facturas hasta reabrirla.
- **Migracion**: las OCs ya entregadas y pagadas quedaron cerradas con una nota de cierre automatico.

## Invariantes de salud

### 1. Secuencia de estatus

El pago inicial de un hito posterior no puede tener un nivel de avance mayor que el pago inicial de un hito anterior de la misma OC.

Ejemplo invalido:

```text
Hito 1: por_autorizar
Hito 2: autorizado
```

Ejemplo valido:

```text
Hito 1: autorizado
Hito 2: autorizado
Hito 3: por_autorizar
```

La regla se evalua antes de guardar cambios de estatus. En operaciones masivas, todos los pagos seleccionados de la misma OC se evalúan juntos para permitir una autorizacion o pago simultaneo consistente.

### 2. Secuencia de fechas

Las fechas manuales/provisionales de los pagos iniciales deben ser no decrecientes conforme al orden de los hitos. El vencimiento automatico de credito es independiente: fecha base propia + dias pactados. El reporte de salud excluye de la comparacion de fechas los hitos con plazo de credito propio.

Ejemplo invalido:

```text
Hito 1: 2026-09-15
Hito 2: 2026-09-01
```

La validacion revisa tanto el hito anterior como el posterior cuando aplica. Por eso evita que la edicion de un hito intermedio rompa una secuencia que ya era valida.

### 3. Hitos sin pagos

Un hito sin pagos no se compara hasta tener un pago inicial. Cuando se crea ese primer pago posteriormente, su fecha se valida contra los hitos con pago anteriores y posteriores de la misma OC.

## Implementacion de las validaciones

El servicio [app/Services/PurchaseOrderPaymentSequenceValidator.php](../app/Services/PurchaseOrderPaymentSequenceValidator.php) centraliza las invariantes de estatus y fechas.

Se invoca desde estos puntos de mutacion:

| Flujo | Controlador | Proteccion |
|---|---|---|
| Crear hito | `PurchaseOrderMilestoneController::store` | Fecha del nuevo pago inicial |
| Editar hito | `PurchaseOrderMilestoneController::update` | Fecha del pago inicial actualizado o creado para un hito legado |
| Registrar pago | `PaymentController::store` | Fecha cuando el pago es el primero del hito |
| Cambiar estatus individual | `PaymentController::update` | Secuencia del pago inicial |
| Autorizar/rechazar/posponer por swipe | `PaymentController::swipe` | Secuencia del pago inicial |
| Reactivar pago rechazado | `PaymentController::requestReactivation` | Secuencia del pago inicial |
| Marcar pagos pagados con SPEI | `PaymentController::markMultiplePaidWithSpei` | Secuencia agrupada por OC |
| Autorizar pagos multiples | `PaymentController::authorizeMultiple` | Secuencia agrupada por OC |
| Autorizar OC con pagos seleccionados | `PurchaseOrderController::approveWithPayments` | Secuencia de los pagos de esa OC |

El servicio carga los hitos por `purchase_order_id` y agrupa los pagos por `milestone_id`. Este aislamiento es obligatorio para evitar que pagos de otras OCs afecten una validacion.

### Presentacion de errores de validacion

El layout [resources/views/layouts/app.blade.php](../resources/views/layouts/app.blade.php) muestra automáticamente un modal cuando existe un error de sesion (`session('error')`) o errores de validacion (`$errors`).

Esto aplica tanto al detalle de la OC como a las bandejas de pagos. Los errores de secuencia muestran el motivo en esa ventana para que el usuario pueda corregir la operacion sin depender de las alertas superiores de la pagina. No se deben abrir modales adicionales de forma automática para el mismo error, ya que pueden superponerse al modal global.

## Reporte de salud

Ejecutar:

```bash
php artisan purchase-orders:health
```

El reporte es de solo lectura y presenta:

- `Alertas de estatus`: pagos iniciales con avance fuera de orden entre hitos de una misma OC.
- `Pagos rechazados`: pagos iniciales rechazados, que requieren revision pero no son bloqueados por el flujo.
- `Alertas de fechas`: fechas de pagos iniciales fuera de orden entre hitos de una misma OC.
- `Hitos omitidos sin pagos`: registros que aun no tienen pago inicial y no pueden compararse.

Cada categoria muestra el conteo y el listado sin duplicados de folios de OCs afectadas. Las alertas individuales indican los hitos, pagos y valores que originaron el hallazgo.

## Ajustes futuros

Antes de cambiar las reglas, definir el efecto esperado sobre las OCs ya existentes y actualizar el servicio, el reporte y sus pruebas de forma coordinada.

| Cambio propuesto | Lugares que deben revisarse |
|---|---|
| Agregar o cambiar estatus de pago | Rango en `PurchaseOrderPaymentSequenceValidator`, leyenda del reporte, transiciones de `PaymentController` y pruebas |
| Cambiar el orden de los hitos | Servicio validador, comando de salud, vistas de OC y reglas de fechas |
| Comparar todos los pagos, no solo el inicial | Servicio, comando, manejo de pagos divididos y pruebas de pagos multiples |
| Convertir pagos rechazados en bloqueo | Flujo de reactivacion, permisos, UI y reporte |
| Agregar reglas de montos, facturas o vencimientos | Nueva categoria de reporte, validacion transaccional y cobertura de pruebas |
| Incluir OCs archivadas | Consulta del comando y definicion de alcance; no habilitar mutaciones sobre registros archivados sin una regla adicional |

## Verificacion recomendada

1. Crear una OC con varios hitos y pagos iniciales en fechas crecientes.
2. Intentar autorizar o pagar un hito posterior mientras uno anterior sigue pendiente; la solicitud debe ser rechazada.
3. Intentar asignar una fecha anterior a un hito posterior; la solicitud debe ser rechazada.
4. Autorizar o pagar varios hitos de la misma OC en una sola operacion; debe permitirse si el resultado final conserva la secuencia.
5. Ejecutar `php artisan purchase-orders:health` para revisar datos historicos y confirmar que las nuevas OCs no generan alertas.

## Pruebas disponibles

- [tests/Unit/PurchaseOrderPaymentSequenceValidatorTest.php](../tests/Unit/PurchaseOrderPaymentSequenceValidatorTest.php): contrato de validacion de secuencias.
- [tests/Feature/PurchaseOrderHealthReportCommandTest.php](../tests/Feature/PurchaseOrderHealthReportCommandTest.php): salida y aislamiento por OC del reporte.
- [tests/Feature/PaymentMultipleMilestoneTest.php](../tests/Feature/PaymentMultipleMilestoneTest.php): comportamiento de pagos divididos y operaciones de pago.
- [tests/Feature/MilestoneEventStatusTest.php](../tests/Feature/MilestoneEventStatusTest.php): estado del evento, regularizacion documental y resumen economico.
- [tests/Feature/MilestoneCreditTermsTest.php](../tests/Feature/MilestoneCreditTermsTest.php): plazo de credito, recalculo de vencimiento y checklist segun el inicio del plazo.
- [tests/Feature/PurchaseOrderClosureTest.php](../tests/Feature/PurchaseOrderClosureTest.php): requisitos, cierre, reapertura y bloqueo de la OC.
- [tests/Feature/MilestoneChecklistTest.php](../tests/Feature/MilestoneChecklistTest.php): reglas del checklist, bloqueo de autorizacion y entregas ligadas a hitos.

Actualmente las pruebas que usan `RefreshDatabase` requieren que las migraciones SQLite sean compatibles. La migracion existente de `project_documents` usa `ALTER TABLE ... MODIFY`, sintaxis que SQLite no admite; mientras no se ajuste, las pruebas se detienen antes de ejecutar sus aserciones.