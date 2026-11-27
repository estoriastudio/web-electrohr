---
spec_id: HR-014
type: ext-rule
title: Impuesto cedular como retención en órdenes de compra
module: Ordenes de Compra
module_key: ordenes_de_compra
extends: HR-013
status: implementado
implemented: 2026-11-27
author: Jorge
reviewers: [N/A]
branch: 
created: 2026-11-27
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/purchase-order-payment-health-flow.md
  - docs/supplier-account-statement.md
open_questions: 0
---

# HR-014 · Ordenes de Compra — Impuesto cedular como retención en órdenes de compra

**Módulo:** Ordenes de Compra (`ordenes_de_compra`) · **Tipo:** C3 · Cambio de regla · **Extiende:** HR-013

> ✓ Estado: **Implementado** (2026-11-27). Este spec describe código que ya existe: no lo vuelvas a implementar. Los cambios nuevos van en un spec de extensión (C1–C4).

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos (si ya los leíste en esta conversación y no cambiaron, no los vuelvas a leer). Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos, fuera de alcance)
- `docs/controller-structure-and-notifications.md`
- `docs/purchase-order-payment-health-flow.md`
- `docs/supplier-account-statement.md`

## 1. Propósito

Restar el impuesto cedular del total de las órdenes de compra como una retención y actualizar de forma controlada los importes históricos elegibles.

**Flujo de interacción**

El usuario consulta el detalle o el PDF y ve el total con el impuesto cedular restado.

## 2. Módulo afectado

- **Módulo:** Ordenes de Compra
- **Usa Livewire:** no

**Comportamiento actual**

PurchaseOrder calcula el impuesto cedular sobre el subtotal, pero lo suma tanto al total dinámico como al importe persistido. El detalle y el PDF consumen el total dinámico; otras pantallas y el estado de cuenta consumen el importe guardado. Recalcular una OC también puede modificar pagos porcentuales por autorizar.

**Archivos que se tocan**

- app/Models/PurchaseOrder.php: fórmula dinámica, importe persistido y sincronización existente.
- tests/Unit/ y tests/Feature/: cobertura de la fórmula y de la actualización histórica, según las decisiones aprobadas.
- docs/purchase-order-payment-health-flow.md, docs/supplier-account-statement.md y manuales afectados: revisar y actualizar solo donde el cambio lo requiera.

**Debe permanecer igual**

- La base de cálculo del cedular, las tasas, el redondeo individual de impuestos y las demás operaciones de la fórmula.
- Los pagos autorizados y pagados, las facturas, los comprobantes y los datos guardados de órdenes cerradas o excluidas de la actualización histórica.
- Los permisos, rutas, esquema de base de datos, comprobaciones de checklist y reglas de secuencia existentes, salvo excepciones expresamente aprobadas.

**Riesgos**

- Un total dinámico corregido puede diferir de purchase_orders.amount en órdenes cerradas u omitidas y afectar pantallas, saldos, facturación y cierre.
- Una orden puede tener pagos, abonos, facturas o hitos fijos superiores al nuevo total; ejecutar recalculateAmount() masivamente sin revisar puede alterar pagos pendientes y dejar inconsistencias.
- Los datos pueden cambiar entre previsualización y ejecución: hay que volver a comprobarlos bajo bloqueos antes de escribir.
- La actualización histórica modifica datos financieros existentes y requiere respaldo, revisión del reporte y autorización expresa antes de ejecutarse.

## 3. Regla

**Actual**

Total = subtotal + IVA - ISR - retención IVA - retención ISR + impuesto cedular. Los importes de cada impuesto se calculan sobre el subtotal y se redondean a dos decimales.

**Nueva**

Total = subtotal + IVA - ISR - retención IVA - retención ISR - impuesto cedular. El impuesto cedular sigue calculándose sobre el subtotal y redondeándose a dos decimales. Aplicar la misma fórmula al total dinámico y al importe persistido en operaciones posteriores.

**Dónde se aplica:** modelo (scope o método)

**Casos afectados**

- Con subtotal 1,000.00, IVA 16 %, ISR 10 %, retención IVA 4 %, retención ISR 2 % y cedular 3 %, el total dinámico y el importe guardado son 970.00.
- Sin cedular o con tasa cero, el total conserva el comportamiento anterior; los importes se redondean como antes.
- El detalle, las respuestas al editar conceptos, el PDF simple, el PDF con anexos y el importe en letras reflejan el total dinámico corregido.
- Una OC abierta elegible actualiza su importe y sus pagos porcentuales por autorizar; los pagos autorizados y pagados no cambian.
- Una OC con conflicto se omite y reporta sin modificar su importe ni sus pagos; una OC cerrada conserva los datos guardados, aunque el total dinámico cambie.
- La previsualización no escribe datos, y repetir la ejecución con datos sin cambios no altera los resultados.

**Datos históricos**

Actualizar únicamente órdenes emitidas (excluir autorizadas, archivadas, eliminadas y cerradas) con impuesto cedular, sujetas a las exclusiones y definiciones pendientes. Recalcular su importe y los pagos porcentuales por autorizar; conservar pagos autorizados y pagados. Omitir  las órdenes en conflicto si lo pagado, autorizado o facturado (facturado es lo que se asocia a los hitos desde invoices, solo facturas con estatus aceptada) supera el nuevo total. No modificar datos guardados de órdenes omitidas o cerradas. El total dinámico de todas las órdenes, incluidas las omitidas y cerradas, usa la fórmula nueva.

## 4. Reglas de negocio

- Contexto adicional obligatorio: app/Models/PurchaseOrder.php, app/Models/PurchaseOrderMilestone.php, app/Models/Payment.php, app/Services/MilestoneAmountAdjuster.php, app/Services/PurchaseOrderEconomicSummary.php, app/Services/PurchaseOrderClosureEvaluator.php, app/Services/SupplierAccountStatementService.php, app/Http/Controllers/PurchaseOrderController.php, resources/views/purchase_orders/show.blade.php, resources/views/purchase_orders/pdf.blade.php, resources/views/purchase_orders/pdf_with_annexes.blade.php y resources/views/purchase_orders/partials/_pdf_body.blade.php.
- No hacer backfill dentro de una migración ni ejecutar automáticamente la actualización al desplegar. La ejecución sobre datos existentes requiere autorización separada tras revisar la previsualización y acordar respaldo y recuperación.
- La previsualización debe identificar órdenes elegibles, omitidas y cerradas, importes anteriores y propuestos, pagos que cambiarían y motivos de exclusión; no modifica registros.
- La ejecución debe ser idempotente y transaccional por orden, volver a validar bajo bloqueos y reportar explícitamente errores, omisiones y resultados sin modificar parcialmente una orden.
- Las preguntas abiertas sobre alcance, cómputo de conflictos y manejo de hitos son bloqueantes: no implementar ni aprobar el spec hasta resolverlas.
- Las vistas usan el total del modelo; modificar vistas o rutas solo si una decisión aprobada lo exige. No crear columnas, tablas ni migraciones.

## 5. Validaciones

_Convenciones de docs/controller-structure-and-notifications.md §4._

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Propuesta pendiente de aprobación: HR-010 conserva el contenido de los PDF; se permite exclusivamente que sus totales numéricos y en letras reflejen la nueva fórmula, sin cambiar permisos ni formato.
- Propuesta pendiente de aprobación: HR-012 y HR-013 conservan recálculos y controles financieros; se sustituye únicamente la base del total para la fórmula y la actualización histórica definidas por este spec. Los límites, secuencias y demás reglas no se eliminan.

## 7. Preguntas abiertas

- [x] ¿Qué hacer si la suma de hitos fijos, pagos pospuestos, abonos o importes de un hito supera su capacidad nueva, aunque no exceda el total de la orden? → **Se mantiene la configuración de hitos, solo se actualiza el total de la orden.**
- [x] ¿Cómo manejar un total nuevo negativo o cero y pagos por autorizar que la sincronización llevaría a cero? → **Se hará ajuste manual posteriomente**

## 8. Fuera de alcance

Aplica la lista `oos` de `docs/specs/_project.json`, salvo lo que este spec sí incluye.

**Este spec sí incluye:**

- Unit / Feature tests
- Artisan commands

**Además, fuera de alcance en este spec:**

- Cambiar el esquema de base de datos, crear migraciones o instalar paquetes.
- Modificar pagos autorizados o pagados, facturas o datos guardados de órdenes cerradas o excluidas.
- Ejecutar la actualización sobre datos existentes antes de la aprobación del spec y de una autorización específica posterior.

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en el modelo (scope/método): Total = subtotal + IVA - ISR - retención IVA - retención ISR - impuesto cedular. El impuesto cedular sigue calculándose sobre el subtotal y redondeándose a dos decimales. Aplicar la misma fórmula al total dinámico y al importe persistido en operaciones posteriores.
- [ ] Caso: Con subtotal 1,000.00, IVA 16 %, ISR 10 %, retención IVA 4 %, retención ISR 2 % y cedular 3 %, el total dinámico y el importe guardado son 970.00.
- [ ] Caso: Sin cedular o con tasa cero, el total conserva el comportamiento anterior; los importes se redondean como antes.
- [ ] Caso: El detalle, las respuestas al editar conceptos, el PDF simple, el PDF con anexos y el importe en letras reflejan el total dinámico corregido.
- [ ] Caso: Una OC abierta elegible actualiza su importe y sus pagos porcentuales por autorizar; los pagos autorizados y pagados no cambian.
- [ ] Caso: Una OC con conflicto se omite y reporta sin modificar su importe ni sus pagos; una OC cerrada conserva los datos guardados, aunque el total dinámico cambie.
- [ ] Caso: La previsualización no escribe datos, y repetir la ejecución con datos sin cambios no altera los resultados.
- [ ] Datos históricos: Actualizar únicamente órdenes emitidas (excluir autorizadas, archivadas, eliminadas y cerradas) con impuesto cedular, sujetas a las exclusiones y definiciones pendientes. Recalcular su importe y los pagos porcentuales por autorizar; conservar pagos autorizados y pagados. Omitir  las órdenes en conflicto si lo pagado, autorizado o facturado (facturado es lo que se asocia a los hitos desde invoices, solo facturas con estatus aceptada) supera el nuevo total. No modificar datos guardados de órdenes omitidas o cerradas. El total dinámico de todas las órdenes, incluidas las omitidas y cerradas, usa la fórmula nueva.
- [ ] Permanece igual: La base de cálculo del cedular, las tasas, el redondeo individual de impuestos y las demás operaciones de la fórmula.
- [ ] Permanece igual: Los pagos autorizados y pagados, las facturas, los comprobantes y los datos guardados de órdenes cerradas o excluidas de la actualización histórica.
- [ ] Permanece igual: Los permisos, rutas, esquema de base de datos, comprobaciones de checklist y reglas de secuencia existentes, salvo excepciones expresamente aprobadas.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-014]` en el mensaje.

---

_Generado con Estoria Spec Tool v4.1 · Estoria Studio_
