---
mode: agent
description: Revisa un spec antes de aprobarlo (ambigüedades y contradicciones con docs/)
---

Revisa el spec abierto (${file}) **sin escribir código**.

## Qué significa "listo"

Un spec está listo cuando un desarrollador puede implementar el flujo que describe **sin adivinar nada que cambie el comportamiento, los datos guardados o quién puede hacer qué**. No tiene que cubrir cada caso posible: `/implementar-spec` se detiene y pregunta si encuentra algo no cubierto. Tu trabajo es encontrar lo que haría implementar mal, no cerrar el spec al máximo.

## Pasos

1. Lee `.github/copilot-instructions.md`, `docs/specs/_project.json` y todos los docs de su sección "0. Contexto obligatorio". Si ya los leíste en esta conversación y no cambiaron, no los vuelvas a leer: lee solo los que este spec agrega.
2. Revisa el código existente que el spec menciona (modelos, controllers, rutas, vistas del módulo afectado).
3. Clasifica cada hallazgo en uno de tres niveles:

   **Bloqueante** (solo estos cambian el veredicto):
   - Contradicción con docs/ que no está en "Excepciones a los docs".
   - Supuesto falso sobre el código actual (archivo, método, columna o relación que no existe o funciona distinto).
   - Una decisión que falta y que dos desarrolladores razonables resolverían distinto con resultado visible para el usuario o los datos: tipo de una columna, onDelete de una FK, quién puede ejecutar una acción, qué pasa con registros existentes en una migración.
   - Riesgo de pérdida o corrupción de datos, o de exponer datos a quien no debe verlos.
   - Pregunta en "Preguntas abiertas" que sigue sin respuesta.

   **Recomendación** (máximo 5, las de más impacto): mejoras que harían el spec más claro o robusto pero que el implementador puede resolver con los docs, las convenciones o el sentido común sin cambiar lo que el spec pide.

   **No reportes**:
   - Lo que ya resuelven `copilot-instructions.md`, `docs/`, `.github/instructions/` o el comportamiento por defecto de Laravel (paginación, llaves de flash, mensajes de validación, nombres de rutas, timestamps, etc.).
   - Textos exactos de mensajes, salvo que el spec los exija y no los dé.
   - Casos fuera del flujo que el spec describe, salvo que caigan en "pérdida de datos o permisos".
   - Lo que ya está en "Fuera de alcance", "Excepciones a los docs" o respondido en "Preguntas abiertas".
   - Detalles de estilo, redacción o estructura del spec.

4. **Si en esta conversación ya hubo una revisión**: verifica primero que los bloqueantes anteriores se resolvieron. No subas a bloqueante algo que antes era recomendación o que no se mencionó, salvo que el cambio al spec lo haya introducido. Una respuesta del equipo a un hallazgo es una decisión: no la vuelvas a cuestionar.

## Formato de respuesta

```
### Bloqueantes
1. [Sección del spec] Qué falta o está mal → qué decisión se necesita.
(o "Ninguno")

### Recomendaciones
- ...
(o "Ninguna")

### Preguntas sugeridas para "Preguntas abiertas"
Solo una por cada bloqueante que sea una decisión del equipo (no por recomendaciones). Omite la sección si no hay.

### Veredicto
`listo para aprobar` o `requiere cambios`
```

El veredicto es `listo para aprobar` cuando no hay bloqueantes, aunque haya recomendaciones. No inventes bloqueantes para justificar `requiere cambios`: si el spec describe un flujo coherente que se puede implementar, dilo.

No modifiques el spec ni ningún otro archivo.
