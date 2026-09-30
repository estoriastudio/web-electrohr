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
    ],
    'actions' => [
        'read' => 'Ver',
        'create' => 'Crear',
        'update' => 'Editar',
        'delete' => 'Eliminar',
    ],
    'extra' => ['invoices.approve' => 'Aprobar facturas'],
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
        ],
        'Proyectos' => ['projects' => ['read', 'create', 'update', 'delete']],
        'Engineer' => ['projects' => ['read']],
        'Moviles' => [
            'mobile_assets' => ['read', 'create', 'update', 'delete'],
            'material_vouchers' => ['read', 'create', 'update', 'delete'],
        ],
        'Proveedor' => ['material_vouchers' => ['read', 'create', 'update', 'delete']],
        'Recepción' => ['invoices' => ['read', 'create', 'update', 'delete', 'approve']],
        'Inventario' => ['stocks' => ['read', 'create', 'update', 'delete']],
    ],
];