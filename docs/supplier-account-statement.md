# Estado de Cuenta de Proveedores

## Alcance

Consulta exclusiva para admin en `/proveedores/estado-cuenta`. Proveedores es un desplegable con Listado (permisos existentes) y Estado de Cuenta. Las rutas del reporte son solo GET: listado, Excel y resumen de OC. Consultar no registra aplicaciones, genera pagos, cambia documentos ni envia notificaciones.

Proveedor carga factura por portal; Compras acepta/rechaza y vincula al hito; Pagos realiza transferencia y carga SPEI. Regularizar significa incorporar la factura faltante a un pago existente, no crear un nuevo hito, pago o solicitud. Estas operaciones siguen en sus modulos responsables.

## Fuentes y calculos

- Facturas aceptadas de OCs no eliminadas, incluidas las archivadas. Una factura por renglon.
- Solo `payments.status = pagado` cuenta como desembolso. `por_autorizar`, `pospuesto` y `autorizado` no reducen saldo. El SPEI es evidencia consultable, no el criterio del calculo.
- Facturacion liquida: `net_scope`; para legado sin ese dato, `amount - credit_note_amount`. Total fiscal y nota de credito permanecen identificables en detalle y Excel.
- Saldo de factura: liquido menos importes efectivamente aplicados desde pagos realizados del hito vinculado por Compras.
- Aplicaciones explicitas de `invoice_payment_allocations` prevalecen y no se aumentan ni se trasladan al consultar. Importes asignados a pagos no realizados no cuentan como cobertura.
- Sin aplicacion explicita, solo una factura aceptada compatible en el hito permite inferir cobertura, limitada a su saldo. Cada pago se cuenta una sola vez. Con varias facturas no se prorratea ni se asigna por orden.
- Pago sin factura aceptada/aplicada: renglon temporal con importe pagado no regularizado, sin importe ni saldo de factura. Remanente de aplicacion parcial se conserva, no se duplica.
- Pendiente: saldo positivo y vencimiento no superado o ausente. Vencido: saldo positivo y fecha anterior a hoy. Pagado: saldo cero. La fecha actual usa zona horaria de Laravel.
- Montos en centavos durante el calculo; totales separados por moneda, sin conversion ni suma de MXN/USD/EUR.

## Calidad del dato

`Por conciliar` es una advertencia adicional de integridad, no un estatus operativo del pago. Se utiliza cuando varias facturas comparten un pago sin asignacion monetaria, existe una aplicacion invalida o hay discrepancia de monedas. No implica que la factura falte.

Una factura ambigua muestra saldo no determinado, no una deuda ficticia. Los indicadores Pendiente confirmado y Vencido confirmado excluyen esos saldos y muestran advertencia; los pagos realizados siguen incluidos una sola vez. Completar el importe por pago en la validacion existente de Compras permite resolver la ambiguedad sin otro desembolso.

Compras puede registrar importes por pago en su formulario existente de validacion. Se comprueba que el pago pertenezca a un hito seleccionado, a la OC/moneda correcta, que no se exceda importe del pago ni alcance liquido de la factura, y que el total global ya asignado a otras facturas respete el limite. Tambien admite asignacion previa a la transferencia, pero solo se vuelve efectiva cuando el pago queda `pagado`.

Rechazo o retorno a revision desactiva cobertura de esa factura sin borrar el pago ni redistribuir su importe. Reaceptacion valida limites. Facturas/pagos con asignaciones no se eliminan hasta corregir la asignacion en Compras; los documentos y `covered_amount` permanecen intactos al devolver ese error.

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

Incluye detalle por estatus/moneda. Si faltan asignaciones, Facturado sin pagar indica Por conciliar; si hay facturas con moneda discordante, no convierte ni suma su importe al total de la OC. Diferencias historicas negativas se muestran con advertencia, no se ocultan con truncamiento. No se agrega resumen al detalle existente de OC.

## Despliegue e historico

Respaldar y ejecutar la migracion nueva antes de habilitar el reporte:

```sh
php artisan migrate --path=database/migrations/2026_09_30_210000_create_invoice_payment_allocations_table.php
php artisan purchase-orders:backfill-invoice-payments --dry-run
```

La simulacion informa relaciones historicas inequivocas y OCs por conciliar sin guardar. Revisar los resultados con responsables; para conservar relaciones inequivocas:

```sh
php artisan purchase-orders:backfill-invoice-payments
```

El comando solo registra cobertura integra del pago contra una unica factura aceptada compatible y dentro del saldo disponible. Omite repartos ambiguos, relaciones ya explicitas y aplicaciones parciales inferidas. Es idempotente; nunca modifica importes, estados, hitos ni SPEI. El reporte puede inferir casos univocos sin este comando; persistirlos preserva el vinculo ante futuras facturas del mismo hito.

## Verificacion

```sh
vendor/bin/phpunit tests/Feature/SupplierAccountStatementTest.php tests/Unit/SupplierAccountStatementTest.php tests/Feature/PurchaseOrderBuyerTest.php
```

Cobertura: estatus de pagos, facturas posteriores sin duplicidad, parciales, repartos explicitos/ambiguos, rechazo, vencimientos, notas de credito, monedas, archivadas/eliminadas, filtros combinados, paginacion, Excel real, permisos directos, simulacion e idempotencia, proteccion de documentos y lectura sin escrituras. El esquema SQLite aislado evita migraciones historicas incompatibles; las suites existentes con RefreshDatabase siguen limitadas por `ALTER TABLE ... MODIFY` de `project_documents` bajo SQLite.