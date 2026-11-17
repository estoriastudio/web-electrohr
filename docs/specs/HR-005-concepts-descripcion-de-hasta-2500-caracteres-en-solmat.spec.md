---
spec_id: HR-005
type: ext-rule
title: Descripción de hasta 2500 caracteres en SOLMAT, SOLCOM y OC
module: Suministros
module_key: concepts
status: implementado
implemented: 2026-11-16
author: Jorge Peña
reviewers: [Mariam]
branch: satellite
created: 2026-11-16
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
open_questions: 0
---

# HR-005 · Suministros — Descripción de hasta 2500 caracteres en SOLMAT, SOLCOM y OC

**Módulo:** Suministros (`concepts`) · **Tipo:** C3 · Cambio de regla

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`

## 1. Propósito

Que una partida de SOLMAT, SOLCOM u OC acepte la misma longitud de descripción que un suministro (hasta 2,500 caracteres), para poder usar en esos documentos los suministros con descripciones extensas.

**Flujo de interacción**

Quien administra Suministros da de alta un concepto con una descripción larga, por ejemplo: «SUBESTACIÓN ENCAPSULADA EN SF6 PARA 230 KV, CON ARREGLO DE INTERRUPTOR Y MEDIO, CON ENVOLVENTE TRIFÁSICA PARA BARRAS, FORMADA POR UN TOTAL DE SIETE BAHÍAS: DOS BAHÍAS PARA LÍNEAS, TRES BAHÍAS PARA BANCO DE TRANSFORMACIÓN, UNA BAHÍA A LT FUTURO Y UNA BAHÍA DE EQUIPO DE BARRAS. INSTALACIÓN INTERIOR, TENSIÓN NOMINAL 230 KV, TENSIÓN NOMINAL DE AGUANTE AL IMPULSO POR RAYO 1 050 KV, CORRIENTE NOMINAL EN BARRAS 2 000 A, CAPACIDAD INTERRUPTIVA 40 KA. DE ACUERDO CON LAS CARACTERÍSTICAS PARTICULARES NO. NSN01082024 B03EM0002.» (521 caracteres). Después lo agrega a una SOLMAT, una SOLCOM o una OC desde el buscador del catálogo y la partida se guarda sin error de validación.

## 2. Módulo afectado

- **Módulo:** Suministros
- **Usa Livewire:** no

**Comportamiento actual**

`ConceptController@store` y `@update` validan `description` con `max:2500`. `MaterialRequestController@storeItem`, `PurchaseRequestController@storeItem` y `PurchaseOrderController@storeItem` la validan con `max:500`. El front envía la descripción completa del concepto seleccionado (`fd.append('description', …)` en `public/assets/js/material_request.js` y `public/assets/js/purchase_request.js`; `addItem({ description: … })` en `resources/views/purchase_orders/show.blade.php`), así que un suministro de 501 a 2,500 caracteres se guarda en el catálogo pero al agregarlo a SOLMAT, SOLCOM u OC el servidor responde 422 con el error de longitud. Los `updateItem` de los tres controllers no validan `description`. Las columnas `description` de las partidas ya son `text`.

**Archivos que se tocan**

- app/Http/Controllers/MaterialRequestController.php (storeItem)
- app/Http/Controllers/PurchaseRequestController.php (storeItem)
- app/Http/Controllers/PurchaseOrderController.php (storeItem)

**Debe permanecer igual**

- La validación de `description` en Suministros (`ConceptController`: `max:2500`).
- Las demás reglas de validación de `storeItem` en los tres controllers.
- Los `updateItem` de SOLMAT, SOLCOM y OC: no validan `description`, no hay otra regla que cambiar.
- El mensaje de error es el estándar de Laravel en español (`lang/es/validation.php` → `max.string`): «El campo description no debe contener más de 2500 caracteres.»
- El concepto libre de la OC (`#oc_inp_manual_desc` en `resources/views/purchase_orders/show.blade.php`) conserva `maxlength="255"`: el límite de 2,500 solo se alcanza con conceptos del catálogo.
- Columnas, vistas, JavaScript y PDFs.

**Riesgos**

- PDFs de SOLMAT, SOLCOM y OC (`material_requests/pdf`, `purchase_requests/pdf`, `purchase_orders/partials/_pdf_body`): imprimen la descripción completa en una celda y las filas tienen `page-break-inside: avoid`. Una partida de 2,500 caracteres hace la fila muy alta y puede saltar completa a la hoja siguiente o desbordar el maquetado. Se verifica con el caso de 2,500 caracteres.
- Vistas con tablas de partidas (show de SOLMAT, SOLCOM y OC): muestran la descripción completa, así que con textos largos las filas y las listas crecen mucho. Se verifica con el mismo caso.
- Observaciones de SOLCOM: `PurchaseRequestController@updateItem` (línea 665) inserta `{$item->description}` completa en el texto de la observación que registra el cambio de cantidad de un concepto fincado. Con 2,500 caracteres cada observación se vuelve muy larga (la columna es JSON, no falla).
- Comparación de partidas en `PurchaseRequestController` (descripciones normalizadas, líneas 1570, 1684 y 1695): con textos largos solo aumenta el costo de comparación; el comportamiento no cambia.
- Notificaciones: `notifications.data` es `text`, no falla aunque algún mensaje incluya la descripción.

## 3. Regla

**Actual**

La descripción de una partida de SOLMAT, SOLCOM u OC admite hasta 500 caracteres; la de un suministro, hasta 2,500.

**Nueva**

La descripción de una partida de SOLMAT, SOLCOM u OC admite hasta 2,500 caracteres, igual que la de un suministro: `max:500` cambia a `max:2500` en `storeItem` de los tres controllers.

**Dónde se aplica:** controller

**Casos afectados**

- El suministro del ejemplo (521 caracteres) se agrega a una SOLMAT, a una SOLCOM y a una OC sin error de validación y la partida guarda el texto completo.
- Un suministro de 2,500 caracteres se agrega a SOLMAT, SOLCOM y OC sin error; el detalle y el PDF de cada documento se revisan con esa partida (riesgos de PDFs y vistas).
- Una descripción de 2,501 caracteres en SOLMAT, SOLCOM u OC regresa 422 con «El campo description no debe contener más de 2500 caracteres.»

**Datos históricos**

No aplica (capa 2 del orden de implementación): no hay cambios sobre datos existentes ni migraciones; las columnas `description` ya son `text`.

## 4. Reglas de negocio

- El límite de la descripción es 2,500 caracteres en Suministros, SOLMAT, SOLCOM y OC.
- Solo se implementa la capa 1 del orden de implementación (validación en los tres controllers). Las capas 2 (datos históricos) y 3 (vistas/mensajes) no aplican: no hay migración ni cambios de vistas o mensajes.

## 5. Validaciones

| Campo | Regla |
|---|---|
| `description` | MaterialRequestController@storeItem: requerido, string, máx 2500 |
| `description` | PurchaseRequestController@storeItem: requerido, string, máx 2500 |
| `description` | PurchaseOrderController@storeItem: requerido, string, máx 2500 |

## 6. Excepciones a los docs

_Ninguna. Aplican todos los docs listados en la sección 0._

## 7. Preguntas abiertas

- [x] ¿Las vistas y los PDFs de SOLMAT, SOLCOM y OC deben mostrar la descripción completa o truncada? Si el maquetado falla con 2,500 caracteres, ¿se resuelve en este spec o en un `/ajuste`? → **Completa, como hoy (sin truncar). Si el maquetado falla con 2,500 caracteres, se corrige en un `/ajuste`, no en este spec.**
- [x] ¿Hay que acotar la descripción en las observaciones de SOLCOM (`PurchaseRequestController` línea 665)? → **No. La observación sigue incluyendo la descripción completa.**

## 8. Fuera de alcance

- Rutas API (routes/api.php)
- Unit / Feature tests
- Factories y seeders
- Form Requests
- Observers / Event listeners
- Jobs y colas
- Artisan commands
- Policies / Gates
- Clases Mail / Notification de Laravel
- API Resources / Transformers
- Instalar paquetes no listados en el perfil
- Cambios a vite.config.js / package.json
- Vales de material: `MaterialVoucherController@storeItem` y su columna `string(500)` se quedan en 500 (captura manual, no se alimenta de las partidas de SOLMAT)
- Límite del concepto libre de la OC (`maxlength="255"`)
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

Detente al terminar cada capa y lista los archivos creados o modificados.

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en el controller: La descripción de una partida de SOLMAT, SOLCOM u OC admite hasta 2,500 caracteres, igual que la de un suministro: `max:500` cambia a `max:2500` en `storeItem` de los tres controllers.
- [ ] Caso: El suministro del ejemplo (521 caracteres) se agrega a una SOLMAT, a una SOLCOM y a una OC sin error de validación y la partida guarda el texto completo.
- [ ] Caso: Un suministro de 2,500 caracteres se agrega a SOLMAT, SOLCOM y OC sin error; el detalle y el PDF de cada documento se revisan con esa partida (riesgos de PDFs y vistas).
- [ ] Caso: Una descripción de 2,501 caracteres en SOLMAT, SOLCOM u OC regresa 422 con «El campo description no debe contener más de 2500 caracteres.»
- [ ] Datos históricos: No aplica (capa 2 del orden de implementación): no hay cambios sobre datos existentes ni migraciones; las columnas `description` ya son `text`.
- [ ] Permanece igual: La validación de `description` en Suministros (`ConceptController`: `max:2500`).
- [ ] Permanece igual: Las demás reglas de validación de `storeItem` en los tres controllers.
- [ ] Permanece igual: Los `updateItem` de SOLMAT, SOLCOM y OC: no validan `description`, no hay otra regla que cambiar.
- [ ] Permanece igual: El mensaje de error es el estándar de Laravel en español (`lang/es/validation.php` → `max.string`): «El campo description no debe contener más de 2500 caracteres.»
- [ ] Permanece igual: El concepto libre de la OC (`#oc_inp_manual_desc` en `resources/views/purchase_orders/show.blade.php`) conserva `maxlength="255"`: el límite de 2,500 solo se alcanza con conceptos del catálogo.
- [ ] Permanece igual: Columnas, vistas, JavaScript y PDFs.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-005]` en el mensaje.

---

_Generado con Estoria Spec Tool v4 · Estoria Studio_
