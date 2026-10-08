# Matriz de Roles y Permisos

Este archivo es la fuente de referencia para la matriz de acceso por rol.

Cuando se agreguen o cambien roles, actualiza este archivo primero.

## Roles Vigentes

- admin
- Moviles
- Orden de compra
- Pagos
- Proveedor
- Proyectos
- Recepción
- Inventario
- Solcom
- Solmat
- supplier_portal_access

> **Nota:** Los roles anteriores `orders` y `payments` (en inglés) quedaron obsoletos el 2026-06-16.
> Reasigna los usuarios con esos roles vía el panel Admin antes de borrarlos.

## Permisos Base (CRUD)

### Permisos por modulo (2026-09-30)

- Los permisos de interfaz usan `<modulo>.<accion>`; los CRUD globales son heredados y no conceden permisos por modulo.
- Los roles actuales siguen permitiendo acceder a las rutas. Los perfiles compartidos conceden permisos de interfaz; un usuario necesita tanto el rol de acceso como el perfil correspondiente.
- Spatie suma concesiones de perfiles y permisos directos. Un perfil de solo lectura no revoca permisos concedidos por otro perfil.
- `admin` conserva acceso total a los controles. No se agregan restricciones de endpoints: ocultar controles no protege solicitudes directas.
- El catalogo incluye Compras, Pagos, Facturas, SOLCOM, SOLMAT, Proveedores, Proyectos, Moviles, Vales e Inventario. Facturas incluye `invoices.approve`.
- La migracion crea perfiles iniciales a partir de los CRUD de los roles existentes y los asigna a sus usuarios para conservar acceso. Los permisos directos heredados se traducen solo a modulos accesibles por sus roles.
- Los perfiles predeterminados se crean una sola vez; repetir los seeders no sobrescribe ajustes. Administrador de pagos permite gestionar Pagos y Facturas; Ayudante de pagos solo permite consultarlos.
- Usuarios permite crear y editar perfiles con cualquier permiso del catalogo. Un nuevo nombre fuera del catalogo no tiene comportamiento implementado.
- Ejemplo: asignar `Orden de compra`, `Pagos`, `Comprador` y `Ayudante de pagos` permite gestionar Compras y consultar Pagos/Facturas. Retirar `Pagos - permisos iniciales`, otros perfiles y permisos directos que concedan gestion de Pagos si se requiere solo lectura.
- Los roles de acceso se conservan separados; asignar solo un perfil no permite atravesar los middleware de rol existentes. Las restricciones previas de admin, comprador y estado del documento siguen vigentes.
- `update` controla cambios de contenido, notas, documentos y registros dependientes dentro del detalle del modulo; `create`/`delete` controlan el alta/eliminacion del registro principal. Entradas y salidas de inventario usan `stocks.create`; ajustes manuales usan `stocks.update`.
- Hitos y condiciones de pago dentro de una OC usan `payments.create`, `payments.update` y `payments.delete`; editar Compras no concede gestion de Pagos.
- Esta primera integracion cubre los diez modulos del catalogo. Recursos Humanos, Herramientas, Suministros/Conceptos y Portal de proveedores mantienen su comportamiento previo y no forman parte del catalogo.
- La migracion no borra perfiles ni concesiones al revertirla, para no eliminar configuraciones que un administrador haya ajustado despues.

- create
- read
- update
- delete

## Matriz Actual

| Rol              | create | read | update | delete |
|------------------|:------:|:----:|:------:|:------:|
| admin            |   SI   |  SI  |   SI   |   SI   |
| Moviles          |   SI   |  SI  |   SI   |   SI   |
| Orden de compra  |   SI   |  SI  |   SI   |   SI   |
| Pagos            |   SI   |  SI  |   SI   |   SI   |
| Proveedor        |   SI   |  SI  |   SI   |   SI   |
| Proyectos        |   SI   |  SI  |   SI   |   SI   |
| Recepción        |   SI   |  SI  |   SI   |   SI   |
| Inventario       |   SI   |  SI  |   SI   |   SI   |
| Solcom           |   SI   |  SI  |   SI   |   SI   |
| Solmat           |   SI   |  SI  |   SI   |   SI   |
| supplier_portal_access | SI | SI | SI | SI |

## Mapeo de Modulo por Rol

Usa esta seccion para mantener clara la relacion entre modulo funcional y rol esperado.

| Modulo Funcional           | Rol Esperado                    |
|----------------------------|---------------------------------|
| Dashboard                  | Cualquier rol con read          |
| Bienes Moviles             | Moviles                         |
| Proveedores                | Orden de compra, admin          |
| Portal de Proveedores      | supplier_portal_access          |
| Facturacion portal proveedor | supplier_portal_access        |
| Vales de Material          | Pagos, Proveedor, Moviles       |
| Ordenes de Compra (lectura)| Orden de compra, Pagos          |
| Ordenes de Compra (CUD)    | Orden de compra                 |
| Hitos de Pago              | Pagos, Orden de compra          |
| Alta de Facturas (interna) | Pagos, Recepción, Orden de compra |
| Evidencias de Entrega OC   | admin, Solmat, Pagos, Orden de compra |
| Autorización de Pagos      | Pagos                           |
| Proyectos                  | Proyectos, Solmat, Engineer     |
| Solicitudes de Compra      | Solcom, Orden de compra, admin  |
| Pila SOLCOM                | Solcom, Orden de compra         |
| Carga de Trabajo           | Orden de compra                 |
| Solicitudes de Material    | Solmat                          |
| Pila SOLMAT                | Solmat, Orden de compra         |
| Inventario                 | Inventario                      |

## Convenciones

- Los roles controlan visibilidad de modulos en el menu.
- Los permisos CRUD controlan acciones dentro de las vistas.
- `admin` tiene acceso total.

## Ordenes de Compra por Comprador

- `buyer_id` vincula la OC a `users.id`. Al crear desde el listado o desde Pila SOLCOM se asigna el usuario autenticado; Elabora Orden muestra su nombre y no admite texto libre.
- Admin y Pagos reciben el listado completo por defecto y pueden cambiar a Mis OC. Todos los demas perfiles con acceso al listado reciben Mis OC, incluso si solicitan `scope=all`; no se devuelve un error por rol.
- Listados activos, archivados y facturas recientes de la pagina de OC respetan ese alcance. Admin y Pagos pueden filtrar OC sin comprador asignado con `buyer_status=unassigned`. Las bandejas administrativas, aprobaciones y reasignacion de comprador siguen exclusivas de admin.
- Blade muestra controles de Compras solo para admin o el comprador vinculado. Solo admin ve el selector para asignar o cambiar comprador. La reasignacion registra una notificacion y conserva la firma historica `elaborated_by`.
- Pagos conserva sus flujos de pagos/facturas. SOLMAT y Recepcion conservan los accesos colaborativos existentes. Facturas, contadores, exportacion y seleccion de OC para alta se filtran por comprador cuando el usuario tiene Compras sin ninguno de esos roles operativos ni admin.
- No se agregan bloqueos de rol en controladores ni nuevas policies o middleware de rol. Se mantienen los middleware y restricciones de estado existentes. **Los controles Blade son visibilidad, no autorizacion de solicitudes directas:** este cambio no impide invocar endpoints de gestion manualmente cuando los middleware existentes lo permiten.

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
