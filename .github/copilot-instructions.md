# Instrucciones del proyecto para GitHub Copilot

Este repositorio sigue **Spec Driven Development**. Ningún módulo nuevo ni cambio de comportamiento se implementa sin un spec aprobado en `docs/specs/`.

**Excepciones sin spec**, solo cuando la conversación se abrió con su comando:
- `/ajuste`: textos y acomodo de pantallas. Cambia cómo se ve o se lee, no lo que el sistema hace, permite o guarda.
- `/bug`: el código no cumple un spec implementado o el manual, y el arreglo lo regresa a eso.
- `/autorizar`: el desarrollador autoriza un cambio de comportamiento con su motivo. Cubre todo excepto el esquema de la base de datos (migraciones, columnas, modelos), que siempre necesita spec.

Cada prompt (`.github/prompts/ajuste.prompt.md`, `bug.prompt.md` y `autorizar.prompt.md`) define sus límites. Lo que no cabe en ellos necesita spec: dilo y no lo hagas.

## Fuentes de verdad (en este orden)

1. **El spec que se está implementando** (`docs/specs/{ID}-{modulo}-{titulo}.spec.md`; el frontmatter indica `module`, `module_key` y, si aplica, el spec que `extends`). Su sección "Excepciones a los docs" es lo único que puede contradecir a los docs.
2. **`docs/`**: reglas permanentes del proyecto (controllers, notificaciones, UI kit, Livewire, vistas públicas).
3. **`docs/specs/_project.json`**: perfil del proyecto (versiones, layouts, paquetes instalados, archivos protegidos, fuera de alcance por defecto).
4. **Este archivo.**

`docs/manual/` contiene los manuales de uso para operación y QA. No son fuente de verdad para implementar: se generan desde los specs y el código con `/generar-manual`.

Si dos fuentes se contradicen y el spec no declara la excepción, **detente y pregunta**. No elijas una por tu cuenta.

## Antes de escribir código

- Lee el spec completo y todos los archivos listados en su sección "0. Contexto obligatorio". Si ya los leíste en esta conversación (por ejemplo, al implementar otro spec) y no cambiaron, no los vuelvas a leer.
- Si el `status` del frontmatter no es `aprobado`, o `open_questions` es mayor que 0, avisa y no implementes salvo que el desarrollador lo confirme explícitamente.
- Si el `status` es `implementado`, el spec describe código que ya existe: no lo vuelvas a implementar. Un cambio sobre ese módulo necesita su propio spec de extensión.
- Ignora `docs/specs/_archive/`: son specs reemplazados por cambios ya implementados. Para saber cómo funciona hoy un módulo, lee su código y `docs/manual/<clave>.md`, no la cadena de specs.
- Confirma en una lista breve: modelos, rutas, vistas y el orden de implementación que vas a seguir.

## Cómo implementar

- Sigue los pasos del "Orden de implementación" del spec en ese orden, sin detenerte entre ellos. Detente solo para decisiones que el spec y los docs no cubren, contradicciones, o antes de ejecutar algo que modifique datos existentes. Antes de terminar, revisa que migración, modelo, validación, rutas y vistas usen los mismos nombres. Al terminar, lista los archivos creados o modificados por paso.
- Toca solo los archivos necesarios para el spec. En specs de extensión, respeta la lista "Debe permanecer igual".
- Nunca modifiques los archivos de `protected` en `_project.json`.
- No instales paquetes que no estén en `packages` de `_project.json`.
- No generes nada de "Fuera de alcance": la lista `oos` de `_project.json` (por defecto: rutas API, tests, factories, seeders, Form Requests, observers, jobs, policies, API resources) más la del spec, menos lo que el spec dice que sí incluye.
- Para cambiar una tabla existente crea una migración nueva; nunca edites una migración que ya corrió.

## Convenciones universales

- Código en inglés: clases en `PascalCase`, variables en `$snake_case`, tablas en `snake_case` plural, rutas nombradas `{tabla}.{accion}`.
- Textos visibles al usuario en español.
- Modelos nuevos con `php artisan make:model NombreDelModelo -mcr` (o `-m` si el spec indica que no lleva controller).
- Validación con `$request->validate()` dentro del controller (o reglas del componente Livewire).
- Listados con `paginate(25)`. Mensajes flash con las llaves `success`, `error` e `info`.
- Las vistas usan exclusivamente las clases y patrones de los docs del UI kit. No escribas CSS propio si existe una clase del kit.
- Íconos: Remix Icon (`ri-*`).

## Commits

Cada commit incluye el ID del spec al final del asunto:

```
feat(projects): tabla de tareas en el detalle [PRJ-001]
```

Excepciones: `/ajuste` usa `[ajuste]` (o `[ajuste #ticket]`), `/autorizar` usa `[autorizado]` (o `[autorizado #ticket]`) con el motivo en el cuerpo, y `/bug` usa el ID del spec que restaura.

Si durante la implementación aparece algo que el spec no cubre, no lo resuelvas por tu cuenta: propón la actualización del spec primero.
