<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Fase 5: los roles anteriores (Solmat, Solcom, Inventario, suministros, Proyectos, Engineer…) y los
 * permisos genéricos create/read/update/delete ya no se crean. Los roles y permisos salen de la matriz
 * (database/data/access_matrix.php); este seeder se conserva para quien todavía lo llame.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccessMatrixSeeder::class);
    }
}
