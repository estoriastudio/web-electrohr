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
- Solcom
- Solmat
- supplier_portal_access

> **Nota:** Los roles anteriores `orders` y `payments` (en inglés) quedaron obsoletos el 2026-06-16.
> Reasigna los usuarios con esos roles vía el panel Admin antes de borrarlos.

## Permisos Base (CRUD)

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
| Proyectos                  | Proyectos, Solmat               |
| Solicitudes de Compra      | Solcom, Orden de compra, admin  |
| Pila SOLCOM                | Solcom, Orden de compra         |
| Carga de Trabajo           | Orden de compra                 |
| Solicitudes de Material    | Solmat                          |
| Pila SOLMAT                | Solmat, Orden de compra         |

## Convenciones

- Los roles controlan visibilidad de modulos en el menu.
- Los permisos CRUD controlan acciones dentro de las vistas.
- `admin` tiene acceso total.

## Historial de Cambios

Agrega una linea por cambio para trazabilidad.

- 2026-06-15: Creacion inicial de matriz de roles y permisos.
- 2026-06-16: Roles renombrados a español; eliminados `orders`/`payments` (inglés). Agregado rol `Recepción` (solo Alta de Facturas). Bienes Móviles y Proyectos ahora protegidos por middleware. Solmat accede a Proyectos y Pila SOLMAT. Orden de compra reemplaza a Solcom como nombre de rol.
- 2026-07-13: Se agrega rol `supplier_portal_access` y su mapeo de Portal de Proveedores. Se documenta tambien `Proveedor` en Vales de Material y se actualizan mapeos de Hitos/Alta de Facturas segun rutas actuales.
- 2026-07-13: Se agrega el modulo funcional Evidencias de Entrega OC con acceso para admin, Solmat, Pagos y Orden de compra.
