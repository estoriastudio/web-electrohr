---
spec_id: HR-009
type: ext-rule
title: Editar y eliminar movimientos por Almacén
module: Inventario
module_key: stocks
status: implementado
implemented: 2026-11-19
author: Jorge Peña
reviewers: []
branch: master
created: 2026-11-18
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/purchase-order-payment-health-flow.md
  - docs/supplier-account-statement.md
open_questions: 0
---

# HR-009 · Inventario — Editar y eliminar movimientos por Almacén

**Módulo:** Inventario (`stocks`) · **Tipo:** C3 · Cambio de regla

> ✓ Estado: **Implementado** (2026-11-19). Este spec describe código que ya existe: no lo vuelvas a implementar. Los cambios nuevos van en un spec de extensión (C1–C4).

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/purchase-order-payment-health-flow.md`
- `docs/supplier-account-statement.md`

## 1. Propósito

Permitir al rol Almacén editar y eliminar entradas y salidas existentes de inventario mediante los controles y rutas actuales, sin conceder el permiso para crear ajustes manuales de existencias.

**Flujo de interacción**

El usuario Almacén abre Entradas o Salidas, selecciona el icono de editar o eliminar y utiliza el flujo existente. Al eliminar se muestra la confirmación actual. Los movimientos de ajustes manuales se pueden eliminar, pero siguen sin poder editarse. Las validaciones y consecuencias existentes sobre inventario y movimientos vinculados se conservan.

## 2. Módulo afectado

- **Módulo:** Inventario
- **Usa Livewire:** no

**Comportamiento actual**

La matriz asigna a ALM los permisos view, create y register de stock_entries y stock_exits, pero no update. Los iconos de editar y eliminar están protegidos por @can de update y las cuatro rutas mutantes exigen ese mismo permiso. Por ello Almacén no ve los iconos y recibe 403 por URL directa. En la matriz la creación de ajustes manuales depende del permiso distinto stocks.update.

**Archivos que se tocan**

- database/data/access_matrix.php: agregar update a las acciones ALM de stock_entries y stock_exits, conservando el texto raw de la matriz original.
- routes/web.php: actualizar únicamente el comentario de Inventario que afirma que editar o borrar es solo para admin; conservar rutas y middleware.
- docs/roles-permissions-matrix.md: documentar el acceso de Almacén y la excepción para eliminación de ajustes, conservando el permiso independiente de ajuste manual.

**Debe permanecer igual**

- Los controles existentes de resources/views/stocks/entries/index.blade.php y resources/views/stocks/exits/index.blade.php, incluidos @can, confirmaciones, formularios y la prohibición visual de editar ajustes manuales.
- StockEntryController y StockExitController: reglas de validación, transacciones, límites de existencias, eliminación de archivos, registro de auditoría y demás efectos actuales.
- Todas las rutas, verbos, nombres, middleware y permisos existentes; las rutas de edición y eliminación siguen requiriendo stock_entries.update o stock_exits.update.
- Los permisos de los demás roles y las demás acciones de Almacén, en particular no se concede stocks.update ni la creación de ajustes manuales.
- La edición de ajustes manuales continúa prohibida para todos; eliminarlos sigue sujeto al flujo y validaciones actuales.
- No se modifican movimientos históricos ni se crean migraciones, roles, permisos, seeders ni paquetes.

**Riesgos**

- El permiso update existente autoriza tanto editar como eliminar; incluye eliminar movimientos de ajustes manuales. El usuario confirmó expresamente este alcance.
- Eliminar una entrada puede rechazarse si deja existencias negativas; eliminar una entrada de retorno puede reabrir el préstamo asociado. Eliminar un préstamo devuelto también elimina su entrada de retorno vinculada, según el flujo existente.
- AccessMatrixSeeder sincroniza los permisos de todos los roles de la matriz y elimina permisos con punto que ya no están en ella, no solo los dos permisos de este cambio. Revisar la matriz y coordinar su ejecución por entorno.
- Modificar únicamente los iconos no autoriza las rutas; los permisos deben aplicarse al rol mediante el seeder existente.

## 3. Regla

**Actual**

Corregir o borrar entradas y salidas queda reservado a admin; Almacén solo tiene los permisos de consulta, creación y registro declarados en la matriz.

**Nueva**

Añadir update a las acciones de ALM para stock_entries y stock_exits en database/data/access_matrix.php. AccessMatrix y AccessMatrixSeeder suministran los permisos existentes stock_entries.update y stock_exits.update al rol Almacén; los middleware y las condiciones @can actuales habilitan sus rutas e iconos. Ambos permisos también permiten eliminar movimientos de ajustes manuales. No conceder stocks.update ni cambiar los demás permisos.

**Dónde se aplica:** service existente

**Casos afectados**

- Después de aplicar la matriz actualizada y sincronizar el rol, Almacén ve los iconos de editar y eliminar una entrada de compra y una salida definitiva normales, y puede ejecutar sus rutas con datos válidos.
- Almacén puede eliminar un movimiento de ajuste manual de entrada o de salida conforme a las validaciones existentes; no aparece el icono de editarlo y un intento directo de edición conserva el rechazo actual.
- Almacén no obtiene stocks.update y continúa sin poder crear un ajuste manual de existencias.
- Almacén no puede reducir ni eliminar una entrada cuando la validación existente determina que dejaría existencias negativas; tampoco aumentar una salida por encima de las existencias disponibles.
- Editar o eliminar conserva las notificaciones de auditoría existentes. Eliminar una entrada de retorno reabre el préstamo asociado; eliminar un préstamo devuelto también elimina su entrada de retorno vinculada.
- Un usuario sin stock_entries.update o stock_exits.update sigue sin ver los controles de la acción correspondiente y recibe 403 al invocar su ruta directamente. Admin conserva el comportamiento anterior.
- Al ejecutar el seeder repetidamente el rol Almacén conserva ambos permisos update sin duplicados. Los demás roles conservan los permisos declarados en la matriz.

**Datos históricos**

No se modifican movimientos, existencias, archivos ni asignaciones de usuarios por este cambio. Al ejecutar AccessMatrixSeeder en cada entorno se sincronizan los permisos de los roles conforme a la matriz y se limpia la caché de permisos; se deben revisar previamente sus efectos y coordinar su ejecución. No ejecutar el seeder automáticamente durante la implementación ni usar access:migrate-users: los usuarios que ya tienen Almacén reciben sus permisos por el rol.

## 4. Reglas de negocio

- Contexto adicional obligatorio: leer docs/roles-permissions-matrix.md, database/data/access_matrix.php, app/Services/AccessMatrix.php, database/seeders/AccessMatrixSeeder.php, routes/web.php, los dos controllers de movimientos y sus vistas de listado, además de las instrucciones aplicables de .github/instructions/.
- Cambiar únicamente las listas actions de las celdas ALM de stock_entries y stock_exits para incluir update; conservar raw como representación del Excel original y el resto de la matriz. No crear permisos nuevos.
- Mantener los middleware permission:stock_entries.update y permission:stock_exits.update en las rutas existentes PUT/PATCH y DELETE, y los @can correspondientes en ambas vistas. La modificación de la matriz es la fuente de autorización, no un chequeo nuevo por nombre de rol ni una excepción solo visual.
- La autorización también cubre eliminar entradas y salidas de ajuste manual, que usan esas mismas rutas. No habilita su edición: los controllers y las vistas mantienen el rechazo existente. No habilita POST stocks.adjust, que sigue exigiendo stocks.update.
- Conservar el tratamiento existente de compras, salidas definitivas, préstamos, retornos y ajustes; no agregar ni retirar validaciones, restricciones por tipo, lógica de saldo, transacciones o notificaciones.
- Despliegue: después de revisar los efectos de sincronización del seeder, ejecutar php artisan db:seed --class=AccessMatrixSeeder en cada entorno autorizado. El seeder existente limpia la caché de permisos y sincroniza los roles de la matriz. La ejecución en un entorno real requiere autorización del operador.
- Implementación por capas: primero actualizar la matriz y el comentario de rutas; segunda capa sin cambios de datos históricos; tercera capa actualizar la documentación. Tras cada capa, listar archivos y esperar confirmación conforme a las instrucciones del proyecto.

## 5. Validaciones

_Convenciones de docs/controller-structure-and-notifications.md §4._

## 6. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Sustituye la regla de docs/roles-permissions-matrix.md que reserva a admin corregir y borrar movimientos: Almacén también puede editar y eliminar entradas y salidas con los permisos existentes. Ajustar existencias mediante stocks.update permanece reservado como antes.
- La indicación automática del generador en Orden de implementación que dice «sin detenerte entre ellas» no aplica: se solicita confirmación al terminar cada capa, conforme a .github/copilot-instructions.md.

## 7. Preguntas abiertas

- [x] ¿Almacén también puede eliminar movimientos generados por ajustes manuales? → **Sí. La edición y creación de ajustes manuales no se amplían.**

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
- Cambiar el permiso stocks.update, crear ajustes manuales para Almacén o permitir editar movimientos de ajuste.
- Cambiar el comportamiento o la seguridad de los controllers de inventario, las vistas, las rutas o los cálculos de existencias.
- Añadir roles, permisos, migraciones, seeders, pruebas permanentes o paquetes.
- Ejecutar el seeder en una base de datos real sin autorización del operador.
- Implementar el cambio antes de aprobar el spec.
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

Implementa las capas en este orden sin detenerte entre ellas. Al terminar, lista los archivos creados o modificados por capa.

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en un service existente: Añadir update a las acciones de ALM para stock_entries y stock_exits en database/data/access_matrix.php. AccessMatrix y AccessMatrixSeeder suministran los permisos existentes stock_entries.update y stock_exits.update al rol Almacén; los middleware y las condiciones @can actuales habilitan sus rutas e iconos. Ambos permisos también permiten eliminar movimientos de ajustes manuales. No conceder stocks.update ni cambiar los demás permisos.
- [ ] Caso: Después de aplicar la matriz actualizada y sincronizar el rol, Almacén ve los iconos de editar y eliminar una entrada de compra y una salida definitiva normales, y puede ejecutar sus rutas con datos válidos.
- [ ] Caso: Almacén puede eliminar un movimiento de ajuste manual de entrada o de salida conforme a las validaciones existentes; no aparece el icono de editarlo y un intento directo de edición conserva el rechazo actual.
- [ ] Caso: Almacén no obtiene stocks.update y continúa sin poder crear un ajuste manual de existencias.
- [ ] Caso: Almacén no puede reducir ni eliminar una entrada cuando la validación existente determina que dejaría existencias negativas; tampoco aumentar una salida por encima de las existencias disponibles.
- [ ] Caso: Editar o eliminar conserva las notificaciones de auditoría existentes. Eliminar una entrada de retorno reabre el préstamo asociado; eliminar un préstamo devuelto también elimina su entrada de retorno vinculada.
- [ ] Caso: Un usuario sin stock_entries.update o stock_exits.update sigue sin ver los controles de la acción correspondiente y recibe 403 al invocar su ruta directamente. Admin conserva el comportamiento anterior.
- [ ] Caso: Al ejecutar el seeder repetidamente el rol Almacén conserva ambos permisos update sin duplicados. Los demás roles conservan los permisos declarados en la matriz.
- [ ] Datos históricos: No se modifican movimientos, existencias, archivos ni asignaciones de usuarios por este cambio. Al ejecutar AccessMatrixSeeder en cada entorno se sincronizan los permisos de los roles conforme a la matriz y se limpia la caché de permisos; se deben revisar previamente sus efectos y coordinar su ejecución. No ejecutar el seeder automáticamente durante la implementación ni usar access:migrate-users: los usuarios que ya tienen Almacén reciben sus permisos por el rol.
- [ ] Permanece igual: Los controles existentes de resources/views/stocks/entries/index.blade.php y resources/views/stocks/exits/index.blade.php, incluidos @can, confirmaciones, formularios y la prohibición visual de editar ajustes manuales.
- [ ] Permanece igual: StockEntryController y StockExitController: reglas de validación, transacciones, límites de existencias, eliminación de archivos, registro de auditoría y demás efectos actuales.
- [ ] Permanece igual: Todas las rutas, verbos, nombres, middleware y permisos existentes; las rutas de edición y eliminación siguen requiriendo stock_entries.update o stock_exits.update.
- [ ] Permanece igual: Los permisos de los demás roles y las demás acciones de Almacén, en particular no se concede stocks.update ni la creación de ajustes manuales.
- [ ] Permanece igual: La edición de ajustes manuales continúa prohibida para todos; eliminarlos sigue sujeto al flujo y validaciones actuales.
- [ ] Permanece igual: No se modifican movimientos históricos ni se crean migraciones, roles, permisos, seeders ni paquetes.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-009]` en el mensaje.

---

_Generado con Estoria Spec Tool v4 · Estoria Studio_
