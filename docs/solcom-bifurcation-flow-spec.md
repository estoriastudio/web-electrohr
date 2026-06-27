# Especificacion: Bifurcacion de SOLCOM para Multiples OC

## Objetivo
Permitir que una misma SOLCOM pueda generar multiples OCs de forma clara, seleccionando desde el formulario de creacion que conceptos se heredan a cada nueva OC.

## Alcance funcional implementado

### 1) Seleccion de conceptos en crear OC desde SOLCOM
- Pantalla: `purchase_orders/create_from_solcom`.
- Comportamiento:
  - Todos los conceptos aparecen seleccionados por defecto.
  - El usuario puede desmarcar conceptos para crear una OC parcial (bifurcacion).
  - Acciones rapidas: "Seleccionar todos" y "Limpiar seleccion".
  - Se muestra contador de conceptos seleccionados.
  - Se muestra subtotal estimado dinamico de solo los seleccionados.
  - Regla: no se puede enviar el formulario sin al menos 1 concepto seleccionado.

### 2) Copia parcial de conceptos en backend
- Controlador: `PurchaseOrderController@store`.
- Validaciones nuevas cuando `purchase_request_id` viene informado:
  - `selected_item_ids`: requerido, arreglo, minimo 1.
  - Cada id debe existir en `purchase_request_items` y pertenecer a la SOLCOM origen.
- Al crear la OC desde SOLCOM:
  - Se copian solo los conceptos seleccionados.
  - Se recalcula `amount` con los conceptos heredados.

### 3) Estado de SOLCOM para soportar bifurcaciones
- Se elimina el cierre automatico de SOLCOM al crear la primera OC.
- La SOLCOM se mantiene en `sent_to_purchasing` para permitir nuevas bifurcaciones.
- El cierre debe ser una accion explicita posterior (fuera de este cambio).

### 4) Pila SOLCOM clasificada por prioridad operativa
- Pantalla: `purchasing/solcom_pile`.
- Nueva clasificacion por bandeja:
  - `entrada`: SOLCOMs sin OCs generadas.
  - `salida`: SOLCOMs con una o mas OCs generadas.
  - `todas`: combinado.
- Se agrega columna `OCs Generadas` para visibilidad de bifurcaciones.

### 5) Mapa de proceso con ramas estilo git
- Partial: `purchase_orders/partials/_process_map`.
- Visualizacion:
  - Tronco SOLMAT -> SOLCOM.
  - Ramas a todas las OCs de la SOLCOM.
  - OC actual marcada como `(actual)`.

## Reglas de negocio vigentes
- Una SOLCOM puede tener N OCs asociadas.
- Cada OC puede heredar un subconjunto de conceptos de la SOLCOM.
- La prioridad operativa en Pila SOLCOM favorece `entrada` sobre `salida`.

## Trazabilidad item-a-item (implementado)
- Se agrega `purchase_request_item_id` en `purchase_order_items` (nullable, FK a `purchase_request_items`).
- Al crear OC desde SOLCOM, cada item heredado guarda su origen de SOLCOM.
- Al generar OCs hijas recurrentes desde una OC padre, se conserva la referencia del origen.
- Esto habilita reportes de cobertura por item de SOLCOM y auditoria de bifurcaciones.

## Pendientes recomendados (no incluidos en este cambio)
- Agregar cierre explicito de SOLCOM (boton y regla de permisos).
- Indicador de "conceptos pendientes de asignar" por SOLCOM.
