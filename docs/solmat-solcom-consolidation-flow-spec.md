# Especificacion: SOLCOM consolidada desde multiples SOLMAT

## Objetivo
Permitir que Almacen seleccione multiples SOLMAT del mismo proyecto desde la Pila SOLMAT y cree una sola SOLCOM consolidada, unificando conceptos repetidos y sumando cantidades en un solo documento.

## Problema que resuelve
El flujo anterior solo permitia crear una SOLCOM a partir de una SOLMAT.
Eso generaba friccion operativa cuando un mismo proyecto tenia varias SOLMAT relacionadas y se necesitaba comprar de forma centralizada:

- Se creaban varias SOLCOM para una misma necesidad de compra.
- Se multiplicaba la carga administrativa para Compras.
- Se fragmentaba la trazabilidad funcional de una sola compra.
- Se dificultaba visualizar el total real solicitado por proyecto y por obra.

## Alcance funcional implementado

### 1) Pila SOLMAT con seleccion multiple
- Se agrega una columna izquierda con checkbox por fila.
- Se agrega checkbox maestro para seleccionar en la pagina actual.
- Se agrega accion Crear SOLCOM consolidada.
- Validacion en cliente para no mezclar SOLMAT de proyectos distintos.
- Se mantiene el boton individual Crear SOLCOM por fila para compatibilidad.

### 2) Creacion consolidada (formulario)
- Nuevo endpoint para recibir material_request_ids y abrir el formulario prellenado.
- Se validan reglas de servidor:
  - Al menos una SOLMAT seleccionada.
  - Todas deben existir.
  - Todas deben pertenecer al mismo proyecto.
  - Estatus permitido: sent_to_warehouse y linked.
- La cabecera inicial toma datos de la primera SOLMAT seleccionada.
- Las obras disponibles son la union de obras de todas las SOLMAT origen.

### 3) Consolidacion de conceptos
Al guardar la SOLCOM:
- Se unifican conceptos duplicados en una sola linea.
- Criterio de unificacion:
  - Primero por concept_id cuando exista.
  - Fallback por code + description + unit para registros legacy sin concept_id.
- La cantidad solicitada se calcula sumando cantidades de todos los origenes, respetando las obras seleccionadas por el usuario.

### 4) Estados y compatibilidad
- Todas las SOLMAT usadas en la consolidacion se marcan como linked.
- Se conserva compatibilidad con el flujo anterior de una sola SOLMAT.
- El flujo singular sigue funcionando con material_request_id.

## Necesidad de la nueva migracion pivote

### Contexto del modelo anterior
La tabla purchase_requests ya tenia material_request_id.
Ese campo representa una relacion uno-a-uno logica (o una-a-muchas desde SOLMAT a SOLCOM), util para el flujo singular, pero insuficiente para consolidacion multi-origen.

Con el nuevo flujo, una SOLCOM puede originarse desde N SOLMAT.
Ese escenario no puede modelarse correctamente con un solo material_request_id sin perder informacion.

### Riesgo de no crear pivote
Sin tabla pivote:
- Solo podriamos guardar una SOLMAT origen (normalmente la primera).
- Se perderia trazabilidad completa de los demas folios usados.
- Auditoria e investigacion de incidencias quedarian incompletas.
- Reportes de cobertura por SOLMAT tendrian sesgo.
- Futuras funcionalidades (analitica, historial, desglose documental) quedarian limitadas.

### Solucion aplicada
Se crea la tabla pivote purchase_request_material_requests para registrar la relacion muchos-a-muchos entre:
- purchase_requests
- material_requests

Beneficios:
- Trazabilidad completa de todos los origenes de una SOLCOM consolidada.
- Compatibilidad hacia atras conservando material_request_id como referencia legacy principal.
- Base de datos preparada para reportes y auditoria robusta.
- Evolucion segura del dominio sin romper vistas actuales.

## Estructura de datos nueva
Tabla pivote: purchase_request_material_requests

Campos principales:
- id
- purchase_request_id (FK)
- material_request_id (FK)
- timestamps
- indice unico compuesto por purchase_request_id + material_request_id

## Reglas de negocio vigentes
- Se permite consolidar SOLMAT en estatus sent_to_warehouse y linked.
- Todas las SOLMAT seleccionadas deben ser del mismo proyecto.
- Debe existir al menos una obra seleccionada para crear SOLCOM.
- La consolidacion suma cantidades por concepto y por obras elegidas.

## Compromisos por obra

- Cada cantidad de un concepto por obra puede marcarse como comprometida desde una SOLMAT editable (`pending` o `changes_requested`).
- La marca compromete el 100% de la cantidad de esa combinacion concepto-obra; no modifica la cantidad original de la SOLMAT.
- El porcentaje mostrado en los listados se calcula por cantidad: cantidad comprometida entre cantidad total solicitada.
- Al crear una SOLCOM, solo se heredan cantidades de obras seleccionadas que no esten comprometidas.
- Los conceptos con saldo disponible se muestran preseleccionados y el usuario puede desmarcarlos antes de crear la SOLCOM.
- Una SOLMAT sin saldo disponible no puede ser origen de una SOLCOM individual ni consolidada. El servidor tambien excluye esas fuentes aunque se intente manipular la solicitud.

## Impacto tecnico
Archivos principales involucrados:
- Vista de pila SOLMAT con seleccion multiple.
- Ruta nueva de creacion consolidada.
- Controlador de SOLCOM con validacion y agregacion de items.
- Vista de creacion desde SOLMAT en modo singular y multi.
- Migracion pivote y relaciones many-to-many en modelos.

## Verificacion recomendada
1. Seleccionar 2 o mas SOLMAT del mismo proyecto y crear una SOLCOM consolidada.
2. Confirmar en UI que conceptos repetidos quedaron en una sola linea con cantidad sumada.
3. Confirmar en base de datos que la SOLCOM tiene registros en la tabla pivote para todos los folios origen.
4. Validar que el flujo singular siga funcionando sin cambios funcionales.

## Nota de evolucion
A futuro, si se requiere trazabilidad item-a-item entre SOLCOM consolidada y cada item origen de SOLMAT, se puede agregar una pivote adicional de detalle por item. Esta iteracion cubre trazabilidad a nivel documento (SOLCOM-SOLMAT).
