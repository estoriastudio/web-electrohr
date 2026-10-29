---
mode: agent
description: Revisa un spec antes de aprobarlo (ambigüedades y contradicciones con docs/)
---

Revisa el spec abierto (${file}) **sin escribir código**.

1. Lee `.github/copilot-instructions.md`, `docs/specs/_project.json` y todos los docs de su sección "0. Contexto obligatorio".
2. Revisa el código existente que el spec menciona (modelos, controllers, rutas, vistas del módulo afectado).
3. Devuelve una lista con:
   - **Contradicciones** entre el spec y docs/ que no estén declaradas en "Excepciones a los docs".
   - **Ambigüedades** que obligarían a adivinar: tipos de columna, onDelete de FKs, quién puede ejecutar una acción, qué pasa con datos existentes, textos de mensajes.
   - **Supuestos falsos** sobre el código actual (archivos, métodos o columnas que no existen o funcionan distinto).
   - **Riesgos** no listados (migraciones sobre datos, rutas que chocan, rendimiento de consultas).
   - **Preguntas sugeridas** para agregar a "Preguntas abiertas".
4. Termina con un veredicto: `listo para aprobar` o `requiere cambios`.

No modifiques el spec ni ningún otro archivo.
