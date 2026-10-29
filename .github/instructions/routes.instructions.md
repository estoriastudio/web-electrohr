---
applyTo: "routes/**"
---

# Rutas

- Todas las rutas de módulos van en `routes/web.php`. No toques `routes/api.php` salvo que el spec lo pida.
- Módulos internos dentro del grupo con middleware `auth` (o el que indique el spec).
- `Route::resource('{tabla}', {Modelo}Controller::class)`; sub-recursos con `Route::resource('{padre}.{hijo}', ...)->shallow()`.
- Acciones extra (C2) se declaran **antes** del resource del mismo prefijo para que no las capture `{id}`.
- Rutas públicas en un grupo sin `auth`, con nombres `public.*` y `throttle` si reciben formularios.
- Agrega, no reordenes ni renombres rutas existentes.
