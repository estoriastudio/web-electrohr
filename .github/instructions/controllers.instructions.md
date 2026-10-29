---
applyTo: "app/Http/Controllers/**"
---

# Controllers

Sigue `docs/controller-structure-and-notifications.md` completo. Resumen de lo que no se negocia:

- Resource controller estándar. `create()` siempre redirige a `index` (la creación es por modal).
- Return types: `View` en index/show/edit, `RedirectResponse` en store/update/destroy.
- Si el modelo es auditable, inyecta `NotificationService` por constructor con property promotion y registra la acción en store, update y destroy **antes** del redirect:
  - `type`: nombre de la clase del modelo en PascalCase
  - `action_by`: `Auth::id()`
  - `model_action`: `create` | `update` | `destroy`
  - `data`: verbo en pasado + descripción legible con el nombre del recurso
- `index` con `paginate(25)` y eager loading (`with()` / `withCount()`).
- Flash: `->with('success', ...)` en éxito, `->with('error', ...)` en error de negocio, `->with('info', ...)` para avisos neutros (sin cambios, filas omitidas, etc.).
- Campos `nullable` declarados explícitamente en la validación.
- No modifiques `UserController`, `NotificationController` ni `NotificationService` (protegidos en `docs/specs/_project.json`).
- Las vistas públicas usan controllers de solo lectura en `App\Http\Controllers\Public` y no notifican (ver `docs/public-views.md`).
- Archivos: usa el disco que indica el spec (`local` o `s3`) con `Storage::disk(...)`. Ambos existen en `config/filesystems.php`; no agregues ni modifiques discos.
