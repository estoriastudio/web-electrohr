---
spec_id: HR-013
type: new-module
variant: crud
title: Nómina etapa 2: montos, cierre y pago anticipado
module: Recursos Humanos
module_key: recursos-humanos_oficina_nomina
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
  - docs/livewire-conventions.md
open_questions: 0
---

# HR-013 · Recursos Humanos — Nómina etapa 2: montos, cierre y pago anticipado

**Módulo:** Recursos Humanos (`recursos-humanos_oficina_nomina`) · **Tipo:** Módulo nuevo (CRUD)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Segunda etapa de la nómina: RH jefe toma los Proyectos revisados, define montos (valor de la hora extra, reembolsos), descuentos, caja de ahorro y el salario final de cada persona, y cierra el Proyecto, congelando las filas y aplicando los abonos de préstamos y de caja de ahorro. Incluye el cálculo del valor del día, los ajustes de periodos cerrados y el pago anticipado. Depende de HR-005, HR-010, HR-012 y HR-015.

**Flujo de interacción**

RH jefe abre Nómina RH, elige el periodo y ve los Proyectos con su estado. En un Proyecto revisado captura por persona: valor de HE, reembolsos, descuentos, caja de ahorro (aportación o devolución con motivo) y salario final; puede pegar columnas desde Excel (una sola entrada en bitácora). Si el salario final difiere más de $50 del propuesto debe dar un motivo. "Cerrar Proyecto" congela los valores y escribe los abonos. Puede regresar el Proyecto al Revisor o reabrirlo. Con "Pagar hasta el [día]" define un pago anticipado: los días posteriores se pagan con el periodo siguiente.

## 2. Modelos

### PayrollEntry — tabla `payroll_entries` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `payroll_project_stage_id` | foreignId | no | FK → payroll_project_stages.id, cascade on delete · único junto con worker_id |
| `worker_id` | foreignId | no | FK → workers.id, restrict on delete |
| `day_amounts` | json | sí | importes distintos al valor del día solo en Descanso, Vacaciones y Libranza (fecha → importe) |
| `ot_hourly_rate` | decimal(10,2) | sí | valor de la hora extra; sugerido = sueldo semanal / 7 / 8 × 2 |
| `reimbursement` | decimal(10,2) | no | default 0 |
| `deductions` | json | sí | descuentos de la semana con concepto e importe |
| `savings_contribution` | decimal(10,2) | no | default 0 |
| `savings_return` | decimal(10,2) | no | default 0 |
| `savings_reason` | string | sí | Devolución anual o Devolución por baja |
| `final_salary` | decimal(10,2) | sí | salario final fijado por RH jefe |
| `difference_reason` | text | sí | obligatorio si final_salary difiere más de 50 pesos del propuesto |
| `frozen` | json | sí | fila congelada al cerrar el Proyecto |

Relaciones:
- `belongsTo` → PayrollProjectStage (`payroll_project_stage_id`)
- `belongsTo` → Worker (`worker_id`)
- `hasMany` → PayrollEntryAdjustment (`payroll_entry_id`)

### PayrollCutoff — tabla `payroll_cutoffs` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `period_start` | date | no | único |
| `last_paid_date` | date | no | dentro del periodo y anterior al jueves |
| `reason` | text | sí |  |
| `set_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → User (`set_by`)

### PayrollEntryAdjustment — tabla `payroll_entry_adjustments` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `payroll_entry_id` | foreignId | no | FK → payroll_entries.id, cascade on delete |
| `payroll_adjustment_id` | foreignId | no | FK → payroll_adjustments.id, restrict on delete · tabla de HR-010 · único |
| `amount` | decimal(10,2) | no | importe cobrado en esta fila (con signo) |

Relaciones:
- `belongsTo` → PayrollEntry (`payroll_entry_id`)
- `belongsTo` → PayrollAdjustment (`payroll_adjustment_id`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/oficina/nomina`
- **Nombres de ruta:** `recursos-humanos.oficina.nomina.*`
- **Middleware:** `role:RH jefe|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/oficina/nomina` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `index.blade.php` | Blade | Nómina RH. Selector de periodo, bloque de "Pagar hasta el…" y tarjetas por Proyecto con estado, total y avisos. Los Proyectos aún En revisión se ven bloqueados con la razón. |
| `show.blade.php` | Blade + Livewire | Captura de RH jefe por Proyecto. Componente App\Livewire\Hr\PayrollEntriesTable: tabla por persona con días (incidencia y valor), HE y valor de la hora, reembolsos, descuentos, préstamo, caja de ahorro, ajustes, salario propuesto y final. Celdas pegables desde Excel. Panel "Cambios después de la revisión", motivo obligatorio cuando la diferencia supera $50, indicador de datos incompletos y avisos de Libranza con % por definir. Botones Cerrar Proyecto, Regresar al Revisor y Reabrir. |

**Búsqueda / filtros:** Filtros por periodo, Proyecto, Obra y persona

## 5. Reglas de negocio

- Depende de: HR-005 (HrSetting y permisos), HR-010 (PayrollAdjustment), HR-012 (PayrollProjectStage y su estado) y HR-015 (préstamos, caja de ahorro, forma de pago y descuentos fijos que se consumen aquí).
- Debe permanecer igual: nada del módulo anterior; PayrollPeriod, PayrollLine, PayrollGenerationService y WorkerIncentiveService ya fueron retirados en HR-005 y no se migra información. Las filas de este spec son la única nómina.
- Valor del día = sueldo semanal ÷ 7 (plano). Asistencia en domingo = sueldo semanal ÷ divisor del domingo (6) + bono ($50), configurables en HrSetting. Libranza A/B/C = valor del día × % configurado; sin % definido paga 100 % con aviso "por definir". Descanso, Vacaciones y Libranza pueden tener otro importe capturado por RH jefe.
- Valor sugerido de la hora extra = sueldo semanal ÷ 7 ÷ 8 × 2; RH jefe puede cambiarlo por persona. Los importes en pesos se guardan de forma consistente (decimal(10,2)) y se redondean igual en pantalla, cierre y Excel.
- El salario final difiere del propuesto: si la diferencia supera $50 se exige motivo. Se puede pegar desde Excel; el pegado se valida celda por celda y registra una sola entrada de bitácora.
- Cerrar Proyecto exige: estado reviewed, sin datos incompletos de personas (forma de pago, etc.) y sin correcciones pendientes de esas personas. Al cerrar, en una sola transacción (DB::transaction): congela las filas, escribe los abonos de préstamo (el más antiguo primero, hasta su saldo) y los movimientos de caja de ahorro (aportación o devolución), cobra los ajustes pendientes del periodo (PayrollEntryAdjustment), pasa a validated y deja un punto de recuperación (HR-016). Un fallo parcial no puede duplicar abonos.
- Reabrir (a RH jefe o al Revisor) exige motivo; si estaba cerrado, reversa con nuevos movimientos REVERSAL los abonos de préstamo y caja de ahorro (nunca se borran), descongela y libera los ajustes cobrados.
- Un cambio de datos de un periodo ya cerrado no reabre el periodo: HR-010 registra un PayrollAdjustment y aquí se calcula su importe (diferencia entre la fila viva y la congelada, más lo ya ajustado) y se cobra en el siguiente periodo abierto de la persona (que no esté cerrado ni autorizado).
- Pago anticipado: "Pagar hasta el [día]" dentro del periodo; los días posteriores se pagan con el periodo siguiente (columnas "se paga la semana siguiente"). El incentivo semanal se define en el último día pagado y la revisión abre al día siguiente a las 10:00. Solo se puede cambiar mientras ningún Proyecto del periodo haya sido revisado y el periodo no esté autorizado.
- Dirección (admin) puede devolver un Proyecto cerrado a RH jefe con comentario (HR-014); mientras haya un Proyecto devuelto no se autoriza el periodo.
- Toda acción con importes se escribe en bitácora como sensitive y no la ven el RH ayudante ni el Revisor; el Revisor y el RH ayudante no acceden a esta ventana (403).
- Riesgo: los importes en decimales deben ser coherentes entre Excel, pantalla y cierre.

## 6. Validaciones

| Campo | Regla |
|---|---|
| `ot_hourly_rate` | nullable, numeric, min:0 |
| `reimbursement, savings_contribution, savings_return` | nullable, numeric, min:0 |
| `deductions.*.amount` | numeric, min:0; concept requerido |
| `final_salary` | nullable, numeric, min:0 |
| `difference_reason` | requerido si \|final_salary − propuesto\| > 50 |
| `last_paid_date` | requerido, date, dentro del periodo y menor al jueves |
| `reason (reapertura o devolución)` | requerido, string |

## 7. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- El cierre, la reapertura y el pago anticipado son acciones POST en transacción con redirect o dispatch('flash'); la captura de celdas es de un componente Livewire.
- Controllers Hr\Office\PayrollController y Hr\Office\PayrollCutoffController; nombres de ruta rh.office.payroll.*; las vistas viven en resources/views/hr/office/payroll/ en lugar de la carpeta derivada del prefijo (HR-005).
- PayrollCalculator y PayrollStageService son servicios simples en App\Services\Hr (no Policies ni Gates).

## 8. Preguntas abiertas

- [x] ¿Cómo se relacionan estas filas con la nómina del módulo anterior (PayrollLine: fiscal, complemento, Infonavit, comidas)? → **No se relacionan: el módulo anterior de nómina se elimina (HR-005) y no hay convivencia ni migración. Las filas de este spec son la única nómina.**
- [x] ¿Las HE nuevas (overtime_entries) son el único origen de las horas extra? → **Sí: el incentivo del módulo anterior se elimina y overtime_entries es el único origen de verdad.**
- [x] ¿El valor de la hora extra sugerido (doble de hora de 8 h) es la regla vigente de la empresa? → **Se puede determina manualmente.**
- [x] ¿Cuáles son los descuentos fijos y variables que existen (pensión alimenticia, Infonavit, material extraviado)? → **El prototipo usa Pensión alimenticia, Infonavit y Caja de ahorro como descuentos fijos, y son cofigurables por Worker**

## 9. Fuera de alcance

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
- Timbrado fiscal, CFDI, IMSS e Infonavit reales (el prototipo no los calcula).
- Autorización de Dirección y Excel de dispersión (HR-014).
- Préstamos, caja de ahorro y formas de pago (HR-015): este spec solo los consume.
- Modificar archivos protegidos del perfil del proyecto

## 10. Orden de implementación

1. Migraciones (en orden de FKs)
2. Modelos: fillable, casts, relaciones
3. Controller(s) y rutas en routes/web.php
4. Componentes Livewire
5. Vistas Blade y entrada en el menú de navegación

Detente al terminar cada capa y lista los archivos creados o modificados.

## 11. Criterios de aceptación

- [ ] `php artisan migrate` corre sin errores; las migraciones se crean en orden de dependencia de FKs.
- [ ] `PayrollEntry` declara `$fillable`, `$casts` y las relaciones belongsTo(PayrollProjectStage), belongsTo(Worker), hasMany(PayrollEntryAdjustment).
- [ ] `PayrollCutoff` declara `$fillable`, `$casts` y las relaciones belongsTo(User).
- [ ] `PayrollEntryAdjustment` declara `$fillable`, `$casts` y las relaciones belongsTo(PayrollEntry), belongsTo(PayrollAdjustment).
- [ ] `Route::resource('recursos-humanos.oficina.nomina', PayrollEntryController::class)` bajo middleware `role:RH jefe|admin`; `create()` redirige a `recursos-humanos.oficina.nomina.index`.
- [ ] Existe `resources/views/recursos-humanos/oficina/nomina/index.blade.php` que extiende `layouts.app`.
- [ ] Existe `resources/views/recursos-humanos/oficina/nomina/show.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.oficina.nomina.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Filtros por periodo, Proyecto, Obra y persona.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de PayrollEntry, PayrollCutoff registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-013]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
