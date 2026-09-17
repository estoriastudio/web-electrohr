# Flujo de salud de Ordenes de Compra, hitos y pagos

## Objetivo

Este documento describe el flujo operativo de una Orden de Compra (OC) y las reglas que preservan la consistencia entre sus hitos y pagos.

Tambien documenta el comando `php artisan purchase-orders:health`, que detecta registros historicos que incumplen estas reglas. Las validaciones de aplicacion previenen que se creen nuevas inconsistencias; el reporte no modifica datos existentes.

## Modelo de datos y alcance

La revision siempre se hace dentro de una sola OC. No se comparan hitos ni pagos de ordenes distintas.

```text
purchase_orders
  └── purchase_order_milestones (purchase_order_id)
        └── payments (milestone_id)
```

- Una OC puede tener varios hitos.
- Un hito puede tener varios pagos, por ejemplo cuando se divide su pago inicial.
- Para la salud de una OC, los hitos se ordenan por `purchase_order_milestones.id` ascendente.
- Dentro de cada hito, los pagos se ordenan por `payments.id` ascendente.
- El pago inicial es el pago con menor `id` de un hito. Solo ese pago participa en las reglas de secuencia entre hitos.
- El alcance operativo y del reporte son OCs activas: se excluyen las archivadas (`archived_at`) y las eliminadas lógicamente.

## Flujo de la OC

### Estados de la OC

```text
pendiente -> emitida -> autorizada
```

- `pendiente`: OC creada y editable.
- `emitida`: OC enviada para autorizacion.
- `autorizada`: OC habilitada para registrar y procesar pagos.

Solo un administrador puede autorizar una OC. Las OCs autorizadas restringen su edicion; las OCs de destajo mantienen las excepciones operativas definidas en los controladores.

### Alta y modificacion de hitos

Al crear un hito se genera un pago inicial con:

- Monto igual al importe efectivo del hito.
- Fecha de pago igual a `due_date` del hito.
- Estatus `por_autorizar`.

Al editar un hito:

- Si ya tiene pagos pendientes, se ajustan sus montos y la fecha de esos pagos conforme a la fecha del hito.
- Si es un hito legado sin pagos, se crea su pago inicial durante la actualizacion.
- Antes de crear o modificar un pago inicial, se valida que su fecha conserve el orden de fechas dentro de la OC.

### Flujo de pagos

Los estatus operativos de pago son:

```text
por_autorizar / pospuesto -> autorizado -> pagado
```

- `por_autorizar`: pago pendiente de autorizacion.
- `pospuesto`: pago pendiente temporalmente diferido; tiene el mismo nivel de avance que `por_autorizar` para la regla de secuencia.
- `autorizado`: pago aprobado para pago.
- `pagado`: pago finalizado; incrementa `covered_amount` del hito.
- `rechazado`: pago que requiere revision. Se permite para conservar el flujo de solicitud de reactivacion, pero el reporte lo informa como alerta.

Los pagos se pueden crear desde un hito autorizado, dividir cuando el pago inicial aun esta por autorizar, autorizar individualmente o en bloque, pagar con comprobante SPEI individual o multiple, posponer, rechazar y solicitar su reactivacion.

## Invariantes de salud

### 1. Secuencia de estatus

El pago inicial de un hito posterior no puede tener un nivel de avance mayor que el pago inicial de un hito anterior de la misma OC.

Ejemplo invalido:

```text
Hito 1: por_autorizar
Hito 2: autorizado
```

Ejemplo valido:

```text
Hito 1: autorizado
Hito 2: autorizado
Hito 3: por_autorizar
```

La regla se evalua antes de guardar cambios de estatus. En operaciones masivas, todos los pagos seleccionados de la misma OC se evalúan juntos para permitir una autorizacion o pago simultaneo consistente.

### 2. Secuencia de fechas

La fecha del pago inicial debe ser no decreciente conforme al orden de los hitos.

Ejemplo invalido:

```text
Hito 1: 2026-09-15
Hito 2: 2026-09-01
```

La validacion revisa tanto el hito anterior como el posterior cuando aplica. Por eso evita que la edicion de un hito intermedio rompa una secuencia que ya era valida.

### 3. Hitos sin pagos

Un hito sin pagos no se compara hasta tener un pago inicial. Cuando se crea ese primer pago posteriormente, su fecha se valida contra los hitos con pago anteriores y posteriores de la misma OC.

## Implementacion de las validaciones

El servicio [app/Services/PurchaseOrderPaymentSequenceValidator.php](../app/Services/PurchaseOrderPaymentSequenceValidator.php) centraliza las invariantes de estatus y fechas.

Se invoca desde estos puntos de mutacion:

| Flujo | Controlador | Proteccion |
|---|---|---|
| Crear hito | `PurchaseOrderMilestoneController::store` | Fecha del nuevo pago inicial |
| Editar hito | `PurchaseOrderMilestoneController::update` | Fecha del pago inicial actualizado o creado para un hito legado |
| Registrar pago | `PaymentController::store` | Fecha cuando el pago es el primero del hito |
| Cambiar estatus individual | `PaymentController::update` | Secuencia del pago inicial |
| Autorizar/rechazar/posponer por swipe | `PaymentController::swipe` | Secuencia del pago inicial |
| Reactivar pago rechazado | `PaymentController::requestReactivation` | Secuencia del pago inicial |
| Marcar pagos pagados con SPEI | `PaymentController::markMultiplePaidWithSpei` | Secuencia agrupada por OC |
| Autorizar pagos multiples | `PaymentController::authorizeMultiple` | Secuencia agrupada por OC |
| Autorizar OC con pagos seleccionados | `PurchaseOrderController::approveWithPayments` | Secuencia de los pagos de esa OC |

El servicio carga los hitos por `purchase_order_id` y agrupa los pagos por `milestone_id`. Este aislamiento es obligatorio para evitar que pagos de otras OCs afecten una validacion.

### Presentacion de errores de validacion

El layout [resources/views/layouts/app.blade.php](../resources/views/layouts/app.blade.php) muestra automáticamente un modal cuando existe un error de sesion (`session('error')`) o errores de validacion (`$errors`).

Esto aplica tanto al detalle de la OC como a las bandejas de pagos. Los errores de secuencia muestran el motivo en esa ventana para que el usuario pueda corregir la operacion sin depender de las alertas superiores de la pagina. No se deben abrir modales adicionales de forma automática para el mismo error, ya que pueden superponerse al modal global.

## Reporte de salud

Ejecutar:

```bash
php artisan purchase-orders:health
```

El reporte es de solo lectura y presenta:

- `Alertas de estatus`: pagos iniciales con avance fuera de orden entre hitos de una misma OC.
- `Pagos rechazados`: pagos iniciales rechazados, que requieren revision pero no son bloqueados por el flujo.
- `Alertas de fechas`: fechas de pagos iniciales fuera de orden entre hitos de una misma OC.
- `Hitos omitidos sin pagos`: registros que aun no tienen pago inicial y no pueden compararse.

Cada categoria muestra el conteo y el listado sin duplicados de folios de OCs afectadas. Las alertas individuales indican los hitos, pagos y valores que originaron el hallazgo.

## Ajustes futuros

Antes de cambiar las reglas, definir el efecto esperado sobre las OCs ya existentes y actualizar el servicio, el reporte y sus pruebas de forma coordinada.

| Cambio propuesto | Lugares que deben revisarse |
|---|---|
| Agregar o cambiar estatus de pago | Rango en `PurchaseOrderPaymentSequenceValidator`, leyenda del reporte, transiciones de `PaymentController` y pruebas |
| Cambiar el orden de los hitos | Servicio validador, comando de salud, vistas de OC y reglas de fechas |
| Comparar todos los pagos, no solo el inicial | Servicio, comando, manejo de pagos divididos y pruebas de pagos multiples |
| Convertir pagos rechazados en bloqueo | Flujo de reactivacion, permisos, UI y reporte |
| Agregar reglas de montos, facturas o vencimientos | Nueva categoria de reporte, validacion transaccional y cobertura de pruebas |
| Incluir OCs archivadas | Consulta del comando y definicion de alcance; no habilitar mutaciones sobre registros archivados sin una regla adicional |

## Verificacion recomendada

1. Crear una OC con varios hitos y pagos iniciales en fechas crecientes.
2. Intentar autorizar o pagar un hito posterior mientras uno anterior sigue pendiente; la solicitud debe ser rechazada.
3. Intentar asignar una fecha anterior a un hito posterior; la solicitud debe ser rechazada.
4. Autorizar o pagar varios hitos de la misma OC en una sola operacion; debe permitirse si el resultado final conserva la secuencia.
5. Ejecutar `php artisan purchase-orders:health` para revisar datos historicos y confirmar que las nuevas OCs no generan alertas.

## Pruebas disponibles

- [tests/Unit/PurchaseOrderPaymentSequenceValidatorTest.php](../tests/Unit/PurchaseOrderPaymentSequenceValidatorTest.php): contrato de validacion de secuencias.
- [tests/Feature/PurchaseOrderHealthReportCommandTest.php](../tests/Feature/PurchaseOrderHealthReportCommandTest.php): salida y aislamiento por OC del reporte.
- [tests/Feature/PaymentMultipleMilestoneTest.php](../tests/Feature/PaymentMultipleMilestoneTest.php): comportamiento de pagos divididos y operaciones de pago.

Actualmente las pruebas que usan `RefreshDatabase` requieren que las migraciones SQLite sean compatibles. La migracion existente de `project_documents` usa `ALTER TABLE ... MODIFY`, sintaxis que SQLite no admite; mientras no se ajuste, las pruebas se detienen antes de ejecutar sus aserciones.