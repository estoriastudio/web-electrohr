---
spec_id: HR-012
type: ext-rule
title: Pagos corrige pagos en hitos de órdenes de compra
module: Pagos
module_key: payments
extends: HR-008
status: aprobado
author: 
reviewers: []
branch: 
created: 2026-11-25
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/purchase-order-payment-health-flow.md
  - docs/supplier-account-statement.md
open_questions: 0
---

# HR-012 · Pagos — Pagos corrige pagos en hitos de órdenes de compra

**Módulo:** Pagos (`payments`) · **Tipo:** C3 · Cambio de regla · **Extiende:** HR-008

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos (si ya los leíste en esta conversación y no cambiaron, no los vuelvas a leer). Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos, fuera de alcance)
- `docs/controller-structure-and-notifications.md`
- `docs/purchase-order-payment-health-flow.md`
- `docs/supplier-account-statement.md`

## 1. Propósito

Permitir al rol Pagos corregir el importe o comprobante SPEI de un pago pagado y regresarlo a autorizado desde el detalle de una orden de compra, sin permitirle eliminar ese pago por el solo hecho de tener el rol Pagos.

**Flujo de interacción**

En una orden de compra abierta, Pagos abre el menú de un PAY pagado en la tarjeta del hito y elige Corregir pago. Puede cambiar el importe o reemplazar el comprobante y guardar, o regresar el pago a autorizado, con las vistas previas y confirmaciones existentes. No ve la opción de eliminar salvo que también tenga el acceso que actualmente permite eliminar pagos pagados.

## 2. Módulo afectado

- **Módulo:** Pagos
- **Usa Livewire:** no

**Comportamiento actual**

El detalle de la OC muestra Corregir pago para un PAY pagado solo si el usuario tiene payment_authorizations.authorize y payments_payable.update. El controller exige payment_authorizations.authorize para la vista previa y la corrección de un pago pagado y también para eliminarlo. Pagos tiene payments_payable.update y payments_paid.register, pero no payment_authorizations.authorize; no ve la opción y recibe 403 al intentar corregir por las rutas existentes. El modal compartido incluye cambiar importe o comprobante, regresar a autorizado y eliminar.

**Archivos que se tocan**

- app/Http/Controllers/PaymentController.php: separar la autorización de corrección de la autorización existente para eliminar y autorizar; proteger las operaciones y sus vistas previas.
- resources/views/purchase_orders/show.blade.php: mostrar Corregir pago al rol Pagos con acceso a las rutas y conservar el acceso previo.
- resources/views/payments/utilities/correct_modal.blade.php: mostrar la eliminación solo a quien ya puede eliminar un pago pagado y actualizar el texto sobre quién puede corregir.
- docs/roles-permissions-matrix.md: documentar el alcance de corrección de Pagos y la exclusión de eliminación.
- docs/manual/milestones.md: actualizar los pasos y destinatarios afectados mediante el procedimiento de generación de manuales del proyecto.

**Debe permanecer igual**

- La eliminación de pagos pagados, incluida su vista previa, conserva payment_authorizations.authorize y el middleware payments_payable.update; Pagos sin esa autorización recibe 403 y no ve el formulario de eliminación.
- La autorización de pagos y su vista previa siguen requiriendo payment_authorizations.authorize; no se amplían los permisos de autorización, las excepciones de checklist ni las transiciones ajenas a la corrección.
- Las validaciones, transacciones, bloqueos, vistas previas, confirmaciones, recálculos y efectos existentes de cada corrección, incluyendo las restricciones de OC cerrada y pagos que generaron abonos.
- Los accesos previos de administración y de las cuentas con las combinaciones de permisos existentes, así como las restricciones de los demás roles y del portal de proveedores.
- Las rutas, verbos, middleware, matriz de permisos, roles, asignaciones, modelos y esquema de base de datos.

**Riesgos**

- Cambiar un importe o regresar a autorizado revierte y recalcula efectos financieros existentes; deben conservarse las validaciones y advertencias actuales.
- El modal reúne operaciones con permisos diferentes: ocultar Eliminar no basta; tanto la vista previa como la ruta de eliminación deben seguir protegidas en el servidor.
- No usar el permiso de autorización de pagos para conceder esta capacidad a Pagos: también permitiría otras operaciones de autorización.

## 3. Regla

**Actual**

Corregir o eliminar un pago pagado requiere payment_authorizations.authorize además del middleware payments_payable.update de las rutas.

**Nueva**

Además de los usuarios que ya pueden corregir pagos pagados, una cuenta con el rol Pagos y acceso a las rutas existentes puede cambiar el importe o el comprobante SPEI y regresar el pago a autorizado. El rol Pagos por sí solo no permite eliminar pagos pagados: esa acción y su vista previa siguen requiriendo la autorización existente. La concesión es específica de estas correcciones y no concede payment_authorizations.authorize.

**Dónde se aplica:** controller

**Casos afectados**

- Pagos sin payment_authorizations.authorize ve Corregir pago en un PAY pagado de una OC abierta y puede cambiar el importe, reemplazar el comprobante y regresar a autorizado, sujeto a las reglas existentes.
- Pagos sin payment_authorizations.authorize no ve Eliminar pago en el modal; solicitar la vista previa de eliminación o enviar DELETE para el PAY pagado devuelve 403.
- Una cuenta Pagos que además cumpla los permisos actualmente necesarios para eliminar conserva esa capacidad; la combinación de roles no elimina derechos preexistentes.
- Un usuario sin el rol Pagos y sin autorización previa no obtiene acceso a las vistas previas o cambios de pagos pagados mediante solicitudes directas.
- Las vistas previas y transiciones para autorizar pagos siguen rechazando a Pagos sin payment_authorizations.authorize.
- Para una OC cerrada o un pago que no puede corregirse por sus movimientos, siguen operando los bloqueos y mensajes existentes.
- Administración conserva la corrección y eliminación y se mantienen las confirmaciones y vistas previas actuales.
- Pagos no ve Corregir pago para un PAY que no esté pagado; los demás controles y transiciones conservan sus condiciones actuales.

**Datos históricos**

No hay migraciones, backfill, cambios masivos de datos ni sincronización de roles o permisos. La nueva regla aplica tras el despliegue a cuentas existentes con el rol Pagos; los pagos históricos pagados quedan sujetos a las mismas validaciones de corrección.

## 4. Reglas de negocio

- Contexto adicional obligatorio: leer docs/roles-permissions-matrix.md, docs/manual/milestones.md, database/data/access_matrix.php, routes/web.php, app/Http/Controllers/PaymentController.php, el servicio de pagos de hitos, resources/views/purchase_orders/show.blade.php y resources/views/payments/utilities/correct_modal.blade.php, además de las instrucciones aplicables de controllers y vistas.
- Este spec extiende HR-008 y modifica exclusivamente su requisito de conservar todos los permisos de usuarios sin supplier_portal_access: se concede a Pagos la corrección aquí descrita. También modifica exclusivamente la preservación de las acciones de Pagos en pagos indicada en HR-010; las demás garantías de ambos specs siguen vigentes.
- Conservar el middleware payments_payable.update. Para el PAY pagado, permitir corrección de importe, comprobante y regreso a autorizado a quien ya tenga payment_authorizations.authorize o tenga el rol Pagos; mantener controles de autorización distintos para las operaciones no comprendidas en este cambio.
- En payments.preview diferenciar la vista previa de eliminación de las de cambio de importe y regreso a autorizado; Pagos sin payment_authorizations.authorize no obtiene la vista previa de eliminación. En payments.destroy conservar la verificación existente de eliminación de pagos pagados, también para solicitudes directas.
- No conceder payment_authorizations.authorize a Pagos ni usar una comprobación general que permita autorizar pagos o eludir el checklist. Los roles combinados conservan los permisos previamente concedidos.
- El dropdown y el modal deben respetar las mismas capacidades que el servidor. El formulario Eliminar se muestra solo si el usuario cumple la condición existente de eliminación; las demás opciones de corrección se muestran para los usuarios autorizados a corregir.
- Orden por capas: primero autorización en controller y vistas previas; luego controles del detalle y modal; finalmente documentación y manual. Al terminar cada capa, listar los archivos y esperar confirmación antes de la siguiente.

## 5. Validaciones

_Convenciones de docs/controller-structure-and-notifications.md §4._

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- docs/roles-permissions-matrix.md, sección Pagos y Recepción: se precisa que Pagos puede corregir importe o comprobante y regresar a autorizado un PAY pagado, sin conceder autorización de pagos ni eliminación de PAY pagados.

## 7. Preguntas abiertas

- [x] ¿Qué operaciones del modal se conceden al rol Pagos? → **Cambiar importe o comprobante y regresar a autorizado; no eliminar pagos pagados por el solo hecho de tener el rol Pagos.**

## 8. Fuera de alcance

Aplica la lista `oos` de `docs/specs/_project.json`.

**Además, fuera de alcance en este spec:**

- Eliminar pagos pagados por el solo hecho de tener el rol Pagos.
- Conceder a Pagos autorización de pagos, excepciones de checklist o cambios en otras transiciones.
- Crear permisos, roles, rutas, migraciones, paquetes o cambiar asignaciones de permisos.
- Modificar pagos históricos en masa o implementar antes de la aprobación del spec.

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en el controller: Además de los usuarios que ya pueden corregir pagos pagados, una cuenta con el rol Pagos y acceso a las rutas existentes puede cambiar el importe o el comprobante SPEI y regresar el pago a autorizado. El rol Pagos por sí solo no permite eliminar pagos pagados: esa acción y su vista previa siguen requiriendo la autorización existente. La concesión es específica de estas correcciones y no concede payment_authorizations.authorize.
- [ ] Caso: Pagos sin payment_authorizations.authorize ve Corregir pago en un PAY pagado de una OC abierta y puede cambiar el importe, reemplazar el comprobante y regresar a autorizado, sujeto a las reglas existentes.
- [ ] Caso: Pagos sin payment_authorizations.authorize no ve Eliminar pago en el modal; solicitar la vista previa de eliminación o enviar DELETE para el PAY pagado devuelve 403.
- [ ] Caso: Una cuenta Pagos que además cumpla los permisos actualmente necesarios para eliminar conserva esa capacidad; la combinación de roles no elimina derechos preexistentes.
- [ ] Caso: Un usuario sin el rol Pagos y sin autorización previa no obtiene acceso a las vistas previas o cambios de pagos pagados mediante solicitudes directas.
- [ ] Caso: Las vistas previas y transiciones para autorizar pagos siguen rechazando a Pagos sin payment_authorizations.authorize.
- [ ] Caso: Para una OC cerrada o un pago que no puede corregirse por sus movimientos, siguen operando los bloqueos y mensajes existentes.
- [ ] Caso: Administración conserva la corrección y eliminación y se mantienen las confirmaciones y vistas previas actuales.
- [ ] Caso: Pagos no ve Corregir pago para un PAY que no esté pagado; los demás controles y transiciones conservan sus condiciones actuales.
- [ ] Datos históricos: No hay migraciones, backfill, cambios masivos de datos ni sincronización de roles o permisos. La nueva regla aplica tras el despliegue a cuentas existentes con el rol Pagos; los pagos históricos pagados quedan sujetos a las mismas validaciones de corrección.
- [ ] Permanece igual: La eliminación de pagos pagados, incluida su vista previa, conserva payment_authorizations.authorize y el middleware payments_payable.update; Pagos sin esa autorización recibe 403 y no ve el formulario de eliminación.
- [ ] Permanece igual: La autorización de pagos y su vista previa siguen requiriendo payment_authorizations.authorize; no se amplían los permisos de autorización, las excepciones de checklist ni las transiciones ajenas a la corrección.
- [ ] Permanece igual: Las validaciones, transacciones, bloqueos, vistas previas, confirmaciones, recálculos y efectos existentes de cada corrección, incluyendo las restricciones de OC cerrada y pagos que generaron abonos.
- [ ] Permanece igual: Los accesos previos de administración y de las cuentas con las combinaciones de permisos existentes, así como las restricciones de los demás roles y del portal de proveedores.
- [ ] Permanece igual: Las rutas, verbos, middleware, matriz de permisos, roles, asignaciones, modelos y esquema de base de datos.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-012]` en el mensaje.

---

_Generado con Estoria Spec Tool v4.1 · Estoria Studio_
