<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\AccessMatrix;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Filtra un modelo según el alcance que la matriz de acceso le da al usuario
 * para una vista y una acción (p. ej. `hr_workers` + `view`).
 *
 * Cada modelo implementa applyAccessScope() con los alcances que entiende
 * (`assigned_works`, `assigned_workers`, …).
 */
trait HasAccessScope
{
    public function scopeVisibleTo(Builder $query, User $user, string $view, string $action = 'view'): Builder
    {
        $scopes = app(AccessMatrix::class)->scopesFor($user, $view, $action);

        if (in_array(AccessMatrix::ALL, $scopes, true)) {
            return $query;
        }

        if ($scopes === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $any) use ($scopes, $user) {
            foreach ($scopes as $scope) {
                $any->orWhere(fn (Builder $scoped) => $this->applyAccessScope($scoped, $scope, $user));
            }
        });
    }

    public function isVisibleTo(User $user, string $view, string $action = 'view'): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user, $view, $action)->exists();
    }

    abstract public function applyAccessScope(Builder $query, string $scope, User $user): void;

    protected function unsupportedAccessScope(string $scope): never
    {
        throw new InvalidArgumentException(sprintf('%s no soporta el alcance %s', static::class, $scope));
    }
}
