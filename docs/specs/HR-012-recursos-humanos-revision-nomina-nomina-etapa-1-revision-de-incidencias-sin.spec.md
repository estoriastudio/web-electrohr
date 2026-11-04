---
spec_id: HR-012
type: new-module
variant: workflow
title: Nómina etapa 1: revisión de incidencias sin montos
module: Recursos Humanos
module_key: recursos-humanos_revision_nomina
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

# HR-012 · Recursos Humanos — Nómina etapa 1: revisión de incidencias sin montos

**Módulo:** Recursos Humanos (`recursos-humanos_revision_nomina`) · **Tipo:** Módulo nuevo (CRUD + Workflow)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`

## 1. Propósito

Introducir la primera etapa de la nómina por periodo (viernes a jueves) y por Proyecto: el Revisor de nómina revisa Obra por Obra las incidencias, las horas extra por día y la letra de incentivo, sin ver ningún importe; al revisar, el Proyecto pasa a RH jefe. Depende de HR-005, HR-007, HR-008 y HR-010.

**Flujo de interacción**

Desde el viernes a las 10:00 el Revisor abre Revisión de nómina, elige el periodo y el Proyecto y ve una tabla por Obra con una fila por persona y una columna por día (incidencia, HE y letra de incentivo). Marca cada Obra como revisada y, cuando todas las Obras con personal están marcadas, pulsa "Revisar Proyecto": el Proyecto pasa a "Revisado: con RH jefe". Puede exportar su vista a CSV sin importes. RH jefe puede regresar un Proyecto al Revisor (HR-013) y Dirección puede devolverlo a RH jefe (HR-014).

## 2. Modelos

### PayrollProjectStage — tabla `payroll_project_stages` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `period_start` | date | no | viernes del periodo · único junto con project_id |
| `project_id` | foreignId | no | FK → projects.id, restrict on delete |
| `status` | string | no | open, reviewed, validated · validado con in: · default open |
| `review_snapshot` | json | sí | resumen de lo revisado para detectar cambios posteriores |
| `reviewed_by` | foreignId | sí | FK → users.id, null on delete |
| `reviewed_at` | timestamp | sí |  |
| `returned` | json | sí | {by, at, comment, to}: devolución de Dirección a RH jefe (HR-014) |
| `history` | json | sí | movimientos de reapertura o devolución con motivo |

Relaciones:
- `belongsTo` → Project (`project_id`)
- `hasMany` → PayrollSiteReview (`payroll_project_stage_id`)

### PayrollSiteReview — tabla `payroll_site_reviews` · Anidado bajo el modelo principal

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `payroll_project_stage_id` | foreignId | no | FK → payroll_project_stages.id, cascade on delete |
| `project_work_id` | foreignId | no | FK → project_works.id, restrict on delete · único junto con la etapa |
| `reviewed_by` | foreignId | sí | FK → users.id, null on delete |
| `reviewed_at` | timestamp | sí |  |

Relaciones:
- `belongsTo` → PayrollProjectStage (`payroll_project_stage_id`)
- `belongsTo` → ProjectWork (`project_work_id`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/revision/nomina`
- **Nombres de ruta:** `recursos-humanos.revision.nomina.*`
- **Middleware:** `role:Revisor de nómina|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/revision/nomina` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `index.blade.php` | Blade | Periodos y Proyectos. Selector de periodo (viernes–jueves) y tarjetas por Proyecto con su estado (En revisión, Revisado: con RH jefe, Cerrado por RH), Obras revisadas de total y el motivo por el que aún no se puede revisar ("el periodo no ha terminado", "hay Obras pendientes de revisión"). |
| `show.blade.php` | Blade | Revisión de un Proyecto. Por Obra, tabla de personas por día con incidencia, HE y letra de incentivo; indicadores de datos incompletos y días sin captura; botones "Marcar Obra revisada" y "Revisar Proyecto" (deshabilitado con su razón) y exportación CSV. Sin sueldo, importes, préstamos ni cuenta. |

**Búsqueda / filtros:** Filtros por periodo, Proyecto y Obra

## 5. Estatus y transiciones

| Valor | Etiqueta | Puede pasar a |
|---|---|---|
| `open` | En revisión | reviewed |
| `reviewed` | Revisado: con RH jefe | validated \| open (RH jefe regresa al Revisor) |
| `validated` | Cerrado por RH | reviewed (reapertura o devolución de Dirección) |

## 6. Reglas de negocio

- Depende de: HR-005 (HrClock y permisos), HR-007 y HR-008 (incidencias, HE y letra por día) y HR-010 (correcciones pendientes que se deben ver). Este spec crea las etapas por Proyecto; HR-013 y HR-014 las consumen.
- Debe permanecer igual: nada del módulo anterior; PayrollPeriod, PayrollLine y sus pantallas ya fueron retirados en HR-005 y las etapas de este spec son la única nómina.
- El periodo de nómina es viernes a jueves; la revisión del Revisor abre el viernes siguiente a las 10:00 (hora de HrClock, America/Mexico_City) o, con pago anticipado, el día siguiente al último pagado a las 10:00.
- El estado se lleva por Proyecto y periodo: open → reviewed → validated. Solo el Revisor pasa de open a reviewed; solo RH jefe regresa a open (con motivo) o cierra (HR-013).
- "Revisar Proyecto" exige: periodo terminado y todas las Obras con personal marcadas como revisadas.
- El Revisor revisa por Obra: incidencias por día, HE por día (sin importes), HE aprobadas fuera de plazo y letra de incentivo; los indicadores marcan personas con datos incompletos y días sin captura.
- Al revisar se guarda un snapshot de lo revisado; si después cambia algo, RH jefe lo ve en "Cambios después de la revisión" (HR-013).
- El Revisor nunca recibe importes: el controller construye las filas sin ellos (PayrollRowBuilder), no solo la vista; su CSV tampoco los incluye, ni ve entradas de bitácora sensitive.
- Cada revisión y reapertura se escribe en bitácora (HrAudit) y notifica con NotificationService.
- El periodo se identifica por period_start (el viernes) y no depende de ninguna tabla de periodos: se calcula con HrClock::periodOf y las etapas se crean al primer uso.

## 7. Validaciones

| Campo | Regla |
|---|---|
| `periodStart (ruta)` | date y debe ser viernes; el periodo debe haber terminado para revisar |
| `project (ruta)` | exists:projects con personal en el periodo |
| `reason (regresar al Revisor)` | requerido, string |

## 8. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- La exportación CSV usa respuesta de streaming sin importes; maatwebsite/excel (ya en el perfil) se usa solo si el equipo prefiere .xlsx.
- Controller Hr\Review\PayrollReviewController; nombres de ruta rh.review.payroll.*; las vistas viven en resources/views/hr/review/payroll/ en lugar de la carpeta derivada del prefijo (HR-005).
- Las transiciones de estatus son acciones POST (revisar Obra, revisar Proyecto, reabrir) y no un update del recurso.

## 9. Preguntas abiertas

- [x] ¿La revisión abre el viernes a las 10:00 como en el prototipo? → **Según el prototipo v6 sí, y con pago anticipado abre al día siguiente del último pagado a las 10:00.**
- [x] ¿El Revisor puede ver la bitácora completa o solo sus acciones y las no sensibles? → **Según el prototipo v6 solo las entradas sin importes (D6-36).**

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
- Montos, descuentos, salario final y cierre (HR-013).
- Autorización y Excel de dispersión (HR-014).
- Modificar archivos protegidos del perfil del proyecto

## 11. Orden de implementación

1. Migraciones (en orden de FKs)
2. Modelos: fillable, casts, relaciones
3. Controller(s) y rutas en routes/web.php
4. Vistas Blade y entrada en el menú de navegación

Detente al terminar cada capa y lista los archivos creados o modificados.

## 12. Criterios de aceptación

- [ ] `php artisan migrate` corre sin errores; las migraciones se crean en orden de dependencia de FKs.
- [ ] `PayrollProjectStage` declara `$fillable`, `$casts` y las relaciones belongsTo(Project), hasMany(PayrollSiteReview).
- [ ] `PayrollSiteReview` declara `$fillable`, `$casts` y las relaciones belongsTo(PayrollProjectStage), belongsTo(ProjectWork).
- [ ] `Route::resource('recursos-humanos.revision.nomina', PayrollProjectStageController::class)` bajo middleware `role:Revisor de nómina|admin`; `create()` redirige a `recursos-humanos.revision.nomina.index`.
- [ ] `PayrollSiteReview` usa rutas anidadas `recursos-humanos.revision.nomina.payroll_site_reviews` (shallow) con su propio controller.
- [ ] Existe `resources/views/recursos-humanos/revision/nomina/index.blade.php` que extiende `layouts.app`.
- [ ] Existe `resources/views/recursos-humanos/revision/nomina/show.blade.php` que extiende `layouts.app`.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.revision.nomina.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Filtros por periodo, Proyecto y Obra.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de PayrollProjectStage registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] El estatus solo cambia por las transiciones declaradas en la tabla de estatus; una transición no permitida regresa con flash `error`.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-012]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
