---
mode: agent
description: Sesión de cambios de comportamiento autorizados sin spec (todo excepto el esquema de la base de datos)
---

Abre una **sesión autorizada**. Qué se autoriza y por qué: ${input:autorizacion:qué cambio se autoriza, por qué y número de ticket si hay}

Esta autorización sustituye la aprobación de un spec para cambios en lo que el sistema **hace, permite o guarda**: validaciones, lógica de una acción, permisos, rutas, vistas y mensajes. La sesión es conversacional: después de este pedido vendrán otros, y estas reglas aplican a todos hasta que yo la cierre.

Si no di un **motivo** (por qué se hace el cambio), pídelo antes de tocar nada: va en el commit y es el único registro de la decisión.

## Antes de tocar cada pedido

Di en una línea qué archivos vas a tocar y hazlo, sin esperar confirmación. Detente y no lo hagas si:

- **Toca el esquema de la base de datos**: migraciones, columnas, tablas, índices o modelos nuevos. Eso necesita spec, porque `docs:map --check` compara el esquema contra los specs. Anótalo como **Necesita spec** y sigue con los demás pedidos.
- **Hace un campo opcional y su columna no acepta null.** Revisa la migración o `docs/architecture/_code.json` antes de quitar un `required`. Si la columna es `NOT NULL`, hace falta una migración: es el caso anterior.
- **Contradice "Debe permanecer igual"** de un spec vigente (`aprobado` o `implementado`) del módulo. Dímelo y espera.

Si el pedido crece a algo del tamaño de un módulo (una pantalla nueva, un flujo nuevo, varios modelos involucrados), sugiere hacer un spec. Si insisto, sigue.

Si el pedido solo cambia textos o acomodo, hazlo también, pero anótalo como ajuste: va en su propio commit con el formato de `/ajuste`.

Reglas fijas: las de `.github/copilot-instructions.md` (convenciones, UI kit, textos en español), los docs de `docs/` que apliquen a cada archivo, nunca modifiques los archivos `protected` de `_project.json` ni instales paquetes.

## Durante la sesión

Termina cada respuesta con la lista acumulada, con el antes y después de cada regla:

> **Cambios autorizados**
> - `clients`: `rfc` required → nullable (validación en `ClientController@store` y formulario)
> - `payments`: el vendedor ya puede ver el botón **Exportar** (`@can('payments.export')` → `@role('vendedor|admin')`)
>
> **Necesita spec:** agregar la columna `rfc_verified_at`

- Si te piden deshacer algo, usa la lista para saber qué regresar y quítalo de ella.
- Si los cambios ya tocan varios módulos o más de unos 8 archivos, sugiere cerrar y hacer commit de lo que hay antes de seguir.
- No corras `docs:map` ni toques manuales mientras la sesión sigue abierta.

## Cierre

La sesión se cierra cuando digo algo como **"listo, eso es todo"** o **"hemos terminado"**. Un "listo" o "ok" solo, a mitad de la conversación, no la cierra. Al cerrar:

1. **Manuales.** Corre `php artisan docs:map --check`. Para cada tarea `stale` o `missing_steps` que reporte, y para cada paso de `docs/manual/*.md` que mencione una regla que cambió (un campo "obligatorio", quién puede hacer algo, un límite), sigue la sección 3 de `.github/prompts/generar-manual.prompt.md`:
   - Si cambió lo que el usuario ve o puede hacer, corrige el texto del paso y pon la huella nueva. Si el manual estaba `verificado`, regresa a `generado`.
   - Si el paso sigue siendo correcto, cambia solo la huella.
   - Si el texto afectado está dentro de `<!-- human:... -->`, no lo toques: repórtalo.
   - No agregues nada a `covers` (no hay spec).
2. **Specs.** Si `docs:map --check` reporta diferencias nuevas entre specs y código, algo tocó el esquema: repórtalo, no lo cierres así.
3. **Capturas.** Lista las imágenes de `docs/manual/img/` de los pasos afectados: hay que volver a tomarlas. No las borres.
4. **Commit.** Propón un commit por módulo, con `[autorizado]` al final del asunto (con el ticket si lo di: `[autorizado #123]`) y en el cuerpo el motivo y la lista de cambios con el antes y después:

   ```
   fix(clients): RFC opcional en el alta [autorizado #123]

   Motivo: el cliente no siempre tiene RFC al registrarse.
   - rfc: required → nullable (validación y formulario)
   ```

   Usa `feat` si el cambio agrega algo que antes no se podía hacer y `fix` si corrige o relaja una regla. Los ajustes van en su propio commit con `[ajuste]`. No hagas ningún commit sin que lo confirme.
5. **Reporte final:** cambios hechos, manuales actualizados, capturas por rehacer y la lista **Necesita spec**.
