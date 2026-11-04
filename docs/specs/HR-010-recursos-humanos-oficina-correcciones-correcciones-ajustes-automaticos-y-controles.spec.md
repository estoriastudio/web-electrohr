---
spec_id: HR-010
type: new-module
variant: workflow
title: Correcciones, ajustes automáticos y controles semanales
module: Recursos Humanos
module_key: recursos-humanos_oficina_correcciones
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
  - docs/livewire-conventions.md
open_questions: 0
---

# HR-010 · Recursos Humanos — Correcciones, ajustes automáticos y controles semanales

**Módulo:** Recursos Humanos (`recursos-humanos_oficina_correcciones`) · **Tipo:** Módulo nuevo (CRUD + Workflow)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Permitir que el campo solicite correcciones de incidencias (día cerrado o persona no capturada) y que RH las decida o las haga directamente; generar ajustes automáticos cuando un cambio afecta un periodo ya cerrado, y ofrecer a RH los controles semanales (alertas de patrones y muestra de auditoría de campo). Depende de HR-005, HR-006, HR-007 y HR-008.

**Flujo de interacción**

Campo: en una fila de Asistencias bloqueada (día liberado, descanso rechazado, persona sin captura) el Supervisor elige "Solicitar corrección" con la nueva incidencia y el motivo; para personas no capturadas pide una captura retroactiva de varios días. RH: Incongruencias lista las correcciones pendientes con "Aprobar" o "Rechazar"; en Captura general también puede corregir directo con motivo. Controles semanales muestra las alertas del periodo (se marcan como revisadas con nota) y permite generar una muestra aleatoria de trabajador-días a verificar en campo.

## 2. Modelos

### AttendanceCorrection — tabla `attendance_corrections` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `type` | string | no | change (día cerrado) o retro (persona no capturada) · validado con in: |
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `project_work_id` | foreignId | no | FK → project_works.id, restrict on delete |
| `dates` | json | no | una fecha en change; varias en retro |
| `current_incident` | string | sí | incidencia vigente al pedir (pending si no había) |
| `new_incident` | string | no | incidencia pedida |
| `reason` | text | no |  |
| `status` | string | no | pending, approved, rejected · validado con in: · default pending |
| `requested_by` | foreignId | no | FK → users.id, restrict on delete |
| `resolved_by` | foreignId | sí | FK → users.id, null on delete |
| `resolved_at` | timestamp | sí |  |
| `resolution_note` | text | sí |  |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → ProjectWork (`project_work_id`)
- `belongsTo` → User (`requested_by`)

### PayrollAdjustment — tabla `payroll_adjustments` · Sin controller (Livewire o solo datos)

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `origin_period_start` | date | no | viernes del periodo cerrado que cambió |
| `target_period_start` | date | no | viernes del siguiente periodo abierto donde se aplica |
| `amount` | decimal(10,2) | sí | diferencia con signo; null hasta que HR-013 la calcula; solo la ve quien tiene seeAmounts |
| `concept` | string | no |  |
| `reason` | text | sí |  |
| `source` | string | no | correction (automático) o manual · validado con in: |
| `created_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → Worker (`worker_id`)

### BulkCaptureLog — tabla `bulk_capture_logs` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `project_work_id` | foreignId | no | FK → project_works.id, cascade on delete |
| `date` | date | no |  |
| `incident` | string | no |  |
| `count` | unsignedInteger | no | personas a las que se aplicó |
| `roster_size` | unsignedInteger | no | tamaño del padrón ese día |
| `user_id` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → User (`user_id`)

### WeeklyAlertReview — tabla `weekly_alert_reviews` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `payroll_period_start` | date | no | único junto con alert_key |
| `alert_key` | string | no | p. ej. OT-{worker}, 77-{worker}, CR-{user}, BK-{user} |
| `note` | text | no |  |
| `reviewed_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → User (`reviewed_by`)

### FieldAuditSample — tabla `field_audit_samples` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `payroll_period_start` | date | no |  |
| `worker_attendance_id` | foreignId | sí | FK → worker_attendances.id, null on delete |
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `date` | date | no |  |
| `result` | string | sí | ok o discrepancy · validado con in: |
| `note` | text | sí |  |
| `checked_by` | foreignId | sí | FK → users.id, null on delete |
| `checked_at` | timestamp | sí |  |

Relaciones:
- `belongsTo` → Worker (`worker_id`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/oficina/correcciones`
- **Nombres de ruta:** `recursos-humanos.oficina.correcciones.*`
- **Middleware:** `role:RH jefe|RH ayudante|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/oficina/correcciones` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `field-correction.blade.php` | Blade + Livewire | Solicitar corrección y Captura retroactiva (rutas /recursos-humanos/campo/correcciones con role:Supervisor\|Gerente de proyecto\|admin). Modal del componente AttendanceTable de HR-007 (clase CorrectionRequestForm): incidencia pedida, fechas (una o varias) y motivo obligatorio. La fila bloqueada por descanso rechazado ofrece "Solicitar corrección" aunque sea hoy. |
| `inconsistencies.blade.php` | Blade | Tipo Corrección pendiente. Agrega a Incongruencias (HR-006) la tercera clase "Corrección pendiente" con el detalle "fecha: incidencia actual → pedida", quién la pidió y su motivo; acciones Aprobar o Rechazar. |
| `controls.blade.php` | Blade | Controles semanales. Selector de periodo. Alertas: horas extra todos los días trabajados (4 o más días), 7 de 7 días trabajados, muchas solicitudes de corrección (3 o más por usuario) y 100 % Asistencia en bloque (3 o más fechas). Cada alerta se marca como revisada con nota. Botón "Generar muestra de auditoría" y captura del resultado (verificado o discrepancia) por trabajador-día. |

**Búsqueda / filtros:** Filtros por periodo, Obra, estado y tipo de alerta

## 5. Estatus y transiciones

| Valor | Etiqueta | Puede pasar a |
|---|---|---|
| `pending` | Pendiente | approved \| rejected |
| `approved` | Aprobada |  |
| `rejected` | Rechazada |  |

## 6. Reglas de negocio

- Depende de: HR-005, HR-006 (Incongruencias), HR-007 (captura y BulkCaptureLog) y HR-008 (HE para las alertas).
- Debe permanecer igual: nada del módulo anterior; la edición de asistencias y el cálculo de nómina anteriores ya no existen (HR-005) y los importes de los ajustes los calcula HR-013.
- Hay dos tipos de corrección: change (una fecha ya cerrada o bloqueada) y retro (varias fechas de una persona no capturada). El campo solicita y RH decide; la solicitud guarda incidencia vigente, incidencia pedida y motivo obligatorio.
- Aprobar una corrección fuerza la captura aunque el día esté liberado, escribe AttendanceHistory y bitácora (quién pidió, quién aprobó, motivo) y, si el periodo que paga esa fecha ya está cerrado, genera un ajuste automático.
- Una captura retroactiva crea la asignación correspondiente si la persona no tenía una en esa Obra (con aprobación de RH) y registra los días.
- La corrección directa de RH (HR-007) también genera ajuste automático cuando el periodo está cerrado.
- Ajuste automático: se registra un PayrollAdjustment por persona y periodo cerrado afectado (origin_period_start) con destino en el siguiente periodo abierto de la persona (que no esté cerrado ni autorizado); el importe (diferencia entre la fila viva y la congelada) lo calcula HR-013 y mientras tanto queda null; un periodo autorizado no se modifica.
- Solo existen tres incongruencias diarias: Activo sin asignación, Solicitado por más de una Obra y Corrección pendiente.
- Alertas semanales: HE todos los días trabajados (4 o más trabajados y todos con HE), 7 de 7 días trabajados, muchas solicitudes de corrección (3 o más del mismo usuario en el periodo) y 100 % Asistencia en bloque (3 o más fechas con el padrón completo).
- Cada alerta revisada guarda nota, quién y cuándo. La muestra de auditoría excluye oficinas y toma un máximo de 8 trabajador-días trabajados y anteriores a hoy, de forma reproducible por periodo.
- Los importes de los ajustes solo los ve quien tiene seeAmounts; el RH ayudante ve que existe un ajuste, no su monto.
- Riesgo: una corrección sobre un día con pago anticipado o de un periodo autorizado debe caer en el periodo que paga ese día (payingPeriodOf), no en el de la fecha.

## 7. Validaciones

| Campo | Regla |
|---|---|
| `type` | requerido, in:change,retro |
| `dates` | requerido, array, min:1; cada fecha date anterior o igual a hoy; una sola en change |
| `new_incident` | requerido, in: claves de WorkerAttendance::INCIDENTS |
| `reason` | requerido, string |
| `note (alerta revisada)` | requerido, string |
| `result (auditoría)` | requerido, in:ok,discrepancy |

## 8. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Las solicitudes del campo se envían desde un componente Livewire; las decisiones de RH redirigen con flash success / error / info o usan dispatch('flash').
- payroll_adjustments se crea aquí porque la corrección la necesita; HR-013 la consume en la nómina.
- Los controllers viven en App\Http\Controllers\Hr\{Field,Office}; nombres de ruta rh.field.corrections.*, rh.office.corrections.* y rh.office.controls.*.
- Las vistas viven en resources/views/hr/{field,office}/... en lugar de la carpeta derivada del prefijo.

## 9. Preguntas abiertas

- [x] ¿Quién puede aprobar correcciones sobre un periodo ya revisado por el Revisor: RH jefe, RH ayudante o ambos? → **Ambos**
- [x] ¿Los umbrales de las alertas (4 días, 3 solicitudes, 3 días en bloque, muestra de 8) son configurables o fijos? → **Fijos**
- [x] ¿Qué pasa con una corrección que cae en un periodo ya autorizado? → **Según el prototipo, el periodo autorizado no se modifica y el ajuste va al siguiente periodo abierto.**

## 10. Fuera de alcance

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
- Cálculo del importe del ajuste y su aplicación en nómina (HR-013).
- Alertas configurables por rol o por Proyecto.
- Envío push de notificaciones (se usa el NotificationService existente).
- Modificar archivos protegidos del perfil del proyecto

## 11. Orden de implementación

1. Migraciones (en orden de FKs)
2. Modelos: fillable, casts, relaciones
3. Controller(s) y rutas en routes/web.php
4. Componentes Livewire
5. Vistas Blade y entrada en el menú de navegación

Detente al terminar cada capa y lista los archivos creados o modificados.

## 12. Criterios de aceptación

- [ ] `php artisan migrate` corre sin errores; las migraciones se crean en orden de dependencia de FKs.
- [ ] `AttendanceCorrection` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(ProjectWork), belongsTo(User).
- [ ] `PayrollAdjustment` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker).
- [ ] `BulkCaptureLog` declara `$fillable`, `$casts` y las relaciones belongsTo(User).
- [ ] `WeeklyAlertReview` declara `$fillable`, `$casts` y las relaciones belongsTo(User).
- [ ] `FieldAuditSample` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker).
- [ ] `Route::resource('recursos-humanos.oficina.correcciones', AttendanceCorrectionController::class)` bajo middleware `role:RH jefe|RH ayudante|admin`; `create()` redirige a `recursos-humanos.oficina.correcciones.index`.
- [ ] Existe `resources/views/recursos-humanos/oficina/correcciones/field-correction.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] Existe `resources/views/recursos-humanos/oficina/correcciones/inconsistencies.blade.php` que extiende `layouts.app`.
- [ ] Existe `resources/views/recursos-humanos/oficina/correcciones/controls.blade.php` que extiende `layouts.app`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Filtros por periodo, Obra, estado y tipo de alerta.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de AttendanceCorrection, PayrollAdjustment registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] El estatus solo cambia por las transiciones declaradas en la tabla de estatus; una transición no permitida regresa con flash `error`.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-010]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
