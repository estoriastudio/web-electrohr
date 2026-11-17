---
applyTo: "docs/manual/**"
---

# Manuales de uso

Un manual por módulo en `docs/manual/<clave>.md`. Describe cómo se usa **hoy** el módulo. Lo lee quien opera el sistema y QA, no el equipo técnico. Se genera con `/generar-manual`; los specs son el historial y el manual es el estado actual.

## Lenguaje

- Español, segunda persona, oraciones cortas: "Clic en **Guardar**", "Captura el título".
- Etiquetas de botones, campos, menús y columnas **en negritas y exactamente como aparecen en la pantalla**.
- Sin términos técnicos: nada de nombres de tablas, columnas, rutas, modelos, controllers ni valores internos (`draft`); usa la etiqueta que ve el usuario (Borrador).
- Organiza por tarea ("Publicar un artículo"), no por pantalla.

## Estructura

- El frontmatter y las secciones siguen `docs/manual/_plantilla.md`.
- Cada tarea va entre `<!-- step:<id> fp:<huella> -->` y `<!-- /step -->`. El ID es el nombre de la ruta (`articles.update`). Para acciones de Livewire: ruta de la página que monta el componente + `@` + método (`articles.create@save`). Para tareas sin ruta propia dentro de una pantalla: `ruta#tema` (`articles.index#filtros`).
- Las rutas que solo muestran un formulario (`create`, `edit`) no llevan tarea propia: la tarea es la acción que guarda (`store`, `update`, o la acción Livewire de esa página).
- La huella (`fp`) se copia de `docs/architecture/_code.json` (la genera `php artisan docs:map`): `fp` de la ruta, o `livewire[].actions[<método>]` para `ruta@método`. Resume el código de la acción, su vista y las reglas que usa; si cambia, `docs:map --check` marca la tarea para revisar. Nunca la inventes ni la cambies sin revisar la tarea.
- Las secciones generales van entre `<!-- gen:<nombre> -->` y `<!-- /gen:<nombre> -->`.
- Las notas de personas e imágenes van entre `<!-- human:<id> -->` y `<!-- /human -->`, justo después del paso al que pertenecen. **Nunca se modifican ni se borran** al regenerar.
- Imágenes en `docs/manual/img/<clave>/<id-del-paso>-<n>.png` (sustituye `@` y `#` por `-` en el nombre del archivo), comprimidas.

## Qué no se inventa

Solo va lo que se deriva de un spec del módulo, del código o de las vistas. Lo demás se escribe como:

```markdown
> ⏳ Pendiente: ¿<pregunta concreta>?
```

Estos pendientes son la lista de trabajo de QA y negocio. Una persona los resuelve reemplazando la línea por el texto, dentro de una zona `human`.

## Estados

- `generado`: lo escribió el agente; aún no se revisa contra la aplicación.
- `verificado`: QA siguió cada paso en la aplicación y coincide. Se llenan `verified_by` y `verified_at`.

Cualquier cambio al texto de un paso de un manual `verificado` lo regresa a `generado`. Actualizar solo la huella, porque el código cambió pero el paso sigue siendo correcto (un ajuste de acomodo, un bug que no cambia lo que el usuario hace), no cambia el `status`.
