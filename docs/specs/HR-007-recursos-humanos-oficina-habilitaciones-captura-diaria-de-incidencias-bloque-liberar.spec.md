---
spec_id: HR-007
type: new-module
variant: crud
title: Captura diaria de incidencias: bloque, liberar día y habilitación 24 h
module: Recursos Humanos
module_key: recursos-humanos_oficina_habilitaciones
status: implementado
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

# HR-007 · Recursos Humanos — Captura diaria de incidencias: bloque, liberar día y habilitación 24 h

**Módulo:** Recursos Humanos (`recursos-humanos_oficina_habilitaciones`) · **Tipo:** Módulo nuevo (CRUD)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Capturar incidencias diarias (Asistencia, Falta, Descanso, Vacaciones, Libranza A/B/C y sus variantes trabajadas) por el Supervisor o Gerente en su Obra y por RH para cualquier persona, con captura en bloque, día liberado, letra de incentivo semanal, apoyos entre Obras y habilitación temporal de captura. Reemplaza por completo la captura por cuadrilla sí/no del módulo anterior. Depende de HR-005 (que elimina el módulo anterior) y HR-006.

**Flujo de interacción**

Campo: Asistencias muestra el padrón de la Obra elegida y un selector de día (Ayer solo consulta, Hoy captura). Se selecciona a las personas (o a todas con la casilla del encabezado) y se aplica una incidencia en bloque; cada fila permite cambiarla. La letra de incentivo (A/B/C/D/N/A) se define en jueves o en el último día pagado de un pago anticipado. "Liberar incidencias" cierra el día y no avanza mientras haya Pendientes o falte la letra en día de incentivo. Un apoyo (medio día o día completo) indica a qué Obra se carga el costo. RH: Captura general registra a cualquier persona con filtros por Proyecto, Obra, cuadrilla o cargo; un día pasado o bloqueado exige motivo (corrección directa). "Habilitar captura 24 h" abre una fecha de una Obra para el campo.

## 2. Modelos

### CaptureUnlock — tabla `capture_unlocks` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `project_work_id` | foreignId | no | FK → project_works.id, cascade on delete |
| `date` | date | no |  |
| `reason` | text | no |  |
| `granted_by` | foreignId | sí | FK → users.id, null on delete |
| `expires_at` | timestamp | no | 24 h después de otorgarla |
| `active` | boolean | no | default true |

Relaciones:
- `belongsTo` → ProjectWork (`project_work_id`)

### WorkerAttendance — tabla `worker_attendances` · Sin controller (Livewire o solo datos)

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete · único junto con date |
| `date` | date | no | 1 trabajador + 1 fecha = 1 registro |
| `project_work_id` | foreignId | no | FK → project_works.id, restrict on delete · Obra donde se capturó · índice (project_work_id, date) |
| `worker_group_id` | foreignId | sí | FK → worker_groups.id, null on delete · cuadrilla al capturar, solo historial |
| `incident` | string | no | pending, attendance, absence, rest, rest_worked, vacation, vacation_worked, day_off_a, day_off_b, day_off_c · catálogo INCIDENTS en el modelo, validado con in: (no enum de base de datos) |
| `captured_by` | foreignId | sí | FK → users.id, null on delete |
| `captured_at` | timestamp | sí |  |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → ProjectWork (`project_work_id`)
- `hasMany` → AttendanceHistory (`worker_attendance_id`)

### AttendanceDayClosure — tabla `attendance_day_closures` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `project_work_id` | foreignId | no | FK → project_works.id, cascade on delete |
| `date` | date | no | único junto con project_work_id |
| `closed_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → ProjectWork (`project_work_id`)

### AttendanceHistory — tabla `attendance_histories` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_attendance_id` | foreignId | sí | FK → worker_attendances.id, null on delete · sobrevive a la anulación del registro |
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `date` | date | no |  |
| `project_work_id` | foreignId | sí | FK → project_works.id, null on delete |
| `user_id` | foreignId | sí | FK → users.id, null on delete |
| `field` | string | no | Incidencia, Obra, Anulación… |
| `before` | text | sí |  |
| `after` | text | sí |  |
| `reason` | text | sí |  |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → User (`user_id`)

### WeeklyIncentiveLetter — tabla `weekly_incentive_letters` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `payroll_period_start` | date | no | viernes del periodo · único junto con worker_id |
| `project_work_id` | foreignId | no | FK → project_works.id, restrict on delete |
| `category` | string | no | A, B, C, D, NA · validado con in: |
| `set_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → Worker (`worker_id`)

### WorkSupport — tabla `work_supports` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `date` | date | no | único junto con worker_id |
| `from_project_work_id` | foreignId | no | FK → project_works.id, restrict on delete |
| `to_project_work_id` | foreignId | no | FK → project_works.id, restrict on delete · Obra que absorbe el costo |
| `fraction` | string | no | half, full · validado con in: |
| `set_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → Worker (`worker_id`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/oficina/habilitaciones`
- **Nombres de ruta:** `recursos-humanos.oficina.habilitaciones.*`
- **Middleware:** `role:RH jefe|RH ayudante|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/oficina/habilitaciones` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `index.blade.php` | Blade | Habilitar captura 24 h. Lista de habilitaciones activas por Obra y fecha con vencimiento y botón Retirar; formulario en modal para otorgar una nueva con motivo. Las fechas sin liberar aparecen como "Día sin liberar". |
| `field-attendance.blade.php` | Blade + Livewire | Asistencias del campo (rutas /recursos-humanos/campo/asistencias con role:Supervisor\|Gerente de proyecto\|admin). Componente App\Livewire\Hr\AttendanceTable: selector de Obra y de día (Ayer solo consulta, Hoy captura), tabla con casilla de selección, persona, cuadrilla, incidencia, letra de incentivo (solo con seeIncentives), apoyo y columna HE (HR-008). Acciones: aplicar incidencia en bloque y Liberar incidencias. Las filas bloqueadas muestran su motivo (Vacaciones aprobadas, Descanso aprobado, Descanso rechazado = Falta bloqueada con "Solicitar corrección"). |
| `general-capture.blade.php` | Blade + Livewire | Captura general de RH. Mismo componente con filtros por Proyecto, Obra, cuadrilla y cargo sobre cualquier persona. Un día pasado o bloqueado abre un modal con motivo obligatorio. La columna de incentivo no aparece para RH ayudante. |

**Búsqueda / filtros:** Filtros en vivo por Proyecto, Obra, cuadrilla, cargo y nombre

## 5. Reglas de negocio

- Depende de: HR-005 (roles, HrClock, HrAudit y retiro del módulo anterior, que elimina la tabla worker_attendances vieja) y HR-006 (padrón por asignaciones). Este spec crea de nuevo worker_attendances con la estructura del prototipo; no hay backfill ni convivencia con la asistencia anterior.
- Debe permanecer igual: nada del módulo anterior; las pantallas, rutas y exports de asistencia anteriores ya fueron retirados en HR-005 y los incentivos del módulo anterior no se migran. La Libranza y la letra de incentivo viven solo en este módulo.
- Capturar es crear o actualizar un WorkerAttendance desde AttendanceService; los demás modelos de este spec guardan solo lo que rodea a la captura.
- 1 trabajador + 1 fecha = 1 registro vigente; capturar a alguien ya registrado ese día en otra Obra se rechaza con "Ya lo registró X. Si es un error, solicita corrección".
- Catálogo de incidencias (WorkerAttendance::INCIDENTS): pending (P), attendance (A), absence (F), rest (D), rest_worked (DT), vacation (V), vacation_worked (VT), day_off_a|b|c (LA/LB/LC).
- Día normal ofrece Asistencia, Falta y Libranza A/B/C. Con vacaciones aprobadas solo Vacaciones y Vacaciones trabajadas. Con descanso aprobado solo Descanso y Descanso trabajado. Con descanso rechazado el día es una Falta bloqueada y se pide corrección a RH.
- Un día de vacaciones aprobadas, descanso aprobado o descanso rechazado aparece sin captura como registro virtual (no se guarda hasta que se capture).
- El campo captura solo hoy (día no liberado) o una fecha con habilitación activa; Ayer es solo consulta. La captura en bloque no toca a quien tiene vacaciones o descanso rechazado y queda registrada con alerta X de N (HR-010).
- Liberar día se bloquea si hay Pendientes o, en día de incentivo (jueves o último día pagado de un pago anticipado), personas sin letra A/B/C/D/N/A. Cierra la edición del campo.
- La letra de incentivo es semanal por persona; el RH ayudante no la ve (seeIncentives, HR-005).
- Los apoyos son informativos y solo reasignan el costo (HR-016); solo aplican si la incidencia cuenta como trabajada: cambiar a una no trabajada los borra.
- Captura general de RH: hoy sin bloqueo es captura normal; un día pasado o bloqueado exige motivo, es corrección directa (forzada), queda en bitácora y en AttendanceHistory y, si el periodo ya está cerrado, crea un ajuste automático (HR-010/HR-013).
- Habilitación 24 h: la otorga RH por Obra y fecha, vence a las 24 h, se puede retirar y una asignación retroactiva (HR-006) la crea automática. No abre las HE (HR-008).
- Cada cambio de incidencia guarda un AttendanceHistory (quién, antes, después, motivo); anular un registro lo elimina y deja su rastro ahí.
- El valor del día y las fórmulas pertenecen a HR-013; aquí solo se capturan incidencias.
- Los componentes Livewire (App\Livewire\Hr\AttendanceTable) se montan desde la vista Blade que retorna el controller; las mutaciones validan permiso y alcance y registran NotificationService y HrAudit.

## 6. Validaciones

| Campo | Regla |
|---|---|
| `date` | requerido, date; hoy, o fecha con habilitación activa |
| `incident` | requerido, in: claves permitidas para esa persona y fecha |
| `worker_ids (bloque)` | requerido, array, min:1; personas del padrón de la Obra ese día |
| `category (incentivo)` | requerido, in:A,B,C,D,NA |
| `reason (corrección directa y habilitación)` | requerido si el día es pasado o está bloqueado, string |
| `fraction (apoyo)` | nullable, in:half,full; to_project_work_id distinto de la Obra actual |

## 7. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Las acciones de captura son de componentes Livewire; la liberación de día y las habilitaciones usan flash de sesión o dispatch('flash') según docs/livewire-conventions.md.
- El primer modelo (CaptureUnlock) define el prefijo del módulo; la captura del campo vive en /recursos-humanos/campo/asistencias con controller Hr\Field\AttendanceController y nombres rh.field.attendance.* (como my-projects.*).
- Las vistas viven en resources/views/hr/{field,office}/... en lugar de la carpeta derivada del prefijo (HR-005).
- Los controllers viven en App\Http\Controllers\Hr\{Field,Office}; los modelos sin controller (cierres, historial, letras, apoyos) los usa AttendanceService.

## 8. Preguntas abiertas

- [x] ¿La Libranza reemplaza al incentivo de libranza (day_off_exchange) del módulo anterior? (no se migra ningún dato) → **Si**
- [x] ¿La letra de incentivo (A–D) reemplaza al incentivo del módulo anterior? (no se migra ningún dato) → **Si**
- [x] ¿Qué hacemos con Incapacidad y Permiso del módulo anterior y con el documento de ausencia? → **Se eliminan: no se usan. Solo existen las incidencias del catálogo y no hay documento de ausencia.**
- [x] ¿Se exige diseño móvil específico para la captura de campo (escenario D6-09 del prototipo)? → **Si es necesario**
- [x] ¿Qué rol puede otorgar habilitaciones de 24 h: RH jefe, RH ayudante o ambos? → **RH jefe**

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
- Cambios a las demás tablas del módulo anterior: no se migra información.
- Horas extra por día (HR-008), descansos y vacaciones (HR-009), correcciones (HR-010) y cálculo de importes (HR-013).
- Captura sin conexión y sincronización (el prototipo solo la simula).
- Modificar archivos protegidos del perfil del proyecto

## 10. Orden de implementación

1. Migraciones (en orden de FKs)
2. Modelos: fillable, casts, relaciones
3. Controller(s) y rutas en routes/web.php
4. Componentes Livewire
5. Vistas Blade y entrada en el menú de navegación

Detente al terminar cada capa y lista los archivos creados o modificados.

## 11. Criterios de aceptación

- [ ] `php artisan migrate` corre sin errores; las migraciones se crean en orden de dependencia de FKs.
- [ ] `CaptureUnlock` declara `$fillable`, `$casts` y las relaciones belongsTo(ProjectWork).
- [ ] `WorkerAttendance` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(ProjectWork), hasMany(AttendanceHistory).
- [ ] `AttendanceDayClosure` declara `$fillable`, `$casts` y las relaciones belongsTo(ProjectWork).
- [ ] `AttendanceHistory` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(User).
- [ ] `WeeklyIncentiveLetter` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker).
- [ ] `WorkSupport` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker).
- [ ] `Route::resource('recursos-humanos.oficina.habilitaciones', CaptureUnlockController::class)` bajo middleware `role:RH jefe|RH ayudante|admin`; `create()` redirige a `recursos-humanos.oficina.habilitaciones.index`.
- [ ] Existe `resources/views/recursos-humanos/oficina/habilitaciones/index.blade.php` que extiende `layouts.app`.
- [ ] Existe `resources/views/recursos-humanos/oficina/habilitaciones/field-attendance.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] Existe `resources/views/recursos-humanos/oficina/habilitaciones/general-capture.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.oficina.habilitaciones.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Filtros en vivo por Proyecto, Obra, cuadrilla, cargo y nombre.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de CaptureUnlock, WorkerAttendance registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-007]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
