---
spec_id: HR-011
type: new-module
variant: listing
title: Mis trabajadores del campo: lista y perfil reducido
module: Recursos Humanos
module_key: recursos-humanos_campo_trabajadores
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

# HR-011 · Recursos Humanos — Mis trabajadores del campo: lista y perfil reducido

**Módulo:** Recursos Humanos (`recursos-humanos_campo_trabajadores`) · **Tipo:** Módulo nuevo (Solo listado)

## 0. Contexto obligatorio

Antes de generar código, lee completos estos archivos. Sus reglas aplican sin repetirse aquí; este spec solo declara lo específico del cambio y sus excepciones.

- `.github/copilot-instructions.md`
- `docs/specs/_project.json` — perfil del proyecto (Laravel 13, layouts, paquetes, archivos protegidos)
- `docs/controller-structure-and-notifications.md`
- `docs/ui-kit-design-system.md`
- `docs/ui-kit-table-structure.md`
- `docs/livewire-conventions.md`

## 1. Propósito

Dar al Supervisor y al Gerente de proyecto su propia ventana de trabajadores, "Mis trabajadores", con la lista de las personas asignadas a sus Obras y un perfil reducido de solo lectura (sin sueldo, cuenta, forma de pago, préstamos, descuentos ni recibos). Sigue el mismo enfoque que Mis proyectos. Depende de HR-005, HR-006 y HR-017.

**Flujo de interacción**

El usuario de campo abre Mis trabajadores y ve a las personas con asignación vigente en alguna de sus Obras, con filtro por cuadrilla, Obra y búsqueda por nombre. Puede seleccionar personas o una cuadrilla y usar las acciones de HR-009 (Solicitar vacaciones, Proponer descanso, Ya no está en mi Obra). Al abrir una persona ve su perfil reducido: datos generales, Obra y cuadrilla actuales, documentos del expediente (PDF descargables) y DC3 con su vigencia.

## 2. Modelos

### Worker — tabla `workers`

auditable: no · soft_deletes: sí

_(sin columnas propias definidas)_

Relaciones:
- `hasMany` → WorkAssignment (`worker_id`)

## 3. Rutas

- **Prefijo:** `/recursos-humanos/campo/trabajadores`
- **Nombres de ruta:** `recursos-humanos.campo.trabajadores.*`
- **Middleware:** `role:Supervisor|Gerente de proyecto|admin`

## 4. Vistas

Carpeta: `resources/views/recursos-humanos/campo/trabajadores` · layout: `layouts.app`

| Vista | Render | Comportamiento |
|---|---|---|
| `index.blade.php` | Blade + Livewire | Componente App\Livewire\Hr\MyWorkersTable (tabla del UI kit, paginada de 25): personas asignadas a las Obras del usuario, filtros con #[Url], selección múltiple y botones de las acciones de HR-009. Muestra los avisos "Salida en RH" y "Vacaciones aprobadas" cuando aplican. |
| `show.blade.php` | Blade | Perfil reducido de solo lectura: datos generales, Obra y cuadrilla actuales, documentos del expediente con descarga y DC3 con su vigencia ("vencida el 31/08/2026" en rojo). No incluye sueldo, cuenta, forma de pago, préstamos, descuentos ni recibos ni los envía el controller. |

**Búsqueda / filtros:** Búsqueda por nombre, NSS o número de empleado; filtros en vivo por cuadrilla, Obra y estado

## 5. Reglas de negocio

- Depende de: HR-005 (roles y alcance de campo), HR-006 (asignaciones vigentes) y HR-017 (cuadrilla del trabajador). HR-020 define el perfil por rol en el módulo existente de RH; aquí solo se crea la vista del campo.
- No se crean tablas ni migraciones: el modelo Worker ya existe y este módulo solo lo consulta con un scope scopeInFieldScope(User $user) basado en work_assignments.
- Debe permanecer igual: las pantallas de Trabajadores (human_resources.workers.*, expediente, DC3, bajas) y su importación y exportación, que HR-020 pasa a los roles nuevos, y el acceso del Engineer a Mis proyectos. El módulo anterior de RH ya no existe (HR-005).
- Solo existen las rutas index y show, bajo role:Supervisor|Gerente de proyecto|admin; no hay store, update ni destroy. Las acciones de solicitud (vacaciones, descanso, salida) son de HR-009 y se montan sobre la selección de esta lista.
- El campo solo ve a las personas con asignación vigente en alguna de sus Obras (Supervisor: supervisor_user_id o resident_user_id; Gerente: Obras de sus Proyectos). Abrir por URL a una persona fuera de su alcance responde 403.
- El perfil reducido nunca recibe sueldo, tipo de pago, cuenta, forma de pago, préstamos, descuentos, caja de ahorro, recibos ni incentivos: el controller no los consulta, no solo la vista los oculta.
- Los documentos del expediente y DC3 se descargan con las rutas actuales del expediente; se agrega a esas descargas el candado de alcance de campo (la persona debe estar en sus Obras).
- Un usuario con los roles Engineer y Supervisor resuelve el alcance por sus Obras de supervisor/residente (HR-005); el rol Engineer no amplía lo que ve en esta ventana.
- Toda consulta de perfil se limita a lo necesario; no se registra NotificationService porque es solo lectura.

## 6. Validaciones

| Campo | Regla |
|---|---|
| `search` | nullable, string, max:100 |
| `worker (ruta)` | debe tener asignación vigente en una Obra del alcance del usuario; de lo contrario 403 |

## 7. Excepciones a los docs

Estas reglas de este spec **sustituyen** a las de docs/ solo para este cambio:

- El modelo Worker ya existe: este módulo no crea migración ni modifica el modelo salvo el scope de alcance (database.instructions.md aplica solo a ese scope).
- Controller Hr\Field\MyWorkerController (solo index y show) y nombres de ruta rh.field.workers.* (como my-projects.*); la vista vive en resources/views/hr/field/workers/ en lugar de la carpeta derivada del prefijo.
- El componente Livewire vive en App\Livewire\Hr (HR-005).

## 8. Preguntas abiertas

- [x] ¿El Gerente de proyecto puede ver a personas de cualquier Obra de sus Proyectos o solo a las que tienen asignación vigente? → **solo con asignación vigente**
- [x] ¿Se muestran también las personas con baja reciente (últimos días) para poder consultar su último día? → **Si**

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
- Permisos y secciones del perfil completo de RH (HR-020).
- Edición de datos personales por el campo.
- Las acciones de solicitud sobre la selección (HR-009).
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
- [ ] `Worker` declara `$fillable`, `$casts` y las relaciones hasMany(WorkAssignment); usa `SoftDeletes`.
- [ ] Solo existen las rutas `recursos-humanos.campo.trabajadores.index` y `recursos-humanos.campo.trabajadores.show` bajo middleware `role:Supervisor|Gerente de proyecto|admin`. No existen store/update/destroy.
- [ ] Existe `resources/views/recursos-humanos/campo/trabajadores/index.blade.php` que extiende `layouts.app` y monta un componente Livewire según docs/livewire-conventions.md.
- [ ] Existe `resources/views/recursos-humanos/campo/trabajadores/show.blade.php` que extiende `layouts.app`.
- [ ] El módulo tiene su entrada en el menú de navegación (parcial de `resources/views/layouts/`) apuntando a `recursos-humanos.campo.trabajadores.index`.
- [ ] El index pagina con `paginate(25)`; búsqueda/filtros: Búsqueda por nombre, NSS o número de empleado; filtros en vivo por cuadrilla, Obra y estado.
- [ ] No se modificó ningún archivo protegido del perfil del proyecto.
- [ ] No se generó nada listado en Fuera de alcance ni se instalaron paquetes nuevos.
- [ ] Los commits incluyen `[HR-011]` en el mensaje.

---

_Generado con Estoria Spec Tool v3 · Estoria Studio_
