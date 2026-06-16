# Matriz de Roles y Permisos

Este archivo es la fuente de referencia para la matriz de acceso por rol.

Cuando se agreguen o cambien roles, actualiza este archivo primero.

## Roles Vigentes

- admin
- Moviles
- Orden de compra
- Pagos
- Proyectos
- Solcom
- Solmat

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
| Proyectos        |   SI   |  SI  |   SI   |   SI   |
| Solcom           |   SI   |  SI  |   SI   |   SI   |
| Solmat           |   SI   |  SI  |   SI   |   SI   |

## Mapeo de Modulo por Rol

Usa esta seccion para mantener clara la relacion entre modulo funcional y rol esperado.

| Modulo Funcional           | Rol Esperado      |
|---------------------------|-------------------|
| Dashboard                 | Cualquier rol con read |
| Bienes Moviles            | Moviles           |
| Proveedores               | Orden de compra   |
| Ordenes de Compra         | Orden de compra   |
| Pagos                     | Pagos             |
| Proyectos                 | Proyectos         |
| Solicitudes de Compra     | Solcom            |
| Solicitudes de Material   | Solmat            |

## Convenciones

- Los roles controlan visibilidad de modulos en el menu.
- Los permisos CRUD controlan acciones dentro de las vistas.
- `admin` tiene acceso total.

## Historial de Cambios

Agrega una linea por cambio para trazabilidad.

- 2026-06-15: Creacion inicial de matriz de roles y permisos.
