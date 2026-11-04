---
spec_id: HR-008
type: new-module
variant: workflow
title: Horas extra: ventana de las 10:00 y HE fuera de plazo
module: Recursos Humanos
module_key: recursos-humanos_oficina_horas-extra
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

# HR-008 · Recursos Humanos — Horas extra: ventana de las 10:00 y HE fuera de plazo

**Módulo:** Recursos Humanos (`recursos-humanos_oficina_horas-extra`) · **Tipo:** Módulo nuevo (CRUD + Workflow)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Capturar las horas extra (HE) de cada día al día siguiente, hasta las 10:00 (hora America/Mexico_City), sin importes, y gestionar las que llegan tarde mediante una solicitud a RH persona por persona. Depende de HR-005 y HR-007.

**Flujo de interacción**

Campo: en Asistencias, con el selector en Ayer, la columna HE está abierta hasta hoy a las 10:00 (las del jueves, hasta el viernes a las 10:00). Se capturan HE por persona o en bloque y "Liberar horas extra" cierra la captura antes de la hora. Pasadas las 10:00 la columna se cierra con el aviso del plazo y cada fila ofrece "HE fuera de plazo…" (horas y motivo obligatorio). RH: la pestaña "HE fuera de plazo" de la bandeja lista las solicitudes (sin importes); se aprueban o rechazan una o varias a la vez con una nota.

## 2. Modelos

### LateOvertimeRequest — tabla `late_overtime_requests` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `project_work_id` | foreignId | no | FK → project_works.id, restrict on delete |
| `date` | date | no |  |
| `hours` | unsignedInteger | no | horas enteras (1 a 16) |
| `reason` | text | no |  |
| `status` | string | no | pending, approved, rejected · validado con in: · default pending |
| `requested_by` | foreignId | no | FK → users.id, restrict on delete |
| `resolved_by` | foreignId | sí | FK → users.id, null on delete |
| `resolved_at` | timestamp | sí |  |
| `note` | text | sí |  |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → ProjectWork (`project_work_id`)
- `belongsTo` → User (`requested_by`)

### OvertimeEntry — tabla `overtime_entries` · Sin controller (Livewire o solo datos)

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `date` | date | no | único junto con worker_id |
| `project_work_id` | foreignId | no | FK → project_works.id, restrict on delete · donde trabajó |
| `charge_project_work_id` | foreignId | no | FK → project_works.id, restrict on delete · Obra de cargo |
| `hours` | unsignedInteger | no | horas enteras (1 a 16) |
| `source` | string | no | field, hr, late · validado con in: |
| `set_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → ProjectWork (`project_work_id`)

### OvertimeClosure — tabla `overtime_closures` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `project_work_id` | foreignId | no | FK → project_works.id, cascade on delete |
| `date` | date | no | único junto con project_work_id |
| `closed_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → ProjectWork (`project_work_id`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/oficina/horas-extra`
- **Nombres de ruta:** `recursos-humanos.oficina.horas-extra.*`
- **Middleware:** `role:RH jefe|RH ayudante|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/oficina/horas-extra` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `field-overtime.blade.php` | Blade + Livewire | Columna HE de Asistencias (rutas /recursos-humanos/campo/horas-extra con role:Supervisor\|Gerente de proyecto\|admin). Parte del componente AttendanceTable de HR-007. Con el día Ayer y la ventana abierta, celda de horas editable, captura en bloque y botón "Liberar horas extra" con el texto "Hasta hoy a las 10:00". Con la ventana cerrada, la columna muestra el aviso del plazo y cada fila el botón "HE fuera de plazo…" (modal con horas y motivo). |
| `index.blade.php` | Blade + Livewire | Pestaña HE fuera de plazo de RH. Componente App\Livewire\Hr\LateOvertimeInbox dentro de la bandeja de HR-006: tabla de solicitudes pendientes (persona, Obra, fecha, horas, motivo, quién pidió) con selección múltiple y acciones Aprobar / Rechazar con nota; resumen de resultados al terminar. Sin importes. |

**Búsqueda / filtros:** Filtros por estado, Obra, fecha y persona

## 5. Estatus y transiciones

| Valor | Etiqueta | Puede pasar a |
|---|---|---|
| `pending` | Por aprobar | approved \| rejected |
| `approved` | Aprobada |  |
| `rejected` | Rechazada |  |

## 6. Reglas de negocio

- Depende de: HR-005 (HrClock, hr_settings.ot_deadline_hour, HrAudit) y HR-007 (la columna HE vive en la captura diaria).
- Debe permanecer igual: el campo no ve ni captura importes y la habilitación 24 h de HR-007 abre incidencias, no HE. No hay convivencia con el módulo anterior: los incentivos y HE anteriores no se migran ni alimentan nada; overtime_entries es el único origen de las HE (HR-013 las valúa).
- Solo se capturan las HE del día anterior, hasta ot_deadline_hour (10:00, America/Mexico_City) de hoy o hasta que alguien libere las HE de esa Obra y fecha.
- Solicitud fuera de plazo: persona del alcance del usuario, horas mayores a 0 y hasta 16, fecha anterior a hoy, ventana cerrada, persona en el padrón de esa Obra ese día, motivo obligatorio y una sola solicitud pendiente por persona y día.
- Aprobar registra las HE con source = late, deja en bitácora quién pidió, quién aprobó y por qué y, si el periodo ya está cerrado, crea un ajuste automático (HR-010/HR-013). Rechazar solo deja bitácora.
- La aprobación en bloque procesa una por una; el resumen indica cuántas se aplicaron y por qué falló cada otra.
- RH puede capturar HE directamente (source = hr) con motivo, también en bitácora.
- La pestaña de RH y las solicitudes no muestran importes; el RH ayudante puede resolverlas.
- Las horas son enteras (1 a 16; decisión del equipo: sin fracciones y el tope de 16 h es regla de negocio).
- Los componentes Livewire validan permiso y alcance y registran NotificationService y HrAudit en cada mutación.

## 7. Validaciones

| Campo | Regla |
|---|---|
| `hours` | requerido, integer, min:1, max:16 |
| `date` | requerido, date; ayer si la ventana está abierta; anterior a hoy en la solicitud |
| `reason` | requerido en la solicitud fuera de plazo, string |
| `ids (aprobar o rechazar)` | requerido, array, cada elemento exists:late_overtime_requests |

## 8. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Las acciones masivas (liberar, aprobar en bloque) responden con dispatch('flash') y resumen según docs/livewire-conventions.md.
- Los controllers del campo viven en App\Http\Controllers\Hr\Field y los componentes en App\Livewire\Hr (HR-005); nombres de ruta rh.field.overtime.* y rh.office.late-overtime.*.
- Las vistas viven en resources/views/hr/{field,office}/... en lugar de la carpeta derivada del prefijo.

## 9. Preguntas abiertas

- [x] ¿Se permiten fracciones de hora (0.5)? El prototipo usa horas enteras. → **No**
- [x] ¿El tope de 16 h es regla de negocio o solo del prototipo? → **Regla de negocio**
- [x] ¿Quién puede liberar HE: Supervisor, Gerente o ambos? → **Ambos**
- [x] ¿Cuál es la hora límite de captura de HE? → **Según el prototipo v6, las HE de cada día se capturan hasta las 10:00 del día siguiente y es configurable en hr_settings (HR-005). Por confirmar.**

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
- Valor monetario de la hora y su cálculo (HR-013).
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
- [ ] `LateOvertimeRequest` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(ProjectWork), belongsTo(User).
- [ ] `OvertimeEntry` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(ProjectWork).
- [ ] `OvertimeClosure` declara `$fillable`, `$casts` y las relaciones belongsTo(ProjectWork).
- [ ] `Route::resource('recursos-humanos.oficina.horas-extra', LateOvertimeRequestController::class)` bajo middleware `role:RH jefe|RH ayudante|admin`; `create()` redirige a `recursos-humanos.oficina.horas-extra.index`.
- [ ] Existe `resources/views/recursos-humanos/oficina/horas-extra/field-overtime.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] Existe `resources/views/recursos-humanos/oficina/horas-extra/index.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.oficina.horas-extra.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Filtros por estado, Obra, fecha y persona.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de LateOvertimeRequest, OvertimeEntry registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] El estatus solo cambia por las transiciones declaradas en la tabla de estatus; una transición no permitida regresa con flash `error`.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-008]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
