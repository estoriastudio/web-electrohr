<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Hr\HrFieldScope;

/**
 * Valida en el controller (403) que el usuario sea RH jefe, RH ayudante o admin;
 * ocultar el menú o filtrar la ruta no es suficiente (HR-005).
 */
trait RequiresHrOffice
{
    public function callAction($method, $parameters)
    {
        abort_unless(HrFieldScope::isUnrestricted(), 403);

        return $this->{$method}(...array_values($parameters));
    }
}
