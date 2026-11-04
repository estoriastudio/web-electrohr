---
spec_id: ARCH-001
type: ext-rule
title: Tipado y pertenencia de modelos
module: Mapa de arquitectura
module_key: architecture
status: aprobado
author: 
reviewers: []
branch: moon
created: 2026-11-04
profile: docs/specs/_project.json
docs:
open_questions: 0
---

# ARCH-001 · Mapa de arquitectura — Tipado y pertenencia de modelos

**Módulo:** Mapa de arquitectura (`architecture`) · **Tipo:** C3 · Cambio de regla

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)

## 1. Propósito

Eliminar las 22 advertencias de docs:map y obtener un mapa fiel al dominio, sin alterar el comportamiento de la aplicación ni inventar relaciones o rutas. La fase local corrige ocho tipos de retorno; la asignación de módulos depende de un contrato aprobado y de una versión autorizada del kit Estoria.

**Flujo de interacción**

El desarrollador aprueba el spec y resuelve las preguntas abiertas. Se implementa y valida el tipado, se detiene para confirmación y solo después se aborda la integración autorizada del contrato de mapeo. No se modifica localmente el generador protegido ni se considera aprobado el spec por haber solicitado su creación.

## 2. Módulo afectado

- **Módulo:** Mapa de arquitectura
- **Usa Livewire:** no

**Comportamiento actual**

Diagnóstico obtenido con php artisan docs:map: 68 modelos, 30 módulos y 22 advertencias (8 relation_without_return_type y 14 model_without_module). Las ocho relaciones se detectan por lectura del código fuente. Trece de los modelos sin módulo ya tienen relaciones; la inferencia BelongsTo no puede elegir entre varios módulos padre o padres aún sin asignar. UserInfo está vacío y tiene columna user_id, pero el mapa no reporta una FK. Además, PurchaseOrder.module queda en supplier_portal y OvertimeClosure en project_works. HR-008 se carga, pero sus modelos de horas extra no permiten anclar la asignación del spec a un módulo.

**Archivos que se tocan**

- app/Models/Notification.php: importar BelongsTo y tipar user(): BelongsTo.
- app/Models/Supplier.php: importar HasMany y HasOne; tipar purchaseOrders(), materialVouchers(), contacts() y locations() como HasMany; primaryContact() como HasOne.
- app/Models/SupplierContact.php: importar BelongsTo y tipar supplier(): BelongsTo.
- app/Models/SupplierLocation.php: importar BelongsTo y tipar supplier(): BelongsTo.
- docs/architecture/_code.json: salida generada, nunca corregir manualmente.
- docs/specs/ARCH-001-architecture-tipado-y-pertenencia-de-modelos.spec.json y su .spec.md: fuente editable y documento generado con Estoria Spec Tool.
- La futura declaración de pertenencia no tiene archivo autorizado todavía. Su ubicación se resolverá antes de ampliar esta lista; no crear archivos de configuración especulativos.

**Debe permanecer igual**

- Rutas, nombres de rutas, controladores, middleware, permisos, vistas, componentes Livewire y navegación.
- Las ocho relaciones conservan su clase concreta, modelo relacionado, llaves y filtros; solo cambian imports y declaraciones de retorno.
- Los 68 modelos, su esquema, datos, fillable, casts y relaciones existentes.
- UserInfo y User sin cambios.
- Todos los archivos protegidos de docs/specs/_project.json, especialmente app/Console/Commands/DocsMap.php, y el propio perfil sin cambios.
- Los specs funcionales existentes, incluido HR-008; no reescribirlos para encajar artificialmente el diagnóstico.
- El uso de PurchaseOrder desde el portal de proveedores y otros módulos; pertenencia canónica no equivale a exclusividad de uso.

**Riesgos**

- Cero advertencias no garantiza asignaciones correctas; revisar explícitamente PurchaseOrder y OvertimeClosure.
- Crear otro spec o agregar relaciones no soluciona necesariamente la ambigüedad del algoritmo actual.
- El tipo ext-rule se usa para documentar la corrección técnica; la aplicación del contrato en el kit queda bloqueada y no autoriza modificar el comando protegido.
- El mapeador lee specs JSON incluso si están en borrador; este spec no declara models ni un target para evitar usarlo como ancla artificial.
- La integración del kit puede requerir otro alcance autorizado y una revisión del spec; no copiar un nuevo generador sobre el archivo protegido por iniciativa del implementador.
- El mapa ya tenía cambios locales antes del diagnóstico. Preservarlos y comparar el resultado con una copia previa.
- docs:map --check puede detectar diferencias preexistentes contra specs; deben separarse de las advertencias de este alcance.

## 3. Regla

**Actual**

El generador prioriza controladores con rutas, después specs asociados a un módulo ya identificado y finalmente un único recorrido de relaciones BelongsTo. Solo asigna por padres cuando obtiene un módulo único. Un modelo compartido puede quedar asociado al primer módulo descubierto. Las advertencias no hacen fallar por sí solas el comando. Los specs de extensión de reglas no constituyen actualmente un mecanismo de configuración de pertenencia.

**Nueva**

Fase local: declarar los ocho tipos de retorno indicados, conservando consultas, filtros y llaves. Contrato propuesto para el kit, todavía no implementable: permitir pertenencia explícita modelo -> módulo principal; dicha declaración prevalece sobre la inferencia, no cambia rutas y conserva los usos del modelo desde otros módulos. Validar todas las referencias antes de aplicar asignaciones; una declaración inválida debe producir un diagnóstico visible y salida fallida, sin escribir un mapa parcial. La inferencia sigue como respaldo y resuelve cadenas hasta estabilizar, sin elegir arbitrariamente entre padres de módulos distintos. Ubicación, formato y versión del contrato quedan pendientes de aprobación.

### Identificadores de modelos y pertenencia funcional confirmados

La clave de cada modelo es su nombre convertido a snake_case, sin pluralizar. Esta clave identifica al modelo; no es la clave técnica de su módulo principal.

| Modelo | Clave del modelo | Módulo principal confirmado |
|---|---|---|
| LateOvertimeRequest | late_overtime_request | Recursos Humanos |
| OvertimeEntry | overtime_entry | Recursos Humanos |
| OvertimeClosure | overtime_closure | Recursos Humanos |
| MaterialRequestChangeNote | material_request_change_note | SOLMAT |
| MaterialRequestItem | material_request_item | SOLMAT |
| MaterialRequestItemProjectWork | material_request_item_project_work | SOLMAT |
| NotificationRecipient | notification_recipient | Auditoría |
| PurchaseOrder | purchase_order | Órdenes de Compra |
| PurchaseOrderItem | purchase_order_item | Órdenes de Compra |
| PurchaseOrderItemDelivery | purchase_order_item_delivery | Órdenes de Compra |
| PurchaseRequestChangeNote | purchase_request_change_note | SOLCOM |
| PurchaseRequestItem | purchase_request_item | SOLCOM |
| StockCertificate | stock_certificate | Almacén |
| StockEntryItem | stock_entry_item | Almacén |
| StockExitItem | stock_exit_item | Almacén |
| UserInfo | user_info | Usuarios |

Los nombres funcionales fueron confirmados por el desarrollador. El contrato pendiente debe definir cómo se representan sus identificadores técnicos; no asumir que Auditoría equivale a notifications ni que Recursos Humanos equivale a human_resources (hoy Cuadrillas). Esta tabla sustituye la propuesta previa basada en prefijos de rutas, sin autorizar cambios de rutas, menús ni agrupaciones ajenas a este alcance. Los dos modelos adicionales (PurchaseOrder y OvertimeClosure) se incluyen porque una asignación errónea puede no producir advertencias.

**Dónde se aplica:** modelo (scope o método)

**Casos afectados**

- Notification::user -> BelongsTo.
- Supplier::purchaseOrders, materialVouchers, contacts y locations -> HasMany; Supplier::primaryContact -> HasOne.
- SupplierContact::supplier y SupplierLocation::supplier -> BelongsTo.
- Catorce modelos sin pertenencia y dos asignaciones canónicas cuestionables, según la tabla de la regla nueva.
- Metadatos con un modelo o módulo inexistente: diagnóstico explícito, salida fallida y sin sobrescribir el mapa válido previo.
- Cadenas de modelos padre todavía no asignados: resolución estable, independiente del orden de descubrimiento.
- Un modelo con padres de módulos diferentes y sin declaración explícita: conservar la advertencia, nunca elegir por orden.

**Datos históricos**

No se modifican registros, tablas, FKs, migraciones, archivos subidos ni permisos. UserInfo se conserva sin cambios y pertenece a Usuarios solo mediante metadatos del mapa. No agregar UserInfo::user, no modificar User y no retirar el modelo. Regenerar el mapa debe preservar los 68 modelos.

## 4. Reglas de negocio

- Antes de implementar, leer completos .github/copilot-instructions.md, .github/instructions/database.instructions.md, .github/instructions/specs.instructions.md, docs/specs/_project.json, este spec, los cuatro modelos afectados y app/Console/Commands/DocsMap.php. Este último se lee solo para entender el contrato existente.
- No implementar mientras status no sea aprobado o existan preguntas abiertas, salvo confirmación explícita del desarrollador conforme a las reglas del proyecto.
- Confirmar antes de escribir código: modelos afectados (Notification, Supplier, SupplierContact, SupplierLocation); rutas y vistas sin cambios; capas tipado, integración autorizada y verificación.
- Orden específico de capas: 1) aprobar el contrato y cerrar preguntas; 2) tipar las ocho relaciones y validar; detenerse y listar archivos; 3) solo tras confirmación y disponibilidad del contrato autorizado del kit, acordar archivos y aplicar metadatos sin tocar protegidos; detenerse y listar archivos; 4) regenerar y verificar el mapa completo.
- Validación de la capa de tipado: php -l en cada uno de los cuatro modelos y php artisan docs:map --no-write. Con la misma base de datos y sin otros cambios, deben desaparecer las ocho advertencias de tipado y permanecer las catorce de asignación.
- Validación final: php artisan docs:map; inspeccionar el JSON, no solo el código de salida. Exigir warnings vacío, 68 modelos y ningún module nulo. Revisar la tabla canónica aprobada.
- Las ocho relaciones deben quedar detectadas por return_type, conservando los atributos semánticos exportados antes del cambio (tipo, destino y llaves), además del filtro is_primary de primaryContact.
- Verificar que depends_on preserve las dependencias entre módulos correspondientes a relaciones BelongsTo; el módulo canónico no debe borrar relaciones ni usos compartidos.
- Ejecutar php artisan docs:map --check --no-write como control adicional. Registrar diferencias preexistentes por separado, sin alterar specs funcionales para ocultarlas.
- No fijar 30 módulos como criterio final: el conteo depende del contrato aprobado. No eliminar modelos, crear rutas ficticias ni editar el JSON generado para alcanzar los umbrales.
- La especificación completa solo se considera implementada después de resolver también la pertenencia; completar el tipado por sí solo no satisface el objetivo de cero advertencias.

## 5. Validaciones

| Campo | Regla |
|---|---|
| `return_types` | Ocho métodos con retorno concreto correcto; imports Eloquent existentes reutilizados; sin cambios de consultas, llaves o filtros. |
| `module_assignments` | Contrato pendiente: cada referencia debe identificar un modelo descubierto y un módulo válido; errores visibles y sin salida parcial. |
| `architecture_output` | Al finalizar el plan completo: warnings=[], 68 modelos, cero module nulos y tabla canónica aprobada satisfecha. |

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Para esta corrección técnica ext-rule, el orden específico de capas descrito en Reglas de negocio prevalece sobre el orden genérico generado al final. No hay tratamiento de datos históricos ni ajustes de vistas.
- El punto de aplicación «modelo» corresponde exclusivamente al tipado local. El contrato de mapeo pertenece al kit y no se implementa en modelos ni en un service nuevo de la aplicación.
- No se solicita ninguna excepción a los archivos protegidos ni al perfil. La dependencia del kit debe resolverse mediante un mecanismo autorizado antes de implementar la pertenencia.

## 7. Preguntas abiertas

- [x] ¿Qué identificadores de modelo y pertenencias funcionales se aprueban para los 16 modelos de la tabla? → **Claves de modelo en snake_case sin pluralizar. Pertenencias confirmadas: Recursos Humanos (LateOvertimeRequest, OvertimeEntry, OvertimeClosure); SOLMAT (MaterialRequestChangeNote, MaterialRequestItem, MaterialRequestItemProjectWork); Auditoría (NotificationRecipient); Órdenes de Compra (PurchaseOrder, PurchaseOrderItem, PurchaseOrderItemDelivery); SOLCOM (PurchaseRequestChangeNote, PurchaseRequestItem); Almacén (StockCertificate, StockEntryItem, StockExitItem); Usuarios (UserInfo).**
- [x] ¿Debe conservarse UserInfo sin nuevas relaciones ni migraciones? → **Sí. Conservar sin cambios y asignarlo a Usuarios solo mediante metadatos del mapa, según confirmación del desarrollador.**

## 8. Fuera de alcance

- Rutas API (routes/api.php)
- Unit / Feature tests
- Factories y seeders
- Form Requests
- Observers / Event listeners
- Jobs y colas
- Artisan commands
- Policies / Gates
- Clases Mail / Notification de Laravel
- API Resources / Transformers
- Instalar paquetes no listados en el perfil
- Cambios a vite.config.js / package.json
- Cambiar rutas, controladores, servicios, vistas, componentes Livewire o permisos.
- Migraciones, cambios de datos, nuevas relaciones, eliminación de modelos y modificación de UserInfo o User.
- Modificar localmente el kit Estoria o actualizar/copiar su generador protegido como parte de este spec.
- Editar manualmente docs/architecture/_code.json o regenerar docs/architecture.md antes de validar el mapa.
- Cambiar specs existentes o docs/specs/_project.json para silenciar advertencias.
- Implementar un esquema de pertenencia sin aprobación de formato, ubicación y mecanismo de integración.
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

Detente al terminar cada capa y lista los archivos creados o modificados.

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en el modelo (scope/método): Fase local: declarar los ocho tipos de retorno indicados, conservando consultas, filtros y llaves. Contrato propuesto para el kit, todavía no implementable: permitir pertenencia explícita modelo -> módulo principal; dicha declaración prevalece sobre la inferencia, no cambia rutas y conserva los usos del modelo desde otros módulos. Validar todas las referencias antes de aplicar asignaciones; una declaración inválida debe producir un diagnóstico visible y salida fallida, sin escribir un mapa parcial. La inferencia sigue como respaldo y resuelve cadenas hasta estabilizar, sin elegir arbitrariamente entre padres de módulos distintos. Ubicación, formato y versión del contrato quedan pendientes de aprobación.

### Identificadores de modelos y pertenencia funcional confirmados

La clave de cada modelo es su nombre convertido a snake_case, sin pluralizar. Esta clave identifica al modelo; no es la clave técnica de su módulo principal.

| Modelo | Clave del modelo | Módulo principal confirmado |
|---|---|---|
| LateOvertimeRequest | late_overtime_request | Recursos Humanos |
| OvertimeEntry | overtime_entry | Recursos Humanos |
| OvertimeClosure | overtime_closure | Recursos Humanos |
| MaterialRequestChangeNote | material_request_change_note | SOLMAT |
| MaterialRequestItem | material_request_item | SOLMAT |
| MaterialRequestItemProjectWork | material_request_item_project_work | SOLMAT |
| NotificationRecipient | notification_recipient | Auditoría |
| PurchaseOrder | purchase_order | Órdenes de Compra |
| PurchaseOrderItem | purchase_order_item | Órdenes de Compra |
| PurchaseOrderItemDelivery | purchase_order_item_delivery | Órdenes de Compra |
| PurchaseRequestChangeNote | purchase_request_change_note | SOLCOM |
| PurchaseRequestItem | purchase_request_item | SOLCOM |
| StockCertificate | stock_certificate | Almacén |
| StockEntryItem | stock_entry_item | Almacén |
| StockExitItem | stock_exit_item | Almacén |
| UserInfo | user_info | Usuarios |

Los nombres funcionales fueron confirmados por el desarrollador. El contrato pendiente debe definir cómo se representan sus identificadores técnicos; no asumir que Auditoría equivale a notifications ni que Recursos Humanos equivale a human_resources (hoy Cuadrillas). Esta tabla sustituye la propuesta previa basada en prefijos de rutas, sin autorizar cambios de rutas, menús ni agrupaciones ajenas a este alcance. Los dos modelos adicionales (PurchaseOrder y OvertimeClosure) se incluyen porque una asignación errónea puede no producir advertencias.
- [ ] Caso: Notification::user -> BelongsTo.
- [ ] Caso: Supplier::purchaseOrders, materialVouchers, contacts y locations -> HasMany; Supplier::primaryContact -> HasOne.
- [ ] Caso: SupplierContact::supplier y SupplierLocation::supplier -> BelongsTo.
- [ ] Caso: Catorce modelos sin pertenencia y dos asignaciones canónicas cuestionables, según la tabla de la regla nueva.
- [ ] Caso: Metadatos con un modelo o módulo inexistente: diagnóstico explícito, salida fallida y sin sobrescribir el mapa válido previo.
- [ ] Caso: Cadenas de modelos padre todavía no asignados: resolución estable, independiente del orden de descubrimiento.
- [ ] Caso: Un modelo con padres de módulos diferentes y sin declaración explícita: conservar la advertencia, nunca elegir por orden.
- [ ] Datos históricos: No se modifican registros, tablas, FKs, migraciones, archivos subidos ni permisos. UserInfo se conserva sin cambios y pertenece a Usuarios solo mediante metadatos del mapa. No agregar UserInfo::user, no modificar User y no retirar el modelo. Regenerar el mapa debe preservar los 68 modelos.
- [ ] Permanece igual: Rutas, nombres de rutas, controladores, middleware, permisos, vistas, componentes Livewire y navegación.
- [ ] Permanece igual: Las ocho relaciones conservan su clase concreta, modelo relacionado, llaves y filtros; solo cambian imports y declaraciones de retorno.
- [ ] Permanece igual: Los 68 modelos, su esquema, datos, fillable, casts y relaciones existentes.
- [ ] Permanece igual: UserInfo y User sin cambios.
- [ ] Permanece igual: Todos los archivos protegidos de docs/specs/_project.json, especialmente app/Console/Commands/DocsMap.php, y el propio perfil sin cambios.
- [ ] Permanece igual: Los specs funcionales existentes, incluido HR-008; no reescribirlos para encajar artificialmente el diagnóstico.
- [ ] Permanece igual: El uso de PurchaseOrder desde el portal de proveedores y otros módulos; pertenencia canónica no equivale a exclusividad de uso.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[ARCH-001]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
