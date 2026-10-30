# Checklist de hitos para la autorización de pagos

Guía para usuarios de Compras, Recepción / Almacén, Administración y Pagos.

## ¿Qué es y para qué sirve?

Cada **hito** de una Orden de Compra (OC) tiene un **checklist de comprobación**: la lista de eventos que deben haber ocurrido *antes* de que su pago pueda ser autorizado (por ejemplo, que ya se recibió lo comprado o que ya existe una factura aceptada).

Con esto:

- **Administración** solo verifica el checklist y autoriza. Si el hito no cumple, el sistema **no permite** autorizar.
- **Pagos** tiene la certeza de que todo lo autorizado cumplió la regla y cuenta con su documentación.
- Todos pueden ver, en cualquier momento, qué hitos ya están listos y cuáles siguen pendientes.

> Los checklists son **por hito**, y aplican a **todos los pagos** de ese hito (incluso si el pago se dividió en varios).

## Reglas generales

Lo que se pide depende del tipo de hito:

| Tipo de hito | Entrega confirmada | Factura aceptada |
|---|:---:|:---:|
| **Anticipo** (hito marcado como anticipo) | No aplica | No aplica |
| **Contado** (no anticipo) | Requerida | No requerida |
| **Crédito**, plazo inicia con *validación de factura* | Requerida | Requerida |
| **Crédito**, plazo inicia con *cumplimiento validado* | Requerida | No requerida para autorizar (se regulariza después) |

- **Entrega confirmada:** el hito está **verificado** (cumplimiento). Según el tipo de cumplimiento, es una recepción física que un usuario confirmó como "entrega completa para este hito", o un cumplimiento por Estimación o avance, Documental o Anticipo validado con su evidencia.
- **Factura aceptada:** hay al menos una factura **ligada al hito** cuyo estatus es **Aceptada**. Una factura *En proceso* o *Rechazada* **no cumple**.
- Un hito **completo** muestra "Listo para autorizar". Mientras falte algo muestra **Pendiente** y el detalle de lo que falta.

## Estado de cada hito (evento de pago)

Además del checklist, cada hito muestra su **estado del evento** con tres dimensiones independientes:

- **Cumplimiento**: la entrega del hito está confirmada.
- **Facturación**: hay una factura aceptada ligada al hito.
- **Pago**: el hito está pagado por completo (o *Pago parcial*).

Cuando las tres están completas el evento queda **"Evento cerrado"**. Mientras no, se lee **"Abierto – pendiente de cumplimiento / factura / pago"**. Un anticipo puede estar pagado y seguir abierto hasta que se confirme la entrega y se acepte su factura.

## Resumen económico y regularización documental

- En el detalle de la OC, el **Resumen económico** muestra: importe total, facturado, pendiente de facturar, pagado, pendiente de pagar y facturado sin pagar. "Facturado" cuenta solo facturas **aceptadas**.
- Si un pago se realizó **antes de tener la factura aceptada**, queda **pendiente de regularización documental**. Se consulta en *Pagos pagados → Regularización documental* (o *Alta de facturas*) y en el hito se muestra el aviso.
- Cuando la factura llega, se **acepta y se liga al hito**: el pago queda regularizado, **sin crear un pago nuevo ni duplicar importes**. Si la factura se rechaza o elimina, el pago vuelve a pendiente.
- Los pagos anteriores a esta función sin factura aceptada aparecerán en la lista hasta que se ligue su factura.

## Plazo de crédito y fecha de vencimiento

Al crear o editar un hito **a crédito** (que no sea anticipo) se captura:

- **Inicio del plazo de crédito:** *Validación de factura* o *Cumplimiento validado*.
- **Plazo de crédito (días).**

El **vencimiento se calcula solo**: fecha de inicio + días de plazo.

- Con *Validación de factura*, la fecha de inicio es cuando la factura ligada al hito pasa a **Aceptada**.
- Con *Cumplimiento validado*, es cuando se **confirma la entrega** del hito.
- Al calcularse, se actualizan el vencimiento del hito y la fecha de sus pagos aún no autorizados. Antes de que ocurra el evento, el vencimiento es el estimado que se capturó.
- Si la fecha calculada rompiera el orden de fechas entre hitos de la OC, **no se aplica** y el sistema muestra un aviso para que se ajuste manualmente.
- Los hitos a crédito anteriores a esta función quedaron con *Validación de factura* y conservan su fecha de vencimiento actual.

## Cómo se cumple cada requisito

### 1. Entrega confirmada

En el detalle de la OC, abre **Entregas**. El primer paso es elegir el **tipo de cumplimiento** (el tipo no es un dato del hito; se elige aquí cada vez):

| Tipo | Qué se pide | Evidencia | Cómo queda verificado el hito |
|---|---|---|---|
| **Recepción física** | Conceptos con cantidades, hito y evidencia | Evidencia de entrega | Se registra la entrega y después se pulsa **"Confirmar entrega completa del hito"** |
| **Estimación o avance** | Hito, evidencia y comentario opcional | Estimación firmada o visto bueno de la obra | **"Validar cumplimiento"** lo verifica en el mismo paso |
| **Documental** | Hito, evidencia y comentario opcional | Acta o documento aprobado | **"Validar cumplimiento"** |
| **Anticipo** | Hito y evidencia (sin comentario) | Hoja de autorización firmada por Dirección | **"Validar cumplimiento"** |

**Recepción física**

1. Selecciona los conceptos que se entregan.
2. Elige el **hito que cubre esa entrega** (campo "Hito que cubre esta entrega") y adjunta la evidencia (PDF o imagen).
   - Una entrega se liga a **un solo hito**. Si cubre varios hitos, regístrala por separado para cada uno.
   - Si no eliges hito, la entrega se registra normalmente pero **no cuenta** para ningún checklist.
3. Cuando lo recibido corresponde por completo al hito, pulsa **"Confirmar entrega completa del hito"** en el panel de checklist de ese hito.

**Estimación o avance, Documental y Anticipo**

1. Elige el tipo, el hito y adjunta la evidencia que corresponde. El botón **"Validar cumplimiento"** se habilita cuando hay hito y evidencia (el comentario no condiciona el botón).
2. Al validar se guarda la evidencia (queda en la sección Evidencias de la OC) y el hito queda verificado. El panel del hito muestra el tipo de cumplimiento.
3. Todos los tipos cuentan como "Entrega confirmada" en el checklist y habilitan la dimensión *Cumplimiento* del evento. Los anticipos (`is_advance`) siguen sin requisitos bloqueantes para autorizar su pago; no se valida la hoja de Dirección.

**Reglas**

- Un hito se cumple por **un solo tipo**: no se puede registrar un cumplimiento no físico en un hito con entregas, con otro registro o ya verificado, ni una entrega física en un hito cumplido por registro.
- Si te equivocaste, usa **"Revocar confirmación de entrega"**. Si el hito se cumplió por registro, también se elimina el registro de cumplimiento; el archivo se conserva en las evidencias de la OC. Mientras el registro esté vigente, su evidencia no se puede eliminar desde Evidencias.

Quién puede: en **Recepción física**, Admin, Solmat y Orden de compra (en OCs donde es el comprador). En **Estimación, Documental y Anticipo**, el comprador de la OC (rol Orden de compra) y Admin; los demás roles no ven esos tipos. Revertir una entrega registrada solo lo puede hacer Admin; si el hito se queda sin entregas ni registros, su confirmación se limpia automáticamente.

### 2. Factura aceptada (hitos a crédito)

1. Registra la factura en la OC (o desde el portal de proveedores) y **lígala al hito** que ampara.
2. Revisa la factura y cámbiala a estatus **Aceptada** (Admin u Orden de compra).

## Qué cambia en el sistema

### Detalle de la OC
- Cada hito tiene un panel **"Checklist del hito"** con cada requisito, su estado y el botón para confirmar la entrega.
- En los pagos del hito, la opción **Autorizar** aparece **bloqueada** (con candado y el motivo "Falta: …") mientras el checklist esté pendiente.
- Al autorizar una OC junto con pagos seleccionados, los pagos de hitos con checklist pendiente no pueden seleccionarse. Los pagos de **anticipo** sí pueden autorizarse desde el inicio.

### Autorizaciones de pago (Pagos → Autorizaciones)
- **Por defecto solo se muestran los pagos con checklist completo**, listos para autorizar.
- Una barra amarilla indica **cuántos pagos aún no aparecen** porque su hito no ha completado el checklist, con un botón para verlos.
- El selector de checklist permite alternar entre **Checklist completo**, **Checklist pendiente** y **Todos**.
- Cada pago muestra su checklist (✔ cumplido, ✖ pendiente) y una columna **Tipo de hito** (Anticipo / Pago regular, Contado / Crédito).
- Los pagos con checklist pendiente tienen el botón de autorizar deshabilitado ("Checklist pendiente") y no se pueden marcar para autorización en bloque.
- El **Modo interactivo** (deslizar) solo presenta pagos con checklist completo e informa cuántos se omitieron.

### Pagos por pagar
- Cada pago autorizado muestra **"Checklist verificado"** con la fecha en que se verificó al autorizarlo. Los pagos autorizados antes de esta función muestran "Sin verificación de checklist".

### Hitos de Pago
- La lista general de hitos tiene una columna **Checklist** y un filtro (completo / pendiente) para dar seguimiento a qué OCs ya cumplieron.

## Cierre de la orden de compra

Cuando todo el ciclo termina, la OC se **cierra** y pasa a estatus **Cerrada**. Ya no basta con que esté entregada y pagada: el cierre es un paso explícito y deja constancia de quién y cuándo.

Para poder cerrar una OC deben cumplirse **todos** estos puntos (el panel "Cierre de la OC" en su detalle los muestra con ✔ / ✖):

1. La OC está **autorizada**.
2. **Entrega completa** (todos los conceptos entregados; o, si la OC no tiene entregas físicas, todos sus hitos cumplidos por registro de cumplimiento).
3. **Todos los eventos cerrados**: cada hito tiene cumplimiento, facturación y pago completos. Esto incluye los anticipos, que también deben tener su entrega confirmada y su factura aceptada.
4. **Pagos completos**: todos los pagos están pagados y no queda saldo pendiente.
5. **Importe facturado completo**: las facturas aceptadas (netas de notas de crédito) cubren el total de la OC, con una tolerancia de $0.50.
6. **Sin regularizaciones documentales pendientes**: ningún pago realizado sin factura aceptada y ninguna factura en proceso de revisión.

- Cuando todo se cumple, el panel indica **"Lista para cerrar"** y aparece el botón **Cerrar OC** (con nota opcional). Pueden cerrarla **Admin** y el **comprador de la OC**.
- Mientras algo falte, el botón aparece bloqueado y el panel muestra qué falta.
- Una OC **cerrada** se identifica con la etiqueta **Cerrada** en el listado (hay una bandeja "Cerradas" para Admin) y **no admite cambios** en conceptos, hitos, pagos, entregas ni facturas.
- Solo **Admin** puede **reabrirla**, indicando un motivo (mínimo 10 caracteres); queda registrado en las notificaciones.
- Las OCs que ya estaban entregadas y pagadas al lanzar esta función quedaron **cerradas automáticamente**.

## Qué NO cambia

- Registrar, dividir, editar o rechazar pagos funciona igual.
- Marcar un pago ya autorizado como **pagado** no requiere volver a validar el checklist.
- El orden y las fechas entre hitos de una OC siguen las reglas de secuencia existentes.

## Hitos y OCs existentes

- Los hitos de OCs que ya estaban **Entregadas** al lanzar la función quedaron con la **entrega confirmada** automáticamente.
- Los pagos que aún estén por autorizar sí quedan sujetos al checklist: si su hito es a crédito, deberán tener su factura aceptada ligada al hito.

## Preguntas frecuentes

**No puedo autorizar un pago, ¿qué hago?**
Revisa el hito en la OC: el panel de checklist indica qué falta. Pide al área correspondiente que confirme la entrega o acepte la factura ligada al hito.

**Ya subí la factura y sigue pendiente.**
Verifica que esté **ligada al hito** y con estatus **Aceptada**.

**El proveedor entregó solo una parte.**
Registra la entrega parcial normalmente. Confirma la entrega del hito únicamente cuando lo recibido cubra lo correspondiente a ese hito.

**¿Se puede autorizar con excepción?**
No. El bloqueo aplica a todos los usuarios, incluido Admin. Si el requisito no aplica al hito (por ejemplo un pago previo), el hito debe estar marcado como **Anticipo**.

**¿Dónde veo cuántos pagos están detenidos por checklist?**
En Pagos → Autorizaciones (barra amarilla) o en Hitos de Pago filtrando por **Checklist pendiente**.

---

Detalle técnico para el equipo de desarrollo: [purchase-order-payment-health-flow.md](./purchase-order-payment-health-flow.md#checklist-de-comprobacion-por-hito).
