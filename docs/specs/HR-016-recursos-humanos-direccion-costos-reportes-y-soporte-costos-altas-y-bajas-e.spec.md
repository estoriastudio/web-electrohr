---
spec_id: HR-016
type: new-module
variant: listing
title: Reportes y soporte: costos, altas y bajas e integridad
module: Recursos Humanos
module_key: recursos-humanos_direccion_costos
status: aprobado
author: Jorge Peña
reviewers: [Mariam]
branch: moon
created: 2026-11-03
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/ui-kit-design-system.md
  - docs/ui-kit-table-structure.md
open_questions: 0
---

# HR-016 · Recursos Humanos — Reportes y soporte: costos, altas y bajas e integridad

**Módulo:** Recursos Humanos (`recursos-humanos_direccion_costos`) · **Tipo:** Módulo nuevo (Solo listado)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`

## 1. Propósito

Completar el proceso con los reportes y herramientas de soporte: costos de mano de obra por Gerente, Proyecto, Obra y cuadrilla (con los apoyos), reporte dinámico de altas y bajas, verificación de integridad, puntos de recuperación automáticos al cerrar y autorizar. Depende de HR-005 a HR-015.

**Flujo de interacción**

Admin (Dirección) y RH jefe abren Costos y eligen un rango de periodos y una agrupación (Gerente, Proyecto, Obra, cuadrilla); el apoyo de medio día o día completo mueve el costo de una Obra a otra. Movimientos (Altas y bajas) muestra por semana las altas (contratación o alta en obra) y bajas con totales y detalle, agrupables por Proyecto, Obra, cuadrilla, cargo o motivo. Integridad muestra las verificaciones y los puntos de recuperación guardados al cerrar Proyectos y autorizar.

## 2. Modelos

### RecoveryPoint — tabla `recovery_points`

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `label` | string | no | p. ej. Cierre de RH: Proyecto 1805 · 11/09–17/09/2026 |
| `kind` | string | no | close, authorize, manual · validado con in: |
| `period_start` | date | sí |  |
| `payload` | json | no | instantánea de las filas congeladas, movimientos de préstamo y de caja de ahorro y autorización relacionados |
| `checksum` | string | no | hash del payload para detectar alteraciones |
| `created_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → User (`created_by`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/direccion/costos`
- **Nombres de ruta:** `recursos-humanos.direccion.costos.*`
- **Middleware:** `role:RH jefe|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/direccion/costos` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `index.blade.php` | Blade | Costos de mano de obra (role:RH jefe\|admin, seeAmounts). Rango de periodos con atajos (semana actual, mes, últimas N semanas cerradas), agrupación por Gerente, Proyecto, Obra o cuadrilla, totales con el apoyo ya reasignado y exportación CSV. Solo muestra periodos cerrados por RH. |
| `show.blade.php` | Blade | Altas y bajas (role:RH jefe\|RH ayudante\|admin; la verificación de integridad y los puntos de recuperación son de role:RH jefe\|admin con permiso backups). Reporte por semana con altas (+), bajas (−) y neto; detalle por persona con fecha, Proyecto, Obra, cuadrilla, cargo y motivo (Contratación (ERP), Alta en obra o el motivo de la baja); agrupable y filtrable; los totales cuadran con el detalle; CSV. |

**Búsqueda / filtros:** Rango de periodos, agrupación y filtros por Proyecto, Obra, cuadrilla, cargo y motivo

## 5. Reglas de negocio

- Depende de: HR-005 a HR-015. Este es el último spec de la iniciativa y solo consulta datos de los anteriores; salvo RecoveryPoint, no crea tablas de negocio.
- Es un módulo de listado: solo index y show de Costos y Altas y bajas (sin store, update ni destroy). La verificación de integridad y los puntos de recuperación se atienden con acciones de solo consulta o generación, declaradas como excepción.
- Debe permanecer igual: nada del módulo anterior; el retiro de sus pantallas, rutas, controllers y tablas se hizo en HR-005 y no hay datos históricos que consultar ni migrar.
- Costos solo de filas congeladas (Cerrado por RH) y autorizadas; los periodos abiertos no aparecen. Los apoyos reasignan el costo del día de la Obra de origen a la de destino en la fracción indicada (medio día o día completo). El reporte muestra la diferencia contra el total de nómina.
- Costos por Gerente: suma de las Obras de los Proyectos del Gerente (Project.manager_user_id); debe cuadrar con el personal asignado a esas Obras.
- Altas y bajas: alta = día de inicio (fecha de contratación o inicio del alta en obra); baja = terminación registrada y no cancelada. Cada línea guarda Proyecto, Obra, cuadrilla, cargo y motivo para poder agrupar. RH ayudante lo ve sin datos de pago.
- Integridad: préstamos con saldo reconstruido desde sus movimientos (nunca negativo, reversos válidos, abonos solo en Proyectos cerrados); caja de ahorro sin saldo negativo y con movimientos solo en Proyectos cerrados; nóminas cerradas cuyas filas suman su total; periodos autorizados que coinciden con lo cerrado y con el checksum de su archivo de dispersión.
- Puntos de recuperación: automáticos al cerrar Proyecto (HR-013) y al autorizar (HR-014), con checksum; se guardan los últimos 3 por tipo. Un punto alterado (checksum distinto) se marca y se rechaza. Restaurar solo lo hace admin, en transacción, con confirmación y bitácora (pregunta abierta 1).
- Se modifican PayrollStageService y PayrollAuthorizationService (HR-013 y HR-014) solo para registrar los puntos de recuperación.
- Toda acción de soporte (generar, verificar, restaurar) queda en bitácora; las que incluyen importes se marcan sensitive.
- Riesgo: el respaldo real de la base de datos y de S3 es infraestructura y no vive en el módulo; este spec solo cubre integridad y puntos de recuperación lógicos.

## 6. Validaciones

| Campo | Regla |
|---|---|
| `from_period, to_period` | requeridos, date, viernes; from_period menor o igual a to_period |
| `group_by` | requerido, in:manager,project,site,crew |
| `type (movimientos)` | nullable, in:alta,baja |
| `recovery_point (restaurar)` | exists:recovery_points y checksum válido; solo admin |

## 7. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Controllers Hr\Director\CostReportController, Hr\Shared\MovementController y Hr\Director\IntegrityController; nombres de ruta rh.director.costs.*, rh.shared.movements.* y rh.director.integrity.*; las vistas viven en resources/views/hr/ en lugar de la carpeta derivada del prefijo (HR-005).
- Además de index y show, la verificación de integridad (POST verify) y la generación de puntos de recuperación (POST) son acciones de soporte sin formulario de crear; los reportes exportan CSV (o .xlsx con maatwebsite/excel, ya en el perfil).
- Se modifican PayrollStageService y PayrollAuthorizationService de HR-013 y HR-014 solo para registrar los puntos de recuperación.

## 8. Preguntas abiertas

- [x] ¿Restaurar puntos de recuperación debe existir en el sistema o basta con consultar y verificar (el respaldo de la base lo hace infraestructura)? → **Punto de recuperación refiere que cuando se haga una acción se pueda cancelar el movimiento.**
- [x] ¿Se conserva el destajo (PieceworkWeeklyEntry) dentro del nuevo proceso de nómina? → **No: el destajo y el resto del módulo anterior no se usan y se eliminan en HR-005.**
- [x] ¿El costo por apoyo se reparte en cualquier fracción o solo medio día y día completo? → **Según el prototipo, solo medio día y día completo. Por confirmar.**

## 9. Fuera de alcance

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
- Respaldos de infraestructura de la base de datos y de S3.
- Reportes fiscales, IMSS e Infonavit.
- Modificar archivos protegidos del perfil del proyecto

## 10. Orden de implementación

1. Migraciones (en orden de FKs)
2. Modelos: fillable, casts, relaciones
3. Controller(s) y rutas en routes/web.php
4. Vistas Blade y entrada en el menú de navegación

Detente al terminar cada capa y lista los archivos creados o modificados.

## 11. Criterios de aceptación

- [ ] `php artisan migrate` corre sin errores; las migraciones se crean en orden de dependencia de FKs.
- [ ] `RecoveryPoint` declara `$fillable`, `$casts` y las relaciones belongsTo(User).
- [ ] Solo existen las rutas `recursos-humanos.direccion.costos.index` y `recursos-humanos.direccion.costos.show` bajo middleware `role:RH jefe|admin`. No existen store/update/destroy.
- [ ] Existe `resources/views/recursos-humanos/direccion/costos/index.blade.php` que extiende `layouts.app`.
- [ ] Existe `resources/views/recursos-humanos/direccion/costos/show.blade.php` que extiende `layouts.app`.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.direccion.costos.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Rango de periodos, agrupación y filtros por Proyecto, Obra, cuadrilla, cargo y motivo.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-016]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
