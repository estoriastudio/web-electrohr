---
spec_id: PRJ-001
type: new-module
variant: crud
module: Projects
status: revision
author: Noé
reviewers: [Jorge, Mariam]
branch: feature/prj-001-projects
created: 2026-10-07
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/ui-kit-design-system.md
  - docs/ui-kit-table-structure.md
  - docs/livewire-conventions.md
open_questions: 0
---

# PRJ-001 · Projects — Módulo nuevo (CRUD)

> ⚠ Estado: **En revisión**. No implementar hasta que el estado sea "aprobado".

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Registrar proyectos internos, asignarlos a un usuario responsable y llevar el control de sus tareas.

**Flujo de interacción**

El usuario entra a Proyectos y da clic en "Nuevo proyecto". Se abre un modal con nombre, descripción, usuario asignado y estado activo. Al guardar, se redirige al detalle del proyecto, donde ve la tabla de tareas y puede agregar, editar o eliminar tareas sin recargar la página.

## 2. Modelos

### Project — tabla `projects` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `name` | string | no | máx 255 |
| `description` | text | sí |  |
| `assigned_user_id` | foreignId | no | FK → users.id, restrict on delete |
| `is_active` | boolean | no | default: true |

Relaciones:
- `belongsTo` → User (`assigned_user_id`)
- `hasMany` → ProjectTask (`project_id`)

### ProjectTask — tabla `project_tasks` · Sin controller (Livewire o solo datos)

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `project_id` | foreignId | no | FK → projects.id, cascade on delete |
| `title` | string | no | máx 255 |
| `status` | enum() | no | pending, in_progress, done · default: pending |
| `due_date` | date | sí |  |

Relaciones:
- `belongsTo` → Project (`project_id`)

## 3. Rutas

- **Prefijo:** `/projects`
- **Nombres de ruta:** `projects.*`
- **Middleware:** `auth`

## 4. Vistas

Carpeta: `resources/views/projects` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `index.blade.php` | Blade | Listado con búsqueda y filtros, badge activo/inactivo. Crear vía modal. |
| `show.blade.php` | Blade + Livewire | Datos del proyecto + tabla de tareas (componente ProjectTasksTable): crear, editar estatus y eliminar tareas sin recargar. |
| `edit.blade.php` | Blade | Formulario de edición del proyecto. |

**Búsqueda / filtros:** por nombre; filtro activo/inactivo; filtro por usuario asignado

## 5. Archivos

- **Disco:** `local` (config/filesystems.php → disks.local · storage/app/private · privado)
- **Especificaciones:** Adjuntos del proyecto: pdf, jpg, png · máx 5 MB · carpeta projects/{id} · hasta 5 archivos por proyecto

## 6. Reglas de negocio

- Un proyecto inactivo no permite registrar tareas nuevas.
- Solo se puede asignar a usuarios existentes.
- Eliminar un proyecto elimina sus tareas.

## 7. Validaciones

| Campo | Regla |
|---|---|
| `name` | requerido, string, máx 255 |
| `assigned_user_id` | requerido, exists:users,id |
| `tasks.title` | requerido, máx 255 |

## 8. Excepciones a los docs

_Ninguna. Aplican todos los docs listados en la sección 0._

## 9. Preguntas abiertas

- [x] ¿Las rutas requieren auth? → **Sí, middleware auth (el spec original decía "sin auth", pero la notificación necesita Auth::id()).**

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
- [ ] `Project` declara `$fillable`, `$casts` y las relaciones belongsTo(User), hasMany(ProjectTask).
- [ ] `ProjectTask` declara `$fillable`, `$casts` y las relaciones belongsTo(Project).
- [ ] `Route::resource('projects', ProjectController::class)` bajo middleware `auth`; `create()` redirige a `projects.index`.
- [ ] Existe `resources/views/projects/index.blade.php` que extiende `layouts.app`.
- [ ] Existe `resources/views/projects/show.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] Existe `resources/views/projects/edit.blade.php` que extiende `layouts.app`.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `projects.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: por nombre; filtro activo/inactivo; filtro por usuario asignado.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de Project, ProjectTask registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] Los archivos se guardan con `Storage::disk('local')` definido en config/filesystems.php; no se agregan discos nuevos.
- [ ] Archivos: Adjuntos del proyecto: pdf, jpg, png · máx 5 MB · carpeta projects/{id} · hasta 5 archivos por proyecto.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[PRJ-001]` en el mensaje.

---

_Generado con Estoria Spec Tool v2 · Estoria Studio_

