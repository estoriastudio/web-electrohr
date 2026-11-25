---
mode: agent
description: Implementa un spec aprobado completo, siguiendo su orden de implementación
---

Implementa el spec ${file}.

1. Verifica el frontmatter: si `status` no es `aprobado` o `open_questions` es mayor que 0, detente y avísame. Si es `implementado`, el código ya existe: detente y avísame que el cambio necesita un spec de extensión.
2. Lee `.github/copilot-instructions.md`, `docs/specs/_project.json` y todos los docs de la sección "0. Contexto obligatorio". Si ya los leíste en esta conversación y no cambiaron, no los vuelvas a leer: lee solo los que este spec agrega.
   - Si el spec es un cambio (C1–C4, con `extends`), **no leas el spec que extiende ni la cadena de specs anteriores** (están o estarán en `docs/specs/_archive/`). Lo que existe hoy está en el código: lee los archivos de "Archivos que se tocan" y, si existe, `docs/manual/<module_key>.md`.
3. Escribe un plan breve (modelos y tablas, rutas, vistas y componentes Livewire, archivos existentes que vas a modificar) y **continúa sin esperar respuesta**.
4. Implementa el spec completo siguiendo los pasos de "Orden de implementación" **en ese orden**: cada paso usa lo que creó el anterior (la migración define las columnas que usa el modelo, el controller usa los modelos, las vistas usan las rutas y las variables del controller). No te detengas entre pasos.
5. Detente y pregunta **solo** si:
   - algo no está cubierto por el spec y hay que decidir algo de comportamiento, datos o permisos;
   - el spec contradice docs/ o el código actual sin que esté en "Excepciones a los docs";
   - vas a ejecutar algo que modifica datos existentes (correr migraciones sobre tablas con datos, comandos de artisan que escriben en la base).
   Lo que resuelven los docs y las convenciones lo decides tú, sin preguntar. Al recibir respuesta, sigue desde donde te quedaste.
6. Antes de responder, revisa que las piezas encajen y corrige lo que no:
   - las columnas de la migración coinciden con `$fillable`, `$casts` y las reglas de validación;
   - las relaciones del modelo usan FKs que existen en las migraciones;
   - cada `route('...')` de las vistas y redirecciones existe con ese nombre en `routes/web.php`;
   - cada variable que usa una vista la envía su controller o componente Livewire.
7. Al terminar, responde con:
   - archivos creados y modificados, agrupados por paso;
   - criterios de aceptación del spec: cuáles quedan cubiertos y cuáles requieren prueba manual;
   - decisiones menores que tomaste con base en convenciones (si las hubo);
   - pasos que me tocan (migraciones por correr, configuración, etc.).

Reglas fijas: no modifiques archivos protegidos de `_project.json`, no instales paquetes, no generes nada de "Fuera de alcance" (la lista `oos` de `_project.json` más la del spec, menos lo que el spec dice que sí incluye) y respeta "Debe permanecer igual".
