---
mode: agent
description: Verifica la implementación contra el spec antes del PR
---

Verifica la implementación del spec ${file} contra los cambios de esta rama (`git diff` contra la rama base).

1. Recorre cada línea de "Criterios de aceptación" y marca: ✅ cumple · ❌ no cumple · ⚠ no se puede verificar sin correr la app. Para cada ❌ indica archivo y línea.
2. Lista cualquier archivo modificado que:
   - esté en `protected` de `docs/specs/_project.json`
   - no se justifique por el spec
   - corresponda a algo de "Fuera de alcance"
3. Revisa "Debe permanecer igual" (en specs de extensión) y confirma que el diff no lo afecta.
4. Revisa que los commits de la rama incluyan el `spec_id`.
5. Si existe `app/Console/Commands/DocsMap.php`, corre `php artisan docs:map --check --no-write` e incluye en el reporte las diferencias que muestre entre los specs y el código.
6. Revisa las convenciones de docs/ en los archivos tocados (paginate(25), flash keys, NotificationService, clases del UI kit, nombres en inglés).

Devuelve un resumen final: `listo para PR` o la lista de pendientes. No corrijas nada sin que te lo pida.
