# Matriz de Roles y Permisos

Este archivo es la fuente de referencia para la matriz de acceso.

Cuando se agreguen o cambien roles, permisos o rutas, actualiza este archivo primero.

## Modelo de acceso (desde 2026-10-08)

El acceso a los modulos del catalogo se decide **solo por permisos** (`<modulo>.<accion>`), no por el nombre del rol.

- **Rol = perfil de permisos.** Un rol es un paquete de permisos reutilizable. Un usuario puede tener varios roles y ademas permisos directos (casilla "Asignar permisos directos" en Usuarios). Spatie suma las concesiones; un perfil de solo lectura no revoca lo concedido por otro.
- **`admin`** conserva acceso total (bypass en `Gate::before`/gates de `AppServiceProvider`) y es el unico que accede a Usuarios, Auditoria, papeleras y aprobaciones.
- **Menu, dashboard, rutas y vistas** usan los mismos permisos: `@can('<modulo>.<accion>')` en Blade, `permission:` / `module:` en rutas.
- **Acciones:** `read` (ver), `create` (alta del registro principal), `update` (editar contenido, notas, documentos y registros dependientes), `delete` (eliminar/archivar). Entradas y salidas de inventario usan `stocks.create`; ajustes manuales `stocks.update`. Hitos y condiciones de pago dentro de una OC usan `payments.*`.
- **Permisos extra** (alcance de datos o acciones especiales, sin patron CRUD):

| Permiso | Efecto |
|---------|--------|
| `invoices.approve` | Aprobar facturas |
| `invoices.view_all` | Ver facturas de todos los compradores (sin el, un comprador solo ve las de sus OC) |
| `purchase_orders.view_all` | Listado completo de OC por defecto (sin el, "Mis OC") |
| `payments.register_any` | Registrar pagos en cualquier OC (sin el, solo OC de destajo) |
| `material_requests.commit` | Comprometer existencias en la pila SOLMAT |

## Catalogo de modulos

`purchase_orders`, `payments`, `invoices`, `purchase_requests`, `material_requests`, `suppliers`, `projects`, `mobile_assets`, `material_vouchers`, `stocks`, `tools`, `concepts`, cada uno con `read`, `create`, `update`, `delete` (mas los extras anteriores). Se define en `config/module_permissions.php`.

## Middleware de rutas

- `module:<modulo>[,<modulo2>]` — metodos seguros (GET/HEAD) exigen `<modulo>.read`; el resto exige alguno de `create`, `update` o `delete`.
- `permission:a|b` — exige cualquiera de los permisos (admin pasa por el Gate).
- `role:` solo se conserva en las areas fuera del catalogo (ver abajo).

## Mapeo modulo -> permiso

| Modulo funcional | Permiso / middleware |
|------------------|----------------------|
| Dashboard: indicadores de Pagos, pendientes de autorizar, grafica | `payments.read` |
| Dashboard: bloque de Compras (SOLCOM pendientes, OC, urgencias de entrega) | `purchase_orders.create` |
| Proveedores y Estado de cuenta | `module:suppliers` (borrar: `suppliers.delete`; estado de cuenta/detalle: `suppliers.read`) |
| Bienes Moviles | `module:mobile_assets` |
| Proyectos y Obras | `module:projects` |
| Vales de Material | `module:material_vouchers` |
| Inventario (consulta, entradas, salidas) | `module:stocks` |
| Herramientas: registro, fotos, calibraciones, Control de uso y Categorias de herramientas | `module:tools` (ver: `tools.read`; alta: `tools.create`; editar, fotos y calibraciones: `tools.update`; archivar/restaurar y eliminar calibraciones: `tools.delete`). Eliminacion definitiva: `role:admin` |
| Conceptos (listado, importar, archivar), Familias de conceptos y subcategorias, asignacion de compradores | `module:concepts` (misma division read/create/update/delete) |
| Precios adjudicados | `concepts.read\|purchase_orders.read` |
| Busquedas JSON de conceptos y subcategorias (formularios de OC/SOLCOM/SOLMAT) | Cualquier usuario autenticado |
| SOLMAT (listado, alta, edicion) | `module:material_requests` |
| Cambios SOLMAT y solicitar/resolver cambios | `material_requests.read` |
| Pila SOLMAT | `material_requests.read`; crear SOLCOM desde SOLMAT: `purchase_requests.create`; compromisos: `material_requests.commit` |
| SOLCOM, Cambios SOLCOM, Pila SOLCOM | `module:purchase_requests` |
| Carga de Trabajo | `purchase_orders.create` |
| Ordenes de Compra (lectura, anexos, PDF) | `purchase_orders.read` |
| Ordenes de Compra (alta, edicion, baja, emitir) | `purchase_orders.create\|update\|delete` |
| Vencidas de entrega | `purchase_orders.create` |
| Evidencias y estatus de entrega | `purchase_orders.read` |
| Hitos de pago (lectura / escritura) | `payments.read` / `payments.create\|update\|delete` |
| Pagos (autorizar, por pagar, pagados) | `module:payments` |
| Registrar pago, solicitar reactivacion | `payments.create\|update` |
| Contrarecibo, comprobante SPEI, hitos de una OC (AJAX) | `payments.read` |
| Alta de Facturas, subir/descargar/borrar | `module:invoices` |
| Listado, detalle y exportacion de Facturas | `invoices.read` |
| Cambiar estatus de factura | `invoices.update\|invoices.approve` |

### Areas que siguen por rol (fuera del catalogo)

| Area | Rol |
|------|-----|
| Recursos Humanos | `admin`, `Recursos Humanos` (consulta propia para `Engineer`) |
| Asignacion de obras a Engineer | `Engineer` |
| Usuarios, Auditoria y Notificaciones (campanas del topbar, bandeja, marcar como leidas), papeleras, aprobaciones, autorizacion de OC, modo interactivo de pagos | `admin` |
| Portal de proveedores y facturacion de portal | `supplier_portal_access` |

> Las notificaciones son exclusivas de `admin`: las campanas del topbar y las rutas `notifications.index`, `notifications.inbox` y `notifications.markAllRead` usan `role:admin`. Los avisos dirigidos a otros usuarios se siguen registrando, pero ya no tienen bandeja visible para ellos.

## Roles heredados (plantillas)

Los roles historicos siguen existiendo como **perfiles** con los permisos de la plantilla de `config/module_permissions.php` (`access_roles` y `access_role_extras`). Las plantillas solo se aplican cuando el rol se crea; despues el administrador puede ajustarlos desde Usuarios y el nombre del rol ya no concede acceso por si mismo.

| Rol | Permisos de plantilla |
|-----|-----------------------|
| Orden de compra | purchase_orders CRUD, purchase_requests CRUD, suppliers CRUD, payments CRUD, invoices CRUD + approve, material_requests read |
| Pagos | payments CRUD, invoices CRUD + approve, suppliers CRUD, material_vouchers CRUD, purchase_orders read; extras `purchase_orders.view_all`, `invoices.view_all`, `payments.register_any` |
| Solcom | purchase_requests CRUD, material_requests read |
| suministros | material_requests read; extra `material_requests.commit` |
| Solmat | material_requests CRUD, projects CRUD, purchase_orders read, invoices read/create, tools CRUD, concepts CRUD; extra `invoices.view_all` |
| Proyectos | projects CRUD |
| Engineer | projects read |
| Moviles | mobile_assets CRUD, tools CRUD, material_vouchers CRUD |
| Proveedor | material_vouchers CRUD |
| Recepción | invoices CRUD + approve; extra `invoices.view_all` |
| Inventario | stocks CRUD |
| admin | Todo (bypass) |
| supplier_portal_access | Portal de proveedores (por rol) |

Perfiles adicionales: `Administrador de pagos` (payments + invoices CRUD/approve + extras de Pagos), `Ayudante de pagos` (`payments.read`, `invoices.read`), `Comprador` (purchase_orders y purchase_requests CRUD, `suppliers.read`) y `<Rol> - permisos iniciales` (creados por la migracion).

> Los roles `orders` y `payments` (en ingles) quedaron obsoletos el 2026-06-16.

## Migracion de usuarios existentes

La migracion `2026_10_08_150000_migrate_access_roles_to_permissions` conserva el acceso de quienes solo tenian el rol heredado:

- Si el usuario tiene el rol heredado y **ninguno** de los permisos `read` de sus modulos, recibe el perfil `<Rol> - permisos iniciales`.
- Si ya tenia permisos de lectura (perfiles o directos), se respeta lo configurado y solo se le agregan los permisos extra de alcance que antes daba el rol (`view_all`, `register_any`, `commit`).
- No se borran perfiles ni concesiones al revertirla.
- `RolesAndPermissionsSeeder` crea `admin` y `supplier_portal_access`, inicializa el catalogo y crea los perfiles/plantillas faltantes sin sobrescribir los existentes.

La migracion `2026_10_08_160000_migrate_tools_concepts_to_permissions` agrega los permisos de `tools` y `concepts` a los roles `Solmat` y `Moviles` (y a sus perfiles `<Rol> - permisos iniciales`) segun la plantilla, para que quienes ya usaban Herramientas y Conceptos conserven el acceso. Es idempotente.

## Cambios de alcance respecto al modelo anterior

Al decidir por permisos en lugar de rol, estos accesos cambian segun lo que cada plantilla concede:

- Quien tenga `payments.*` (p. ej. Orden de compra) accede a las paginas de Pagos y las ve en el menu.
- Quien tenga `material_requests.read` (Solcom, suministros, Orden de compra, Solmat) accede al listado SOLMAT, Cambios SOLMAT y Pila SOLMAT en modo lectura; crear SOLCOM desde la pila exige `purchase_requests.create`.
- Quien tenga `purchase_orders.read` (incluye Pagos) puede cambiar el estatus de entrega y gestionar evidencias.
- La tarjeta de urgencias de entrega del dashboard se muestra a quien tenga `purchase_orders.create` (antes solo admin, aunque los datos se calculaban para Compras).
- Los selectores de comprador y las notificaciones a Solmat/Compras se calculan por permiso (`purchase_orders.create`, `material_requests.create`), no por rol.
- Herramientas y Conceptos pasan de `admin|Solmat` a permisos `tools.*` y `concepts.*`: cualquier perfil con esos permisos accede, y los botones de alta/edicion/archivo se muestran segun la accion. Moviles gana acceso real a Herramientas (antes veia el menu pero las rutas lo rechazaban).
- Notificaciones: Solmat deja de ver la bandeja y las campanas del topbar; quedan solo para `admin`.

## Ordenes de Compra por Comprador

- `buyer_id` vincula la OC a `users.id`. Al crear desde el listado o desde Pila SOLCOM se asigna el usuario autenticado; Elabora Orden muestra su nombre y no admite texto libre.
- Quien tiene `purchase_orders.view_all` (admin y Pagos por plantilla) recibe el listado completo por defecto y puede cambiar a Mis OC. Los demas reciben Mis OC, incluso si solicitan `scope=all`; no se devuelve un error por rol.
- Listados activos, archivados y facturas recientes respetan ese alcance. Con `view_all` se pueden filtrar OC sin comprador con `buyer_status=unassigned`. Las bandejas administrativas, aprobaciones y reasignacion de comprador siguen exclusivas de admin.
- Blade muestra controles de Compras solo para admin o el comprador vinculado. Solo admin ve el selector para asignar o cambiar comprador. La reasignacion registra una notificacion y conserva la firma historica `elaborated_by`.
- Facturas, contadores, exportacion y seleccion de OC para alta se filtran por comprador cuando el usuario tiene `purchase_orders.create` sin `invoices.view_all` y no es admin.
- Los controles Blade son visibilidad; la autorizacion real la dan los middleware `module:`/`permission:` de las rutas y las restricciones de estado y comprador existentes.

## Convenciones

- Menu, dashboard, vistas y rutas usan el mismo permiso para un mismo modulo.
- Para dar acceso a un usuario: asigna uno o mas roles (perfiles) y, si hace falta, "Asignar permisos directos" para ajustes individuales.
- `admin` tiene acceso total.
- Un permiso nuevo del catalogo debe agregarse a `config/module_permissions.php`, a la ruta/vista correspondiente y a este documento.

## Historial de Cambios

Agrega una linea por cambio para trazabilidad.

- 2026-06-15: Creacion inicial de matriz de roles y permisos.
- 2026-06-16: Roles renombrados a español; eliminados `orders`/`payments` (inglés). Agregado rol `Recepción` (solo Alta de Facturas). Bienes Móviles y Proyectos ahora protegidos por middleware. Solmat accede a Proyectos y Pila SOLMAT. Orden de compra reemplaza a Solcom como nombre de rol.
- 2026-07-13: Se agrega rol `supplier_portal_access` y su mapeo de Portal de Proveedores. Se documenta tambien `Proveedor` en Vales de Material y se actualizan mapeos de Hitos/Alta de Facturas segun rutas actuales.
- 2026-07-13: Se agrega el modulo funcional Evidencias de Entrega OC con acceso para admin, Solmat, Pagos y Orden de compra.
- 2026-09-14: Se agrega el rol Inventario para consulta y gestión de existencias, entradas y salidas.
- 2026-09-15: Engineer accede a Proyectos solo cuando está asignado como supervisor o residente de una obra; admin conserva el listado completo.
- 2026-09-29: OC vinculadas a comprador usuario; Mis OC y listado completo admin, precarga de Elabora Orden, reasignacion visual admin y vinculacion historica por nombres exactos.
- 2026-09-30: Pagos accede tambien al listado completo de OC activas y archivadas, sin ampliar permisos de aprobacion o reasignacion.
- 2026-09-30: Permisos por modulo (`<modulo>.<accion>`) y perfiles compartidos para los diez modulos del catalogo.
- 2026-10-08: Migracion completa a permisos. Menu, dashboard, rutas, vistas y controladores deciden por permiso; los roles heredados pasan a ser perfiles-plantilla. Nuevo middleware `module:`, permisos extra (`invoices.view_all`, `purchase_orders.view_all`, `payments.register_any`, `material_requests.commit`) y migracion de usuarios existentes. Matriz CRUD global obsoleta eliminada.
- 2026-10-08: Herramientas y Conceptos migran a permisos (`tools.*`, `concepts.*`, 12 modulos en el catalogo) con `module:tools`/`module:concepts`, plantillas Solmat y Moviles actualizadas y migracion `2026_10_08_160000`. Notificaciones (campanas, bandeja y marcar leidas) quedan solo para admin.
