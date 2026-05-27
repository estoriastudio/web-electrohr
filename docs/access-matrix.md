# Matriz de Accesos — ElectroHR

> **Fuente de verdad** para la gestión de roles y permisos.
> Librería: [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
> Implementación: middleware `role:` en rutas + directivas `@role` / `@hasanyrole` en vistas Blade.

---

## Roles

| Rol | Descripción |
|---|---|
| `admin` | Acceso total a todos los módulos y acciones |
| `payments` | Gestión de pagos y facturas; lectura de órdenes de compra |
| `orders` | Gestión de órdenes de compra e hitos; sin acceso a pagos |

---

## Matriz Detallada

### Proveedores (`/proveedores`)

| Acción | admin | payments | orders |
|---|:---:|:---:|:---:|
| Ver listado | ✅ | ❌ | ✅ |
| Ver detalle | ✅ | ❌ | ✅ |
| Crear proveedor | ✅ | ❌ | ✅ |
| Editar proveedor | ✅ | ❌ | ✅ |
| Eliminar proveedor | ✅ | ❌ | ✅ |
| Importar (Excel) | ✅ | ❌ | ✅ |
| Exportar (Excel) | ✅ | ❌ | ✅ |

**Middleware de ruta:** `role:admin|orders`

---

### Órdenes de Compra (`/ordenes-de-compra`)

| Acción | admin | payments | orders |
|---|:---:|:---:|:---:|
| Ver listado | ✅ | ✅ | ✅ |
| Ver detalle | ✅ | ✅ | ✅ |
| Crear OC | ✅ | ❌ | ✅ |
| Editar OC | ✅ | ❌ | ✅ |
| Eliminar OC | ✅ | ❌ | ✅ |

**Middleware de ruta:** `role:admin|payments|orders`
**Botones de creación/edición/eliminación:** `@hasanyrole('admin|orders')`

---

### Hitos de Pago (`/hitos`)

| Acción | admin | payments | orders |
|---|:---:|:---:|:---:|
| Ver listado independiente (`/hitos`) | ✅ | ✅ | ✅ |
| Ver hitos dentro de una OC | ✅ | ✅ | ✅ |
| Crear hito | ✅ | ❌ | ✅ |
| Editar hito | ✅ | ❌ | ✅ |
| Eliminar hito | ✅ | ❌ | ✅ |

**Middleware de ruta:** `role:admin|payments|orders`
**Botones de creación/edición/eliminación:** `@hasanyrole('admin|orders')`

---

### Pagos (`/pagos`)

| Acción | admin | payments | orders |
|---|:---:|:---:|:---:|
| Ver listado de pagos | ✅ | ✅ | ❌ |
| Modo interactivo (swipable) | ✅ | ❌ | ❌ |
| Crear pago | ✅ | ✅ | ❌ |
| **Autorizar pago** | ✅ | ❌ | ❌ |
| Marcar como pagado | ✅ | ✅ | ❌ |
| Rechazar pago | ✅ | ✅ | ❌ |
| Reactivar pago (→ por autorizar) | ✅ | ✅ | ❌ |
| Eliminar pago | ✅ | ✅ | ❌ |

**Middleware de ruta:** `role:admin|payments`
**Modo interactivo:** `role:admin`
**Botón Autorizar:** `@role('admin')`

---

### Facturas (`/facturas`)

| Acción | admin | payments | orders |
|---|:---:|:---:|:---:|
| Subir factura | ✅ | ✅ | ❌ |
| Descargar factura | ✅ | ✅ | ❌ |
| Eliminar factura | ✅ | ✅ | ❌ |

**Middleware de ruta:** `role:admin|payments`
**Directiva en vistas:** `@hasanyrole('admin|payments')`

---

### Usuarios y Roles (`/usuarios`, `/roles`)

| Acción | admin | payments | orders |
|---|:---:|:---:|:---:|
| Ver listado de usuarios | ✅ | ❌ | ❌ |
| Crear usuario | ✅ | ❌ | ❌ |
| Editar usuario | ✅ | ❌ | ❌ |
| Eliminar usuario | ✅ | ❌ | ❌ |
| Crear rol | ✅ | ❌ | ❌ |
| Eliminar rol | ✅ | ❌ | ❌ |
| Editar perfil propio (`/usuarios/{id}`) | ✅ | ✅ | ✅ |

**Middleware de ruta:** `role:admin` (excepto show propio)

---

### Notificaciones / Auditoría (`/auditoria`)

| Acción | admin | payments | orders |
|---|:---:|:---:|:---:|
| Ver registro de auditoría | ✅ | ❌ | ❌ |
| Marcar notificaciones como leídas | ✅ | ❌ | ❌ |

**Middleware de ruta:** `role:admin`

---

## Resumen de Middleware por Ruta

```php
// Proveedores
role:admin

// Órdenes de Compra (resource completo + show para payments)
role:admin|payments|orders

// Hitos
role:admin|payments|orders

// Pagos (index, store, update, destroy)
role:admin|payments

// Pagos — Modo Interactivo (swipable)
role:admin

// Facturas
role:admin|payments

// Usuarios / Roles
role:admin

// Usuarios — show propio (todos los autenticados)
(solo middleware auth)

// Notificaciones
role:admin
```

---

## Cómo modificar permisos

1. Editar esta matriz según el nuevo requerimiento
2. Actualizar el middleware correspondiente en `routes/web.php`
3. Actualizar las directivas `@role` / `@hasanyrole` en la vista afectada
4. Si se agrega un nuevo rol, ejecutar `php artisan db:seed --class=RolesAndPermissionsSeeder`

---

## Directivas Blade de referencia

```blade
{{-- Solo admin --}}
@role('admin')
    <button>Acción exclusiva admin</button>
@endrole

{{-- Admin o payments --}}
@hasanyrole('admin|payments')
    <button>Acción compartida</button>
@endhasanyrole

{{-- Admin u orders --}}
@hasanyrole('admin|orders')
    <button>Acción compartida</button>
@endhasanyrole
```
