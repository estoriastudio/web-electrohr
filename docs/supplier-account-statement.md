# Estado de Cuenta de Proveedores

## Alcance

Consulta para los roles `admin`, `Pagos` y `Orden de compra` en `/proveedores/estado-cuenta`. Proveedores es un desplegable con Listado (permisos existentes) y Estado de Cuenta, visible para esos tres roles. Las rutas del reporte son solo GET: listado, Excel y resumen de OC, con la misma restriccion de roles en todos los endpoints. Consultar no registra aplicaciones, genera pagos, cambia documentos ni envia notificaciones.

Proveedor carga factura por portal; Compras acepta/rechaza y vincula al hito; Pagos realiza transferencia y carga SPEI. Regularizar significa incorporar la factura faltante a un pago existente, no crear un nuevo hito, pago o solicitud. Estas operaciones siguen en sus modulos responsables.

## Fuentes y calculos

- Facturas aceptadas de OCs no eliminadas, incluidas las archivadas. Una factura por renglon.
- Solo `payments.status = pagado` cuenta como desembolso. `por_autorizar`, `pospuesto` y `autorizado` no reducen saldo. El SPEI es evidencia consultable, no el criterio del calculo.
- Facturacion liquida: `net_scope`; para legado sin ese dato, `amount - credit_note_amount`. Total fiscal y nota de credito permanecen identificables en detalle y Excel.
- Saldo de factura: liquido menos los pagos realizados de los hitos vinculados por Compras. No hay captura manual de importes por pago.
- Varias facturas aceptadas pueden ligarse al mismo hito. Las facturas que comparten hitos se evaluan en conjunto: la suma de sus importes (`amount`) se coteja contra la suma de los pagos del hito que no estan `rechazado`, con tolerancia de 0.50. Si las facturas la exceden, todas quedan `Por revisar`. Si no hay pagos en el hito, no se coteja.
- Los pagos realizados se aplican a las facturas del conjunto en orden de vencimiento (sin vencimiento al final) y luego por ID, cada pago una sola vez y limitado al liquido de cada factura.
- Pago realizado que excede lo facturado: renglon `Pagado sin factura` con el remanente (falta facturar), sin importe ni saldo de factura.
- Pendiente: saldo positivo y vencimiento no superado o ausente. Vencido: saldo positivo y fecha anterior a hoy. Pagado: saldo cero. La fecha actual usa zona horaria de Laravel.
- Montos en centavos durante el calculo; totales separados por moneda, sin conversion ni suma de MXN/USD/EUR.

## Calidad del dato

`Por conciliar` es una advertencia adicional de integridad, no un estatus operativo del pago. Se utiliza cuando las facturas de un hito exceden el importe de sus pagos, hay discrepancia de monedas o la nota de credito supera a la factura. No implica que la factura falte. Cada renglon indica el motivo y la accion para corregirlo.

Una factura en revision muestra saldo no determinado, no una deuda ficticia. Los indicadores Pendiente confirmado y Vencido confirmado excluyen esos saldos y muestran advertencia; los pagos realizados siguen incluidos una sola vez, como renglon de pago por revisar. El estatus no se guarda: se recalcula en cada consulta, asi que al corregir el importe o moneda de la factura, el hito ligado o los pagos del hito, la factura se reclasifica sola.

En la validacion de Compras (detalle de factura y bandeja de Facturas) se muestra, para los hitos seleccionados, la suma de pagos, de otras facturas aceptadas, de esta factura y la diferencia.

Rechazo o retorno a revision excluye esa factura del cotejo y de la cobertura sin borrar el pago ni redistribuir su importe.

`payment_date` historica puede representar fecha programada. El reporte muestra el valor registrado sin fabricar una fecha real de transferencia. Un pago historico `pagado` sin SPEI sigue computando por su estatus.

## Filtros, resumen y exportacion

Filtros combinables: folio OC, proveedor, proyecto, moneda, comprador `buyer_id` y estatus (Todos por defecto). Las OCs sin comprador/proyecto identificable permanecen en consulta general con fallback visual; no se deduce identidad a partir de `elaborated_by`.

Proveedor y proyecto utilizan Typeahead incluido en los assets del tema. La busqueda ignora mayusculas y acentos; proveedor admite razon social y nombre comercial. Elegir una sugerencia aplica su ID y conserva los demas filtros; el boton de quitar limpia solo ese campo. No se envia un ID anterior al escribir otro nombre. Si Typeahead no carga, permanecen disponibles los selectores originales.

Indicadores y desglose corresponden a todo el conjunto filtrado, antes de paginar. Excel comparte el mismo recorrido/calculo, incluye todas las filas filtradas, datos fiscales, importes numericos, filtros y resumen por moneda; textos se guardan como texto para evitar formulas inyectadas.

La consulta carga OCs y relaciones por lotes de 100, conserva solo filas de la pagina y acumulados. No carga todo el historico en una coleccion. Cada request usa transaccion de lectura para mantener la instantanea consistente bajo el aislamiento configurado de la base de datos. No depende de cache de saldos.

## Modal de OC

El folio abre un modal de solo lectura, sin panel lateral. Usa toda la OC seleccionada, independientemente del filtro de estatus aplicado al listado:

| Concepto | Calculo |
| --- | --- |
| Importe total OC | `purchase_orders.amount`, total contractual persistido |
| Facturado | Suma de liquidos de facturas aceptadas |
| Pendiente facturar | Total OC menos facturado |
| Pagado | Suma de pagos `pagado` por ID, una sola vez |
| Pendiente pagar sobre OC | Total OC menos pagado |
| Facturado sin pagar | Suma de saldos determinados de facturas aceptadas |

Incluye detalle por estatus/moneda. Si hay facturas por revisar, Facturado sin pagar indica Por revisar; si hay facturas con moneda discordante, no convierte ni suma su importe al total de la OC. Diferencias historicas negativas se muestran con advertencia, no se ocultan con truncamiento. No se agrega resumen al detalle existente de OC.

## Despliegue

El reporte no requiere migraciones ni comandos de carga historica: se calcula en cada consulta con las facturas, hitos y pagos existentes. La migracion `2026_10_01_000000_drop_invoice_payment_allocations_table` elimina la tabla de asignaciones manuales por pago, que ya no se usa:

```sh
php artisan migrate
```

## Verificacion

```sh
vendor/bin/phpunit tests/Feature/SupplierAccountStatementTest.php tests/Unit/SupplierAccountStatementTest.php tests/Feature/PurchaseOrderBuyerTest.php
```

Cobertura: estatus de pagos, facturas posteriores sin duplicidad, parciales, repartos explicitos/ambiguos, rechazo, vencimientos, notas de credito, monedas, archivadas/eliminadas, filtros combinados, paginacion, Excel real, permisos directos, simulacion e idempotencia, proteccion de documentos y lectura sin escrituras. El esquema SQLite aislado evita migraciones historicas incompatibles; las suites existentes con RefreshDatabase siguen limitadas por `ALTER TABLE ... MODIFY` de `project_documents` bajo SQLite.