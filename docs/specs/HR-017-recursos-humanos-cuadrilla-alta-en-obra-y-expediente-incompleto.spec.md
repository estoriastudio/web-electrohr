---
spec_id: HR-017
type: ext-fields
title: Cuadrilla, alta en obra y expediente incompleto del trabajador
module: Recursos Humanos
module_key: recursos_humanos
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
open_questions: 0
---

# HR-017 · Recursos Humanos — Cuadrilla, alta en obra y expediente incompleto del trabajador

**Módulo:** Recursos Humanos (`recursos_humanos`) · **Tipo:** C1 · Agregar campos

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`

## 1. Propósito

Preparar la tabla workers (la única base del módulo anterior que se conserva) para el nuevo proceso de asistencia por rol: la cuadrilla pasa a ser un atributo del trabajador y un catálogo simple (sin Obra ni miembros), y se registran los datos del alta en obra (quién la dio, cuándo y el plazo de 7 días para completar el expediente) y la marca de expediente incompleto. Se implementa después de HR-005 y antes de HR-006 y HR-011.

**Flujo de interacción**

RH jefe y RH ayudante administran el catálogo de cuadrillas (nombre, estado y notas) y asignan la cuadrilla desde el formulario del trabajador. La cuadrilla se muestra y filtra en los listados y perfiles de trabajadores; el alta en obra (HR-006) y la activación sin expediente completo (HR-020) llenan las demás columnas.

## 2. Módulo afectado

- **Módulo:** Recursos Humanos
- **Usa Livewire:** no

**Comportamiento actual**

workers no guarda la cuadrilla: se obtenía de worker_group_members (joined_at / left_at) con Worker::currentGroup(), y la Obra de la persona salía de la cuadrilla (worker_groups.project_work_id, obligatorio). WorkerGroupController administra cuadrillas con miembros, Obra y reubicación. HR-005 elimina worker_group_members, WorkerGroupMembershipService y WorkerGroupRelocationService porque el módulo anterior no se usa. No hay datos del alta hecha desde campo ni marca de expediente incompleto; el estado (status) es pre_registered, active o terminated.

**Archivos que se tocan**

- database/migrations/*_add_group_and_field_registration_to_workers_table.php (nueva: 5 columnas nullable o con default e índice en worker_group_id; down quita las columnas)
- database/migrations/*_drop_project_work_from_worker_groups_table.php (nueva: quita worker_groups.project_work_id y su índice; la cuadrilla deja de pertenecer a una Obra)
- app/Models/Worker.php ($fillable y $casts de las columnas nuevas; relación workerGroup(): BelongsTo; se quitan groups(), currentGroup(), currentProjectWork() e isAssignedToResponsibleWork() que dependían de worker_group_members)
- app/Models/WorkerGroup.php (relación workers(): HasMany por worker_group_id; se quita projectWork y members)
- app/Http/Controllers/WorkerGroupController.php (se simplifica a catálogo de cuadrillas: index, store, update y destroy con nombre, estado y notas; se quitan miembros, reubicación y búsqueda de elegibles) y sus rutas human_resources.worker-groups.* para RH jefe, RH ayudante y admin
- resources/views/human_resources/worker-groups/index.blade.php y _form.blade.php (catálogo simple); se eliminan show, edit y attendance de esa carpeta
- app/Http/Controllers/WorkerController.php y resources/views/human_resources/workers/_form.blade.php, index.blade.php y show.blade.php (selector de cuadrilla en el formulario, columna y filtro por cuadrilla en el listado, etiqueta de cuadrilla en el perfil)

**Debe permanecer igual**

- La base de Trabajadores completa (workers, worker_files, worker_dc3s, worker_terminations, position_categories) y sus pantallas, expediente, DC3 y bajas.
- El NSS como identificador único del trabajador (migración 2026_09_03_000001) y el estado (pre_registered, active, terminated).
- La tabla worker_groups como catálogo de cuadrillas (nombre, estado, notas); workers.project_work_id no se usa en el nuevo proceso pero no se toca.

**Riesgos**

- Se quita worker_groups.project_work_id: ningún código nuevo debe leerlo; buscar con grep antes de aplicar (PayrollCostReportService y las pantallas de cuadrillas viejas ya se eliminan en HR-005).
- Las cuadrillas existentes quedan sin miembros: todos los trabajadores arrancan con worker_group_id en NULL y RH debe asignar la cuadrilla (decisión del equipo: no hay migración ni convivencia).
- Cambiar WorkerController y sus vistas puede afectar la importación y exportación de trabajadores (WorkerExport, WorkerPayrollImport): verificar que no escriben ni leen las relaciones de cuadrilla eliminadas.

## 3. Campos nuevos

Tabla: `workers`

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_group_id` | foreignId | sí | FK → worker_groups.id, null on delete · cuadrilla del trabajador · indexado · queda NULL en los trabajadores existentes (no se migran membresías) |
| `registered_by_user_id` | foreignId | sí | FK → users.id, null on delete · quién dio el alta en obra (HR-006) |
| `field_registered_at` | timestamp | sí | cuándo se dio el alta en obra |
| `profile_complete_deadline` | date | sí | 7 días después del alta en obra |
| `file_incomplete` | boolean | no | default false · true cuando RH activa a la persona sin expediente completo (HR-020) |

## 4. Registros existentes

- **Estrategia:** columnas nullable, sin rellenar

## 5. Impacto

- [x] Validación store/update
- [x] Formularios crear/editar
- [x] Columnas del listado
- [x] Filtros del listado
- [x] Vista de detalle
- [ ] Plantilla de importación/exportación

## 6. Reglas de negocio

- workers.worker_group_id es la cuadrilla vigente de la persona; una persona tiene una sola cuadrilla o ninguna.
- La cuadrilla es un catálogo de etiquetas (OPERADORES, Los Rayos…) que no determina la Obra: la Obra de una persona sale de su asignación vigente (work_assignments, HR-006).
- No hay backfill: las membresías del módulo anterior no se migran y la cuadrilla de todos los trabajadores existentes arranca en NULL.
- profile_complete_deadline = field_registered_at + 7 días; si venció y file_incomplete es true, RH lo ve en rojo (HR-006).
- file_incomplete solo lo marca la activación sin expediente completo (HR-020) y se limpia cuando el expediente tiene los documentos obligatorios.

## 7. Validaciones

| Campo | Regla |
|---|---|
| `worker_group_id` | nullable, exists:worker_groups,id |
| `registered_by_user_id` | nullable, exists:users,id (solo lo asigna el sistema, no el formulario) |
| `profile_complete_deadline` | nullable, date (solo lo asigna el sistema) |
| `file_incomplete` | boolean (solo lo asigna el sistema) |
| `name (cuadrilla)` | requerido, string, max:255, unique:worker_groups,name |

## 8. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Se modifica worker_groups (tabla existente) en una segunda migración dentro de este mismo spec, porque la cuadrilla deja de depender de una Obra; es parte del mismo cambio y no un spec aparte.
- Se simplifica WorkerGroupController (existente, no protegido) a catálogo; sus notificaciones de WorkerGroup se conservan.

## 9. Preguntas abiertas

- [x] ¿WorkerGroupMembershipService (alta y baja de miembros de una cuadrilla) debe mantener workers.worker_group_id sincronizado mientras convivan las pantallas viejas? → **No habrá convivencia de pantallas viejas**
- [x] ¿La cuadrilla sigue siendo un catálogo editable desde la pantalla actual de cuadrillas o se administra aparte? → **Si**

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
- Alta en obra, asignaciones y bandeja de RH (HR-006).
- Activar sin expediente completo y permisos del perfil (HR-020).
- Migrar membresías de cuadrilla o cualquier dato del módulo anterior.
- Modificar archivos protegidos del perfil del proyecto

## 11. Orden de implementación

1. Migración nueva
2. Modelo: fillable y casts
3. Validación en store/update
4. Vistas afectadas

Detente al terminar cada capa y lista los archivos creados o modificados.

## 12. Criterios de aceptación

- [ ] Se agrega una migración nueva sobre `workers`; no se edita ninguna migración existente.
- [ ] Las columnas nuevas son nullable; los registros existentes quedan en NULL sin error.
- [ ] El modelo actualiza `$fillable` y `$casts` con las columnas nuevas.
- [ ] store/update validan las columnas nuevas (nullable declarado explícitamente).
- [ ] Los formularios de crear y editar incluyen los campos nuevos.
- [ ] El listado muestra las columnas nuevas.
- [ ] El listado permite filtrar por los campos nuevos.
- [ ] El detalle muestra los campos nuevos.
- [ ] Permanece igual: La base de Trabajadores completa (workers, worker_files, worker_dc3s, worker_terminations, position_categories) y sus pantallas, expediente, DC3 y bajas.
- [ ] Permanece igual: El NSS como identificador único del trabajador (migración 2026_09_03_000001) y el estado (pre_registered, active, terminated).
- [ ] Permanece igual: La tabla worker_groups como catálogo de cuadrillas (nombre, estado, notas); workers.project_work_id no se usa en el nuevo proceso pero no se toca.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-017]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
