---
applyTo: "docs/specs/**"
---

# Specs

- Los `.spec.md` se generan con la Estoria Spec Tool. Los `.spec.json` son la fuente editable de la herramienta: no los leas para implementar, usa el `.md`.
- No edites un spec para que coincida con el código. Si el código necesita algo distinto, propón el cambio al spec y espera aprobación.
- `_project.json` es el perfil del proyecto. Solo se cambia por decisión del equipo.
- Los specs son órdenes de trabajo: una vez implementados son historial. Cómo funciona hoy cada módulo está en el código, en `docs/manual/<clave>.md` (se genera con `/generar-manual`) y en `docs/architecture/_code.json`.
- Un spec implementado no se edita. Un cambio al módulo va en un spec nuevo (C1–C4) que lo extiende (`extends`); al implementarse ese cambio, el spec original se mueve a `docs/specs/_archive/`.
- **No leas `docs/specs/_archive/`**: son specs reemplazados y su contenido ya no describe el código. Solo se consulta si alguien pregunta por qué se tomó una decisión.
