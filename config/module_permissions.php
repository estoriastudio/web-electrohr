<?php

return [
    'modules' => [
        'purchase_orders' => 'Ordenes de compra',
        'payments' => 'Pagos',
        'invoices' => 'Facturas',
        'purchase_requests' => 'Solicitudes de compra',
        'material_requests' => 'Solicitudes de material',
        'suppliers' => 'Proveedores',
        'projects' => 'Proyectos',
        'mobile_assets' => 'Bienes moviles',
        'material_vouchers' => 'Vales de material',
        'stocks' => 'Inventario',
        'tools' => 'Herramientas',
        'concepts' => 'Conceptos y familias',
    ],
    'actions' => [
        'read' => 'Ver',
        'create' => 'Crear',
        'update' => 'Editar',
        'delete' => 'Eliminar',
    ],
    'extra' => [
        'invoices.approve' => 'Aprobar facturas',
        'invoices.view_all' => 'Ver facturas de todos los compradores',
        'purchase_orders.view_all' => 'Ver todas las OC (listado completo)',
        'payments.register_any' => 'Registrar pagos en cualquier OC',
        'material_requests.commit' => 'Comprometer existencias en la pila SOLMAT',
    ],
    // Permisos extra que conserva cada rol heredado (alcance de datos, no de modulo).
    'access_role_extras' => [
        'Pagos' => ['purchase_orders.view_all', 'invoices.view_all', 'payments.register_any'],
        'Solmat' => ['invoices.view_all'],
        'Recepción' => ['invoices.view_all'],
        'suministros' => ['material_requests.commit'],
    ],
    // Plantilla de permisos de cada rol heredado. Solo se aplica cuando el rol se crea;
    // el acceso a los modulos del catalogo depende unicamente de los permisos asignados.
    'access_roles' => [
        'Orden de compra' => [
            'purchase_orders' => ['read', 'create', 'update', 'delete'],
            'purchase_requests' => ['read', 'create', 'update', 'delete'],
            'material_requests' => ['read'],
            'suppliers' => ['read', 'create', 'update', 'delete'],
            'payments' => ['read', 'create', 'update', 'delete'],
            'invoices' => ['read', 'create', 'update', 'delete', 'approve'],
        ],
        'Pagos' => [
            'payments' => ['read', 'create', 'update', 'delete'],
            'invoices' => ['read', 'create', 'update', 'delete', 'approve'],
            'purchase_orders' => ['read'],
            'suppliers' => ['read', 'create', 'update', 'delete'],
            'material_vouchers' => ['read', 'create', 'update', 'delete'],
        ],
        'Solcom' => [
            'purchase_requests' => ['read', 'create', 'update', 'delete'],
            'material_requests' => ['read'],
        ],
        'suministros' => ['material_requests' => ['read']],
        'Solmat' => [
            'material_requests' => ['read', 'create', 'update', 'delete'],
            'purchase_orders' => ['read'],
            'projects' => ['read', 'create', 'update', 'delete'],
            'invoices' => ['read', 'create'],
            'tools' => ['read', 'create', 'update', 'delete'],
            'concepts' => ['read', 'create', 'update', 'delete'],
        ],
        'Proyectos' => ['projects' => ['read', 'create', 'update', 'delete']],
        'Engineer' => ['projects' => ['read']],
        'Moviles' => [
            'mobile_assets' => ['read', 'create', 'update', 'delete'],
            'tools' => ['read', 'create', 'update', 'delete'],
            'material_vouchers' => ['read', 'create', 'update', 'delete'],
        ],
        'Proveedor' => ['material_vouchers' => ['read', 'create', 'update', 'delete']],
        'Recepción' => ['invoices' => ['read', 'create', 'update', 'delete', 'approve']],
        'Inventario' => ['stocks' => ['read', 'create', 'update', 'delete']],
    ],
];