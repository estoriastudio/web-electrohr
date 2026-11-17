---
mode: agent
description: Genera o actualiza el manual de uso de un módulo (docs/manual/{clave}.md)
---

Genera o actualiza el manual de uso del módulo `${input:modulo:clave del módulo, ej. noticias}`.

El manual es para quien **opera** el sistema y para QA, no para el equipo técnico. Sigue `.github/instructions/manual.instructions.md` y el formato de `docs/manual/_plantilla.md`.

## 1. Alcance del módulo

1. Corre `php artisan docs:map` para tener las huellas actuales del código (requiere `app/Console/Commands/DocsMap.php` 0.3.0 o posterior). Después lee `docs/specs/_project.json` y `docs/architecture/_code.json`.
2. Resuelve qué entra en el módulo:
   - Si `_project.json` tiene `architecture.modules.<clave>`, el módulo incluye los módulos técnicos de `from` y los modelos de `models`.
   - Si no (sin la sección o con `modules` vacío, que es lo normal al empezar), son los specs con `module_key: <clave>` y los módulos técnicos de `_code.json` cuyo `specs` los incluye.
3. Specs que entran: los `.spec.md` del módulo con `status: implementado` en `docs/specs/` (no en `_archive/`), más el spec `aprobado` de la rama actual si se está implementando (búscalo por el ID en los commits de la rama). No incluyas `borrador` ni `revision`. Lo que hacían los specs archivados ya está en el código y en el manual: no los leas.
   - `admin-view` con `location: embedded` se documenta dentro del módulo donde vive.
   - `public-view` no se documenta.
4. Antes de escribir, responde con una lista breve: specs incluidos, módulos técnicos, controllers, componentes Livewire y rutas. Si la clave no resuelve a ningún spec implementado, detente y avísame.

## 2. Fuentes que debes leer

| Fuente | Qué sacar |
|---|---|
| Specs (`.spec.md`) | "Propósito" (para qué sirve y quién lo usa), "Estatus y transiciones", "Reglas de negocio", "Validaciones", "Archivos", "Criterios de aceptación" |
| `_code.json` | Rutas con nombre, middleware y huella (`fp`), componentes Livewire de cada ruta con sus acciones (`livewire[].actions`), modelos, `depends_on`, si audita (`notifies`), discos, la etiqueta y área del menú (`nav_label`, `area`) y el estado del manual en `manuals` |
| Controllers del módulo | Qué hace cada acción, `validate()`, mensajes flash, a dónde redirige |
| Componentes Livewire | `rules()` o `#[Validate]`, métodos públicos (son acciones del usuario), `dispatch('flash', ...)` |
| Vistas Blade | Etiquetas exactas de botones, campos, columnas y filtros; qué se muestra según el estatus o el rol (`@can`, `@role`, `@if`) |
| Menú (`nav_partial` de `_project.json`) | Ruta de navegación para llegar a cada pantalla |

Lee el código: la etiqueta que va en el manual es la que aparece en la vista, no el nombre de la columna ni lo que dice el spec si difiere. Si el spec y el código no coinciden, documenta lo que hace el código y repórtalo en el paso 5.

## 3. Escribir o actualizar `docs/manual/<clave>.md`

**Si no existe:** créalo desde `docs/manual/_plantilla.md`.

**Si existe:** actualiza solo lo necesario. `_code.json` → `manuals.items[]` del módulo dice qué cambió:
- `behind`: specs implementados que no están en `covers`. Agrega o modifica solo los pasos y secciones que esos specs tocan.
- `stale`: tareas cuya huella cambió. Vuelve a leer el código de esa acción y actualiza el paso solo si cambió algo que el usuario ve o puede hacer. En los dos casos pon la huella nueva.
- `orphan`: tareas cuya ruta o método ya no existe. No las borres; agrega al inicio del paso `> ⚠ Esta acción ya no existe en el sistema.` y repórtalo.
- `missing_steps`: acciones del módulo sin tarea. Agrega la tarea.
- `unknown`: tareas sin huella. Revisa que sigan correctas y agrega la huella.
- No reescribas pasos que no cambiaron, aunque puedas redactarlos mejor.
- Nunca modifiques ni borres el contenido entre `<!-- human:... -->` y `<!-- /human -->`.

Reglas de contenido:
- Una tarea por acción del usuario, con el ID de paso de las instrucciones del manual (nombre de ruta; en Livewire, `ruta@metodo`) y su huella: `<!-- step:<id> fp:<huella> -->`. La huella se copia de `_code.json`: `fp` de la ruta, o `livewire[].actions[<metodo>]` para `ruta@metodo`; `ruta#tema` usa la de la ruta.
- Cada dato del manual sale de una fuente del paso 2. Lo que no se puede derivar (por qué existe un proceso, cuándo conviene una opción, el orden en la operación real) va como `> ⏳ Pendiente:` con una pregunta concreta. No lo deduzcas.
- Actualiza el frontmatter: `covers` con los IDs incluidos, `generated_at` con la fecha de hoy. Si cambiaste el texto de algún paso de un manual `verificado`, regresa `status` a `generado`. Si solo actualizaste huellas, déjalo como está.

## 4. Revisión propia

Antes de terminar, corre `php artisan docs:map --check` y confirma que la sección de manuales no reporta nada para este módulo (salvo los pendientes que dejaste a propósito). Después recorre el manual y confirma para cada paso:
- La ruta o el método existen y el rol que lo describe tiene acceso (middleware, `@can`, `@role`).
- Las etiquetas coinciden con la vista.
- Los campos obligatorios y límites coinciden con las reglas de validación.

## 5. Reporte

Responde con:
- **Archivo**: creado o actualizado, y `covers`.
- **Pasos**: nuevos, modificados y marcados como inexistentes (por ID).
- **Pendientes**: la lista de preguntas `⏳`, para QA o negocio.
- **Diferencias spec / código** encontradas, si las hay.

No modifiques código, specs ni ningún archivo fuera de `docs/manual/`.
