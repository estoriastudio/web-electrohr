---
mode: agent
description: Implementa un spec aprobado capa por capa
---

Implementa el spec ${file}.

1. Verifica el frontmatter: si `status` no es `aprobado` o `open_questions` es mayor que 0, detente y avísame. Si es `implementado`, el código ya existe: detente y avísame que el cambio necesita un spec de extensión.
2. Lee `.github/copilot-instructions.md`, `docs/specs/_project.json` y todos los docs de la sección "0. Contexto obligatorio".
   - Si el spec es un cambio (C1–C4, con `extends`), **no leas el spec que extiende ni la cadena de specs anteriores** (están o estarán en `docs/specs/_archive/`). Lo que existe hoy está en el código: lee los archivos de "Archivos que se tocan" y, si existe, `docs/manual/<module_key>.md`.
3. Antes de escribir código, responde con:
   - modelos y tablas
   - rutas (nombre y verbo)
   - vistas y componentes Livewire
   - archivos existentes que vas a modificar
   - el orden de implementación del spec
4. Implementa **solo la primera capa** del "Orden de implementación". Al terminar, lista los archivos creados o modificados y espera mi confirmación para seguir con la siguiente.
5. Si algo no está cubierto por el spec o contradice docs/ sin estar en "Excepciones a los docs", pregunta en vez de decidir.

Reglas fijas: no modifiques archivos protegidos de `_project.json`, no instales paquetes, no generes nada de "Fuera de alcance" y respeta "Debe permanecer igual".
