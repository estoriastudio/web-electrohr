# Estructura de Controladores y Sistema de Notificaciones

> Documento de referencia para mantener cohesión y consistencia entre controladores.
> Fecha: Abril 2026

---

## 1. Estructura Base de un Controlador de Recurso

Todos los controladores de recursos del sistema siguen el patrón RESTful estándar de Laravel. La estructura base es:

```
app/Http/Controllers/
├── Controller.php                      ← Clase base (vacía, extiende la de Laravel)
├── SupplierController.php
├── PurchaseOrderController.php
├── PurchaseOrderMilestoneController.php
├── PurchaseOrderInvoiceController.php
├── PaymentController.php
├── UserController.php
├── UserInfoController.php
├── NotificationController.php
└── AdminController.php
```

### 1.1 Anatomía de un Controlador de Recurso

```php
class FooController extends Controller
{
    // Inyección del servicio de notificaciones (cuando aplica)
    public function __construct(private NotificationService $notification) {}

    public function index()    { /* listado paginado */ }
    public function create()   { /* redirige a index (modal en la vista) */ }
    public function store()    { /* validación + crear + notificación + redirect */ }
    public function show()     { /* detalle del recurso */ }
    public function edit()     { /* vista de edición */ }
    public function update()   { /* validación + actualizar + notificación + redirect */ }
    public function destroy()  { /* eliminar + notificación + redirect */ }
}
```

### 1.2 Reglas de flujo

| Método | Comportamiento esperado |
|--------|------------------------|
| `index` | Paginar con `paginate(25)`. Usar `with()` / `withCount()` para eager loading. |
| `create` | **Siempre** redirigir a `index`. La creación se maneja via modal en la vista. |
| `store` | Validar → crear modelo → enviar notificación → `redirect()->route(...)->with('success', ...)` |
| `show` | Cargar relaciones necesarias con `load()` o `with()`. |
| `edit` | Retornar vista con el modelo. |
| `update` | Validar → actualizar modelo → enviar notificación → `redirect()->route(...)->with('success', ...)` |
| `destroy` | Eliminar modelo → enviar notificación → redirigir a `index`. |

### 1.3 Return types

Los controladores que retornan vistas tipan como `View`, y los que redirigen como `RedirectResponse`. Ejemplo:

```php
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

public function index(): View { ... }
public function store(Request $request): RedirectResponse { ... }
```

---

## 2. Controladores con lógica especial

### SupplierController

- Incluye métodos `export()` e `import()` para manejo de archivos Excel (Maatwebsite/Excel).
- `import` valida el archivo con: `mimes:xlsx,xls,csv|max:10240`.
- `show` re-consulta el modelo con `withCount('purchaseOrders')` para tener el conteo disponible.

### PurchaseOrderController

- La validación en `store` es **condicional** según `type` y `recurrence_type`.
- Carga relaciones anidadas en `show`: `supplier`, `milestones.payments`, `invoices.milestones`.

### PaymentController

- El listado en `index` usa un `JOIN` raw para ordenar pagos urgentes primero (vencimiento ≤ 7 días).
- Al crear un pago con `status = 'pagado'`, incrementa automáticamente `covered_amount` en el hito relacionado.
- Verifica que el hito (`milestone`) no esté completamente cubierto (`is_complete`) antes de registrar el pago.
- Genera un folio automático (`PAY-XXXXX`) si el campo llega vacío.

### UserController

- **No** inyecta `NotificationService` (operación interna de administración).
- Maneja roles y permisos via `spatie/laravel-permission` (`syncRoles`, `syncPermissions`).
- El método `show` siempre retorna el usuario autenticado, ignorando el parámetro de ruta.

### NotificationController

- Solo tiene dos métodos: `index` (listado filtrable) y `markAllRead` (JSON endpoint).
- No inyecta `NotificationService`; opera directamente sobre el modelo `Notification`.

---

## 3. Sistema de Notificaciones

### 3.1 Componentes

```
App\Services\NotificationService   ← Servicio que registra la acción
App\Models\Notification            ← Modelo Eloquent / tabla notifications
App\Http\Controllers\NotificationController ← Visualización y gestión
```

### 3.2 NotificationService

```php
// app/Services/NotificationService.php

class NotificationService
{
    public function send(array $payload): void
    {
        Notification::create([
            'action_by'    => $payload['action_by'],    // ID del usuario autenticado
            'model_action' => $payload['model_action'], // 'create' | 'update' | 'destroy'
            'model_id'     => $payload['model_id'],     // ID del recurso afectado
            'type'         => $payload['type'],         // Nombre del modelo (ej. 'Supplier')
            'data'         => $payload['data'],         // Descripción legible de la acción
            'is_hidden'    => false,                    // Visible por defecto
        ]);
    }
}
```

El servicio es deliberadamente simple: **solo persiste** un registro en base de datos. No envía emails ni eventos en tiempo real. Su propósito actual es auditoría y log de actividad.

### 3.3 Modelo Notification

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `action_by` | `unsignedBigInteger` | FK → `users.id` (usuario que realizó la acción) |
| `model_action` | `string` | Acción: `create`, `update`, `destroy` |
| `model_id` | `unsignedBigInteger` | ID del registro afectado |
| `type` | `string` | Nombre del modelo afectado (e.g. `Supplier`, `PurchaseOrder`) |
| `data` | `string` | Texto descriptivo legible por humanos |
| `read_at` | `timestamp\|null` | Marca de lectura (actualmente no en uso activo) |
| `is_hidden` | `boolean` | `false` = visible, `true` = oculta/marcada como leída |

Relación: `Notification::user()` → `belongsTo(User::class, 'action_by')`

### 3.4 Inyección del servicio

El servicio se inyecta via constructor con **property promotion**:

```php
public function __construct(private NotificationService $notification) {}
```

Laravel resuelve la dependencia automáticamente por el contenedor de servicios (no requiere binding manual).

### 3.5 Llamada estándar al enviar una notificación

```php
$this->notification->send([
    'type'         => 'NombreDelModelo',   // PascalCase, igual que el nombre de clase
    'action_by'    => Auth::id(),          // Siempre el usuario autenticado
    'model_action' => 'create',            // 'create' | 'update' | 'destroy'
    'model_id'     => $model->id,          // ID del registro afectado
    'data'         => 'verbo + descripción legible',
]);
```

#### Ejemplos de `data` por acción

| `model_action` | Ejemplo de `data` |
|---------------|-------------------|
| `create` | `'creó un nuevo proveedor Acme S.A.'` |
| `update` | `'actualizó la información del proveedor Acme S.A.'` |
| `destroy` | `'eliminó al proveedor Acme S.A.'` |

La cadena de `data` sigue el patrón: **verbo en pasado + descripción del objeto**, e incorpora el nombre del recurso cuando está disponible (`commercial_name ?? rfc_name`).

### 3.6 Controladores que usan el servicio

| Controlador | Acciones notificadas |
|-------------|---------------------|
| `SupplierController` | `store`, `update`, `destroy` |
| `PurchaseOrderController` | `store`, `update`, `destroy` |
| `PurchaseOrderMilestoneController` | `store`, `update`, `destroy` |
| `PurchaseOrderInvoiceController` | `store`, `update`, `destroy` |
| `PaymentController` | `store`, `update`, `destroy` |
| `UserController` | ❌ No notifica |
| `NotificationController` | ❌ No notifica (es el receptor) |

### 3.7 Visualización de notificaciones

`NotificationController::index()` soporta filtrado por:
- `?type=Supplier` → filtra por tipo de modelo
- `?action=create` → filtra por acción

`markAllRead()` es un endpoint JSON (`GET /notifications/mark-all-read`) que marca todas las notificaciones visibles como ocultas (`is_hidden = true`) y devuelve `{ success: true, count: 0 }`.

---

## 4. Convenciones de validación

- Campos de texto corto: `string|max:255`
- Campos de texto largo (dirección, etc.): `string|max:1000`
- Enums: `in:valor1,valor2,...` (sin espacios)
- Monedas: `in:MXN,USD,EUR`
- RFC número: `string|max:20`
- CLABE: `string|max:18`
- SWIFT: `string|max:11`
- Archivos Excel: `file|mimes:xlsx,xls,csv|max:10240`
- Importes: `numeric|min:0`

---

## 5. Mensajes flash de sesión

Todos los redirects de éxito usan `->with('success', '...')`.  
Los errores de negocio (no validación) usan `->with('error', '...')`.

```php
return redirect()->route('suppliers.index')->with('success', 'Proveedor eliminado correctamente.');
return redirect()->route('purchase_orders.show', $milestone->purchase_order_id)
    ->with('error', 'El hito ya está completamente cubierto.');
```

---

## 6. Checklist al crear un nuevo controlador de recurso

- [ ] Inyectar `NotificationService` en el constructor si el recurso es auditable.
- [ ] El método `create()` redirige a `index` (la creación es por modal).
- [ ] Toda acción mutante (`store`, `update`, `destroy`) envía notificación antes del redirect.
- [ ] `action_by` siempre usa `Auth::id()`.
- [ ] `type` usa el nombre de la clase del modelo en PascalCase.
- [ ] Los redirects de éxito usan `->with('success', ...)`.
- [ ] El listado usa `paginate(25)`.
- [ ] Los campos `nullable` se declaran explícitamente como `nullable` en la validación.
