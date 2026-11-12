<?php

/*
 * Simulación de accesos: el admin configurado puede ver el ERP como otro rol o entrar como otro
 * usuario desde la barra superior, para probar la matriz de permisos. Pensado para el servidor
 * de pruebas; en producción debe quedar apagado (ACCESS_SIMULATION_ENABLED=false).
 */
return [
    'enabled' => (bool) env('ACCESS_SIMULATION_ENABLED', false),

    // Único usuario que puede simular. Además debe tener el rol admin.
    'admin_email' => env('ACCESS_SIMULATION_ADMIN_EMAIL', 'admin@electrohr.test'),
];
