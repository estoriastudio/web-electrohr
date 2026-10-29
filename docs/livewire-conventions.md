# Convenciones de Livewire

> **Borrador v0.1** · Octubre 2026 · Validar con el equipo antes de adoptarlo como regla.
> Escrito para Livewire 3 con componentes de clase. Si el proyecto usa Livewire 4, se conserva el estilo de componentes de clase por consistencia.

Livewire se usa para **reducir AJAX manual**, no para reemplazar la arquitectura de controllers. Los controllers de recurso, las rutas y las vistas Blade siguen siendo la estructura base; Livewire agrega interactividad dentro de esas vistas.

---

## 1. Cuándo usar Livewire

| Usar Livewire | Usar Blade + controller |
|---|---|
| Búsqueda y filtros que se actualizan mientras se escribe | Listados con búsqueda por submit |
| Cambiar estado o eliminar filas sin recargar la página | Formularios de edición completos |
| Sub-recursos dentro del detalle del padre (ej. tareas de un proyecto) | Vistas de detalle de solo lectura |
| Selects dependientes (estado → municipio) | Exportaciones e importaciones de archivo |
| Validación en tiempo real en formularios largos | Acciones que redirigen a otra página |

Si una vista no gana nada visible con Livewire, va en Blade. El spec indica en la tabla de vistas cuál usa cada una.

---

## 2. Estructura y nombres

```
app/Livewire/
└── Projects/
    ├── ProjectsTable.php
    └── ProjectTasksTable.php

resources/views/livewire/
└── projects/
    ├── projects-table.blade.php
    └── project-tasks-table.blade.php
```

- Carpeta por módulo en PascalCase plural (`Projects`), igual que la tabla.
- Clase con sufijo según su función: `*Table` (listado), `*Form` (formulario), `*Panel` (bloque de detalle).
- Vista en kebab-case con la misma ruta relativa.
- Generar con `php artisan make:livewire Projects/ProjectTasksTable`.

---

## 3. Cómo se monta

El controller sigue retornando la vista Blade y la vista monta el componente:

```blade
{{-- resources/views/projects/show.blade.php --}}
@extends('layouts.app')

@section('content')
    @include('partials.flash')
    {{-- datos del proyecto (Blade) --}}
    <livewire:projects.project-tasks-table :project="$project" />
@endsection
```

- **No** se usan componentes full-page (`Route::get('/x', Componente::class)`) salvo que el spec lo pida.
- Los datos que el componente necesita entran como parámetros en `mount()`, nunca por consulta global sin filtro.
- El layout debe incluir `@livewireStyles` y `@livewireScripts` (una sola vez, en `layouts/app.blade.php`).

---

## 4. Listados

```php
namespace App\Livewire\Projects;

use App\Models\Project;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectsTable extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function render()
    {
        $projects = Project::with('assignedUser')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->latest()
            ->paginate(25);

        return view('livewire.projects.projects-table', compact('projects'));
    }
}
```

- Siempre `paginate(25)` y eager loading.
- `wire:model.live.debounce.300ms` en el input de búsqueda; `wire:model.live` en selects de filtro.
- `#[Url]` en filtros para que la URL conserve el estado al compartir o recargar.
- Reiniciar la página (`resetPage()`) al cambiar cualquier filtro.
- La tabla usa exactamente la estructura de `docs/ui-kit-table-structure.md`.

---

## 5. Formularios y validación

- Hasta 4 campos: propiedades tipadas en el componente con `#[Validate]`.
- Más de 4 campos: Form object (`php artisan livewire:form ProjectForm`).
- Las reglas siguen las convenciones de `docs/controller-structure-and-notifications.md` §4.
- Errores con las mismas clases del UI kit:

```blade
<input type="text" wire:model="title"
       class="form-control @error('title') is-invalid @enderror">
@error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
```

- Nunca declares un modelo como propiedad pública editable directamente (`public Project $project` + `wire:model="project.name"`). Usa propiedades separadas o Form objects.

---

## 6. Mutaciones y notificaciones

Toda creación, edición o eliminación de un modelo auditable registra `NotificationService`, igual que en los controllers:

```php
public function delete(int $task_id, NotificationService $notification): void
{
    $task = $this->project->tasks()->findOrFail($task_id);
    $task->delete();

    $notification->send([
        'type'         => 'ProjectTask',
        'action_by'    => Auth::id(),
        'model_action' => 'destroy',
        'model_id'     => $task->id,
        'data'         => "eliminó la tarea {$task->title} del proyecto {$this->project->name}",
    ]);

    $this->dispatch('flash', type: 'success', message: 'Tarea eliminada correctamente.');
}
```

- El servicio se inyecta por parámetro del método (Livewire lo resuelve del contenedor).
- Las consultas de mutación siempre parten de la relación del padre (`$this->project->tasks()`), nunca de `ProjectTask::find($id)` sin filtrar.

---

## 7. Feedback al usuario

Las acciones que no redirigen avisan con un evento:

```php
$this->dispatch('flash', type: 'success', message: 'Tarea creada correctamente.');
```

Tipos válidos: `success`, `error`, `info` (los mismos del flash de sesión).

El parcial `partials/flash.blade.php` escucha ese evento y muestra la alerta (ver `docs/CAMBIOS-docs-existentes.md`). No se crean toasts ni alertas propias por componente.

Si la acción redirige, usa el flash de sesión normal:

```php
session()->flash('success', 'Proyecto actualizado.');
return $this->redirectRoute('projects.show', $this->project);
```

---

## 8. Confirmaciones, carga y modales

- Eliminar: `wire:confirm="¿Eliminar esta tarea?"` en el botón.
- Estado de carga en botones:

```blade
<button wire:click="save" class="btn btn-primary" wire:loading.attr="disabled">
    <span wire:loading wire:target="save" class="spinner-border spinner-border-sm me-1"></span>
    Guardar
</button>
```

- Modales Bootstrap dentro de un componente: `wire:ignore.self` en el `.modal` y apertura/cierre por eventos:

```php
$this->dispatch('modal', id: 'taskModal', action: 'hide');
```

```blade
@push('scripts')
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('modal', ({ id, action }) => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById(id))[action]();
        });
    });
</script>
@endpush
```

Este listener vive en el layout una sola vez cuando el primer módulo lo necesite.

---

## 9. Lo que no se hace

- Alpine.js propio más allá de lo que ya trae Livewire, salvo que el spec lo pida.
- Polling (`wire:poll`) sin justificación en el spec.
- Lógica de negocio duplicada entre controller y componente: si ambos la necesitan, va en el modelo (scope o método).
- Instalar paquetes de terceros para Livewire (tablas, modales, toasts).
