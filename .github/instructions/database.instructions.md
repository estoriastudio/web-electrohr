---
applyTo: "database/migrations/**,app/Models/**"
---

# Migraciones y modelos

- Nunca edites una migración existente. Cualquier cambio a una tabla va en una migración nueva (`add_{campo}_to_{tabla}_table`).
- FKs con `foreignId('x_id')->constrained()` y el `onDelete` que indique el spec (`cascadeOnDelete()`, `restrictOnDelete()`, `nullOnDelete()`). Si el spec no lo dice, pregunta.
- Columnas nuevas sobre tablas con datos: nullable o con default, según la estrategia del spec.
- `softDeletes()` solo si el spec lo marca.
- Enums como `string` validado con `in:` salvo que el spec pida `enum()` de base de datos.
- Modelos: `$fillable` explícito (nunca `$guarded = []`), `$casts` para booleanos, fechas, decimales y json, y relaciones con return type (`BelongsTo`, `HasMany`…).
- Scopes locales para filtros que se repiten (`scopeActive`, `scopeSearch`).
- No crees factories ni seeders salvo que el spec los saque de Fuera de alcance.
- No modifiques la migración ni el modelo de `users`.
