---
spec_id: HR-015
type: new-module
variant: crud
title: Préstamos, caja de ahorro, formas de pago y descuentos fijos
module: Recursos Humanos
module_key: recursos-humanos_oficina_prestamos
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

# HR-015 · Recursos Humanos — Préstamos, caja de ahorro, formas de pago y descuentos fijos

**Módulo:** Recursos Humanos (`recursos-humanos_oficina_prestamos`) · **Tipo:** Módulo nuevo (CRUD)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Llevar dentro del sistema, con historial de movimientos, los préstamos, la caja de ahorro, la forma de pago de cada persona (su cuenta, otro trabajador, otra persona con cuenta registrada o efectivo) y los descuentos fijos semanales (pensión alimenticia, Infonavit y caja de ahorro). Estos datos alimentan la nómina (HR-013) y el Excel de dispersión (HR-014). Se implementa antes de HR-013. Depende de HR-005 y HR-020.

**Flujo de interacción**

RH jefe abre Préstamos y pulsa "+ Nuevo préstamo": elige a la persona, concepto, importe, a quién se dispersa y el abono por periodo o el número de periodos; el dinero sale en la dispersión del periodo actual y los abonos empiezan el siguiente. En Beneficiarios registra a otras personas con titular, cuenta y banco. En el perfil de cada trabajador define su forma de pago, sus descuentos fijos y consulta su caja de ahorro. Dirección (admin) consulta Préstamos y su detalle sin editar.

## 2. Modelos

### Loan — tabla `loans` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, restrict on delete |
| `concept` | string | no | default Préstamo personal |
| `amount` | decimal(10,2) | no |  |
| `installment` | decimal(10,2) | no | abono por periodo |
| `periods` | unsignedInteger | no | número de abonos |
| `payee_type` | string | no | self, worker, payee · validado con in: |
| `payee_worker_id` | foreignId | sí | FK → workers.id, null on delete |
| `payee_id` | foreignId | sí | FK → payees.id, null on delete |
| `source` | string | no | module · validado con in: |
| `granted_period_start` | date | no | periodo en que se dispersa el dinero |
| `first_period_start` | date | no | primer periodo de abono; posterior al de entrega |
| `status` | string | no | active, cancelled · validado con in: · default active; "liquidado" se calcula con el saldo |
| `cancelled_reason` | text | sí |  |
| `created_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → Payee (`payee_id`)
- `hasMany` → LoanMovement (`loan_id`)

### LoanMovement — tabla `loan_movements` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `loan_id` | foreignId | no | FK → loans.id, cascade on delete |
| `type` | string | no | disbursement, installment, reversal · validado con in: |
| `period_start` | date | no |  |
| `project_id` | foreignId | sí | FK → projects.id, null on delete · Proyecto en cuyo cierre se aplicó el abono |
| `amount` | decimal(10,2) | no |  |
| `reverses_id` | foreignId | sí | FK → loan_movements.id, null on delete |
| `reason` | text | sí |  |
| `created_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → Loan (`loan_id`)

### SavingsMovement — tabla `savings_movements` · Sin controller (Livewire o solo datos)

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, restrict on delete |
| `type` | string | no | contribution, payout, reversal · validado con in: |
| `period_start` | date | no |  |
| `project_id` | foreignId | sí | FK → projects.id, null on delete |
| `amount` | decimal(10,2) | no |  |
| `reverses_id` | foreignId | sí | FK → savings_movements.id, null on delete |
| `reason` | string | sí | Devolución anual o Devolución por baja |

Relaciones:
- `belongsTo` → Worker (`worker_id`)

### Payee — tabla `payees` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `name` | string | no | titular de la cuenta |
| `account` | string | no | cuenta; sensible, solo con seeAmounts |
| `bank` | string | sí |  |
| `relation` | string | sí | parentesco o relación |
| `active` | boolean | no | default true |
| `created_by` | foreignId | sí | FK → users.id, null on delete |

### WorkerPayoutMethod — tabla `worker_payout_methods` · Sin controller (Livewire o solo datos)

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete · el registro vigente tiene ended_at nulo; el historial queda en filas cerradas |
| `method` | string | no | own, worker, payee, cash · validado con in:; "sin definir" se calcula si no hay cuenta |
| `payee_worker_id` | foreignId | sí | FK → workers.id, null on delete |
| `payee_id` | foreignId | sí | FK → payees.id, null on delete |
| `set_by` | foreignId | sí | FK → users.id, null on delete |
| `ended_at` | timestamp | sí | null = vigente |

Relaciones:
- `belongsTo` → Worker (`worker_id`)
- `belongsTo` → Payee (`payee_id`)

### StandingDeduction — tabla `standing_deductions` · Sin controller (Livewire o solo datos)

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `worker_id` | foreignId | no | FK → workers.id, cascade on delete |
| `type` | string | no | alimony, infonavit, savings · validado con in: |
| `weekly_amount` | decimal(10,2) | no |  |
| `from_period_start` | date | no |  |
| `until_period_start` | date | sí | null = vigente |
| `created_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → Worker (`worker_id`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/oficina/prestamos`
- **Nombres de ruta:** `recursos-humanos.oficina.prestamos.*`
- **Middleware:** `role:RH jefe|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/oficina/prestamos` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `index.blade.php` | Blade + Livewire | Préstamos. Componente App\Livewire\Hr\LoansTable (paginado de 25, filtros con #[Url]): persona, concepto, importe, abono, periodos, saldo, estado (Activo, Liquidado, Cancelado) y a quién se dispersa. Modal "+ Nuevo préstamo" (dar el abono o el número de periodos; el otro se calcula). Pestañas Beneficiarios y Caja de ahorro (acumulado por persona y personas sin forma de pago). Admin ve lo mismo sin acciones. |
| `show.blade.php` | Blade | Detalle del préstamo. Plan del préstamo y su lista de movimientos (desembolso, abonos, reversos) con periodo y Proyecto; botón Cancelar con motivo cuando aplica. |
| `worker-payment.blade.php` | Blade + Livewire | Panel de pago del perfil. Componente App\Livewire\Hr\WorkerPaymentPanel, incluido en el perfil de RH jefe (workers/show de HR-020): selector de forma de pago (Su cuenta, Otro trabajador, Otra persona con cuenta registrada, Efectivo) con historial; descuentos fijos semanales con vigencia; saldo de caja de ahorro y movimientos. RH ayudante no lo ve. |

**Búsqueda / filtros:** Filtros en vivo por persona, estado y periodo; búsqueda por nombre

## 5. Reglas de negocio

- Depende de: HR-005 (permisos editLoans, editPayout, viewLoans, seeAmounts) y HR-020 (perfil del trabajador por rol, donde se incluye el panel de pago). HR-013 escribe los abonos y movimientos al cerrar un Proyecto; HR-014 usa la forma de pago para el Excel.
- Debe permanecer igual: el campo de cuenta bancaria del trabajador en workers (base de Trabajadores, se usa para la forma de pago "Su cuenta"). Nada del módulo anterior: los préstamos, la caja de ahorro y los descuentos de la nómina anterior se eliminaron en HR-005 y no se migran.
- Préstamo: se captura el abono por periodo o el número de periodos y el otro se calcula (abono = ceil(importe / periodos / 100) × 100; periodos = ceil(importe / abono)); el abono nunca excede el importe.
- El dinero del préstamo se dispersa en el periodo actual y los abonos empiezan al menos el periodo siguiente; no se registra un préstamo en un periodo ya autorizado (HR-014).
- Abono del periodo: cada préstamo activo (el más antiguo primero) descuenta hasta su abono, sin pasar de su saldo antes del periodo. Se escribe como movimiento al cerrar el Proyecto (HR-013) y se reversa con un movimiento reversal al reabrirlo. El saldo siempre se reconstruye desde los movimientos: no se guarda un saldo editable y los movimientos nunca se editan ni se borran.
- Cancelar un préstamo solo es posible antes del primer abono y antes de autorizar la dispersión de su periodo; después se liquida con abonos o con un ajuste.
- Forma de pago: Su cuenta (requiere cuenta registrada), Otro trabajador (con cuenta) u Otra persona con cuenta registrada (beneficiario), o Efectivo; sin elección y sin cuenta queda "Sin definir". Cada cambio guarda historial y bitácora sensitive. Un préstamo puede dispersarse a su beneficiario (otro trabajador o cuenta registrada) distinto del trabajador.
- Descuentos fijos semanales (pensión alimenticia, Infonavit, caja de ahorro): un cambio aplica desde el periodo actual y conserva el anterior en historial.
- Caja de ahorro: saldo = aportaciones − devoluciones, ignorando movimientos reversados; se devuelve completa en el periodo que contiene la fecha anual configurada (HrSetting savings_payout_date) o en el periodo de la baja ("Devolución por baja").
- RH ayudante y Revisor no ven préstamos, formas de pago, cuentas, descuentos ni caja de ahorro; admin (Dirección) solo consulta. Las cuentas y los importes de la bitácora se marcan sensitive.
- Riesgo: datos financieros y cuentas bancarias; el controller y los componentes validan permiso (403) y no envían cuentas a quien no tiene seeAmounts. Migrar préstamos previos requiere datos fuente confiables: sin importe y abonos no se debe adivinar el saldo.
- Riesgo: una cuenta mal capturada manda dinero a otra persona; se pide confirmación al registrar beneficiarios y se valida el formato de la cuenta.

## 6. Validaciones

| Campo | Regla |
|---|---|
| `worker_id` | requerido, exists:workers, activo |
| `amount` | requerido, numeric, min:0.01 |
| `installment | periods` | al menos uno requerido; installment numeric min:0.01, periods integer min:1 |
| `payee_type` | requerido, in:self,worker,payee; payee_worker_id requerido si worker (distinto del trabajador y con cuenta); payee_id requerido si payee |
| `first_period_start` | nullable, date, posterior al periodo de entrega |
| `name, account (beneficiario)` | requeridos, string; account con formato de cuenta válido; bank nullable |
| `method (forma de pago)` | requerido, in:own,worker,payee,cash; own solo con cuenta registrada |
| `weekly_amount` | requerido, numeric, min:0 |

## 7. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Los movimientos (préstamos y caja de ahorro) nunca se editan ni se borran; las correcciones se hacen con reversal, por lo que no hay edit ni destroy de movimientos.
- Se modifica workers/show (HR-020) solo para incluir el componente WorkerPaymentPanel.
- Controllers Hr\Office\LoanController, PayeeController, WorkerPayoutController y StandingDeductionController; nombres de ruta rh.office.loans.*; las vistas viven en resources/views/hr/office/loans/ en lugar de la carpeta derivada del prefijo (HR-005).

## 8. Preguntas abiertas

- [x] ¿Qué pasará con los préstamos y la caja de ahorro ya registrados (en PayrollLine y fuera del sistema)? → **No esta en uso, se eliminan.**
- [x] ¿Los descuentos fijos de hoy (Infonavit, pensión) se migran desde la nómina anterior o se capturan de nuevo? → **Se capturan de nuevo: el módulo anterior se elimina y no se migra información (solo la base de Trabajadores).**
- [x] ¿La fecha anual de devolución de la caja de ahorro es 15/12? → **El prototipo usa 15/12 configurable (hr_settings.savings_payout_date).**
- [x] ¿Qué formato de cuenta se valida (CLABE de 18 dígitos u otro)? → **CLABE**
- [x] ¿Hay un tope de préstamo por persona o de descuento semanal? → **No**

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
- Intereses, comisiones y reestructura de préstamos.
- Validación de cuentas contra el banco.
- Cobro de préstamos a personas dadas de baja (se liquida con ajuste o fuera del sistema).
- Cálculo de la nómina y cierre de Proyecto (HR-013).
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
- [ ] `Loan` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(Payee), hasMany(LoanMovement).
- [ ] `LoanMovement` declara `$fillable`, `$casts` y las relaciones belongsTo(Loan).
- [ ] `SavingsMovement` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker).
- [ ] `Payee` declara `$fillable`, `$casts`.
- [ ] `WorkerPayoutMethod` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker), belongsTo(Payee).
- [ ] `StandingDeduction` declara `$fillable`, `$casts` y las relaciones belongsTo(Worker).
- [ ] `Route::resource('recursos-humanos.oficina.prestamos', LoanController::class)` bajo middleware `role:RH jefe|admin`; `create()` redirige a `recursos-humanos.oficina.prestamos.index`.
- [ ] Existe `resources/views/recursos-humanos/oficina/prestamos/index.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] Existe `resources/views/recursos-humanos/oficina/prestamos/show.blade.php` que extiende `layouts.app`.
- [ ] Existe `resources/views/recursos-humanos/oficina/prestamos/worker-payment.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.oficina.prestamos.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Filtros en vivo por persona, estado y periodo; búsqueda por nombre.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de Loan, Payee, WorkerPayoutMethod, StandingDeduction registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-015]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
