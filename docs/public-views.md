# Vistas públicas

> **Borrador v0.1** · Octubre 2026 · Validar con el equipo antes de adoptarlo como regla.

Una vista pública es una página de **solo lectura** que no requiere sesión: catálogos, fichas de producto, directorios, consultas de estatus por folio. No son módulos CRUD.

---

## 1. Rutas

```php
// routes/web.php
Route::name('public.')->group(function () {
    Route::get('/catalogo', [Public\CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalogo/{product:slug}', [Public\CatalogController::class, 'show'])->name('catalog.show');
});
```

- Grupo **sin** middleware `auth`, con nombres `public.*`.
- Solo verbos `GET`. Un formulario público (contacto, consulta de folio) usa `POST` con `throttle:10,1`.
- Si la URL muestra un registro, se busca por `slug` o un identificador público, no por `id` autoincremental, salvo que el spec diga lo contrario.

---

## 2. Controllers

- Van en `app/Http/Controllers/Public/`.
- Solo métodos de lectura (`index`, `show`). Return type `View`.
- **No** inyectan `NotificationService`.
- Toda consulta filtra lo que es visible al público (scope en el modelo):

```php
public function scopePublished(Builder $query): Builder
{
    return $query->where('is_active', true);
}
```

- Nunca se pasa el modelo completo a la vista si tiene datos internos. Se seleccionan las columnas declaradas en la tabla "Fuentes de datos" del spec.

---

## 3. Layout

- Extienden `layouts.public` (definido en `docs/specs/_project.json`). Sin sidebar, topbar de administración ni dropdown de notificaciones.
- Comparten el UI kit (tipografía, colores, cards, tablas, badges) para mantener la identidad visual.
- Secciones del layout público:

```blade
@extends('layouts.public')

@section('meta_title', $product->name)
@section('meta_description', Str::limit($product->description, 155))
@section('og_image', $product->image_url)

@section('content')
    ...
@endsection
```

---

## 4. Caché

Opcional y declarada en el spec con su TTL:

```php
$products = Cache::remember('public.catalog.index', now()->addMinutes(10), fn () =>
    Product::published()->select(['id', 'name', 'slug', 'price', 'image'])->get()
);
```

- La llave sigue el nombre de la ruta.
- Si el spec pide invalidar al guardar, se hace en el controller interno que modifica el modelo (`Cache::forget(...)`).

---

## 5. Interactividad

Si el spec marca búsqueda o filtros en vivo, se usa un componente Livewire según `docs/livewire-conventions.md`, montado dentro de la vista pública. El componente aplica el mismo scope `published()`.

---

## 6. Lo que no se hace

- Rutas de escritura sobre modelos internos.
- Mostrar IDs internos, correos de usuarios, montos o datos no listados en el spec.
- Reutilizar vistas internas (`resources/views/{modulo}/`). Las públicas viven en `resources/views/public/{seccion}/`.
