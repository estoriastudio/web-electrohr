---
spec_id: HR-005
type: new-module
variant: crud
title: Fundación por rol: roles, reloj de periodos, bitácora y configuración
module: Recursos Humanos
module_key: recursos-humanos_oficina_configuracion
status: implementado
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

# HR-005 · Recursos Humanos — Fundación por rol: roles, reloj de periodos, bitácora y configuración

**Módulo:** Recursos Humanos (`recursos-humanos_oficina_configuracion`) · **Tipo:** Módulo nuevo (CRUD)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Crear la base común del nuevo proceso de asistencia y nómina (Prototipo RH v6): cinco perfiles nuevos como roles del sistema (Dirección es admin) con sus ventanas y permisos, el reloj de periodos de nómina (viernes a jueves, America/Mexico_City), la bitácora con visibilidad por rol, la configuración editable de reglas, la instalación de Livewire para las pantallas interactivas de los specs siguientes y el retiro del módulo anterior (solo se conserva la base de Trabajadores; no hay convivencia ni migración). Los specs HR-006 a HR-020 se apoyan en esta capa.

**Flujo de interacción**

Al iniciar sesión, cada usuario ve en la navbar solo las ventanas de su rol. Las ventanas cuelgan de cuatro grupos de rutas con el patrón de "Mis proyectos": campo (Supervisor y Gerente de proyecto), oficina (RH jefe y RH ayudante), revisión (Revisor de nómina) y dirección (admin). Cada controller acota los datos al alcance del usuario y valida el permiso (403), no solo oculta el menú. RH jefe abre Configuración para editar los % de Libranza A/B/C, la regla del domingo, la hora límite de HE y la fecha de pago de la caja de ahorro; RH jefe, RH ayudante y admin consultan la Bitácora.

## 2. Modelos

### HrSetting — tabla `hr_settings` · Resource controller propio

auditable: sí · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `key` | string | no | único · claves: day_off_types, sunday, ot_deadline_hour, savings_payout_date |
| `value` | json | no | valor de la clave; casteado a array en el modelo |
| `updated_by` | foreignId | sí | FK → users.id, null on delete |

Relaciones:
- `belongsTo` → User (`updated_by`)

### HrAuditLog — tabla `hr_audit_logs` · Resource controller propio

auditable: no · soft_deletes: no

| Columna | Tipo | Null | Notas |
|---|---|---|---|
| `user_id` | foreignId | sí | FK → users.id, null on delete |
| `role` | string | no | rol con el que actuó el usuario |
| `action` | string | no | p. ej. Corrección de incidencia |
| `entity` | string | no | Asistencia, Asignación, Nómina… |
| `entity_id` | string | sí |  |
| `worker_id` | foreignId | sí | FK → workers.id, null on delete · indexado |
| `before` | text | sí |  |
| `after` | text | sí |  |
| `reason` | text | sí |  |
| `sensitive` | boolean | no | default false · true si incluye importes (préstamos, forma de pago, nómina) |

Relaciones:
- `belongsTo` → User (`user_id`)
- `belongsTo` → Worker (`worker_id`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/oficina/configuracion`
- **Nombres de ruta:** `recursos-humanos.oficina.configuracion.*`
- **Middleware:** `auth + role:RH jefe|admin para Configuración; la Bitácora usa role:RH jefe|RH ayudante|Revisor de nómina|admin con visibilidad filtrada`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/oficina/configuracion` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `index.blade.php` | Blade | Configuración (solo RH jefe y admin). Formulario con la tabla de Libranza A/B/C (% o vacío = por definir), regla del domingo (divisor y bono), hora límite de HE y fecha de pago de la caja de ahorro. Usa las clases del UI kit; muestra los cambios recientes de la bitácora. |
| `audit.blade.php` | Blade + Livewire | Bitácora. Componente App\Livewire\Hr\AuditLogTable (paginado de 25, filtros en vivo con #[Url] por trabajador, entidad y fechas) con quién, cuándo, acción, antes, después y motivo. Oculta las entradas sensitive a RH ayudante y Revisor. Se monta desde una vista Blade que retorna Hr\Shared\AuditLogController. |

## 5. Reglas de negocio

- Roles nuevos (Spatie, guard web), con el mismo nombre del prototipo: Supervisor, Gerente de proyecto, RH jefe, RH ayudante y Revisor de nómina. Dirección no es un rol: equivale a admin (decisión del equipo), que conserva acceso a todo y es el único que autoriza la dispersión.
- Engineer y Supervisor conviven: un usuario puede tener ambos roles, y Engineer sigue dando acceso a Mis proyectos y a los demás módulos donde se usa. El alcance de campo se resuelve por las Obras del usuario (supervisor/residente o gerente), no por el rol Engineer.
- Ventanas por rol (navbar, en layouts/partials/_navbar.blade.php): Supervisor y Gerente (Asistencias, Asignación, Mis trabajadores); RH jefe (Asignación, Asistencias, Incongruencias, Controles, Trabajadores, Nómina RH, Préstamos, Por persona, Costos, Aprobación en lectura, Movimientos, Respaldos, Configuración, Bitácora); RH ayudante (Asignación, Asistencias, Incongruencias, Controles, Trabajadores, Movimientos, Bitácora); Revisor de nómina (Revisión de nómina, Bitácora); admin (Aprobación, Por persona, Costos, Préstamos, Movimientos, Respaldos, Bitácora y todo lo demás).
- Alcance de campo: Supervisor = Obras donde es supervisor_user_id o resident_user_id; Gerente = Obras de los Proyectos donde es manager_user_id. Solo opera personal asignado a sus Obras (mensaje "no está asignado a una de tus Obras").
- Permisos (HrPermissions::can): seeAmounts (RH jefe, admin); seeIncentives (Supervisor, Gerente, RH jefe, Revisor, admin; RH ayudante no); hrInbox (RH jefe, RH ayudante); payrollReview (Revisor); payrollHR, editPayout, editLoans y config (RH jefe); viewLoans y backups (RH jefe, admin); authorize (solo admin). admin pasa todos.
- Periodo de nómina = viernes a jueves; el id del periodo es la fecha del viernes. HrClock expone now(), today(), yesterday(), periodOf(), isThursday() y minutes() con la zona horaria America/Mexico_City (config('app.timezone') debe ser esa).
- No hay simulador de fecha/hora ni Guion de demo dentro del sistema (decisión del equipo): HrClock usa la hora real y los escenarios del prototipo (D6-01 … D6-37) solo son criterios de aceptación.
- Livewire se instala con composer (decisión del equipo; ya está en packages del perfil) siguiendo docs/livewire-conventions.md: componentes de clase en App\Livewire\Hr montados desde vistas Blade que retornan los controllers, sin componentes full-page. Livewire 3 inyecta sus assets, por lo que no se edita layouts/app.blade.php (protegido). La Bitácora es el primer componente.
- Retiro del módulo anterior (sin convivencia ni migración, decisión del equipo): el único dato que se conserva es la base de Trabajadores (workers, worker_files, worker_dc3s, worker_terminations y position_categories) con WorkerController, WorkerFileController, WorkerDc3Controller, WorkerTerminationController, WorkerTerminationService, PositionCategoryController y sus vistas, que pasan a los roles nuevos (HR-020). Se eliminan en este spec, con migraciones nuevas que borran sus tablas, el resto de módulos anteriores porque no se usan: asistencias (WorkerAttendanceController, WorkerAttendanceExport y sus vistas), vacaciones (WorkerVacationController), incentivos (IncentiveController, WorkerIncentiveService), nómina (PayrollPeriodController, PayrollLineController, PayrollCostReportController, PayrollFieldReportController, PayrollCalculationService, PayrollGenerationService, PayrollCostReportService), destajo (PieceworkWeeklyEntryController, PieceworkCalculationService), miembros de cuadrilla (WorkerGroupMembershipService, WorkerGroupRelocationService y la tabla worker_group_members) y sus modelos, rutas human_resources.* correspondientes, vistas de resources/views/human_resources/{attendances,incentives,payroll-lines,payroll-periods,payroll-reports,piecework,vacations} y entradas de navbar. La pantalla de cuadrillas se simplifica en HR-017 y WorkerController, WorkerGroup, PositionCategory, ProjectWork, Notification y Worker dejan de referenciar los modelos retirados.
- Las tablas que los specs nuevos vuelven a necesitar con otra estructura (worker_attendances en HR-007 y worker_vacations en HR-009) se eliminan aquí y se crean de nuevo en esos specs; no hay backfill en ningún caso. El rol Recursos Humanos queda sustituido por RH jefe y RH ayudante: el equipo reasigna usuarios desde el panel Admin y retira el rol viejo; ningún middleware nuevo lo usa.
- Toda acción de HR-006 a HR-020 que el prototipo registra se escribe con HrAudit::log() (antes, después, motivo). Las entradas sensitive no se muestran a RH ayudante ni a Revisor.
- La bitácora no sustituye al NotificationService: los controllers y componentes siguen notificando según docs/controller-structure-and-notifications.md y docs/livewire-conventions.md.
- Cada controller y componente valida permiso y alcance (403); ocultar el botón del menú no es suficiente.
- Las ventanas por rol de la navbar viven en _navbar.blade.php porque layouts/app.blade.php es un archivo protegido.

## 6. Validaciones

| Campo | Regla |
|---|---|
| `day_off_types.*.pct` | nullable, numeric, between:0,100 |
| `sunday.divisor` | requerido, integer, min:1 |
| `sunday.bonus` | requerido, numeric, min:0 (en pesos) |
| `ot_deadline_hour` | requerido, integer, between:0,23 |
| `savings_payout_date` | requerido, date |

## 7. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- Se modifica RolesAndPermissionsSeeder (seeders están fuera de alcance por defecto) solo para crear los cinco roles nuevos; no se crean seeders de datos.
- Se elimina código y se borran tablas del módulo anterior (migración nueva, no se edita ninguna existente): no hay backfill ni down útil porque el equipo confirmó que esos datos no se usan.
- HrPermissions es una clase de servicio simple, no Policies/Gates (se mantienen fuera de alcance).
- Se instala livewire/livewire con composer; está en packages del perfil, por lo que no viola "instalar paquetes no listados".
- Los controllers de campo, oficina, revisión y dirección viven en App\Http\Controllers\Hr\{Field,Office,Review,Director,Shared}; las bandejas y vistas de captura no son CRUD completo y no tienen create().
- Nombres de ruta rh.office.settings.* y rh.shared.audit.* (en lugar del derivado del prefijo), igual que my-projects.* en Mis proyectos; las vistas viven en resources/views/hr/{field,office,review,director,shared}/ en lugar de la carpeta derivada del prefijo.
- Los valores iniciales de hr_settings se insertan en la migración (no en un seeder).

## 8. Preguntas abiertas

- [x] ¿Se instala Livewire (está en packages del perfil pero no en composer.json) para la captura en bloque, o se mantiene Blade + JSON como la asistencia actual? → **Se instala Livewire**
- [x] ¿Los usuarios Engineer actuales migran al rol Supervisor, o conviven ambos roles (un usuario con los dos)? → **Un usuario con los dos ya que el rol Engineer se usa en otros modulos.**
- [x] ¿Se replica el simulador de fecha/hora y el Guion de demo del prototipo dentro del sistema (solo admin, no producción) o los escenarios solo se usan como criterios de aceptación? → **Los escensarios solo se usan como criterios de aceptación**
- [x] ¿Dirección es un rol nuevo o basta con admin? → **Basta con admin**
- [x] ¿Cuál es la zona horaria oficial para los cortes de las 10:00 y del viernes? → **America/Mexico_City**
- [x] ¿Qué roles tendrán los nuevos perfiles y cómo se distinguen sus vistas? → **Decidido por el equipo: roles nuevos con el mismo nombre del prototipo para distinguir las vistas, con el mismo enfoque estructural que Mis proyectos.**
- [x] ¿Convive el módulo anterior de RH (asistencias, vacaciones, nómina, incentivos, destajo, cuadrillas) con el nuevo y se migra su información? → **No: no hay convivencia ni traspaso. Solo se conserva la base de datos de Trabajadores; el resto no se usa y se elimina.**

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
- Retirar el rol Recursos Humanos de la base de datos (lo hace el equipo desde el panel Admin) y migrar usuarios a los roles nuevos.
- Simulador de fecha/hora y Guion de demo dentro del sistema.
- Crear un rol Dirección: se usa admin.
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
- [ ] `HrSetting` declara `$fillable`, `$casts` y las relaciones belongsTo(User).
- [ ] `HrAuditLog` declara `$fillable`, `$casts` y las relaciones belongsTo(User), belongsTo(Worker).
- [ ] `Route::resource('recursos-humanos.oficina.configuracion', HrSettingController::class)` bajo middleware `auth + role:RH jefe|admin para Configuración; la Bitácora usa role:RH jefe|RH ayudante|Revisor de nómina|admin con visibilidad filtrada`; `create()` redirige a `recursos-humanos.oficina.configuracion.index`.
- [ ] Existe `resources/views/recursos-humanos/oficina/configuracion/index.blade.php` que extiende `layouts.app`.
- [ ] Existe `resources/views/recursos-humanos/oficina/configuracion/audit.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.oficina.configuracion.index`.
- [ ] El index pagina con `paginate(25)`.
- [ ] Los errores de validación se muestran en el formulario y el modal de creación se reabre si hay errores.
- [ ] Eliminar pide confirmación previa.
- [ ] store/update/destroy de HrSetting registran NotificationService con `type` = nombre del modelo (también desde componentes Livewire).
- [ ] Los mensajes flash usan las llaves success / error / info.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-005]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
