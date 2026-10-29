---
applyTo: "resources/views/**"
---

# Vistas Blade

Sigue `docs/ui-kit-design-system.md` y `docs/ui-kit-table-structure.md`. Lo esencial:

- Vistas internas: `@extends('layouts.app')` con `@section('page_title')`, `@section('breadcrumbs')` y `@section('content')`. Vistas públicas: layout público (ver `docs/public-views.md`).
- Mensajes flash con `@include('partials.flash')` al inicio de `content` (cubre `success`, `error`, `info` y errores de validación).
- Tablas: card con `card-header d-flex justify-content-between align-items-center border-bottom`, `card-body p-0`, tabla `table align-middle text-nowrap table-hover table-centered mb-0`, `thead.bg-light-subtle`, fila de estado vacío y `{{ $items->links() }}` en `card-footer border-top`.
- Acciones de fila: `btn-soft-primary btn-sm` (editar), `btn-soft-danger btn-sm` (eliminar con confirmación), `btn-light btn-sm` (ver). Íconos `ri-*` con `align-middle fs-18`.
- Badges de estado: `badge bg-{color}-subtle text-{color} py-1 px-2 fs-12`.
- Crear por modal (`modal-lg modal-dialog-centered`) y reabrirlo con JS cuando `$errors->any()`.
- Inputs con `@error` + `is-invalid` + `invalid-feedback` y `old()`.
- CSS y JS de la página solo con `@push('styles')` / `@push('scripts')`. No vuelvas a incluir assets que ya carga el layout.
- No escribas CSS propio si existe una clase del kit.
- No modifiques `layouts/app.blade.php` ni `layouts/auth.blade.php`. Los parciales de `layouts/` (navegación, sidebar, topbar) sí se editan.
- Un módulo nuevo con vista index agrega su entrada al menú de navegación en el mismo flujo, sin esperar instrucción aparte, siguiendo la estructura de `.nav-item` del UI kit.
