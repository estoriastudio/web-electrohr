---
applyTo: "app/Livewire/**,resources/views/livewire/**"
---

# Componentes Livewire

Sigue `docs/livewire-conventions.md`. Lo esencial:

- Componentes de clase en `app/Livewire/{Modulo}/{Componente}.php`, vista en `resources/views/livewire/{modulo}/{componente-kebab}.blade.php`.
- Se montan dentro de una vista Blade que retorna el controller. No uses componentes full-page ni rutas a componentes salvo que el spec lo diga.
- Paginación con `WithPagination`, tema `bootstrap`, 25 registros.
- Búsqueda con `wire:model.live.debounce.300ms` y `#[Url]` para conservar filtros en la URL.
- Validación con atributos `#[Validate]` o `rules()`; errores con las clases del UI kit.
- Toda mutación de un modelo auditable registra `NotificationService` igual que en los controllers.
- Feedback al usuario con `$this->dispatch('flash', type: 'success|error|info', message: '...')`.
- Eliminar con `wire:confirm`.
- Modales Bootstrap con `wire:ignore.self` y apertura/cierre por eventos.
- Nunca expongas modelos completos como propiedades públicas editables sin validar; usa propiedades tipadas o Form objects.
