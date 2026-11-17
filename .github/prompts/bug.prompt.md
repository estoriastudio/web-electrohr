---
mode: agent
description: Corrige un bug contra el spec implementado o el manual, sin spec nuevo
---

Corrige este bug: ${input:bug:qué pasa y en qué pantalla}

Un bug es cuando el código **no hace lo que dice** el spec implementado o el manual. El arreglo regresa el código a eso, así que no necesita spec nuevo.

## 1. Confirma que es un bug

Antes de tocar código, responde con:
- **Qué dice que debe pasar:** cita el spec (`docs/specs/<ID>...spec.md`, sección y línea) o el paso del manual (`docs/manual/<clave>.md`, `step:<id>`). Lee el spec vigente, no `docs/specs/_archive/`.
- **Qué pasa hoy** y en qué archivo y línea está la causa.
- **Archivos que vas a tocar.**

Detente y avísame en lugar de arreglarlo si:
- No hay spec ni paso del manual que diga cómo debe funcionar, o lo que pido contradice lo que dicen. Entonces no es un bug, es un cambio: necesita spec.
- El arreglo necesita una migración, una ruta nueva, cambiar permisos (`@can`, `@role`, middleware) o tocar archivos protegidos de `_project.json`.
- El arreglo crece a varios módulos o más de unos 8 archivos.

Si solo es un texto o el acomodo de la pantalla, es un ajuste: usa las reglas de `/ajuste`.

## 2. Arréglalo

- El cambio mínimo que cumpla lo que dice el spec o el manual. No aproveches para refactorizar ni para mejorar otras cosas: anótalas para después.
- Sigue `.github/copilot-instructions.md` y los docs de `docs/` que apliquen al archivo.

## 3. Cierre

1. **Manual.** Corre `php artisan docs:map --check`. Si marca tareas como `stale` por el arreglo, sigue la sección 3 de `.github/prompts/generar-manual.prompt.md` solo para esas tareas. Si el paso ya describía bien lo que ahora hace el código, cambia solo la huella: eso no cambia el `status` del manual.
2. **Commit.** Propón el commit con `fix`, el ID del spec que se restaura al final del asunto y la causa en el cuerpo. No lo hagas sin que lo confirme.

   ```
   fix(payments): el saldo quedaba negativo al quitar una factura [PRJ-003]

   Causa: updateExistingPivot no recalculaba applied_total.
   ```

   Si la regla viene solo del manual, usa el ID del spec que la introdujo (`covers` del manual) o `[bug]` si no se puede saber.
3. **Reporte:** causa, archivos tocados, tareas del manual actualizadas y lo que anotaste para después.
