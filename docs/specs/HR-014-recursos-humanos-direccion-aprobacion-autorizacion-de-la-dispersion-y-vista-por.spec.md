---
spec_id: HR-014
type: new-module
variant: workflow
title: Autorización de la dispersión y vista Por persona
module: Recursos Humanos
module_key: recursos-humanos_direccion_aprobacion
status: aprobado
author: Jorge Peña
reviewers: [Mariam]
branch: moon
created: 2026-11-03
profile: docs/specs/_project.json
docs:
  - docs/controller-structure-and-notifications.md
  - docs/ui-kit-design-system.md
  - docs/ui-kit-table-structure.md
open_questions: 0
---

# HR-014 · Recursos Humanos — Autorización de la dispersión y vista Por persona

**Módulo:** Recursos Humanos (`recursos-humanos_direccion_aprobacion`) · **Tipo:** Módulo nuevo (CRUD + Workflow)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`

## 1. Propósito

Cerrar el proceso de nómina: Dirección (rol admin) revisa el periodo con todos sus Proyectos cerrados por RH, autoriza la dispersión o devuelve un Proyecto a RH jefe con comentario, y descarga el Excel de dispersión. Incluye la vista "Por persona" para consultar totales y detalle por trabajador. Depende de HR-005, HR-012, HR-013 y HR-015.

**Flujo de interacción**

Dirección abre Aprobación, elige el periodo y ve cada Proyecto con su total, los cambios de RH posteriores al cierre, los préstamos y avisos de pago anticipado. Puede "Devolver" un Proyecto con un comentario obligatorio (vuelve a RH jefe) o "Autorizar dispersión" del periodo completo; tras autorizar descarga el Excel de dispersión (hoja con Nombre, Importe y Cuenta; hoja aparte para efectivo). En Por persona consulta, con filtros combinables, los totales y el detalle por Proyecto y día, y exporta el CSV de lo filtrado. RH jefe consulta Aprobación en solo lectura.

## 2. Modelos

### PayrollAuthorization — tabla `payroll_authorizations`

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `period_start` | date | no | único (viernes del periodo) |
| `status` | string | no | pending, authorized · validado con in: · default pending |
| `total` | decimal(10,2) | sí | total autorizado de todos los Proyectos; null hasta autorizar |
| `authorized_by` | foreignId | sí | FK → users.id, null on delete |
| `authorized_at` | timestamp | sí |  |
| `snapshot` | json | sí | totales por Proyecto y por forma de pago al autorizar, con su checksum |

Relaciones:
- `belongsTo` → User (`authorized_by`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/direccion/aprobacion`
- **Nombres de ruta:** `recursos-humanos.direccion.aprobacion.*`
- **Middleware:** `role:RH jefe|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/direccion/aprobacion` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `index.blade.php` | Blade | Aprobación (lectura para RH jefe y admin; autorizar, devolver y descargar el Excel solo admin, validado en el controller). Selector de periodo. Tarjetas por Proyecto con estado y total, cambios de RH posteriores al cierre, préstamos y aviso de pago anticipado. Botones Devolver (modal con comentario obligatorio) y "Autorizar dispersión" (deshabilitado con su razón si falta cerrar o hay un Proyecto devuelto). Tras autorizar aparece "Descargar Excel de dispersión". RH jefe ve la pantalla sin botones. |
| `show.blade.php` | Blade | Por persona. Por omisión muestra a todas las personas con sus totales; filtros combinables por Proyecto, Obra, cuadrilla, cargo, nombre y periodo; el detalle muestra cada Proyecto y cada día; botón CSV con lo filtrado. |

**Búsqueda / filtros:** Filtros de Por persona: Proyecto, Obra, cuadrilla, cargo, nombre y periodo

## 5. Estatus y transiciones

| Valor | Etiqueta | Puede pasar a |
|---|---|---|
| `pending` | Pendiente de autorizar | authorized |
| `authorized` | Autorizado |  |

## 6. Reglas de negocio

- Depende de: HR-005 (Dirección = admin, decisión del equipo), HR-012 (PayrollProjectStage y su devolución), HR-013 (filas cerradas) y HR-015 (formas de pago y préstamos para el archivo). No se crea el rol Dirección: autorizar, devolver y descargar son exclusivos de admin.
- Debe permanecer igual: nada del módulo anterior (los periodos y la nómina anteriores se eliminaron en HR-005); se usa maatwebsite/excel, ya instalado (perfil del proyecto), sin paquetes nuevos.
- Se puede autorizar un periodo cuando todos los Proyectos con personal están Cerrados por RH, ninguno tiene una devolución pendiente y no hay personas con pagos sin definir que impidan generar el archivo bancario (se listan como pagos sin forma de pago).
- Devolver un Proyecto exige comentario; el Proyecto pasa de validated a reviewed (se reabre con los reversos de HR-013), se guarda en PayrollProjectStage.returned y queda marcado como devuelto a RH jefe hasta que se vuelva a cerrar.
- Al autorizar se guarda quién, cuándo, total y snapshot, se congela el periodo (ningún cambio posterior lo modifica; todo va a ajustes en el siguiente periodo) y se deja un punto de recuperación (HR-016). La autorización es irreversible: se confirma con un diálogo.
- Excel de dispersión (app/Exports/Hr/DispersionExport): hoja Banco con titular, Importe y Cuenta completa según la forma de pago resuelta (su cuenta, otro trabajador, otra persona con cuenta registrada); hoja Efectivo con los pagos en efectivo; los préstamos dispersados en el periodo se incluyen en el importe de su beneficiario. Antes de descargar se verifica que Banco + Efectivo + pagos sin forma de pago = lo autorizado + préstamos.
- El Excel contiene cuentas completas: solo admin puede descargarlo, la ruta valida el rol (403) y cada descarga se registra en bitácora como sensitive.
- Por persona: totales del periodo (o rango) por trabajador con detalle por Proyecto y día; respeta seeAmounts y los filtros combinados; el CSV exporta exactamente lo filtrado.
- Todas las acciones (autorizar, devolver, descargar) se escriben en bitácora; las que incluyen importes se marcan sensitive; se notifica con NotificationService.

## 7. Validaciones

| Campo | Regla |
|---|---|
| `periodStart (ruta)` | date, viernes, con todos los Proyectos con personal en validated |
| `comment (devolver)` | requerido, string |
| `project (devolver)` | exists:projects, con estado validated en el periodo |
| `filtros de Por persona` | nullable; project_id y project_work_id exists; periodo date |

## 8. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Se agrega app/Exports/Hr/DispersionExport.php con maatwebsite/excel, que está en packages del perfil; no se instalan paquetes nuevos.
- Autorizar y devolver son acciones POST (transiciones del estatus) y no un update del recurso.
- Controllers Hr\Director\ApprovalController y Hr\Director\PeopleController; nombres de ruta rh.director.approval.* y rh.director.people.*; las vistas viven en resources/views/hr/director/ en lugar de la carpeta derivada del prefijo (HR-005).

## 9. Preguntas abiertas

- [x] ¿Qué formato exacto debe tener el Excel de dispersión que usa el banco (columnas, orden, clave de banco)? → **El prototipo usa Nombre, Importe y Cuenta, con hoja aparte para efectivo.**
- [x] ¿Dirección autoriza todo el periodo de una vez o puede autorizar por Proyecto? → **Según el prototipo, el periodo completo; solo la devolución es por Proyecto.**
- [x] ¿Después de autorizar, quién marca el periodo como pagado? → **Lo hace RH Jefe**

## 10. Fuera de alcance

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
- Integración bancaria directa (envío del archivo).
- Autorización en varios niveles o firma electrónica.
- Modificar archivos protegidos del perfil del proyecto

## 11. Orden de implementación

1. Migraciones (en orden de FKs)
2. Modelos: fillable, casts, relaciones
3. Controller(s) y rutas en routes/web.php
4. Vistas Blade y entrada en el menú de navegación

Detente al terminar cada capa y lista los archivos creados o modificados.

## 12. Criterios de aceptación

- [ ] `php artisan migrate` corre sin errores; las migraciones se crean en orden de dependencia de FKs.
- [ ] `PayrollAuthorization` declara `$fillable`, `$casts` y las relaciones belongsTo(User).
- [ ] `Route::resource('recursos-humanos.direccion.aprobacion', PayrollAuthorizationController::class)` bajo middleware `role:RH jefe|admin`; `create()` redirige a `recursos-humanos.direccion.aprobacion.index`.
- [ ] Existe `resources/views/recursos-humanos/direccion/aprobacion/index.blade.php` que extiende `layouts.app`.
- [ ] Existe `resources/views/recursos-humanos/direccion/aprobacion/show.blade.php` que extiende `layouts.app`.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.direccion.aprobacion.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Filtros de Por persona: Proyecto, Obra, cuadrilla, cargo, nombre y periodo.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de PayrollAuthorization registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] El estatus solo cambia por las transiciones declaradas en la tabla de estatus; una transición no permitida regresa con flash `error`.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-014]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
