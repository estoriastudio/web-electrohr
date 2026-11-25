---
module: milestones
name: Hitos de pago
area: Pagos
covers: [HR-006, HR-012]
generated_at: 2026-11-25
status: generado
verified_by:
verified_at:
---

# Hitos de pago

<!-- gen:intro -->
Un hito es una condición de pago de una orden de compra (OC): cuánto se paga, cuándo vence y qué se debe cumplir antes de autorizar el pago. Cada hito tiene uno o más pagos (**PAY**) que pasan por autorizar y registrar el SPEI. Lo usan Compras, Pagos, Dirección y administración.

Desde el detalle de la OC, quien autoriza decide **cuánto** se paga de cada PAY, y el sistema mantiene cuadrada la caja de cada hito: genera el PAY por el resto, abona los pagos de más a los hitos siguientes y rehace los PAY pendientes que ya no caben.

**Dónde está:** listado general en **Hitos de Pago**; el trabajo diario de cada hito se hace en el detalle de la OC (tarjetas de hito).
<!-- /gen:intro -->

> ⏳ Pendiente: ¿En qué menú y con qué nombre se llega a **Hitos de Pago** en la barra de navegación (Área → Nombre)?

## Quién puede hacer qué

<!-- gen:roles -->
| Tarea | Quién |
|---|---|
| Consultar el listado de hitos y la caja de cada hito | Quien tiene acceso a Hitos: Gerente, Supervisor, Dirección, Almacén, Compras, Pagos y administración (cada quien ve solo las OC de su alcance) |
| Agregar un hito | Compras en sus OC y administración. En una OC autorizada que no es de destajo, solo administración |
| Editar un hito | Solo administración |
| Modificar el monto de un hito | Compras en sus OC y administración |
| Eliminar un hito | Compras en sus OC y administración; en una OC autorizada, solo administración |
| Autorizar un PAY con monto | Quien autoriza pagos (administración y Dirección) |
| Registrar un PAY como pagado | Pagos y administración |
| Corregir el importe o comprobante, o regresar a autorizado un pago ya pagado | Pagos y quienes autorizan y pueden modificar pagos |
| Eliminar un pago ya pagado | Quien autoriza y puede modificar pagos. Pagos no puede hacerlo solo por tener ese rol |
<!-- /gen:roles -->

> ⏳ Pendiente: El spec HR-006 dice que autorizar con monto es "solo admin", pero el sistema lo permite a quien tiene el permiso de autorizar pagos (incluye Dirección). ¿Debe ser solo administración?

## Conceptos

<!-- gen:concepts -->
- **PAY:** cada pago de un hito, con su folio (por ejemplo PAY-12345).
- **Cubierto:** lo que ya se aplicó al hito: pagos registrados más los abonos que recibió de otros hitos.
- **Saldo por pagar:** lo que falta cubrir al hito. Es 0 cuando el hito está completo.
- **Resto:** PAY que se genera por la parte que no se autorizó. Si autorizas $80.00 de un PAY de $100.00, el sistema crea un PAY de resto por $20.00.
- **Abono:** cuando se autoriza o se paga más de lo que le falta a un hito, el excedente se abona a los hitos siguientes, en orden. Cada abono es un PAY autorizado con la marca **Abono**.
- **Saldo del hito (reposición):** PAY nuevo que sustituye a los pendientes cuando cambia el saldo del hito.
- **Sustituido:** PAY que ya no cuenta porque el hito ya quedó cubierto o fue reemplazado por otro. Se ve atenuado y tachado, y no se puede autorizar ni pagar.
- **Tolerancia:** diferencia de hasta $0.50 por hito que se considera saldada. No genera PAY ni abono.
- **Saldo a favor del proveedor:** lo que un pago de más no puede abonar a ningún hito. Se guarda en la OC y aparece en **Resumen económico**.
- **Autorizar pagos (checklist):** indica si es buen momento de autorizar un pago del hito. Dice **Listo para autorizar** o **No se debe autorizar**; la decisión final es de quien autoriza.
- **Hito abierto / Hito cerrado:** un hito está cerrado cuando ya cumplió los tres eventos: cumplimiento, facturación y pago.
<!-- /gen:concepts -->

## Tareas

<!-- step:milestones.index fp:cdad1016f3 -->
### Consultar el listado de hitos

1. Entra a **Hitos de Pago**.
2. Revisa el **Semáforo**: **Al día**, **Próximo a vencer (≤7 días)**, **Vencido** y **Completo sin factura**.
3. Usa la tabla; las columnas son **Estado**, **#**, **Orden de Compra**, **Proveedor**, **Tipo**, **Valor**, **Monto objetivo**, **Cubierto**, **Progreso**, **Vencimiento**, **Facturas**, **Checklist**, **Estado del evento** y **Acciones**.
4. Clic en el botón **Ver orden de compra** de la fila para abrir la OC.

**Filtros:** escribe en **Buscar por orden de compra o proveedor…**; elige **Todos los checklists**, **Checklist completo** o **Checklist pendiente**; clic en **Buscar**. El botón con la X limpia los filtros.

Los hitos se ordenan por fecha de vencimiento; los que no tienen fecha van al final.
<!-- /step -->

<!-- human:milestones.index -->
<!-- /human -->

<!-- step:milestones.store fp:d58bc340d5 -->
### Agregar un hito a una OC

1. En el detalle de la OC, clic en **Agregar condición de pago**. Si los hitos ya cubren el total de la OC, aparece **Límite de hitos alcanzado**: ajusta o elimina un hito antes de crear otro.
2. En **Nuevo Hito** captura:

   | Campo | Obligatorio | Notas |
   |---|---|---|
   | **Concepto** | No | Máximo 255 caracteres |
   | **Condición de pago** | Sí | **Crédito** o **Contado** |
   | **Tipo de valor** | Sí | **Fijo** o **Porcentaje** |
   | **Valor** | Sí | Mayor a 0. Un porcentaje no puede pasar de 100 % y la suma de los hitos no puede superar el total de la OC |
   | **Fecha vencimiento** | Sí | No puede ser anterior a hoy ni a la fecha de pago de un hito previo |
   | **Inicio del plazo de crédito** | Sí, en crédito que no sea anticipo | **Validación de factura** o **Cumplimiento validado** |
   | **Plazo de crédito (días)** | Sí, en crédito que no sea anticipo | De 0 a 365 |
   | **Esto es un anticipo** | No | Marca si es un pago anticipado, antes de la entrega |

3. Clic en **Crear hito**. Aparece "Hito creado correctamente." y el hito nace con un PAY por el 100 % en **Por autorizar**, con la fecha de vencimiento del hito.
<!-- /step -->

<!-- human:milestones.store -->
<!-- /human -->

<!-- step:milestones.update fp:ffcde0b08a -->
### Editar un hito

1. En la tarjeta del hito, clic en el botón del lápiz (**Editar hito**). Solo lo ve administración.
2. En **Editar Hito #N** puedes cambiar **Concepto**, **Condición de pago**, **Fecha vencimiento**, el plazo de crédito y **Esto es un anticipo**. **Tipo de valor** y **Valor** están bloqueados: para cambiar el importe usa **Modificar monto**.
3. Clic en **Guardar**. Aparece "Hito actualizado correctamente."
<!-- /step -->

<!-- human:milestones.update -->
<!-- /human -->

<!-- step:purchase_orders.milestones.adjust_amount fp:993c875198 -->
### Modificar el monto de un hito

1. En la tarjeta del hito, clic en **Modificar monto**. El botón desaparece si el hito ya tiene un PAY autorizado o pagado, o si la OC está cerrada.
2. En **Modificar monto del Hito #N** captura:

   | Campo | Obligatorio | Notas |
   |---|---|---|
   | **Nuevo monto** | Sí | Mayor a 0, con dos decimales. Debe ser diferente al actual y no menor que lo facturado aceptado ni lo cubierto |
   | **Motivo obligatorio** | Sí | Máximo 1000 caracteres |

3. Si el nuevo monto es **menor**, el saldo restante pasa a un nuevo hito de parcialidad. Captura **Descripción del nuevo hito**, **Condición del nuevo hito**, **Anticipo**, **Inicio del plazo de crédito**, **Días de crédito** (en crédito) y **Vencimiento provisional del nuevo hito**.
4. Clic en **Guardar modificación**. Aparece "Monto del hito actualizado correctamente." y la **Bitácora de modificaciones de monto** (botón con icono de historial junto al de editar) guarda quién, cuándo y el motivo.
<!-- /step -->

<!-- human:purchase_orders.milestones.adjust_amount -->
<!-- /human -->

<!-- step:milestones.destroy fp:c46d0eec74 -->
### Eliminar un hito

1. En la tarjeta del hito, clic en el botón del bote de basura (**Eliminar hito**) y confirma "¿Eliminar este hito y todos sus pagos?".
2. Aparece "Hito eliminado." Se eliminan también sus pagos y sus comprobantes SPEI.

Si el hito tiene un pago **Pagado**, el botón aparece bloqueado y el sistema responde "No se puede eliminar un hito con pago en estatus pagado."
<!-- /step -->

<!-- human:milestones.destroy -->
<!-- /human -->

## Pagos del hito

> ⏳ Pendiente: Estas tareas dependen de las acciones de pagos (autorizar, registrar pagado, corregir, eliminar), que pertenecen al módulo Pagos en el mapa de código y por eso no llevan huella aquí. ¿Se documentan también en el manual del módulo Pagos?

### Agregar un pago por autorizar a un hito

1. En la tarjeta del hito, clic en **Agregar pago por autorizar**.
2. Captura **Importe del nuevo pago**, **Fecha factura** (en crédito) y **Fecha de pago**. Si el hito solo tiene su PAY inicial por autorizar, captura además **Importe que conservará el pago inicial** para dividirlo.
3. Clic en **Registrar para autorización**. Aparece "Pago registrado para autorización." El PAY nuevo queda en **Por autorizar**.

La suma de los pagos no rechazados no puede superar el monto del hito. Los PAY sustituidos no cuentan.

### Autorizar un PAY con monto

Antes de autorizar, revisa el bloque **Autorizar pagos** de la tarjeta del hito: indica **Listo para autorizar** o **No se debe autorizar** y, si falta algo, lo lista como "Falta: …". Un hito anterior sin aprobar también bloquea la autorización.

1. En el comprobante del PAY (**Por autorizar** o **Pospuesto**), abre el menú de tres puntos y elige **Autorizar**. También está el botón **Autorizar** en **Autorización de Pagos**.
2. En **Autorizar pago PAY-…** el **Monto a autorizar** viene con el 100 % del PAY. Cámbialo si autorizas otro importe.
3. Lee **Al confirmar, el sistema hará:** (vista previa; no guarda nada). Puede decir:
   - **Monto menor:** autoriza lo capturado y genera un PAY de resto por la diferencia, salvo que la diferencia sea de $0.50 o menos.
   - **Monto mayor al saldo del hito:** autoriza el saldo y crea PAY autorizados de **Abono** en los hitos siguientes; sus PAY pendientes pasan a **Sustituido** y se rehacen por el saldo.
4. Clic en **Autorizar**. Aparece "Pago autorizado." y la lista de movimientos que hizo el sistema.

Autorizar en bloque, el modo interactivo y **Autorizar OC con pagos** siguen autorizando el 100 % de cada PAY, sin capturar monto.

Si el monto no cabe en el hito y los siguientes, el sistema muestra el máximo permitido. El excedente no salta hitos: se detiene en el primero que no puede recibirlo (por ejemplo, sin entrega confirmada).

### Registrar un PAY como pagado

1. En el PAY **Autorizado**, abre el menú de tres puntos y elige **Registrar pagado** (en **Por Pagar**: **Cargar SPEI**).
2. Captura:

   | Campo | Obligatorio | Notas |
   |---|---|---|
   | **Importe del SPEI** | Sí | Viene con lo autorizado. Mayor a 0, con dos decimales. Menos de lo autorizado solo se acepta si la diferencia es de $0.50 o menos |
   | **Archivo comprobante SPEI** | Sí | PDF, JPG, PNG o WEBP, máximo 10 MB |

3. Lee la vista previa. Si el importe es mayor, avisa qué hitos reciben **Abono** y cuánto pasa a saldo a favor; marca **Confirmo el pago de más y lo que hará el sistema con el excedente.**
4. Clic en **Registrar pagado** (en **Por Pagar**: **Asociar y marcar pagado**). Aparece "Comprobante SPEI registrado y pago marcado como pagado."

El pago no se registra si el hito anterior no está pagado completo ("No se puede registrar pagado: el Hito N aún no está pagado completo."). Un PAY de abono también revisa el checklist de su hito. Con el **SPEI múltiple** en **Por Pagar** un solo comprobante registra varios PAY, cada uno por lo autorizado.

### Corregir un pago ya pagado

Pagos y quienes autorizan y pueden modificar pagos pueden hacer esta corrección en una OC abierta.

1. En el PAY **Pagado**, abre el menú de tres puntos y elige **Corregir pago**.
2. En **Corregir pago PAY-…** elige una opción:
   - **Cambiar importe o comprobante:** captura el **Importe pagado** nuevo y, si quieres, **Reemplazar comprobante (opcional)**. Clic en **Guardar corrección**. Aparece "Pago corregido." o "Comprobante SPEI reemplazado."
   - **Regresar a autorizado:** clic en **Regresar a autorizado**. Aparece "Pago regresado a autorizado."
3. Cada opción muestra su vista previa. Si la corrección deja con saldo un hito que tiene hitos siguientes ya pagados, aparece una advertencia ("Los hitos N y M ya están pagados; esta corrección deja el Hito K con saldo antes que ellos.") y debes marcar **Confirmo la corrección y la advertencia.**

La corrección revierte lo que ese pago movió (lo aplicado, los abonos y el saldo a favor) y crea un PAY **Por autorizar** con el saldo de cada hito que quedó sin cubrir. No se puede corregir un pago que generó PAY de abono: "Este pago generó abonos autorizados en PAY-… y no se puede corregir."

### Eliminar un pago

1. Un PAY pendiente se elimina desde el menú de tres puntos con **Eliminar pago**; confirma "¿Eliminar este pago?". Aparece "Pago eliminado."
2. Un PAY **Pagado** solo lo elimina quien autoriza y puede modificar pagos, desde **Corregir pago** → **Eliminar pago**, con su vista previa y la advertencia si aplica. Pagos no ve esta opción salvo que también tenga esa autorización.

Un PAY **Sustituido** no se elimina. Un PAY autorizado que ya generó resto, abonos o sustituciones tampoco se puede regresar, rechazar, posponer ni eliminar.

## Estados

<!-- gen:states -->
| Estado del PAY | Qué significa | Puede pasar a |
|---|---|---|
| **Por autorizar** | Espera la decisión de quien autoriza | **Autorizado**, **Rechazado**, **Pospuesto**, **Sustituido** (automático) |
| **Pospuesto** | Se dejó para después; opera igual que Por autorizar | **Por autorizar**, **Autorizado**, **Rechazado**, **Sustituido** (automático) |
| **Autorizado** | Aprobado por un monto; falta el SPEI | **Pagado**; **Por autorizar** (administración, si no generó movimientos) |
| **Pagado** | Tiene comprobante SPEI. Pagos y quienes autorizan y pueden modificar pagos lo corrigen | **Autorizado** (Pagos y quienes autorizan y pueden modificar pagos) |
| **Rechazado** | No se paga | **Por autorizar** (reactivación) |
| **Sustituido** | Reemplazado por otro PAY o el hito ya quedó cubierto. No se opera | Ninguno |

| Estado del hito | Qué significa |
|---|---|
| **Hito abierto** | Faltan eventos: cumplimiento, facturación o pago. Se muestra «N de 3 eventos» |
| **Hito cerrado** | Cumplimiento, facturación y pago completos |
<!-- /gen:states -->

## Mensajes y errores frecuentes

<!-- gen:messages -->
| Mensaje | Qué hacer |
|---|---|
| "No se puede autorizar: el Hito N aún no está aprobado." | Autoriza antes todos los PAY del hito anterior |
| "No se puede registrar pagado: el Hito N aún no está pagado completo." | Registra primero el pago del hito anterior |
| "Lo más que se puede autorizar es $X: el saldo de este hito y de los siguientes." | Captura un monto menor o igual al máximo |
| "No hay hito siguiente con saldo donde abonar el excedente. Lo más que se puede autorizar es $X, el saldo de este hito." | Captura un monto menor o igual al máximo |
| "Pagar menos de lo autorizado no está permitido: el monto se decide al autorizar." | Captura el importe autorizado (o hasta $0.50 menos) |
| "Este PAY fue sustituido y ya no se puede autorizar ni pagar." | Usa el PAY que lo sustituye |
| "Este pago ya se registró con SPEI y no se puede modificar ni eliminar." | Pide la corrección a Pagos o a quien autoriza y puede modificar pagos; para eliminarlo, pide ayuda a quien autoriza y puede modificar pagos |
| "Este pago generó abonos autorizados en PAY-… y no se puede corregir." | Revisa esos PAY de abono antes de intentar otra vez |
| "El hito #N no cumple su checklist (pendiente: …)." | Completa el requisito indicado (por ejemplo, confirmar la entrega) |
| "Solo admin puede agregar hitos a una OC autorizada que no es destajo." | Pide el cambio a administración |
| "Solo admin puede editar hitos de una OC autorizada." | Pide el cambio a administración |
| "No se puede eliminar un hito con pago en estatus pagado." | Pide a Pagos o a quien autoriza y puede modificar pagos que regrese el pago a autorizado; para eliminar el pago, acude a quien autoriza y puede modificar pagos |
<!-- /gen:messages -->

## Relación con otros módulos

<!-- gen:relations -->
- **Órdenes de compra:** los hitos y sus PAY se trabajan en el detalle de la OC. El **Resumen económico** muestra el importe total, lo facturado, lo pagado, el saldo por pagar y el saldo a favor del proveedor. **Solicitud de cambio** (debajo de **Editar OC**) abre la autorización de Dirección para cambiar o cancelar una OC autorizada.
- **Pagos (Autorización de Pagos, Por Pagar, Pagados):** autorizar y registrar el SPEI también se hace desde esas pantallas.
- **Estado de cuenta del proveedor:** lo pagado suma el importe del SPEI; las facturas se cotejan contra lo aplicado a cada hito más sus abonos.
- **Facturas y evidencias:** la factura aceptada y la entrega confirmada alimentan el checklist y el cierre del hito.
<!-- /gen:relations -->
