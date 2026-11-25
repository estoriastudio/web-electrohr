---
mode: agent
description: Sesión de ajustes de textos y acomodo de pantallas, sin spec
---

Abre una **sesión de ajustes**. Primer pedido: ${input:pedido:qué hay que ajustar}

Un ajuste cambia **cómo se ve o cómo se lee** una pantalla, no lo que el sistema hace, permite o guarda. No necesita spec. La sesión es conversacional: después de este pedido vendrán otros, y estas reglas aplican a todos hasta que yo la cierre.

## Antes de tocar cada pedido: clasifícalo

| Tipo | Qué entra | Qué haces |
|---|---|---|
| **Ajuste** | Textos visibles (botones, títulos, columnas, placeholders, mensajes flash y de validación), acomodo dentro de la misma pantalla (mover un botón, reordenar columnas o campos, cambiar una tabla por tarjetas con los mismos datos y acciones), íconos y clases del UI kit | Lo haces sin pedir confirmación |
| **Bug** | El código no cumple el spec implementado o el manual, y puedes señalar la línea que no cumple | Lo dices, citas el spec o el paso del manual y lo arreglas con las reglas de `/bug` |
| **Va con `/autorizar`** | Mostrar u ocultar datos, mover algo a otra pantalla, cambiar quién lo ve (`@can`, `@role`, middleware), cambiar lo que hace una acción, validaciones, rutas | **No lo tocas.** Dilo en una línea, anótalo como pendiente y sigue con los demás pedidos |
| **Necesita spec** | Migraciones, columnas, campos nuevos que se guardan, modelos | **No lo tocas.** Igual que el anterior |

Si dudas entre ajuste y otro tipo, no es ajuste. En un controller o componente Livewire solo puedes cambiar el texto de un mensaje: no la llave flash, la regla ni la redirección.

Reglas fijas: las de `.github/copilot-instructions.md` (UI kit, textos en español, archivos protegidos de `_project.json`, nada de paquetes nuevos). No escribas CSS propio si existe una clase del kit.

## Durante la sesión

Termina cada respuesta con la lista acumulada:

> **Ajustes de esta sesión**
> - `payments/show`: botón "Guardar" → "Registrar pago"
> - `payments/show`: botón movido a la barra superior
>
> **Va con `/autorizar`:** ocultar el botón para el rol vendedor
> **Necesita spec:** guardar la fecha de pago real

- Anota el texto anterior de cada texto que cambies: lo necesitas al cerrar.
- Si te piden deshacer algo, usa la lista para saber qué regresar y quítalo de ella.
- Si los cambios ya tocan varios módulos o más de unos 8 archivos, sugiere cerrar y hacer commit de lo que hay antes de seguir.
- No corras `docs:map` ni toques manuales mientras la sesión sigue abierta: las huellas cambian con cada vuelta.

## Cierre

La sesión se cierra cuando digo algo como **"listo, eso es todo"** o **"hemos terminado"**. Un "listo" o "ok" solo, a mitad de la conversación, no la cierra. Al cerrar:

1. **Manuales.** Corre `php artisan docs:map --check`. Después busca en `docs/manual/*.md` cada texto anterior de la lista: encuentra lo que la huella no ve (menú, componentes `<x-...>`, layout, `lang/` y partials compartidos). Para lo que reporte cualquiera de los dos, sigue la sección 3 de `.github/prompts/generar-manual.prompt.md`:
   - Si cambió una etiqueta o un lugar que el manual menciona, corrige ese texto y pon la huella nueva.
   - Si solo cambió el acomodo y el paso sigue siendo correcto, cambia solo la huella. Eso no cambia el `status` del manual.
   - Si el texto anterior aparece dentro de `<!-- human:... -->`, no lo toques: repórtalo.
   - No agregues los ajustes a `covers` (no tienen spec).
2. **Capturas.** Lista las imágenes de `docs/manual/img/` de los pasos afectados: hay que volver a tomarlas. No las borres.
3. **Commit.** Propón un commit por módulo, con la lista de ajustes en el cuerpo y `[ajuste]` al final del asunto (con el número de ticket si lo di: `[ajuste #123]`):

   ```
   style(payments): ajustes de la pantalla de pago [ajuste #123]

   - Botón "Guardar" → "Registrar pago"
   - Botón movido a la barra superior
   ```

   Si la sesión incluyó bugs, van en su propio commit con el formato de `/bug`. No hagas el commit sin que lo confirme.
4. **Reporte final:** ajustes hechos, manuales actualizados, capturas por rehacer y las listas **Va con `/autorizar`** y **Necesita spec**.
