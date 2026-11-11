<?php

namespace App\Console\Commands;

use App\Services\AccessMatrix;
use App\Services\AccessUserMigration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

/**
 * Asigna a los usuarios actuales los roles de la matriz objetivo.
 *
 * Por defecto solo muestra el plan. Con --apply asigna los roles nuevos sin
 * quitar los anteriores. Requiere haber corrido AccessMatrixSeeder.
 */
class AccessMigrateUsers extends Command
{
    protected $signature = 'access:migrate-users
        {--apply : Asigna los roles nuevos (sin esta opción solo muestra el plan)}
        {--csv= : Guarda el plan completo en un CSV en esta ruta}';

    protected $description = 'Calcula y asigna los roles de la matriz objetivo a partir de los roles actuales';

    public function handle(AccessMatrix $matrix, AccessUserMigration $migration): int
    {
        $missingRoles = collect($matrix->roles())
            ->pluck('name')
            ->reject(fn (string $name) => Role::where('name', $name)->where('guard_name', 'web')->exists());

        if ($missingRoles->isNotEmpty()) {
            $this->error('Faltan roles de la matriz: '.$missingRoles->implode(', '));
            $this->line('Corre primero: php artisan db:seed --class=AccessMatrixSeeder');

            return self::FAILURE;
        }

        $plan = $migration->plan();
        $label = fn (array $profiles) => collect($profiles)->map(fn ($p) => $matrix->roleName($p))->implode(', ');

        $changes = $plan->filter(fn (array $row) => $row['missing'] !== [] || $row['flags'] !== []);

        $this->table(
            ['id', 'Usuario', 'Roles actuales', 'Roles a asignar', 'Revisar'],
            $changes->map(fn (array $row) => [
                $row['user']->id,
                $row['user']->name,
                implode(', ', $row['current']) ?: '—',
                $label($row['missing']) ?: '—',
                implode('; ', $row['flags']),
            ])->all(),
        );

        $this->newLine();
        $this->line("Usuarios: {$plan->count()} · con roles por asignar: ".$plan->where('missing', '!=', [])->count()
            .' · con observaciones: '.$plan->where('flags', '!=', [])->count());

        if ($path = $this->option('csv')) {
            $this->writeCsv($path, $plan, $label);
            $this->info("Plan guardado en {$path}");
        }

        if (! $this->option('apply')) {
            $this->comment('Simulación: no se asignó ningún rol. Usa --apply para aplicar.');

            return self::SUCCESS;
        }

        $assigned = $migration->apply($plan);
        $this->info("Roles asignados: {$assigned}. No se quitó ningún rol anterior.");

        return self::SUCCESS;
    }

    private function writeCsv(string $path, $plan, callable $label): void
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['id', 'usuario', 'email', 'roles_actuales', 'roles_objetivo', 'roles_a_asignar', 'revisar']);

        foreach ($plan as $row) {
            fputcsv($handle, [
                $row['user']->id,
                $row['user']->name,
                $row['user']->email,
                implode(', ', $row['current']),
                $label($row['profiles']),
                $label($row['missing']),
                implode('; ', $row['flags']),
            ]);
        }

        rewind($handle);
        File::put($path, stream_get_contents($handle));
        fclose($handle);
    }
}
