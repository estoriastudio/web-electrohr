---
spec_id: HR-015
type: ext-rule
title: Exportar pagos pendientes de autorización a Excel
module: Pagos
module_key: payments
extends: HR-008
status: aprobado
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

# HR-015 · Pagos — Exportar pagos pendientes de autorización a Excel

**Módulo:** Pagos (`payments`) · **Tipo:** C3 · Cambio de regla · **Extiende:** HR-008

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos (si ya los leíste en esta conversación y no cambiaron, no los vuelvas a leer). Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos, fuera de alcance)
- `docs/controller-structure-and-notifications.md`
- `docs/purchase-order-payment-health-flow.md`
- `docs/supplier-account-statement.md`

## 1. Propósito

Permitir descargar a Excel el listado filtrado de pagos pendientes de autorización para conciliar el cierre de mes con los registros de otro departamento.

**Flujo de interacción**

El usuario aplica los filtros de Autorización de Pagos y pulsa Exportar a Excel. El archivo contiene las mismas filas y datos del listado, con importe y moneda separados, columnas para importe original y origen, y una columna NOTA que indica el estado del checklist.

## 2. Módulo afectado

- **Módulo:** Pagos
- **Usa Livewire:** no

**Comportamiento actual**

La bandeja payments.index filtra pagos por búsqueda, vencimiento, moneda, condición de pago y checklist. La tabla puede mostrar pagos con checklist completo o pendiente; sus indicadores solo suman los completos. No existe una descarga Excel para esta bandeja.

**Archivos que se tocan**

- app/Http/Controllers/PaymentController.php: compartir la consulta y evaluación de checklist del listado con la nueva descarga sin alterar el comportamiento del listado.
- app/Exports/: clase de exportación Excel para los pagos de autorización.
- routes/web.php: agregar una ruta GET para la descarga protegida por payment_authorizations.view.
- resources/views/payments/index.blade.php: botón de exportación con los filtros aplicados y restricción de rol.
- docs/purchase-order-payment-health-flow.md y docs/roles-permissions-matrix.md: documentar el reporte, sus filtros, el alcance de NOTA y su acceso.

**Debe permanecer igual**

- Los filtros, filas, orden, indicadores, selección persistente y acciones existentes de payments.index permanecen iguales.
- El checklist, las validaciones de secuencia, los permisos y bloqueos para autorizar pagos no cambian. NOTA no autoriza ni modifica pagos.
- Las restricciones de HR-008 para Pagado, Regularización documental, Estado de Cuenta interno y portal propio permanecen iguales, incluso para cuentas con roles combinados.
- Rutas, permisos, asignaciones de roles, modelos, tablas y registros existentes permanecen iguales; no se crean permisos ni se ejecutan seeders.
- El alcance por defecto de pruebas, archivos protegidos y paquetes de docs/specs/_project.json permanece vigente.

**Riesgos**

- LISTO PARA APROBAR significa solo que el checklist está completo: otras validaciones de secuencia, monto o permisos podrían impedir la autorización real.
- Una cuenta con supplier_portal_access y un rol interno puede tener permiso para la bandeja; se debe denegar expresamente la descarga antes de consultar o generar el archivo.
- Las columnas visibles no incluyen folio de pago y podrían no identificar de forma única un pago para la conciliación; no agregar columnas no solicitadas sin una decisión del equipo.
- Los textos de proveedor y otros datos capturados deben guardarse como texto, no como fórmulas de Excel.

## 3. Regla

**Actual**

No hay exportación del listado de pagos pendientes de autorización.

**Nueva**

La descarga Excel utiliza todos los filtros aplicados al listado, incluido checklist, y contiene los pagos que resulten de ellos, tanto completos como pendientes. NOTA refleja únicamente el checklist: completo es LISTO PARA APROBAR y pendiente es NO APROBAR.

**Dónde se aplica:** controller

**Casos afectados**

- Con el filtro predeterminado Checklist completo, el Excel contiene todos los pagos del listado con checklist completo y NOTA LISTO PARA APROBAR.
- Con Todos los checklists, el Excel contiene las filas completas y pendientes que cumplan los demás filtros; NOTA muestra LISTO PARA APROBAR o NO APROBAR respectivamente.
- Con Checklist pendiente, el Excel contiene los pagos pendientes que cumplan los demás filtros, todos con NOTA NO APROBAR.
- La búsqueda por proveedor y los filtros de vencimiento, moneda y condición de pago producen las mismas filas que el listado; se exportan todos los resultados y no solo una página.
- Cuando no hay resultados, se descarga un Excel válido con encabezados y sin filas de pagos.
- Una cuenta sin supplier_portal_access y con payment_authorizations.view puede descargar; quien no tiene el permiso no puede hacerlo.
- Una cuenta con supplier_portal_access no ve el botón y recibe HTTP 403 en la URL de descarga, incluso si además tiene admin o un rol interno.
- El botón usa los filtros ya aplicados, no valores editados que aún no se hayan enviado.
- Los importes son numéricos con dos decimales y la moneda se identifica por separado; los textos que empiezan con caracteres de fórmula se exportan como texto.
- Cuando la tabla muestra un importe original distinto del monto actual, el Excel lo muestra como valor numérico con dos decimales en Importe original; de lo contrario, la celda queda vacía.
- Origen muestra Resto de [folio] si la tabla muestra el folio del pago origen, Resto si no lo tiene, o Saldo del hito para un reemplazo; en los demás casos queda vacío.
- La descarga no altera pagos, checklist ni selección para autorización en bloque.

**Datos históricos**

No se modifican pagos, hitos, órdenes ni otros datos históricos. El reporte se calcula con el estado vigente al solicitar la descarga.

## 4. Reglas de negocio

- Reutilizar exactamente los mismos filtros, criterio de búsqueda, orden y evaluación de checklist del listado. Aplicar el filtro por defecto de checklist completo si no se envía el selector.
- El alcance anterior de solo checklist completo queda sustituido por la decisión posterior: con Todos se incluyen completos y pendientes, y con Pendiente se exportan pendientes.
- Exportar, en este orden, Semáforo, Orden de Compra, Proveedor, Proyecto / Obra, Monto, Moneda, Importe original, Origen, Tipo de hito, Pago / Vencimiento, Checklist y NOTA. Excluir selección y Acciones. Monto e Importe original son numéricos con dos decimales; Moneda y Origen son columnas separadas.
- Importe original reproduce el valor que la tabla muestra como «de $…», únicamente cuando original_amount no es nulo y difiere de amount por más de 0.004; en otro caso, la celda queda vacía. No sustituir un valor ausente por cero.
- Origen reproduce la etiqueta visible en la tabla: para rest, Resto de [folio] si existe el pago origen y Resto si no existe; para replacement, Saldo del hito; para los demás valores, celda vacía.
- Para las demás columnas conservar el significado y la información visible de la fila de la tabla, incluidos los detalles de condición cuando aparezcan; no agregar folio de pago propio ni otras columnas. En Pago / Vencimiento exportar la fecha de pago mostrada actualmente por la tabla.
- NOTA es LISTO PARA APROBAR si el checklist está completo y NO APROBAR si está pendiente. Su significado se limita al checklist y no garantiza que otras reglas permitan la autorización.
- Mostrar el botón y permitir la ruta a usuarios con payment_authorizations.view, salvo cuentas con supplier_portal_access. Esta exclusión prevalece incluso para admin y se comprueba en servidor antes de consultar o generar el archivo.
- Usar el paquete Excel existente y guardar los valores de texto explícitamente como texto para evitar fórmulas. No modificar la selección persistente al descargar.
- El botón envía los filtros que originaron la tabla visible. Una solicitud directa con los mismos parámetros debe aplicar la misma normalización y el mismo filtro.
- La exportación es de solo lectura y no registra notificaciones ni cambia datos.

## 5. Validaciones

_Convenciones de docs/controller-structure-and-notifications.md §4._

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Este spec extiende HR-008: se autoriza exclusivamente el nuevo acceso de descarga de Autorizaciones a usuarios sin supplier_portal_access que tengan payment_authorizations.view. No se modifican los permisos ni las descargas existentes; las restricciones de proveedor de HR-008 se conservan.
- Se agrega una nueva ruta GET para esta descarga, sin cambiar rutas existentes ni crear permisos o middleware nuevos.

## 7. Preguntas abiertas

- [x] ¿El reporte incluye pagos con checklist pendiente? → **Sí, cuando el filtro aplicado los incluye. Con checklist completo usa LISTO PARA APROBAR y con checklist pendiente NO APROBAR.**
- [x] ¿NOTA evalúa todos los bloqueos de autorización? → **No. Solo refleja si el checklist está completo.**
- [x] ¿Quién puede descargar? → **Quien tiene acceso a Autorizaciones mediante payment_authorizations.view, salvo cuentas con supplier_portal_access, incluso con otros roles.**
- [x] ¿Cómo se representan las columnas del listado? → **Se conservan las columnas de datos visibles más NOTA; se separan monto numérico, moneda, importe original y origen, y se excluyen selección y acciones.**

## 8. Fuera de alcance

Aplica la lista `oos` de `docs/specs/_project.json`.

**Además, fuera de alcance en este spec:**

- Implementar el cambio durante la preparación del spec; primero debe generarse y aprobarse el spec.
- Agregar o modificar columnas, modelos, tablas o registros persistidos.
- Cambiar la lógica de autorización, checklist, secuencia o cálculo de indicadores.
- Agregar permisos, roles, asignaciones de permisos, paquetes o exportaciones de otras bandejas.

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en el controller: La descarga Excel utiliza todos los filtros aplicados al listado, incluido checklist, y contiene los pagos que resulten de ellos, tanto completos como pendientes. NOTA refleja únicamente el checklist: completo es LISTO PARA APROBAR y pendiente es NO APROBAR.
- [ ] Caso: Con el filtro predeterminado Checklist completo, el Excel contiene todos los pagos del listado con checklist completo y NOTA LISTO PARA APROBAR.
- [ ] Caso: Con Todos los checklists, el Excel contiene las filas completas y pendientes que cumplan los demás filtros; NOTA muestra LISTO PARA APROBAR o NO APROBAR respectivamente.
- [ ] Caso: Con Checklist pendiente, el Excel contiene los pagos pendientes que cumplan los demás filtros, todos con NOTA NO APROBAR.
- [ ] Caso: La búsqueda por proveedor y los filtros de vencimiento, moneda y condición de pago producen las mismas filas que el listado; se exportan todos los resultados y no solo una página.
- [ ] Caso: Cuando no hay resultados, se descarga un Excel válido con encabezados y sin filas de pagos.
- [ ] Caso: Una cuenta sin supplier_portal_access y con payment_authorizations.view puede descargar; quien no tiene el permiso no puede hacerlo.
- [ ] Caso: Una cuenta con supplier_portal_access no ve el botón y recibe HTTP 403 en la URL de descarga, incluso si además tiene admin o un rol interno.
- [ ] Caso: El botón usa los filtros ya aplicados, no valores editados que aún no se hayan enviado.
- [ ] Caso: Los importes son numéricos con dos decimales y la moneda se identifica por separado; los textos que empiezan con caracteres de fórmula se exportan como texto.
- [ ] Caso: Cuando la tabla muestra un importe original distinto del monto actual, el Excel lo muestra como valor numérico con dos decimales en Importe original; de lo contrario, la celda queda vacía.
- [ ] Caso: Origen muestra Resto de [folio] si la tabla muestra el folio del pago origen, Resto si no lo tiene, o Saldo del hito para un reemplazo; en los demás casos queda vacío.
- [ ] Caso: La descarga no altera pagos, checklist ni selección para autorización en bloque.
- [ ] Datos históricos: No se modifican pagos, hitos, órdenes ni otros datos históricos. El reporte se calcula con el estado vigente al solicitar la descarga.
- [ ] Permanece igual: Los filtros, filas, orden, indicadores, selección persistente y acciones existentes de payments.index permanecen iguales.
- [ ] Permanece igual: El checklist, las validaciones de secuencia, los permisos y bloqueos para autorizar pagos no cambian. NOTA no autoriza ni modifica pagos.
- [ ] Permanece igual: Las restricciones de HR-008 para Pagado, Regularización documental, Estado de Cuenta interno y portal propio permanecen iguales, incluso para cuentas con roles combinados.
- [ ] Permanece igual: Rutas, permisos, asignaciones de roles, modelos, tablas y registros existentes permanecen iguales; no se crean permisos ni se ejecutan seeders.
- [ ] Permanece igual: El alcance por defecto de pruebas, archivos protegidos y paquetes de docs/specs/_project.json permanece vigente.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-015]` en el mensaje.

---

_Generado con Estoria Spec Tool v4.1 · Estoria Studio_
