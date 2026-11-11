# Matriz de Roles y Permisos

Este archivo es la fuente de referencia para la matriz de acceso por rol.

Cuando se agreguen o cambien roles, actualiza este archivo primero.

## Matriz Objetivo (en transición)

> Estado: **Fase 2, en curso**. Ya existen los permisos y roles nuevos. **Recursos Humanos**, **Proyectos / Mis Proyectos**, **Almacén** (SOLMAT, Pila SOLMAT, Inventario y Vales) **Compras** (SOLCOM, Pila SOLCOM, Carga de Trabajo y OC) **Pagos y Recepción** (Facturas, Alta de Facturas, Hitos, Pagos, Recepción de OC y Evidencias) **Proveedores** (Listado y Estado de Cuenta) **Bienes Móviles y Herramientas** y **Configuración** (Conceptos, Familias, Precios adjudicados, Usuarios y Auditoría) ya usan los permisos de la matriz (ver las secciones "Fase 2"). Los demás módulos todavía usan los roles de "Roles Vigentes" y pasarán a los permisos nuevos uno por uno.

La matriz objetivo vive en [`database/data/access_matrix.php`](../database/data/access_matrix.php). Se generó desde el Excel "Matriz usuarios × vistas del ERP" (hojas «admin» y «Vistas que faltan», con las respuestas V-01 a V-21) y luego se le aplicaron las decisiones de abajo. Para cambiar un permiso, se cambia ese archivo y se vuelve a correr el seeder.

### Piezas (Fase 1)

- `App\Services\AccessMatrix` lee la matriz:
  - `permissionsFor($perfil)` da los permisos de un perfil;
  - `scopesFor($user, $vista, $acción)` da el alcance que le toca al usuario (`all`, una lista de alcances o ninguno). Si alguno de sus roles no tiene restricción, gana `all`;
  - `hiddenFieldsFor($user, $vista)` da los campos que no debe ver. Un campo se oculta solo si todos los roles del usuario que le dan acceso lo ocultan.
- `AccessMatrixSeeder` crea los permisos `{vista}.{acción}` y los roles nuevos, y asigna a cada rol sus permisos. Es idempotente. Conserva los permisos CRUD heredados (`create`, `read`, `update`, `delete`) de los roles que ya los tenían, porque el menú actual depende de ellos. También borra los permisos `{vista}.{acción}` que ya no estén en la matriz. Ya lo llama `DatabaseSeeder`.
- `admin` pasa cualquier verificación de permisos o Policy (`Gate::before` en `AppServiceProvider`).
- `php artisan access:migrate-users` asigna a cada usuario los roles nuevos según `legacy_roles`:
  - sin opciones, solo muestra el plan;
  - con `--apply`, asigna los roles faltantes **sin quitar los anteriores**;
  - con `--csv=ruta`, guarda el plan completo.

Orden para aplicarlo en un servidor:

```bash
php artisan db:seed --class=AccessMatrixSeeder
php artisan access:migrate-users --csv=storage/app/plan-roles.csv
php artisan access:migrate-users --apply
```

El CSV incluye nombres y correos; no se versiona.

**Antes de desplegar cualquier módulo de la Fase 2** hay que correr el seeder y `access:migrate-users --apply` en ese servidor. Si no, los usuarios que solo tienen roles anteriores (por ejemplo, `Engineer`) pierden el acceso a ese módulo.

Contenido:

- 13 perfiles de usuario, cada uno con su rol de Spatie (tabla de abajo).
- 47 vistas: 42 existentes y 5 nuevas que exige el proceso.
- 234 celdas perfil × vista. Cada celda trae el texto original del Excel, las acciones permitidas y, cuando aplica, su alcance, los campos ocultos, si solo recibe notificación y las acciones que el ERP aún no soporta.
- Permisos con el formato `{vista}.{acción}`. Acciones: `view`, `create`, `update`, `cancel`, `authorize`, `register`, `download`. La N del Excel no es un permiso: es la lista de quién recibe notificación.
- Alcances: `assigned_projects`, `assigned_works`, `assigned_workers`, `own_buyer`, `own_supplier`.

### Perfiles y roles

| Perfil | Rol | Estado | Viene de |
|---|---|---|---|
| ADM | `admin` | existe | — |
| DIR | `Dirección` | nuevo | — |
| GER | `Gerente de Proyecto` | nuevo | `Engineer` |
| SUP | `Supervisor` | nuevo | `Engineer` |
| CON | `Contratos` | nuevo | `Proyectos` |
| ALM | `Almacén` | nuevo | `Solmat`, `Inventario`, `Solcom`, `suministros` |
| ALM-A | `Almacén Autoriza` | nuevo | — |
| COM | `Orden de compra` | existe | — |
| PAG | `Pagos` | existe | — |
| PRV | `supplier_portal_access` | existe | — |
| RH | `Recursos Humanos` | existe | — |
| MOV | `Moviles` | existe | — |
| REC | `Recepción` | existe | — (permisos por definir) |

Se eliminan: `Engineer`, `Solmat`, `Inventario`, `Solcom`, `suministros`, `Proyectos`, `Proveedor` y `Ayudante Movil`.

### Decisiones (2026-11-10)

- **Almacén:** `Solmat`, `Inventario`, `Solcom` y `suministros` se unifican en un solo rol. Almacén ve y edita todas las SOLMAT. La regla "cancelar y sustituir, no editar" solo aplica a los flujos de proyectos (GER/SUP).
- **Gerente y Supervisor:** son roles separados, porque sus permisos difieren. Por ejemplo, el Gerente autoriza los cambios de SOLMAT que pide el Supervisor.
- **Dirección:** el Director General tiene usuario propio con el rol `Dirección` y deja de entrar como admin. **Admin también autoriza:** es el webmaster y superusuario. Cada autorización guarda quién la hizo.
- **Contratos:** da de alta Proyectos y Obras, y también carga documentación contractual y estimaciones.
- **Vales de Material:** Móviles los opera igual que Almacén (ver, crear, editar, descargar).
- **Confirmación de entrega en obra:** será una pantalla nueva; no se amplía Recepción de OC.
- **Recepción** se mantiene. El rol interno **Proveedor** se elimina: no da acceso a ninguna ruta, y el portal lo da `supplier_portal_access`.
- **Recepción** queda fuera de este flujo. Conserva solo Alta de Facturas: al pasar ese módulo a permisos se le dio `invoice_intake.view` y `invoice_intake.create` como decisión en la matriz.
- **Estimaciones (Proyectos):** Gerente y Supervisor solo las consultan en Mis Proyectos, como dice la matriz; las cargan Contratos, Dirección y admin.
- **Documentos del proyecto:** la documentación contractual, los convenios y los archivos y facturas de estimaciones son solo de quien edita Proyectos (Contratos, Dirección y admin). Los roles con solo V ven datos del proyecto, obras y estimaciones.
- **SOLMAT:** Gerente, Supervisor y Almacén crean y editan SOLMAT. Gerente y Supervisor solo en sus proyectos y mientras esté Pendiente o con cambios; Almacén, todas. Solo el gerente del proyecto (y admin) la aprueba y envía a Almacén; Almacén ya no la envía.
- **Inventario:** como dice la matriz, Almacén solo crea entradas y salidas. Corregir o borrar movimientos y ajustar existencias queda para admin.
- **OC por comprador:** cada comprador ve y opera solo las OC que tiene asignadas, como hoy. Quedó como alcance `own_buyer` de Compras en la matriz.
- **SOLCOM:** Almacén la edita solo hasta enviarla a Compras; después la edita Compras.
- **PDF de OC:** solo con D de la matriz: Compras (sus OC) y admin. Pagos, Almacén y Dirección ya no descargan el PDF (tampoco el PDF con anexos).
- **Pagos y Recepción (2026-11-11):** se respeta la matriz. Pagos ya no crea ni edita hitos, no da de alta facturas ni sube evidencias; Compras ya no registra entregas ni sube evidencias. Compras ve y valida solo las facturas de sus OC (alcance `own_buyer` en Facturas).
- **Proveedores (2026-11-11):** exportar el listado, el Excel del estado de cuenta y los documentos es D de la matriz: solo admin.
- **Acciones marcadas `( )` en el Excel** (Dirección autoriza Cambios SOLCOM y Órdenes de Compra; Gerente y Supervisor autorizan Hitos): se construyen en la Fase 4.
- **Migración de usuarios:**
  - Todos los usuarios `Solmat` pasan a Almacén. Los que están asignados a un proyecto (como gerente, o como supervisor o residente de obra) también reciben Supervisor.
  - Excepción: los usuarios que tienen `Engineer` y `Solmat` no reciben Almacén. Se les asignan Gerente o Supervisor según la regla de `Engineer`.
  - Los usuarios `Proyectos` pasan a Contratos.
  - El rol `Ayudante Movil` se elimina, porque no está en el Excel.
  - Antes de migrar, la lista de usuarios se vuelve a generar contra producción.

### Criterio de vistas por rol

Una vista se separa para un rol solo cuando se cumple al menos una de estas condiciones:

1. El rol ve un subconjunto distinto de los registros.
2. Hay campos que no debe ver, como el sueldo.
3. Su tarea es distinta, como una bandeja de autorización.

En los demás casos se usa la vista compartida, con permisos (`@can`) y filtros de alcance. Lo que limita los datos son la consulta del controlador y la Policy, no la vista.

Espacios propios previstos:

- **Obra (GER/SUP):** amplía `my-projects.*`.
- **Bandeja de autorizaciones de Dirección.**
- **Portal de proveedores:** ya existe.
- **Autorización de Vales (ALM-A).**

### Fase 2: Recursos Humanos

Piezas nuevas, que reutilizarán los demás módulos:

- `App\Models\Concerns\HasAccessScope`: un modelo que lo usa tiene `Modelo::visibleTo($user, $vista, $acción)` para filtrar consultas y `$modelo->isVisibleTo($user, $vista, $acción)` para revisar un registro. Ambos aplican el permiso y el alcance de la matriz. Hoy lo usan `ProjectWork`, `Worker`, `WorkerGroup`, `WorkerAttendance`, `WorkerVacation` e `Incentive`.
- `User::assignedProjectWorkIds()`: obras donde el usuario es supervisor o residente, más las de los proyectos que gerencia (alcance `assigned_works`).
- En `Controller`: `authorizeAccess($registro, $vista, $acción)` responde 403 si no hay acceso, y `visibleProjectWorks($vista, $acción)` da las obras que el usuario puede elegir en filtros y formularios.
- `AccessMatrix::hasFullScope()` y `AccessMatrix::hidesField()`.

Cambios de comportamiento:

- Cada ruta de `human_resources.*` pide su permiso `hr_*.{acción}`. El menú muestra cada opción con `@can`.
- **Gerente y Supervisor** ven solo los trabajadores, cuadrillas, asistencias, vacaciones e incentivos de sus obras. El Gerente también ve las obras de los proyectos que gerencia; antes solo contaban supervisor y residente.
- **Gerente** puede crear y editar cuadrillas, pero solo en sus obras (permiso nuevo de la matriz).
- **Gerente y Supervisor** ya no ven el sueldo, el historial de nómina ni los recibos de nómina. Antes un `Engineer` podía descargar los recibos.
- **Dirección** consulta todo RRHH, incluido el sueldo, sin botones de edición. Destajo queda solo para RRHH, como dice la matriz.
- **Exportar trabajadores** solo está disponible para quien ve a todos los trabajadores (RRHH, Dirección y admin).
- Se corrigió un 403: el comprobante de una falta capturada en la vista de cuadrilla no se podía abrir desde un usuario de obra.

Criterios aplicados al traducir rutas a permisos:

- **Borrar o cancelar sin X en la matriz** (trabajadores, cuadrillas, asistencias, vacaciones, categorías, nómina, destajo e incentivos) usa el permiso de **editar**. Así RRHH conserva lo que ya podía hacer. Si se quiere restringir, hay que agregar X a la matriz.
- **Capturar asistencia** en la vista de cuadrilla acepta crear o editar. RRHH solo tiene E en la matriz y hoy corrige asistencias desde ahí.
- **Marcar nómina como pagada** usa editar nómina, no registrar (R), porque RRHH no tiene R. La autorización de nómina por Dirección (V-02) se construye en la Fase 4.
- **Expediente y DC3:** quien puede ver al trabajador también descarga sus documentos; la D de la matriz no se exige aquí.
- **Incentivos de Gerente y Supervisor** quedaron con alcance `assigned_workers`: proponen incentivos solo para su gente. El Excel no decía el alcance.

### Fase 2: Proyectos

- Cada ruta de `projects.*` y `project_works.*` pide `projects.view`, `projects.create` o `projects.update`. Borrar proyectos, obras y estimaciones usa editar (mismo criterio que RRHH).
- **Proyectos** (`/proyectos`): `Project` y `ProjectWork` usan `HasAccessScope` con el alcance `assigned_projects`. Quien solo ve sus proyectos (Gerente y Supervisor) recibe el listado filtrado y 403 en proyectos u obras ajenos.
- **Mis Proyectos** (`/mis-proyectos`) pide `projects.view` y siempre lista los proyectos asignados al usuario. Es de solo consulta: se quitaron las rutas `my-projects.estimates.*`.
- **Menú:** "Proyectos" aparece a quien ve todos los proyectos (Dirección, Contratos, Almacén, Compras, Pagos, RRHH, Móviles y admin). "Mis Proyectos" aparece a Gerente y Supervisor, y a cualquier otro usuario que tenga proyectos asignados.
- **Documentación y convenios** (tarjeta completa, subida, aplicabilidad y descarga de nombramientos) y **archivos y facturas de estimaciones** piden `projects.update`.
- Gerente del proyecto y supervisor/residente de obra se eligen entre usuarios con `Gerente de Proyecto`, `Supervisor` o `Engineer` (este último mientras exista).
- Cambio de comportamiento: los usuarios `Solmat` (ahora Almacén) ya no crean ni editan proyectos; solo los consultan, como dice la matriz.
- Sin cambio en esta fase: `projects.search`, `works_json` y `works.search` (los usan los formularios de SOLMAT, SOLCOM y OC) y las pestañas SOLMAT, SOLCOM y OC de Mis Proyectos siguen con la regla de `Engineer` hasta que esos módulos pasen a permisos.

### Fase 2: Almacén

- **SOLMAT** (`material_requests.*`): cada ruta pide `material_requests.view`, `create`, `update` o `authorize`. Las rutas de detalle y PDF, que antes abría cualquier usuario, ahora piden `material_requests.view`.
  - `MaterialRequest` usa `HasAccessScope` (alcance `assigned_projects`). Gerente y Supervisor ven, crean y editan solo SOLMAT de sus proyectos, y solo mientras estén Pendientes o con cambios. Archivar, desarchivar y borrar son de quien edita todas (Almacén y admin).
  - "Aprobar y enviar a Almacén" pide `material_requests.authorize` (Gerente) y además ser gerente del proyecto. Admin siempre puede.
  - El listado "SOLMATs" del menú es para quien ve todas; Gerente y Supervisor siguen en Mis Proyectos.
- **Cambios SOLMAT:** el panel pide `material_request_changes.view` y se filtra por las SOLMAT que el usuario ve. Pedir y resolver cambios pide `material_request_changes.update` (Almacén). La solicitud del Supervisor con autorización del Gerente es flujo nuevo de la Fase 4.
- **Pila SOLMAT:** `material_request_queue.view` (Almacén y Compras); comprometer existencias, `material_request_queue.update` (Almacén). Crear SOLCOM desde la pila pide `purchase_requests.create`: Compras ya no lo hace, como dice la matriz.
- **Inventario:** Consulta pide `stocks.view` (Gerente, Dirección, Almacén, Almacén Autoriza y Compras; el Supervisor no, V-08). Entradas y Salidas piden `stock_entries.*` y `stock_exits.*`. Registrar el retorno de herramienta usa R de Salidas.
- **Vales de Material:** `material_vouchers.*` (Almacén, Almacén Autoriza y Móviles operan; Dirección consulta). Imprimir pide D. Autorizar el vale (también por cambio de estatus) pide `material_voucher_authorizations.authorize`: solo **Almacén Autoriza** y admin.
- Cambios de comportamiento a revisar:
  - **Pagos** pierde Vales de Material (hoy marca Facturado y Pagado) y el detalle de SOLMAT. La matriz no le da esas vistas.
  - **Compras** pierde Cambios SOLMAT y crear SOLCOM desde la pila.
  - Nadie tiene todavía el rol **Almacén Autoriza**: hay que asignarlo a mano a quien autoriza vales. Mientras tanto, solo admin autoriza.

### Fase 2: Compras

- Nuevo en la matriz: `action_scopes` permite un alcance distinto para una acción. Se usa en SOLCOM: Almacén tiene E solo sobre `unsent_purchase_requests` (Pendiente o con cambios).
- **SOLCOM** (`purchase_requests.*`): ver, crear (Almacén), editar (Almacén antes de enviar, Compras siempre). Detalle y PDF piden `purchase_requests.view`; Gerente y Supervisor solo ven las de sus proyectos. Borrar y archivar usan editar.
- **Enviar a Compras y reasignar comprador:** `purchase_request_queue.update` (Almacén). Compras ya no reasigna.
- **Cambios SOLCOM:** el panel pide `purchase_request_changes.view` y se filtra por las SOLCOM que el usuario ve; pedir y resolver cambios, `purchase_request_changes.create` (Almacén y Compras). La autorización de Dirección es Fase 4.
- **Pila SOLCOM:** `purchase_request_queue.view`; sigue mostrando las SOLCOM asignadas al usuario.
- **Carga de Trabajo:** `purchasing_workload.view`. Dirección y admin ven a todos los compradores; cada comprador, solo su carga.
- **OC** (`purchase_orders.*`): `PurchaseOrder` usa `HasAccessScope` con `own_buyer`, `assigned_projects` y `own_supplier`. Compras crea, edita, emite, cierra y archiva solo sus OC; borrar pide X. Pagos, Almacén y Dirección consultan todas; Gerente y Supervisor, las de sus proyectos y sin hitos, pagos, facturas ni evidencias. "OC vencidas de entrega" pide editar OC.
- Sin cambio en esta fase (siguen con `role:`): autorizar OC y reabrir (admin), hitos, entregas (recepción), evidencias y reporte de entregas. Pasan con el módulo de Pagos y Recepción.
- Cambios de comportamiento a revisar:
  - **Contratos** (antes `Proyectos`) ya no abre OC: la matriz no le da esa vista.
  - **Compras** ya no crea SOLCOM ni reasigna comprador; Almacén lo hace.
  - **Pagos** pierde el PDF de la OC y el PDF con anexos.

### Fase 2: Pagos y Recepción

- **Facturas** (`invoices`): `PurchaseOrderInvoice` usa `HasAccessScope` (`own_buyer`, `assigned_projects`, por la OC). Consultan Gerente y Supervisor (sus proyectos), Dirección, Almacén, Compras (sus OC) y Pagos. Validar pide `invoices.authorize`: Compras en sus OC y admin. Exportar no está disponible para quien solo ve sus proyectos. `User::invoiceBuyerFilter()` ahora sale de la matriz.
- **Alta de Facturas** (`invoice_intake`): Compras, Recepción (decisión) y admin. Borrar una factura pide C de Alta. Se agregó "Alta de facturas" al menú Facturas.
- **Hitos** (`milestones`): el listado se acota a las OC que el usuario ve. Crear, editar, ajustar monto y borrar piden C o E (Compras en sus OC, admin).
- **Pagos:** Autorizaciones (`payment_authorizations`: Pagos presenta y registra pagos adicionales; **Dirección autoriza con su usuario**, como admin), Por Pagar (`payments_payable`: Pagos edita) y Pagado (`payments_paid`: Pagos registra con SPEI). Compras y Dirección consultan. Regularización documental, contrarecibo y comprobante SPEI piden V de Pagado.
- **Recepción de OC** (`purchase_order_receipts`): registrar entregas y "Buscar OCs" piden R (Almacén; Gerente y Supervisor en sus proyectos). **Validación de hitos** (`milestone_validations`): confirmar entrega y registrar cumplimiento piden A o C, y ver la OC.
- **Evidencias** (`purchase_order_evidences`): suben Gerente, Supervisor y Almacén; consultan Compras y Pagos; siempre sobre una OC que el usuario ve.
- La vista de la OC muestra cada sección según estos permisos. Gerente y Supervisor siguen viendo la OC sin hitos, pagos, facturas ni evidencias; registran entregas desde "Buscar OCs". Subir evidencias desde la obra queda para su espacio propio (Fase 3).
- Sin cambio: autorizar, autorizar con pagos y reabrir OC, revertir entregas y el modo interactivo de pagos siguen solo para admin. Autorizar OC por Dirección es Fase 4.

### Fase 2: Proveedores

- **Listado** (`suppliers`): Compras da de alta, importa y edita (incluye contactos, sucursales y acceso al portal; borrar usa editar). Dirección, Almacén y Pagos consultan. Los datos de proveedor que usan los formularios de OC piden V.
- **Estado de Cuenta** (`supplier_account_statements`): consultan Dirección, Compras y Pagos. Almacén no.
- **Exportar** el listado, el estado de cuenta y los documentos pide D: solo admin.
- Cambios de comportamiento: Pagos ya no da de alta ni edita proveedores; Compras y Pagos ya no exportan; Almacén y Dirección pasan a consultar el listado.

### Fase 2: Bienes Móviles y Herramientas

- **Bienes Móviles** (`mobile_assets`): Móviles da de alta, importa y edita (fotos, checklist documental y bitácora de mantenimiento usan editar; borrar también). Gerente y Dirección consultan. Exportar y descargar la documentación en ZIP piden D: solo admin.
- **Herramientas: Registro** (`tools`): Almacén y Móviles dan de alta, editan, archivan y registran calibraciones; Gerente y Supervisor consultan. Borrar definitivo sigue solo para admin.
- **Control de uso** (`tool_controls`): Almacén registra, edita y borra controles (R); Gerente y Supervisor consultan. Móviles no lo ve.
- **Categorías de herramientas** (`tool_categories`): solo Almacén y admin.
- El menú muestra Bienes Móviles y cada opción de Herramientas según el permiso. Antes el menú de Herramientas era de Móviles pero las rutas eran de Solmat.
- Cambios de comportamiento: Móviles ya no exporta ni descarga el ZIP; Móviles gana el Registro de herramientas; Gerente y Supervisor pasan a consultar herramientas, y el Gerente, bienes móviles.

### Fase 2: Configuración

- **Conceptos** y **Familias** (`concepts`, `concept_categories`): Almacén mantiene el catálogo (alta, importación, edición, archivo, compradores por familia); Compras consulta. La búsqueda JSON de conceptos y subcategorías sigue abierta a usuarios autenticados porque la usan los formularios de todos los módulos.
- **Precios adjudicados** (`awarded_prices`): Dirección y Compras. Antes lo abría cualquier usuario autenticado.
- **Usuarios** (`users`) y roles: solo admin.
- **Seguridad:** `PUT /usuarios/{user}` no tenía middleware y actualizaba a cualquier usuario, incluidos roles y permisos: cualquier usuario autenticado podía darse `admin`. Ahora, sin `users.update`, solo se edita el perfil propio (nombre, correo y contraseña) y se ignoran roles y permisos.
- **Auditoría** (`notifications_audit`): Dirección y admin. La campana de "Notificaciones globales" del encabezado (actividad de todo el sistema) ahora solo aparece a ellos.
- **Bandeja personal** de notificaciones: abierta a todo usuario autenticado; cada quien ve solo las suyas. Antes solo admin y Solmat, aunque el encabezado mostraba el enlace a todos.
- **Búsqueda por folio** del encabezado: aparece a quien consulta SOLMAT, SOLCOM u OC.
- **Registro público desactivado** (`Features::registration()` en `config/fortify.php`): antes cualquier visitante podía crear una cuenta con `POST /register`. Las cuentas las da de alta admin en Usuarios.

### Pendientes

- Generar la lista de usuarios con su perfil propuesto contra producción. En la base local no existen `Recepción` ni `Proveedor`.

## Roles Vigentes

- admin
- Moviles
- Orden de compra
- Pagos
- Proveedor
- Proyectos
- Recepción
- Inventario
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
| Inventario       |   SI   |  SI  |   SI   |   SI   |
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
| Mis Proyectos (solo lectura, estimaciones sin archivos) | Engineer   |
| Solicitudes de Compra      | Solcom, Orden de compra, admin  |
| Pila SOLCOM                | Solcom, Orden de compra         |
| Carga de Trabajo           | Orden de compra                 |
| Solicitudes de Material    | Solmat                          |
| Pila SOLMAT                | Solmat, Orden de compra         |
| Inventario                 | Inventario                      |

## Convenciones

- Los roles controlan visibilidad de modulos en el menu.
- Los permisos CRUD controlan acciones dentro de las vistas.
- `admin` tiene acceso total.

## Ordenes de Compra por Comprador

- `buyer_id` vincula la OC a `users.id`. Al crear desde el listado o desde Pila SOLCOM se asigna el usuario autenticado; Elabora Orden muestra su nombre y no admite texto libre.
- Admin y Pagos reciben el listado completo por defecto y pueden cambiar a Mis OC. Todos los demas perfiles con acceso al listado reciben Mis OC, incluso si solicitan `scope=all`; no se devuelve un error por rol.
- Listados activos, archivados y facturas recientes de la pagina de OC respetan ese alcance. Admin y Pagos pueden filtrar OC sin comprador asignado con `buyer_status=unassigned`. Las bandejas administrativas, aprobaciones y reasignacion de comprador siguen exclusivas de admin.
- Blade muestra controles de Compras solo para admin o el comprador vinculado. Solo admin ve el selector para asignar o cambiar comprador. La reasignacion registra una notificacion y conserva la firma historica `elaborated_by`.
- Pagos conserva sus flujos de pagos/facturas. SOLMAT y Recepcion conservan los accesos colaborativos existentes. Facturas, contadores, exportacion y seleccion de OC para alta se filtran por comprador cuando el usuario tiene Compras sin ninguno de esos roles operativos ni admin.
- No se agregan bloqueos de rol en controladores ni nuevas policies o middleware de rol. Se mantienen los middleware y restricciones de estado existentes. **Los controles Blade son visibilidad, no autorizacion de solicitudes directas:** este cambio no impide invocar endpoints de gestion manualmente cuando los middleware existentes lo permiten.

## Mis Proyectos (Engineer)

- Engineer restringido = rol `Engineer` sin `admin`, `Proyectos` ni `Solmat`. Accede unicamente a proyectos donde es Supervisor o Residente de alguna obra (`project_works.supervisor_user_id` / `resident_user_id`).
- Rutas `my-projects.*` (`/mis-proyectos`): pestanas Estimaciones y Obras, SOLMAT, SOLCOM y Ordenes de Compra, todas de solo lectura y filtradas por `project_id`. Proyecto ajeno responde 403.
- Estimaciones: solo consulta en sus proyectos, sin archivos (desde la Fase 2 de Proyectos; antes creaba y actualizaba). Documentación y convenios del proyecto solo para quien tiene `projects.update`.
- `show` y PDF de SOLMAT, SOLCOM y OC responden 403 si el registro no es de sus proyectos. El PDF de OC no esta disponible para Engineer y el detalle de OC oculta hitos, pagos, facturas y evidencias.
- Ya no usa `projects.*` ni `obras.*`; `projects.search`, `works_json` y `works.search` se limitan a sus proyectos.
- SOLMAT: la pestaña SOLMAT lista solo las del proyecto y permite crear (proyecto fijo, obras del mismo proyecto), editar encabezado, conceptos y observaciones de cualquier SOLMAT del proyecto mientras esté Pendiente o con Cambios Solicitados. No elimina ni archiva.
- Gerente de Proyecto (`projects.manager_user_id`, usuario con rol Engineer, se asigna al editar el proyecto): accede al proyecto aunque no sea Supervisor/Residente. Solo `admin`, `Solmat` y el gerente del proyecto pueden Aprobar y Enviar a Almacén una SOLMAT; el resto de Engineers recibe 403.

## Historial de Cambios

Agrega una linea por cambio para trazabilidad.

- 2026-06-15: Creacion inicial de matriz de roles y permisos.
- 2026-06-16: Roles renombrados a español; eliminados `orders`/`payments` (inglés). Agregado rol `Recepción` (solo Alta de Facturas). Bienes Móviles y Proyectos ahora protegidos por middleware. Solmat accede a Proyectos y Pila SOLMAT. Orden de compra reemplaza a Solcom como nombre de rol.
- 2026-07-13: Se agrega rol `supplier_portal_access` y su mapeo de Portal de Proveedores. Se documenta tambien `Proveedor` en Vales de Material y se actualizan mapeos de Hitos/Alta de Facturas segun rutas actuales.
- 2026-07-13: Se agrega el modulo funcional Evidencias de Entrega OC con acceso para admin, Solmat, Pagos y Orden de compra.
- 2026-09-14: Se agrega el rol Inventario para consulta y gestión de existencias, entradas y salidas.
- 2026-09-15: Engineer accede a Proyectos solo cuando está asignado como supervisor o residente de una obra; admin conserva el listado completo.
- 2026-09-29: OC vinculadas a comprador usuario; Mis OC y listado completo admin, precarga de Elabora Orden, reasignacion visual admin y vinculacion historica por nombres exactos.
- 2026-09-30: Pagos accede tambien al listado completo de OC activas y archivadas, sin ampliar permisos de aprobacion o reasignacion.
- 2026-10-10: Engineer pasa a Mis Proyectos (`my-projects.*`) de solo lectura acotado a sus proyectos; se retira de `projects.*` y `obras.*`.
- 2026-10-12: Engineer crea y edita SOLMAT de sus proyectos; nuevo Gerente de Proyecto por proyecto; envío a Almacén solo para admin, Solmat y el gerente.
- 2026-11-10: Fase 0 de la matriz objetivo: `database/data/access_matrix.php` generado del Excel "Matriz usuarios × vistas del ERP" con las decisiones de Almacén unificado, Gerente/Supervisor separados, Dirección con usuario propio, Contratos, eliminación del rol Proveedor y pantalla nueva de entrega en obra. Los roles vigentes no cambian todavía.
- 2026-11-10: Fase 1: servicio `AccessMatrix`, `AccessMatrixSeeder` (permisos `{vista}.{acción}` y roles nuevos), `Gate::before` para admin y comando `access:migrate-users` (aditivo). Rutas y menú sin cambios.
- 2026-11-11: Se desactiva el registro público de Fortify.
- 2026-11-11: Fase 2, Configuración con permisos de la matriz; se cierra la escalada de privilegios en `PUT /usuarios/{user}`; auditoría solo Dirección y admin; bandeja personal para todos.
- 2026-11-11: Fase 2, Bienes Móviles y Herramientas con permisos de la matriz; exportar y ZIP solo admin.
- 2026-11-11: Fase 2, Proveedores: Listado y Estado de Cuenta con permisos de la matriz; exportar solo admin.
- 2026-11-11: Fase 2, Pagos y Recepción: Facturas, Alta de Facturas, Hitos, Pagos (Dirección autoriza), Recepción de OC, validación de hitos y Evidencias con permisos de la matriz, sin excepciones para Pagos ni Compras; Recepción conserva Alta de Facturas. Requiere el seeder y `access:migrate-users --apply`.
- 2026-11-10: Fase 2, Compras: SOLCOM, Cambios SOLCOM, Pila SOLCOM, Carga de Trabajo y OC con permisos de la matriz; `action_scopes` en la matriz; OC solo del comprador asignado, SOLCOM editable por Almacén hasta enviarla, PDF de OC solo con D. Requiere haber corrido el seeder y `access:migrate-users --apply`.
- 2026-11-10: Fase 2, Almacén: SOLMAT, Cambios SOLMAT, Pila SOLMAT, Inventario y Vales con permisos de la matriz; Gerente, Supervisor y Almacén crean SOLMAT, solo el gerente del proyecto la envía a Almacén, correcciones de inventario solo admin, autorización de vales para Almacén Autoriza. Requiere haber corrido `access:migrate-users --apply`.
- 2026-11-10: Fase 2, Proyectos: rutas con permisos `projects.*`, alcance `assigned_projects` para Gerente y Supervisor, Mis Proyectos de solo consulta (sin crear ni editar estimaciones), documentos y convenios solo para quien edita y menú por alcance. Requiere haber corrido `access:migrate-users --apply`.
- 2026-11-10: Fase 2, Recursos Humanos: rutas con permisos `hr_*`, filtros por obra con `HasAccessScope`, sueldo y recibos ocultos para Gerente y Supervisor, Dirección de solo consulta y menú con `@can`. Requiere haber corrido `access:migrate-users --apply`.
