<?php

/*
|--------------------------------------------------------------------------
| Matriz objetivo de roles × vistas del ERP
|--------------------------------------------------------------------------
|
| Fuente única de la nueva estructura de roles y permisos. Sale del Excel
| "Matriz usuarios × vistas del ERP" (hojas «admin» y «Vistas que faltan»,
| con las respuestas V-01 a V-21 aplicadas) y de las decisiones del
| 2026-11-10 documentadas en docs/roles-permissions-matrix.md.
|
| Lo leen App\Services\AccessMatrix, el seeder AccessMatrixSeeder (crea los
| permisos `{vista}.{acción}` y los roles) y el comando access:migrate-users.
| Después de cambiar este archivo, vuelve a correr el seeder.
|
| Cada celda `views.{vista}.access.{PERFIL}` tiene:
|   raw       Texto original del Excel, para trazabilidad.
|   actions   Acciones que el ERP debe permitir (claves de `actions`).
|   pending   Acciones que el proceso exige y el ERP aún no soporta: "(A)".
|   scope     Alcance de los registros (claves de `scopes`). Sin clave = todos.
|   action_scopes  Alcance distinto para una acción concreta, p. ej. ['update' => '…'].
|   hidden    Campos que el perfil no debe ver.
|   notify    Recibe notificación (N). No es un permiso.
|   note      Aclaración del Excel.
|   decision  Ajuste posterior al Excel, con fecha.
| Un perfil que no aparece en `access` no tiene acceso a esa vista.
|
*/

return [

    'actions' => [
        'view' => 'V · Ver / consultar',
        'create' => 'C · Crear',
        'update' => 'E · Editar',
        'cancel' => 'X · Cancelar / anular',
        'authorize' => 'A · Autorizar / validar / visto bueno',
        'register' => 'R · Registrar un hecho físico (recepción, salida, pago)',
        'download' => 'D · Descargar / imprimir',
    ],

    'scopes' => [
        'assigned_projects' => 'Proyectos donde el usuario es gerente (projects.manager_user_id) o supervisor/residente de alguna obra. Equivale a Project::assignedTo().',
        'assigned_works' => 'Obras donde el usuario es supervisor o residente, más las obras de los proyectos que gerencia.',
        'assigned_workers' => 'Trabajadores de las cuadrillas de sus obras (assigned_works).',
        'own_buyer' => 'Registros donde el usuario es el comprador (purchase_orders.buyer_id).',
        'own_supplier' => 'Registros del proveedor ligado al usuario (portal de proveedores).',
        'unsent_purchase_requests' => 'SOLCOM que todavía no se envían a Compras (Pendiente o con cambios solicitados).',
    ],

    /*
    | Perfiles del Excel y el rol de Spatie que les corresponde.
    | status: existing = el rol ya existe; new = hay que crearlo.
    */
    'roles' => [
        'ADM' => [
            'name' => 'admin',
            'status' => 'existing',
            'description' => 'Webmaster y superusuario. Tiene todos los permisos, incluido autorizar.',
        ],
        'DIR' => [
            'name' => 'Dirección',
            'status' => 'new',
            'description' => 'Director General, con usuario propio. Autoriza ajustes de SOLCOM, OC y sus cambios o cancelación, dispersión de pagos y nómina.',
        ],
        'GER' => [
            'name' => 'Gerente de Proyecto',
            'status' => 'new',
            'description' => 'Gerente de los proyectos que tiene asignados. Autoriza los cambios de SOLMAT que pide el Supervisor.',
        ],
        'SUP' => [
            'name' => 'Supervisor',
            'status' => 'new',
            'description' => 'Supervisor o residente de obra. Crea SOLMAT y registra recepciones en sus obras.',
        ],
        'CON' => [
            'name' => 'Contratos',
            'status' => 'new',
            'description' => 'Alta de Proyectos y Obras, documentación contractual y estimaciones.',
        ],
        'ALM' => [
            'name' => 'Almacén',
            'status' => 'new',
            'description' => 'Operación de almacén: evalúa y surte SOLMAT, genera SOLCOM, asigna comprador, recibe y registra inventario.',
        ],
        'ALM-A' => [
            'name' => 'Almacén Autoriza',
            'status' => 'new',
            'description' => 'Responsable de Almacén que autoriza Vales de Material. Se puede combinar con Almacén.',
        ],
        'COM' => [
            'name' => 'Orden de compra',
            'status' => 'existing',
            'description' => 'Comprador: proveedores, OC, alta y validación de facturas.',
        ],
        'PAG' => [
            'name' => 'Pagos',
            'status' => 'existing',
            'description' => 'Administración y pagos: presenta autorizaciones y registra pagos.',
        ],
        'PRV' => [
            'name' => 'supplier_portal_access',
            'status' => 'existing',
            'description' => 'Proveedor externo en el portal. Además del rol, el proveedor debe tener portal_access_enabled.',
        ],
        'RH' => [
            'name' => 'Recursos Humanos',
            'status' => 'existing',
            'description' => 'Trabajadores, cuadrillas, asistencias, nómina e incentivos.',
        ],
        'MOV' => [
            'name' => 'Moviles',
            'status' => 'existing',
            'description' => 'Bienes móviles, herramientas y vales de material.',
        ],
        'REC' => [
            'name' => 'Recepción',
            'status' => 'existing',
            'description' => 'Se mantiene (decisión 2026-11-10). El Excel no le asigna vistas; conserva solo Alta de Facturas.',
        ],
    ],

    /*
    | Roles actuales que desaparecen y a qué perfiles pasan sus usuarios.
    | Lo aplica `php artisan access:migrate-users`.
    |   becomes              Perfiles que recibe siempre.
    |   if_manager           Además, si es gerente de algún proyecto (projects.manager_user_id).
    |   if_work_responsible  Además, si es supervisor o residente de alguna obra.
    |   if_unassigned        Además, si no es ninguna de las dos cosas.
    | Una lista vacía significa que el rol se retira sin reemplazo.
    */
    'legacy_roles' => [
        'Solmat' => [
            'becomes' => ['ALM'],
            'if_manager' => ['SUP'],
            'if_work_responsible' => ['SUP'],
            'skip_if_has' => ['Engineer'],
            'note' => 'Pasan a Almacén; los que están asignados a un proyecto también reciben Supervisor. Si además tienen Engineer, no reciben Almacén: los define la regla de Engineer (decisión 2026-11-10).',
        ],
        'Inventario' => ['becomes' => ['ALM']],
        'Solcom' => ['becomes' => ['ALM']],
        'suministros' => ['becomes' => ['ALM']],
        'Engineer' => [
            'becomes' => [],
            'if_manager' => ['GER'],
            'if_work_responsible' => ['SUP'],
            'if_unassigned' => ['SUP'],
            'note' => 'Gerente si gerencia algún proyecto; Supervisor si supervisa o reside alguna obra o si no tiene proyecto asignado.',
        ],
        'Proyectos' => [
            'becomes' => ['CON'],
            'note' => 'Contratos absorbe el alta de proyectos y obras (decisión 2026-11-10).',
        ],
        'Ayudante Movil' => [
            'becomes' => [],
            'note' => 'Se elimina: no está en el Excel (decisión 2026-11-10).',
        ],
        'Proveedor' => [
            'becomes' => [],
            'note' => 'Se elimina: no da acceso a ninguna ruta; el portal lo da supplier_portal_access.',
        ],
    ],

    /*
    | Vistas: 42 existentes (hoja «admin») y 5 nuevas (hoja «Vistas que faltan»).
    | status: existing = la pantalla existe; new = la exige el proceso y no existe.
    */
    'views' => [
        'dashboard' => [
            'label' => 'Vista General',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'SUP' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'CON' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM-A' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PRV' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'RH' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'MOV' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
            ],
        ],
        'suppliers' => [
            'label' => 'Proveedores: Listado',
            'status' => 'existing',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'supplier_account_statements' => [
            'label' => 'Proveedores: Estado de Cuenta',
            'status' => 'existing',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PRV' => [
                    'raw' => 'V (solo el propio)',
                    'actions' => ['view'],
                    'scope' => 'own_supplier',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'mobile_assets' => [
            'label' => 'Bienes Móviles',
            'status' => 'existing',
            'note' => 'GER consulta asignación de vehículos a su proyecto (L0).',
            'access' => [
                'GER' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'MOV' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'tools' => [
            'label' => 'Herramientas: Registro',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'SUP' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'MOV' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'tool_controls' => [
            'label' => 'Herramientas: Control de uso',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'SUP' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V R',
                    'actions' => ['view', 'register'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'tool_categories' => [
            'label' => 'Herramientas: Categorías',
            'status' => 'existing',
            'access' => [
                'ALM' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'projects' => [
            'label' => 'Proyectos',
            'status' => 'existing',
            'note' => 'V-03: dan de alta Dirección, Administración (admin) y Contratos.',
            'access' => [
                'GER' => [
                    'raw' => 'V (propios)',
                    'actions' => ['view'],
                    'scope' => 'assigned_projects',
                ],
                'SUP' => [
                    'raw' => 'V (propios)',
                    'actions' => ['view'],
                    'scope' => 'assigned_projects',
                ],
                'DIR' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'CON' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                    'decision' => 'Contratos también carga documentación contractual y estimaciones (decisión 2026-11-10).',
                ],
                'ALM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'RH' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'MOV' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'hr_workers' => [
            'label' => 'RRHH: Trabajadores',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V (su gente, sin sueldo)',
                    'actions' => ['view'],
                    'scope' => 'assigned_workers',
                    'hidden' => ['salary'],
                ],
                'SUP' => [
                    'raw' => 'V (su gente, sin sueldo)',
                    'actions' => ['view'],
                    'scope' => 'assigned_workers',
                    'hidden' => ['salary'],
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'RH' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'hr_worker_groups' => [
            'label' => 'RRHH: Cuadrillas',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V C E (propias)',
                    'actions' => ['view', 'create', 'update'],
                    'scope' => 'assigned_works',
                ],
                'SUP' => [
                    'raw' => 'V (propias)',
                    'actions' => ['view'],
                    'scope' => 'assigned_works',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'RH' => [
                    'raw' => 'V C E A',
                    'actions' => ['view', 'create', 'update', 'authorize'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'hr_attendances' => [
            'label' => 'RRHH: Asistencias',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V C E (propias)',
                    'actions' => ['view', 'create', 'update'],
                    'scope' => 'assigned_works',
                ],
                'SUP' => [
                    'raw' => 'V C E (propias)',
                    'actions' => ['view', 'create', 'update'],
                    'scope' => 'assigned_works',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'RH' => [
                    'raw' => 'V E A',
                    'actions' => ['view', 'update', 'authorize'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'hr_vacations' => [
            'label' => 'RRHH: Vacaciones',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V (su gente)',
                    'actions' => ['view'],
                    'scope' => 'assigned_workers',
                ],
                'SUP' => [
                    'raw' => 'V (su gente)',
                    'actions' => ['view'],
                    'scope' => 'assigned_workers',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'RH' => [
                    'raw' => 'V C E A',
                    'actions' => ['view', 'create', 'update', 'authorize'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'hr_terminations' => [
            'label' => 'RRHH: Bajas',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'N',
                    'actions' => [],
                    'notify' => true,
                ],
                'SUP' => [
                    'raw' => 'N',
                    'actions' => [],
                    'notify' => true,
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'RH' => [
                    'raw' => 'V C',
                    'actions' => ['view', 'create'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'hr_position_categories' => [
            'label' => 'RRHH: Categorías de puesto',
            'status' => 'existing',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'RH' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'hr_payroll' => [
            'label' => 'RRHH: Nómina',
            'status' => 'existing',
            'note' => 'V-02: Dirección autoriza nómina.',
            'access' => [
                'DIR' => [
                    'raw' => 'V A',
                    'actions' => ['view', 'authorize'],
                ],
                'RH' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'hr_piecework' => [
            'label' => 'RRHH: Destajo',
            'status' => 'existing',
            'note' => 'Destajo está fuera de alcance en el prototipo RH.',
            'access' => [
                'RH' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'hr_incentives' => [
            'label' => 'RRHH: Incentivos',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V C (propone)',
                    'actions' => ['view', 'create'],
                    'scope' => 'assigned_workers',
                    'note' => 'propone',
                    'decision' => 'Alcance assigned_workers agregado en la Fase 2: solo propone incentivos para su gente (2026-11-10).',
                ],
                'SUP' => [
                    'raw' => 'V C (propone)',
                    'actions' => ['view', 'create'],
                    'scope' => 'assigned_workers',
                    'note' => 'propone',
                    'decision' => 'Alcance assigned_workers agregado en la Fase 2: solo propone incentivos para su gente (2026-11-10).',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'RH' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'material_requests' => [
            'label' => 'Almacén: SOLMATs (bandeja)',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V (propias)',
                    'actions' => ['view', 'create', 'update', 'authorize'],
                    'scope' => 'assigned_projects',
                    'decision' => 'Crea y edita SOLMAT de sus proyectos mientras esté Pendiente o con cambios, y la aprueba y envía a Almacén (A) solo en los proyectos que gerencia (decisión 2026-11-10).',
                ],
                'SUP' => [
                    'raw' => 'V (propias)',
                    'actions' => ['view', 'create', 'update'],
                    'scope' => 'assigned_projects',
                    'decision' => 'Crea y edita SOLMAT de sus proyectos mientras esté Pendiente o con cambios; no la envía a Almacén (decisión 2026-11-10).',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V E (evalúa, compromete)',
                    'actions' => ['view', 'create', 'update'],
                    'note' => 'evalúa, compromete',
                    'decision' => 'También crea SOLMAT desde la bandeja, como hoy Solmat; no la envía a Almacén (decisión 2026-11-10).',
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'material_vouchers' => [
            'label' => 'Almacén: Vales de Material',
            'status' => 'existing',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V C E D',
                    'actions' => ['view', 'create', 'update', 'download'],
                ],
                'ALM-A' => [
                    'raw' => 'V C E D',
                    'actions' => ['view', 'create', 'update', 'download'],
                ],
                'MOV' => [
                    'raw' => 'V C E D',
                    'actions' => ['view', 'create', 'update', 'download'],
                    'decision' => 'Confirmado: Móviles opera Vales igual que Almacén (decisión 2026-11-10).',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'stocks' => [
            'label' => 'Inventario: Consulta',
            'status' => 'existing',
            'note' => 'V-08: solo el Gerente consulta; el Supervisor no.',
            'access' => [
                'GER' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM-A' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'stock_entries' => [
            'label' => 'Inventario: Entradas',
            'status' => 'existing',
            'access' => [
                'ALM' => [
                    'raw' => 'V C R',
                    'actions' => ['view', 'create', 'register'],
                ],
                'ALM-A' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'stock_exits' => [
            'label' => 'Inventario: Salidas',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'N',
                    'actions' => [],
                    'notify' => true,
                ],
                'SUP' => [
                    'raw' => 'N',
                    'actions' => [],
                    'notify' => true,
                ],
                'ALM' => [
                    'raw' => 'V C R (ligada a Vale)',
                    'actions' => ['view', 'create', 'register'],
                    'note' => 'ligada a Vale',
                ],
                'ALM-A' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'material_request_changes' => [
            'label' => 'Cambios SOLMAT',
            'status' => 'existing',
            'note' => 'el cambio del Supervisor requiere autorización del Gerente. Compras no lo ve;. Rige cancelar y sustituir, no editar',
            'access' => [
                'GER' => [
                    'raw' => 'V C A (sobre lo que pide SUP)',
                    'actions' => ['view', 'create', 'authorize'],
                    'note' => 'sobre lo que pide SUP',
                ],
                'SUP' => [
                    'raw' => 'V C (requiere A de GER)',
                    'actions' => ['view', 'create'],
                    'note' => 'requiere A de GER',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V E (aplica)',
                    'actions' => ['view', 'update'],
                    'note' => 'aplica',
                    'decision' => 'Almacén ve y edita todas las SOLMAT; "cancelar y sustituir" aplica solo a los flujos de proyectos (decisión 2026-11-10).',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'material_request_queue' => [
            'label' => 'Pila SOLMAT',
            'status' => 'existing',
            'access' => [
                'ALM' => [
                    'raw' => 'V E',
                    'actions' => ['view', 'update'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'purchase_requests' => [
            'label' => 'Compras: SOLCOMs',
            'status' => 'existing',
            'note' => 'Almacén genera la SOLCOM.',
            'access' => [
                'GER' => [
                    'raw' => 'V (propias)',
                    'actions' => ['view'],
                    'scope' => 'assigned_projects',
                ],
                'SUP' => [
                    'raw' => 'V (propias)',
                    'actions' => ['view'],
                    'scope' => 'assigned_projects',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V C (genera)',
                    'actions' => ['view', 'create', 'update'],
                    'action_scopes' => ['update' => 'unsent_purchase_requests'],
                    'decision' => 'Edita la SOLCOM que generó solo hasta enviarla a Compras; después la edita Compras (decisión 2026-11-10).',
                    'note' => 'genera',
                ],
                'ALM-A' => [
                    'raw' => 'V C (genera)',
                    'actions' => ['view', 'create', 'update'],
                    'action_scopes' => ['update' => 'unsent_purchase_requests'],
                    'decision' => 'Edita la SOLCOM que generó solo hasta enviarla a Compras; después la edita Compras (decisión 2026-11-10).',
                    'note' => 'genera',
                ],
                'COM' => [
                    'raw' => 'V E (bajo autorizacion)',
                    'actions' => ['view', 'update'],
                    'note' => 'bajo autorizacion',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'purchase_request_changes' => [
            'label' => 'Compras: Cambios SOLCOM',
            'status' => 'existing',
            'note' => 'Dirección autoriza el ajuste comercial; compras se habilita para hacer ajuste',
            'access' => [
                'GER' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'SUP' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'DIR' => [
                    'raw' => 'V (A)',
                    'actions' => ['view', 'authorize'],
                    'decision' => 'Fase 4: autoriza los cambios marcados como ajuste comercial; los demás cambios no pasan por Dirección (2026-11-11).',
                ],
                'ALM' => [
                    'raw' => 'V C (genera)',
                    'actions' => ['view', 'create'],
                    'note' => 'genera',
                ],
                'ALM-A' => [
                    'raw' => 'V C (genera)',
                    'actions' => ['view', 'create'],
                    'note' => 'genera',
                ],
                'COM' => [
                    'raw' => 'V C (genera bajo autorizacion)',
                    'actions' => ['view', 'create'],
                    'note' => 'genera bajo autorizacion',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'purchase_request_queue' => [
            'label' => 'Compras: Pila SOLCOM',
            'status' => 'existing',
            'note' => 'Almacén asigna comprador.',
            'access' => [
                'ALM' => [
                    'raw' => 'V E (asigna comprador)',
                    'actions' => ['view', 'update'],
                    'note' => 'asigna comprador',
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'purchasing_workload' => [
            'label' => 'Compras: Carga de Trabajo',
            'status' => 'existing',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V (propias)',
                    'actions' => ['view'],
                    'scope' => 'own_buyer',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'purchase_orders' => [
            'label' => 'Órdenes de Compra',
            'status' => 'existing',
            'note' => 'Dirección autoriza OC y sus cambios o cancelación; la autorización en sistema es requerimiento. GER, SUP y ALM consultan.',
            'access' => [
                'GER' => [
                    'raw' => 'V (de sus proyectos)',
                    'actions' => ['view'],
                    'scope' => 'assigned_projects',
                ],
                'SUP' => [
                    'raw' => 'V (de sus obras y del proyecto en el que participa)',
                    'actions' => ['view'],
                    'scope' => 'assigned_projects',
                ],
                'DIR' => [
                    'raw' => 'V (A)',
                    'actions' => ['view', 'authorize'],
                    'decision' => 'Fase 4: autoriza OC emitidas y las solicitudes de cambio o cancelación de OC autorizadas (2026-11-11).',
                ],
                'ALM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V C E X D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'download'],
                    'scope' => 'own_buyer',
                    'decision' => 'Cada comprador ve y opera solo sus OC (comprador asignado), como hoy (decisión 2026-11-10).',
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PRV' => [
                    'raw' => 'V (propias, portal)',
                    'actions' => ['view'],
                    'scope' => 'own_supplier',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'purchase_order_receipts' => [
            'label' => 'Recepción de OC (registro y validación)',
            'status' => 'existing',
            'note' => 'la pantalla existe y hoy solo la ven Compras y Almacén; se debe abrir a Gerente y Supervisor para recibir en obra. en Almacén Central una persona recibe, valida y firma en papel y otra registra sobre la OC.',
            'access' => [
                'GER' => [
                    'raw' => 'V R A (en obra)',
                    'actions' => ['view', 'register', 'authorize'],
                    'scope' => 'assigned_projects',
                ],
                'SUP' => [
                    'raw' => 'V R A (en obra)',
                    'actions' => ['view', 'register', 'authorize'],
                    'scope' => 'assigned_projects',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V A (física, firma en papel) R (registro en ERP)',
                    'actions' => ['view', 'authorize', 'register'],
                    'note' => 'física, firma en papel; registro en ERP',
                ],
                'ALM-A' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'PAG' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'PRV' => [
                    'raw' => 'C (evidencia, portal)',
                    'actions' => ['create'],
                    'scope' => 'own_supplier',
                    'note' => 'evidencia, portal',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'invoices' => [
            'label' => 'Facturas: Contado / Crédito / Ver Todas',
            'status' => 'existing',
            'access' => [
                'GER' => [
                    'raw' => 'V (de sus proyectos)',
                    'actions' => ['view'],
                    'scope' => 'assigned_projects',
                ],
                'SUP' => [
                    'raw' => 'V (de sus obras y del proyecto en el que participa)',
                    'actions' => ['view'],
                    'scope' => 'assigned_projects',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V A (valida el comprador dueño de la OC)',
                    'actions' => ['view', 'authorize'],
                    'scope' => 'own_buyer',
                    'decision' => 'Ve y valida solo las facturas de sus OC, igual que en Órdenes de Compra (decisión 2026-11-10).',
                    'note' => 'valida el comprador dueño de la OC',
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'invoice_intake' => [
            'label' => 'Alta de Facturas',
            'status' => 'existing',
            'note' => 'da de alta Compras, o el proveedor si tiene usuario del portal. Se retira de Pagos y de Recepción.',
            'access' => [
                'COM' => [
                    'raw' => 'V C',
                    'actions' => ['view', 'create'],
                ],
                'PRV' => [
                    'raw' => 'C (portal)',
                    'actions' => ['create'],
                    'scope' => 'own_supplier',
                ],
                'REC' => [
                    'raw' => '',
                    'actions' => ['view', 'create'],
                    'decision' => 'Recepción conserva Alta de Facturas al pasar el módulo a permisos (decisión 2026-11-10), aunque el Excel no se la da.',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'milestones' => [
            'label' => 'Pagos: Hitos',
            'status' => 'existing',
            'note' => 'GER y SUP consultan. La validación operativa del hito por el responsable (recepción, FAT, puesta en servicio) es requerimiento.',
            'access' => [
                'GER' => [
                    'raw' => 'V (A)',
                    'actions' => ['view', 'authorize'],
                    'decision' => 'Fase 4: valida el hito (entrega, estimación o documental) desde la OC de sus proyectos; sin esa validación el pago no se autoriza (2026-11-11).',
                ],
                'SUP' => [
                    'raw' => 'V (A)',
                    'actions' => ['view', 'authorize'],
                    'decision' => 'Fase 4: valida el hito (entrega, estimación o documental) desde la OC de sus proyectos; sin esa validación el pago no se autoriza (2026-11-11).',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'COM' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'payment_authorizations' => [
            'label' => 'Pagos: Autorizaciones',
            'status' => 'existing',
            'note' => 'Pagos presenta, Dirección autoriza.',
            'access' => [
                'DIR' => [
                    'raw' => 'V A (como admin)',
                    'actions' => ['view', 'authorize'],
                    'decision' => 'Dirección autoriza con su propio usuario (decisión 2026-11-10).',
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PAG' => [
                    'raw' => 'V C (presenta)',
                    'actions' => ['view', 'create'],
                    'note' => 'presenta',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'payments_payable' => [
            'label' => 'Pagos: Por Pagar',
            'status' => 'existing',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PAG' => [
                    'raw' => 'V E',
                    'actions' => ['view', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'payments_paid' => [
            'label' => 'Pagos: Pagado',
            'status' => 'existing',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PAG' => [
                    'raw' => 'V R',
                    'actions' => ['view', 'register'],
                ],
                'PRV' => [
                    'raw' => 'V (portal)',
                    'actions' => ['view'],
                    'scope' => 'own_supplier',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'purchase_order_evidences' => [
            'label' => 'Evidencias',
            'status' => 'existing',
            'note' => 'Pagos consulta para corroborar existencia y quién publicó. el proveedor reporta su evidencia de entrega.',
            'access' => [
                'GER' => [
                    'raw' => 'V C',
                    'actions' => ['view', 'create'],
                ],
                'SUP' => [
                    'raw' => 'V C',
                    'actions' => ['view', 'create'],
                ],
                'ALM' => [
                    'raw' => 'V C',
                    'actions' => ['view', 'create'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'PRV' => [
                    'raw' => 'V C (portal)',
                    'actions' => ['view', 'create'],
                    'scope' => 'own_supplier',
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'concepts' => [
            'label' => 'Configuración: Suministros - Conceptos',
            'status' => 'existing',
            'note' => 'Almacén mantiene el catálogo.',
            'access' => [
                'ALM' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'awarded_prices' => [
            'label' => 'Configuración: Suministros - Precios adjudicados',
            'status' => 'existing',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'COM' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'concept_categories' => [
            'label' => 'Configuración: Suministros - Familias',
            'status' => 'existing',
            'access' => [
                'ALM' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'COM' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'users' => [
            'label' => 'Configuración: Usuarios',
            'status' => 'existing',
            'access' => [
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'notifications_audit' => [
            'label' => 'Configuración: Notificaciones - Auditoría',
            'status' => 'existing',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'site_delivery_confirmations' => [
            'label' => 'Confirmación de entrega en obra (enviado por Almacén vs recibido)',
            'status' => 'new',
            'note' => 'Cierra el movimiento Almacén → obra. V-06: la registran Gerente y Supervisor. Pantalla nueva, no se amplía Recepción de OC (decisión 2026-11-10).',
            'access' => [
                'GER' => [
                    'raw' => 'V R A',
                    'actions' => ['view', 'register', 'authorize'],
                    'scope' => 'assigned_works',
                    'decision' => 'Solo las entregas a sus obras (Fase 4, 2026-11-11).',
                ],
                'SUP' => [
                    'raw' => 'V R A',
                    'actions' => ['view', 'register', 'authorize'],
                    'scope' => 'assigned_works',
                    'decision' => 'Solo las entregas a sus obras (Fase 4, 2026-11-11).',
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'ALM-A' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'material_voucher_authorizations' => [
            'label' => 'Vales de Material: autorización en sistema',
            'status' => 'new',
            'note' => 'V-09: el responsable de Almacén autoriza; hoy el ERP solo registra la salida.',
            'access' => [
                'GER' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'SUP' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'ALM' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'ALM-A' => [
                    'raw' => 'V A',
                    'actions' => ['view', 'authorize'],
                ],
                'MOV' => [
                    'raw' => 'V N',
                    'actions' => ['view'],
                    'notify' => true,
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'direction_authorizations' => [
            'label' => 'Autorizaciones de Dirección (ajuste SOLCOM, OC, cambio / cancelación de OC)',
            'status' => 'new',
            'note' => 'V-01 y V-02: Dirección autoriza con su propio usuario (rol Dirección, decisión 2026-11-10); admin también puede autorizar. Hoy solo existe Pagos: Autorizaciones; lo demás se firma en papel.',
            'access' => [
                'GER' => [
                    'raw' => 'N',
                    'actions' => [],
                    'notify' => true,
                ],
                'SUP' => [
                    'raw' => 'N',
                    'actions' => [],
                    'notify' => true,
                ],
                'DIR' => [
                    'raw' => 'V A (como admin)',
                    'actions' => ['view', 'authorize'],
                    'decision' => 'Dirección autoriza con su propio usuario (decisión 2026-11-10).',
                ],
                'ALM' => [
                    'raw' => 'N',
                    'actions' => [],
                    'notify' => true,
                ],
                'COM' => [
                    'raw' => 'V C N',
                    'actions' => ['view', 'create'],
                    'notify' => true,
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'milestone_validations' => [
            'label' => 'Hitos: responsable que valida cada evento (recepción, FAT, puesta en servicio)',
            'status' => 'new',
            'note' => 'El ERP debe detectar solo la recepción validada; el resto lo valida un responsable.',
            'access' => [
                'GER' => [
                    'raw' => 'V A',
                    'actions' => ['view', 'authorize'],
                ],
                'SUP' => [
                    'raw' => 'V A',
                    'actions' => ['view', 'authorize'],
                ],
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'V A (entrega en Almacén Central)',
                    'actions' => ['view', 'authorize'],
                    'note' => 'entrega en Almacén Central',
                ],
                'COM' => [
                    'raw' => 'V C E',
                    'actions' => ['view', 'create', 'update'],
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
        'event_followups' => [
            'label' => 'Seguimiento del evento hasta cierre (pagado pero abierto)',
            'status' => 'new',
            'note' => 'Pago ejecutado no cierra el evento si falta entrega, factura o documento.',
            'access' => [
                'DIR' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ALM' => [
                    'raw' => 'N',
                    'actions' => [],
                    'notify' => true,
                ],
                'COM' => [
                    'raw' => 'V E',
                    'actions' => ['view', 'update'],
                ],
                'PAG' => [
                    'raw' => 'V',
                    'actions' => ['view'],
                ],
                'ADM' => [
                    'raw' => 'V C E X A R D',
                    'actions' => ['view', 'create', 'update', 'cancel', 'authorize', 'register', 'download'],
                ],
            ],
        ],
    ],

];
