---
spec_id: HR-009
type: new-module
variant: workflow
title: Descansos, vacaciones y salidas de Obra
module: Recursos Humanos
module_key: recursos-humanos_oficina_descansos
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

# HR-009 · Recursos Humanos — Descansos, vacaciones y salidas de Obra

**Módulo:** Recursos Humanos (`recursos-humanos_oficina_descansos`) · **Tipo:** Módulo nuevo (CRUD + Workflow)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Pasar de altas de vacaciones hechas solo por RH a un flujo campo → RH: el Supervisor o Gerente propone descansos, solicita vacaciones por persona o cuadrilla y avisa cuando alguien "ya no está en mi Obra"; RH resuelve en bloque viendo el último descanso de cada persona. Reemplaza por completo el módulo anterior de vacaciones (sin migración). Depende de HR-005, HR-006 y HR-007.

**Flujo de interacción**

Campo: en Mis trabajadores (o desde la selección de Asistencias) se elige a una o varias personas o una cuadrilla y se usa "Proponer descanso" (días de hoy en adelante), "Solicitar vacaciones" (el diálogo muestra los días tomados en el año de cada persona) o "Ya no está en mi Obra" (último día en la Obra y motivo). RH: las pestañas Descansos, Vacaciones y Salidas de Obra de la bandeja muestran cada solicitud con sus avisos; RH aprueba o rechaza en bloque y, en Salidas, elige reasignar, registrar baja, dejar sin asignación o rechazar.

## 2. Modelos

### RestRequest — tabla `rest_requests` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `project_work_id` | foreignId | no | FK → project_works.id, restrict on delete |
| `dates` | json | no | días propuestos (array de fechas) · casteado a array |
| `approved_dates` | json | sí | días aprobados (los que seguían siendo aprobables) |
| `rejected_dates` | json | sí | días de una propuesta rechazada que quedan como Falta |
| `reason` | text | sí |  |
| `status` | string | no | pending, approved, rejected, cancelled · validado con in: · default pending; "Vencido" se calcula cuando no quedan días aprobables |
| `proposed_by` | foreignId | no | FK → users.id, restrict on delete |
| `resolved_by` | foreignId | sí | FK → users.id, null on delete |
| `resolved_at` | timestamp | sí |  |
| `note` | text | sí |  |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → ProjectWork (`project_work_id`)
- `belongsTo` → User (`proposed_by`)

### ReleaseRequest — tabla `release_requests` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `work_assignment_id` | foreignId | no | FK → work_assignments.id, cascade on delete · asignación vigente al avisar |
| `last_day` | date | no | último día en la Obra; no futuro |
| `reason_code` | string | no | moved (Se fue a otra Obra), quit (Renunció o dejó de venir), other · validado con in: |
| `note` | text | sí |  |
| `status` | string | no | pending, reassigned, terminated, unassigned, rejected · validado con in: · default pending; "obsolete" se calcula si la asignación ya no es vigente |
| `requested_by` | foreignId | no | FK → users.id, restrict on delete |
| `resolved_by` | foreignId | sí | FK → users.id, null on delete |
| `resolved_at` | timestamp | sí |  |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → WorkAssignment (`work_assignment_id`)
- `belongsTo` → User (`requested_by`)

### WorkerVacation — tabla `worker_vacations` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `start_date` | date | no |  |
| `end_date` | date | no |  |
| `total_days` | unsignedInteger | no |  |
| `status` | string | no | requested, approved, taken, rejected, cancelled · validado con in: · default requested; taken (Tomada) la asigna RH a mano |
| `notes` | text | sí | motivo o nota de la solicitud |
| `requested_by` | foreignId | no | FK → users.id, restrict on delete |
| `resolved_by` | foreignId | sí | FK → users.id, null on delete |
| `resolved_at` | timestamp | sí |  |
| `resolution` | text | sí | nota de RH al aprobar, rechazar o cancelar |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → User (`requested_by`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/oficina/descansos`
- **Nombres de ruta:** `recursos-humanos.oficina.descansos.*`
- **Middleware:** `role:RH jefe|RH ayudante|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/oficina/descansos` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `field-actions.blade.php` | Blade + Livewire | Proponer descanso, Solicitar vacaciones y Ya no está en mi Obra (rutas /recursos-humanos/campo con role:Supervisor\|Gerente de proyecto\|admin). Componente App\Livewire\Hr\WorkerRequestDialogs con tres modales sobre la selección de personas o la cuadrilla. Descanso: cada persona muestra su último descanso (fecha, Obra y hace cuántos días) y no permite días anteriores a hoy. Vacaciones: muestra los días tomados en el año de cada persona y rechaza fechas pasadas con su motivo. Salida: pide último día y motivo y rechaza duplicados. |
| `index.blade.php` | Blade + Livewire | Pestañas Descansos, Vacaciones y Salidas de la bandeja de RH. Componentes RestInbox, VacationInbox y ReleaseInbox dentro de la bandeja de HR-006. Descansos: tabla con último descanso, estado (Por aprobar, Aprobado, Rechazado, Vencido, Cancelado por baja) y aprobación o rechazo en bloque. Vacaciones: avisos en rojo (traslape, fecha pasada, día capturado) y sin botón Aprobar si hay aviso; en bloque solo se aplican las que no tienen aviso y el resumen dice por qué las demás no. Salidas: Reasignar, Registrar baja, Dejar sin asignación o Rechazar. |

**Búsqueda / filtros:** Filtros por tipo, estado, Obra y persona

## 5. Estatus y transiciones

| Valor | Etiqueta | Puede pasar a |
|---|---|---|
| `pending` | Por aprobar | approved \| rejected \| cancelled |
| `approved` | Aprobado | cancelled (por baja) |
| `rejected` | Rechazado |  |
| `cancelled` | Cancelado por baja |  |

## 6. Reglas de negocio

- Depende de: HR-005 (que elimina worker_vacations y WorkerVacationController del módulo anterior), HR-006 (padrón y asignaciones) y HR-007 (captura bloqueada por descansos y vacaciones). Este spec crea de nuevo worker_vacations con estados requested, approved, taken, rejected y cancelled (sin backfill); taken (Tomada) la marca RH a mano.
- Debe permanecer igual: el registro de bajas de Trabajadores (WorkerTerminationController y WorkerTerminationService) como destino de "Registrar baja". No hay convivencia con el módulo anterior: las vacaciones anteriores no se migran y sus rutas human_resources.worker-vacations.* ya no existen.
- Descansos: el campo propone y RH aprueba o rechaza; solo se proponen días de hoy en adelante, de personas del padrón de la Obra ese día, sin captura, sin vacaciones y sin otro descanso pendiente o aprobado.
- Solo los días aprobados permiten capturar Descanso o Descanso trabajado; una propuesta rechazada deja como Falta bloqueada sus días aún aprobables (HR-007).
- Una propuesta Por aprobar sin días aprobables se muestra Vencida y no se puede resolver.
- Al decidir un descanso RH ve el último descanso de la persona (fecha, Obra, días trabajados desde entonces y próximo descanso); no hay mínimo de días entre descansos.
- Vacaciones: se solicitan desde hoy en adelante, sin traslape con otras vacaciones, sin días ya capturados ni descansos aprobados. RH aprueba o rechaza en bloque; solo se cancelan las aprobadas que no han empezado.
- Las vacaciones aprobadas precargan sus días (registro virtual Vacaciones) y bloquean la captura a Vacaciones o Vacaciones trabajadas.
- Salida de Obra: el último día no puede ser futuro ni anterior a la llegada; la persona sigue en el padrón y se captura normalmente hasta que RH resuelve; si su asignación se resolvió por otra vía la salida queda "Ya se movió por otra vía"; un segundo aviso de la misma persona se rechaza.
- La fecha efectiva de cierre de una salida es el último día reportado o el último día ya capturado en esa Obra, el que sea posterior; "Dejar sin asignación" manda a la persona a Incongruencias (HR-006).
- Cuando se registra una baja, se cancelan los descansos y vacaciones posteriores (WorkerTerminationService se modifica solo para esto, en una transacción con la baja): quedan "Cancelado por baja" / "Cancelada".
- El campo solo opera personal asignado a sus Obras; el resultado de lo resuelto llega agrupado en su campana (NotificationService) y toda acción se registra con HrAudit.
- Riesgo: aprobar vacaciones precarga días bloqueados en la captura; validar traslapes y días capturados antes de aprobar y al aprobar en bloque.

## 7. Validaciones

| Campo | Regla |
|---|---|
| `dates (descanso)` | requerido, array, min:1; cada fecha date y no anterior a hoy |
| `start_date, end_date (vacaciones)` | requeridos, date; end_date mayor o igual a start_date; start_date no anterior a hoy |
| `last_day (salida)` | requerido, date, no futuro ni anterior al inicio de la asignación |
| `reason_code (salida)` | requerido, in:moved,quit,other |
| `ids (resolución en bloque)` | requerido, array; cada elemento exists en su tabla |
| `note (rechazo o baja)` | nullable en aprobación; requerido en rechazo |

## 8. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Las resoluciones masivas responden con dispatch('flash') y resumen (docs/livewire-conventions.md).
- Se modifica WorkerTerminationService (existente, no protegido) solo para cancelar descansos y vacaciones al dar de baja.
- Los controllers del campo viven en App\Http\Controllers\Hr\Field y los componentes en App\Livewire\Hr; nombres de ruta rh.field.rests.*, rh.field.releases.* y rh.office.rests.*.
- Las vistas viven en resources/views/hr/{field,office}/... en lugar de la carpeta derivada del prefijo.

## 9. Preguntas abiertas

- [x] ¿Se exige un mínimo de días trabajados entre descansos? → **No: el prototipo solo muestra el último descanso y RH decide (sin mínimo).**
- [x] ¿Las vacaciones se descuentan de un saldo anual por persona o solo se informan los días tomados? → **Mas adelante tenemos nuestro generador de nomina y se contabiliza diferente el valor de vacaciones**
- [x] ¿Quién puede resolver salidas de Obra que terminan en baja: RH jefe, RH ayudante o ambos? → **Ambos**

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
- Migrar vacaciones del módulo anterior: no se migra información.
- Saldo anual de vacaciones y su cálculo legal.
- Integración real con el módulo de vacaciones del ERP (el prototipo la simula).
- Registrar la baja desde cero: se reutiliza WorkerTerminationController.
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
- [ ] `RestRequest` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(ProjectWork), belongsTo(User).
- [ ] `ReleaseRequest` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(WorkAssignment), belongsTo(User).
- [ ] `WorkerVacation` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(User).
- [ ] `Route::resource('recursos-humanos.oficina.descansos', RestRequestController::class)` bajo middleware `role:RH jefe|RH ayudante|admin`; `create()` redirige a `recursos-humanos.oficina.descansos.index`.
- [ ] Existe `resources/views/recursos-humanos/oficina/descansos/field-actions.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] Existe `resources/views/recursos-humanos/oficina/descansos/index.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.oficina.descansos.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Filtros por tipo, estado, Obra y persona.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de RestRequest, ReleaseRequest, WorkerVacation registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] El estatus solo cambia por las transiciones declaradas en la tabla de estatus; una transición no permitida regresa con flash `error`.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-009]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
