---
spec_id: HR-006
type: new-module
variant: workflow
title: Asignaciones de personal a Obra: solicitudes, visto bueno y conflictos
module: Recursos Humanos
module_key: rh_asignaciones
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

# HR-006 · Recursos Humanos — Asignaciones de personal a Obra: solicitudes, visto bueno y conflictos

**Módulo:** Recursos Humanos (`rh_asignaciones`) · **Tipo:** Módulo nuevo (CRUD + Workflow)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Sustituir la pertenencia a una cuadrilla por asignaciones con vigencia trabajador → Obra. El Supervisor o Gerente solicita personal para sus Obras y RH da el visto bueno o resuelve los conflictos. Incluye el alta en obra (pre-registro con NSS y CURP desde campo) y las incongruencias de asignación. Depende de HR-005 y HR-017.

**Flujo de interacción**

Campo: en Asignación el Supervisor filtra por cuadrilla o cargo, elige personas (para operadores filtra la cuadrilla OPERADORES) y arma una solicitud por Obra con "A partir de"; la envía a RH. Puede dar de alta a alguien nuevo con nombre, NSS (11 dígitos) y CURP: se crea un pre-registro con asignación provisional. RH: la bandeja tiene pestañas Pendientes, Conflictos, Altas en obra e Historial (HR-008 y HR-009 agregan Salidas, Descansos, Vacaciones y HE fuera de plazo). En Pendientes, "Visto bueno" aprueba las líneas sin conflicto de una solicitud; las que tienen conflicto pasan a Conflictos, donde RH elige "Asignar aquí", "Dejar donde está" u "Otra Obra…". En Historial RH puede reasignar o anular una asignación aprobada por error.

## 2. Modelos

### PersonnelRequest — tabla `personnel_requests` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `project_work_id` | foreignId | no | FK → project_works.id, restrict on delete |
| `requested_by` | foreignId | no | FK → users.id, restrict on delete |
| `start_date` | date | sí | "A partir de" |
| `status` | string | no | draft, sent, closed · validado con in: · default draft |
| `sent_at` | timestamp | sí |  |

Relaciones:
- `belongsTo` → ProjectWork (`project_work_id`)
- `belongsTo` → User (`requested_by`)
- `hasMany` → PersonnelRequestLine (`personnel_request_id`)

### PersonnelRequestLine — tabla `personnel_request_lines` · Anidado bajo el modelo principal

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `personnel_request_id` | foreignId | no | FK → personnel_requests.id, cascade on delete |
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `status` | string | no | pending, approved, rejected · validado con in: · default pending |
| `resolved_by` | foreignId | sí | FK → users.id, null on delete |
| `resolved_at` | timestamp | sí |  |
| `effective_date` | date | sí | fecha efectiva de la asignación aprobada |
| `note` | text | sí |  |

Relaciones:
- `belongsTo` → PersonnelRequest (`personnel_request_id`)
- `belongsTo` → Worker (`worker_id`)

### WorkAssignment — tabla `work_assignments` · Sin controller (Livewire o solo datos)

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete · índice (worker_id, start_date) |
| `project_id` | foreignId | no | FK → projects.id, restrict on delete |
| `project_work_id` | foreignId | no | FK → project_works.id, restrict on delete |
| `worker_group_id` | foreignId | sí | FK → worker_groups.id, null on delete · cuadrilla al momento, solo historial |
| `start_date` | date | no |  |
| `end_date` | date | sí | null = vigente |
| `personnel_request_id` | foreignId | sí | FK → personnel_requests.id, null on delete |
| `replaced_assignment_id` | foreignId | sí | FK → work_assignments.id, null on delete |
| `provisional` | boolean | no | default false · true en alta en obra hasta completar |
| `approved_by` | foreignId | sí | FK → users.id, null on delete |
| `approved_at` | timestamp | sí |  |
| `closed_reason` | string | sí |  |
| `voided_at` | timestamp | sí | anulación (no se usa softDeletes) |
| `voided_by` | foreignId | sí | FK → users.id, null on delete |
| `void_reason` | text | sí |  |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → Project (`project_id`)
- `belongsTo` → ProjectWork (`project_work_id`)
- `belongsTo` → PersonnelRequest (`personnel_request_id`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/oficina/asignacion`
- **Nombres de ruta:** `recursos-humanos.oficina.asignacion.*`
- **Middleware:** `role:RH jefe|RH ayudante|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/oficina/asignacion` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `request.blade.php` | Blade + Livewire | Asignación del campo (rutas /recursos-humanos/campo/asignacion con role:Supervisor\|Gerente de proyecto\|admin). Componente App\Livewire\Hr\PersonnelRequestBuilder: lista de personal con filtros en vivo por cuadrilla y cargo, selección múltiple y panel "Solicitud por Obra" con "A partir de". Modal "Dar de alta en obra" (nombre, NSS, CURP) con los mensajes de duplicado. Muestra el estado de sus solicitudes. |
| `index.blade.php` | Blade + Livewire | Bandeja de RH. Componente App\Livewire\Hr\AssignmentInbox con pestañas Pendientes (por solicitud con Visto bueno y rechazo), Conflictos (Asignar aquí / Dejar donde está / Otra Obra…), Altas en obra (vencidas en rojo, activar, fusionar duplicado) e Historial (Reasignar…, Anular). Paginación de 25 y filtros con #[Url]. |
| `inconsistencies.blade.php` | Blade | Incongruencias. Lista calculada de Activo sin asignación y Solicitado por más de una Obra, con la acción que corresponde (Asignar / Asignar aquí). HR-010 agrega la tercera clase (Corrección pendiente). |

**Búsqueda / filtros:** Filtros en vivo por Proyecto, Obra, cuadrilla, cargo y búsqueda por nombre o NSS

## 5. Estatus y transiciones

| Valor | Etiqueta | Puede pasar a |
|---|---|---|
| `draft` | Borrador | sent |
| `sent` | Enviada a RH | closed (cuando todas sus líneas dejan de estar pendientes) |
| `closed` | Cerrada |  |

## 6. Reglas de negocio

- Depende de: HR-005 (roles, alcance de campo, HrClock, HrAudit) y HR-017 (workers.worker_group_id y columnas de alta en obra).
- Debe permanecer igual: la base de Trabajadores (expediente, DC3, bajas) y el NSS único. No hay convivencia con el módulo anterior: las membresías de cuadrilla no se migran (HR-005 elimina worker_group_members); todo trabajador activo arranca Sin asignación y aparece en Incongruencias hasta que RH lo asigne o se apruebe una solicitud.
- Livewire: HR-005 instala livewire/livewire y deja la Bitácora como primer componente; este spec monta los siguientes. Livewire 3 inyecta sus assets solos (no se edita layouts/app.blade.php, que es protegido), resources/views/partials/flash.blade.php ya existe desde HR-005 y el listener del evento modal se agrega con @push('scripts') en la vista que monta el componente.
- Un trabajador tiene a lo sumo una asignación vigente (end_date nulo y sin anular). El padrón de una Obra en una fecha son las asignaciones vigentes ese día, compartido por todos los supervisores de la Obra e incluyendo las provisionales.
- Fecha mínima de una asignación = el mayor entre el inicio de la semana activa (viernes) y el día siguiente al último día capturado en otra Obra.
- Hay conflicto cuando el trabajador está asignado a otra Obra o lo pidió otra Obra con una línea pendiente. El visto bueno aprueba solo las líneas sin conflicto, con la mayor fecha entre "a partir de" y la mínima, y envía las demás a Conflictos.
- Aprobar una línea activa el pre-registro, cierra la asignación previa un día antes de la efectiva y, si la persona ya estaba en esa Obra, deja la línea aprobada sin cambios. Una asignación con fecha pasada habilita captura 24 h en la Obra receptora (HR-007).
- Resolver conflicto: "Asignar aquí" rechaza las demás solicitudes de la persona; "Dejar donde está" rechaza esta; "Otra Obra…" asigna a una tercera.
- Anular una asignación aprobada por error exige motivo, anula también los registros de asistencia capturados con ella (se eliminan y quedan en el historial de HR-007) y deja a la persona Sin asignación.
- Alta en obra: sin NSS o CURP no se da el alta; un NSS que no tiene 11 dígitos se rechaza con su motivo; un NSS o CURP ya registrado se bloquea diciendo de quién es; si existe un pre-registro duplicado RH puede fusionar. Se crea un Worker en estado pre_registered con registered_by_user_id, field_registered_at y profile_complete_deadline (7 días) y una asignación provisional. Vencido el plazo se ve en rojo en RH; al activar la persona queda Activa (HR-020 define la marca Expediente incompleto).
- Solo existen dos incongruencias de asignación (calculadas, sin tabla): Activo sin asignación y Solicitado por más de una Obra.
- Supervisor y Gerente solo solicitan personal para sus Obras y solo ven sus solicitudes; el resultado de lo resuelto les llega agrupado en su campana (NotificationService).
- Toda acción se registra con HrAudit (antes, después, motivo) y con NotificationService según docs/controller-structure-and-notifications.md; el campo y RH validan permiso y alcance en el controller (403).
- Riesgo: al no migrar membresías, el primer día todo el personal activo está Sin asignación; RH usa la acción Asignar de Incongruencias (asignación directa con fecha efectiva y motivo, registrada en HrAudit) o aprueba las solicitudes del campo.

## 7. Validaciones

| Campo | Regla |
|---|---|
| `project_work_id` | requerido, exists:project_works, dentro del alcance del usuario |
| `worker_ids` | requerido, array, min:1; cada elemento exists:workers |
| `start_date` | nullable, date, no anterior a la fecha mínima del trabajador |
| `nss` | requerido, digits:11, unique:workers |
| `curp` | requerido, string, size:18, unique:workers |
| `first_name, last_name` | requeridos, string, max:255 |
| `reason (rechazo o anulación)` | requerido, string |

## 8. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- La creación se hace desde componentes Livewire y acciones de bandeja: no hay create() que redirija a index.
- Los controllers viven en App\Http\Controllers\Hr\{Field,Office} y los componentes en App\Livewire\Hr (HR-005).
- Nombres de ruta rh.field.assignments.* y rh.office.assignments.* (en lugar del derivado del prefijo), igual que my-projects.* en Mis proyectos.

## 9. Preguntas abiertas

- [x] ¿Un trabajador puede estar asignado a varias Obras el mismo día o siempre a una (los apoyos se registran aparte en HR-007)? → **Solo a una**
- [x] ¿Quién define el catálogo de cargos para filtrar? Hoy es PositionCategory. → **Se mantiene**
- [x] ¿El Supervisor puede solicitar personal de otro Proyecto o solo personal sin asignación y de su alcance? → **Puede solicitar personal de otro proyecto, cuando RH valida y aprueba se reasigna**
- [x] ¿Cómo se modelan las asignaciones? → **Decidido por el equipo: tabla nueva de asignaciones trabajador → Obra con vigencia, y la cuadrilla queda como atributo del trabajador.**

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
- Descansos, vacaciones, salidas de Obra y HE fuera de plazo (HR-008, HR-009).
- Importación masiva de personal desde el ERP.
- Migrar membresías de cuadrilla a asignaciones: no se migra nada del módulo anterior.
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
- [ ] `PersonnelRequest` declara `$fillable`, `$casts` y las relaciones belongsTo(ProjectWork), belongsTo(User), hasMany(PersonnelRequestLine).
- [ ] `PersonnelRequestLine` declara `$fillable`, `$casts` y las relaciones belongsTo(PersonnelRequest), belongsTo(Worker).
- [ ] `WorkAssignment` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(Project), belongsTo(ProjectWork), belongsTo(PersonnelRequest).
- [ ] `Route::resource('recursos-humanos.oficina.asignacion', PersonnelRequestController::class)` bajo middleware `role:RH jefe|RH ayudante|admin`; `create()` redirige a `recursos-humanos.oficina.asignacion.index`.
- [ ] `PersonnelRequestLine` usa rutas anidadas `recursos-humanos.oficina.asignacion.personnel_request_lines` (shallow) con su propio controller.
- [ ] Existe `resources/views/recursos-humanos/oficina/asignacion/request.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] Existe `resources/views/recursos-humanos/oficina/asignacion/index.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] Existe `resources/views/recursos-humanos/oficina/asignacion/inconsistencies.blade.php` que extiende `layouts.app`.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.oficina.asignacion.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Filtros en vivo por Proyecto, Obra, cuadrilla, cargo y búsqueda por nombre o NSS.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de PersonnelRequest, WorkAssignment registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] El estatus solo cambia por las transiciones declaradas en la tabla de estatus; una transición no permitida regresa con flash `error`.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-006]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
