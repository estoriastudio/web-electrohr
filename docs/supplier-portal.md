# Portal de Proveedores (SAHR 2.0)

Este documento describe el funcionamiento del Portal de Proveedores, su modelo de acceso y su alcance funcional dentro de SAHR 2.0.

## Objetivo

Permitir que cada proveedor cargue su documentacion de facturacion por hito de Orden de Compra, con un flujo simple y controlado por permisos.

## Rol y Control de Acceso

## Rol principal del portal

- supplier_portal_access

## Requisitos para ingresar

- Usuario autenticado en la plataforma.
- Rol supplier_portal_access asignado al usuario.
- Vínculo activo del usuario con un proveedor.
- Acceso al portal habilitado en el registro del proveedor.

## Bloqueo de acceso

El acceso al portal se bloquea cuando:

- El proveedor no tiene el rol requerido.
- El usuario no esta vinculado al proveedor.
- El proveedor tiene el acceso deshabilitado.

El bloqueo aplica tanto para navegar dentro del portal como para iniciar sesion con usuario de proveedor cuando el acceso fue desactivado.

## Rutas del Portal

Prefijo de rutas: /portal-proveedor

- GET /portal-proveedor
  - Nombre: supplier_portal.dashboard
  - Vista principal del portal.
- GET /portal-proveedor/ordenes-compra
  - Nombre: supplier_portal.purchase_orders.index
  - Listado de Ordenes de Compra e hitos relevantes para el proveedor.
- GET /portal-proveedor/vales-material
  - Nombre: supplier_portal.material_vouchers.index
  - Consulta de vales de material relacionados al proveedor.
- GET /portal-proveedor/ordenes-compra/{purchaseOrder}/facturas/nueva
  - Nombre: supplier_portal.invoices.create
  - Formulario de carga de factura por hito.
- POST /portal-proveedor/ordenes-compra/{purchaseOrder}/facturas
  - Nombre: supplier_portal.invoices.store
  - Persistencia de la factura y evidencias.

## Flujo Funcional de Facturacion

1. El proveedor entra al listado de Ordenes de Compra por hitos.
2. El proveedor selecciona el hito habilitado para carga.
3. El sistema precarga datos del hito (moneda e importe) y reduce campos editables.
4. El proveedor sube archivos requeridos:
   - PDF de factura.
   - Evidencia de soporte.
   - XML opcional.
5. El sistema guarda la informacion asociada al hito.
6. En el listado, cuando existe factura y evidencia para el hito, se muestra el estado Documentacion recibida.

## Validaciones Relevantes

- La fecha del hito no debe estar en futuro para permitir el alta.
- El XML es opcional.
- El estado visual en listado depende de evidencia persistida, no solo de elegibilidad del hito.

## Gestion Administrativa del Acceso

Desde el modulo de Proveedores, los usuarios con permisos de compras pueden:

- Habilitar acceso de portal para un proveedor.
- Deshabilitar acceso (incluye cierre de sesiones activas).
- Reactivar acceso.

## Elementos de UI Relacionados

- Topbar: la campana de notificaciones no se muestra para supplier_portal_access.
- Navbar: se muestran accesos del Portal de Proveedor segun rol.
- Pantalla de bienvenida: mensaje especifico para proveedor y banner configurable por rol.

## Convenciones Operativas

- El proveedor solo debe ver informacion propia.
- La carga se hace por hito para evitar errores de captura.
- El equipo interno valida y da seguimiento a la documentacion recibida.

## Historial de Cambios

- 2026-07-13: Creacion inicial del documento del Portal de Proveedores.
