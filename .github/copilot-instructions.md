# Instrucciones del proyecto para GitHub Copilot

Este repositorio sigue **Spec Driven Development**. Ningún módulo nuevo ni cambio de comportamiento se implementa sin un spec aprobado en `docs/specs/`.

## Fuentes de verdad (en este orden)

1. **El spec que se está implementando** (`docs/specs/{ID}-{modulo}.spec.md`). Su sección "Excepciones a los docs" es lo único que puede contradecir a los docs.
2. **`docs/`**: reglas permanentes del proyecto (controllers, notificaciones, UI kit, Livewire, vistas públicas).
3. **`docs/specs/_project.json`**: perfil del proyecto (versiones, layouts, paquetes instalados, archivos protegidos, fuera de alcance por defecto).
4. **Este archivo.**

Si dos fuentes se contradicen y el spec no declara la excepción, **detente y pregunta**. No elijas una por tu cuenta.

## Antes de escribir código

- Lee el spec completo y todos los archivos listados en su sección "0. Contexto obligatorio".
- Si el `status` del frontmatter no es `aprobado`, o `open_questions` es mayor que 0, avisa y no implementes salvo que el desarrollador lo confirme explícitamente.
- Confirma en una lista breve: modelos, rutas, vistas y el orden de implementación que vas a seguir.

## Cómo implementar

- Sigue el "Orden de implementación" del spec. Termina una capa, lista los archivos creados o modificados y espera confirmación antes de la siguiente.
- Toca solo los archivos necesarios para el spec. En specs de extensión, respeta la lista "Debe permanecer igual".
- Nunca modifiques los archivos de `protected` en `_project.json`.
- No instales paquetes que no estén en `packages` de `_project.json`.
- No generes nada de "Fuera de alcance" (por defecto: rutas API, tests, factories, seeders, Form Requests, observers, jobs, policies, API resources).
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

Si durante la implementación aparece algo que el spec no cubre, no lo resuelvas por tu cuenta: propón la actualización del spec primero.
