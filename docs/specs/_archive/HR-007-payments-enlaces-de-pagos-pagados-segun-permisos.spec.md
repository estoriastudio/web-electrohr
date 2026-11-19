---
spec_id: HR-007
type: ext-rule
title: Enlaces de Pagos pagados según permisos
module: Pagos
module_key: payments
extends: HR-006
status: implementado
implemented: 2026-11-19
author: Jorge Peña
reviewers: []
branch: master
created: 2026-11-18
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/purchase-order-payment-health-flow.md
  - docs/supplier-account-statement.md
open_questions: 0
---

# HR-007 · Pagos — Enlaces de Pagos pagados según permisos

**Módulo:** Pagos (`payments`) · **Tipo:** C3 · Cambio de regla · **Extiende:** HR-006

> ✓ Estado: **Implementado** (2026-11-19). Este spec describe código que ya existe: no lo vuelvas a implementar. Los cambios nuevos van en un spec de extensión (C1–C4).

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/purchase-order-payment-health-flow.md`
- `docs/supplier-account-statement.md`

## 1. Propósito

Evitar que la pantalla Pagos pagados presente enlaces a pantallas que el usuario actual no puede consultar. Los enlaces se mostrarán según los permisos existentes, sin cambiar permisos, alcance de datos ni acceso a rutas.

**Flujo de interacción**

Un proveedor con el rol supplier_portal_access abre Pagos pagados. Consulta sus propios pagos, puede seguir navegando a sus órdenes y descargar sus comprobantes cuando estén disponibles, pero no ve el enlace Por Pagar ni un enlace al detalle interno del proveedor, porque su perfil no tiene los permisos correspondientes. Los usuarios que sí tengan esos permisos conservan los enlaces.

## 2. Módulo afectado

- **Módulo:** Pagos
- **Usa Livewire:** no

**Comportamiento actual**

La vista resources/views/payments/paid.blade.php renderiza siempre el enlace Por Pagar y convierte siempre el nombre del proveedor de cada fila en un enlace a suppliers.show. El perfil PRV tiene payments_paid.view con alcance own_supplier, pero no tiene payments_payable.view ni suppliers.view. La ruta de Por Pagar requiere el primer permiso y la ruta de detalle del proveedor requiere el segundo.

**Archivos que se tocan**

- resources/views/payments/paid.blade.php

**Debe permanecer igual**

- El listado de pagos, su consulta y el alcance own_supplier.
- La visibilidad y el funcionamiento de Exportar a Excel y Regularización documental, que corresponden a payments_paid.view.
- El enlace a la orden de compra y el enlace de descarga SPEI, sujetos a sus permisos y alcance actuales.
- El nombre del proveedor y los demás datos de cada fila; sin suppliers.view, el nombre se muestra como texto sin enlace.
- Los permisos de la matriz, middleware, rutas, controladores, modelos, exportaciones y datos.
- El aspecto y acomodo de la pantalla fuera de los dos enlaces indicados.

**Riesgos**

- Una condición basada en el permiso incorrecto podría ocultar enlaces a usuarios autorizados o dejar visible un enlace que responde 403. Se deben usar exactamente payments_payable.view y suppliers.view.
- El ocultamiento en Blade solo mejora la navegación; no sustituye la autorización de las rutas.

## 3. Regla

**Actual**

La vista muestra el enlace Por Pagar y el enlace suppliers.show para todos los usuarios que acceden a Pagos pagados, incluso si no tienen el permiso requerido por esas rutas.

**Nueva**

Renderizar Por Pagar solo dentro de @can('payments_payable.view'). Convertir el nombre del proveedor en enlace a suppliers.show solo dentro de @can('suppliers.view'); sin permiso, mostrar el mismo nombre como texto plano. Mantener sin cambios los demás enlaces.

**Dónde se aplica:** undefined

**Casos afectados**

- Un usuario con payments_paid.view, pero sin payments_payable.view, no ve Por Pagar; un usuario con ambos permisos sí lo ve.
- Un usuario sin suppliers.view ve el nombre del proveedor como texto plano; un usuario con suppliers.view conserva el enlace.
- Una fila sin proveedor sigue mostrando el guion actual.
- Exportar a Excel, Regularización documental, el enlace a la orden de compra y la descarga SPEI conservan su comportamiento.

**Datos históricos**

No aplica: no se modifican ni recalculan datos existentes.

## 4. Reglas de negocio

- payments_payable.view controla solamente la visibilidad del enlace Por Pagar.
- suppliers.view controla solamente la presencia del enlace sobre el nombre del proveedor.
- No se derivan permisos desde roles ni se agregan excepciones específicas para supplier_portal_access; se usa @can con los permisos ya definidos.
- No cambian la autorización del backend, las consultas ni el alcance de los registros.

## 5. Validaciones

_Convenciones de docs/controller-structure-and-notifications.md §4._

## 6. Excepciones a los docs

_Ninguna. Aplican todos los docs listados en la sección 0._

## 7. Preguntas abiertas

_Ninguna._

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
- Cambiar la matriz de permisos, roles, middleware, rutas o autorización del backend.
- Cambiar el alcance de los pagos o de las órdenes de compra.
- Ocultar o modificar Exportar a Excel, Regularización documental, el enlace a la orden de compra o la descarga SPEI.
- Cambiar otras vistas, el navbar o la pantalla de error adjunta.
- Tests permanentes, excluidos por docs/specs/_project.json.
- Modificar archivos protegidos o instalar paquetes.
- Modificar archivos protegidos del perfil del proyecto

## 9. Orden de implementación

1. Lógica de la regla en el punto de aplicación
2. Tratamiento de datos históricos
3. Ajustes en vistas/mensajes

Implementa las capas en este orden sin detenerte entre ellas. Al terminar, lista los archivos creados o modificados por capa.

## 10. Criterios de aceptación

- [ ] La regla nueva se aplica en undefined: Renderizar Por Pagar solo dentro de @can('payments_payable.view'). Convertir el nombre del proveedor en enlace a suppliers.show solo dentro de @can('suppliers.view'); sin permiso, mostrar el mismo nombre como texto plano. Mantener sin cambios los demás enlaces.
- [ ] Caso: Un usuario con payments_paid.view, pero sin payments_payable.view, no ve Por Pagar; un usuario con ambos permisos sí lo ve.
- [ ] Caso: Un usuario sin suppliers.view ve el nombre del proveedor como texto plano; un usuario con suppliers.view conserva el enlace.
- [ ] Caso: Una fila sin proveedor sigue mostrando el guion actual.
- [ ] Caso: Exportar a Excel, Regularización documental, el enlace a la orden de compra y la descarga SPEI conservan su comportamiento.
- [ ] Datos históricos: No aplica: no se modifican ni recalculan datos existentes.
- [ ] Permanece igual: El listado de pagos, su consulta y el alcance own_supplier.
- [ ] Permanece igual: La visibilidad y el funcionamiento de Exportar a Excel y Regularización documental, que corresponden a payments_paid.view.
- [ ] Permanece igual: El enlace a la orden de compra y el enlace de descarga SPEI, sujetos a sus permisos y alcance actuales.
- [ ] Permanece igual: El nombre del proveedor y los demás datos de cada fila; sin suppliers.view, el nombre se muestra como texto sin enlace.
- [ ] Permanece igual: Los permisos de la matriz, middleware, rutas, controladores, modelos, exportaciones y datos.
- [ ] Permanece igual: El aspecto y acomodo de la pantalla fuera de los dos enlaces indicados.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-007]` en el mensaje.

---

_Generado con Estoria Spec Tool v4 · Estoria Studio_
