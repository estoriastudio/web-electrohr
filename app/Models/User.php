<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\AccessMatrix;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function conceptCategories(): BelongsToMany
    {
        return $this->belongsToMany(ConceptCategory::class, 'concept_category_user');
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class, 'portal_user_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'buyer_id');
    }

    /** @var list<int>|null */
    private ?array $assignedProjectWorkIds = null;

    /**
     * Obras a su cargo: es supervisor o residente, o gerente del proyecto.
     * Es el alcance `assigned_works` de la matriz de acceso.
     *
     * @return list<int>
     */
    public function assignedProjectWorkIds(): array
    {
        return $this->assignedProjectWorkIds ??= ProjectWork::query()
            ->where(fn ($works) => $works
                ->where('supervisor_user_id', $this->id)
                ->orWhere('resident_user_id', $this->id)
                ->orWhereHas('project', fn ($projects) => $projects->where('manager_user_id', $this->id)))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Roles que pueden quedar como gerente de proyecto o supervisor/residente de obra. Engineer sigue mientras exista. */
    public const PROJECT_STAFF_ROLES = ['Gerente de Proyecto', 'Supervisor', 'Engineer'];

    public function scopeProjectStaff(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', self::PROJECT_STAFF_ROLES));
    }

    /** La matriz le da esta acción sobre todos los registros de la vista, sin alcance por proyecto u obra. */
    public function hasFullAccessScope(string $view, string $action = 'view'): bool
    {
        return app(AccessMatrix::class)->hasFullScope($this, $view, $action);
    }

    /** Proyectos que gerencia o donde supervisa o reside alguna obra (alcance `assigned_projects`). */
    public function hasAssignedProjects(): bool
    {
        return Project::query()->assignedTo($this)->exists();
    }

    public function isRestrictedEngineer(): bool
    {
        return $this->hasRole('Engineer') && ! $this->hasAnyRole(['admin', 'Proyectos', 'Solmat']);
    }

    /**
     * Comprador cuyas facturas, pagos y OC se listan: el propio usuario si la matriz solo le da sus OC
     * (Compras, alcance own_buyer en `invoices`); null si ve todas o no tiene alcance de comprador.
     */
    public function invoiceBuyerFilter(): ?int
    {
        return app(AccessMatrix::class)->scopesFor($this, 'invoices') === ['own_buyer'] ? (int) $this->id : null;
    }
}
