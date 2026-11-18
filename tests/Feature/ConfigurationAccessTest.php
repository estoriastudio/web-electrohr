<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccessMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 (Configuración): Conceptos, Familias, Precios adjudicados, Usuarios y Auditoría piden los permisos
 * de la matriz. Incluye el cierre de la escalada de privilegios en PUT /usuarios/{user}.
 */
class ConfigurationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AccessMatrixSeeder::class);
    }

    public function test_a_user_cannot_edit_other_users_or_grant_themselves_roles(): void
    {
        $user = $this->userWithRole('Pagos');
        $other = $this->userWithRole('Orden de compra');

        $this->actingAs($user)
            ->put(route('usuarios.update', $other), ['name' => 'Cambiado', 'email' => $other->email])
            ->assertForbidden();
        $this->assertNotSame('Cambiado', $other->fresh()->name);

        $this->put(route('usuarios.update', $user), [
            'name' => 'Mi nombre',
            'email' => $user->email,
            'roles' => ['admin'],
            'permissions' => ['users.update'],
        ])->assertRedirect(route('usuarios.show', $user));

        $user->refresh();
        $this->assertSame('Mi nombre', $user->name);
        $this->assertFalse($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('Pagos'));
        $this->assertFalse($user->hasDirectPermission('users.update'));
    }

    public function test_admin_still_manages_users_and_their_roles(): void
    {
        $other = $this->userWithRole('Pagos');

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('usuarios.update', $other), ['name' => 'Otro', 'email' => $other->email, 'roles' => ['Orden de compra']])
            ->assertRedirect(route('usuarios.index'));

        $this->assertTrue($other->fresh()->hasRole('Orden de compra'));
        $this->get(route('usuarios.index'))->assertOk();
    }

    public function test_warehouse_maintains_the_catalog_and_purchasing_only_consults_it(): void
    {
        $this->actingAs($this->userWithRole('Almacén'));
        $this->get(route('concepts.index'))->assertOk()->assertSee('Nuevo Concepto');
        $this->get(route('concept_categories.index'))->assertOk();
        $this->get(route('concepts.awarded_prices'))->assertForbidden();

        $this->actingAs($this->userWithRole('Orden de compra'));
        $this->get(route('concepts.index'))->assertOk()->assertDontSee('Nuevo Concepto');
        $this->get(route('concept_categories.index'))->assertOk()->assertDontSee('Nueva Categoría');
        $this->post(route('concepts.store'), [])->assertForbidden();
        $this->get(route('concepts.awarded_prices'))->assertOk();

        $this->actingAs($this->userWithRole('Recursos Humanos'))->get(route('concepts.awarded_prices'))->assertForbidden();
    }

    public function test_audit_is_for_direction_and_the_inbox_for_everyone(): void
    {
        $this->actingAs($this->userWithRole('Dirección'));
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('dashboard'))->assertSee('Notificaciones globales');

        $this->actingAs($this->userWithRole('Pagos'));
        $this->get(route('notifications.index'))->assertForbidden();
        $this->get(route('notifications.inbox'))->assertOk();
        $this->get(route('dashboard'))->assertDontSee('Notificaciones globales');
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->post('/register', [
            'name' => 'Visitante',
            'email' => 'visitante@example.com',
            'password' => 'secreto-123',
            'password_confirmation' => 'secreto-123',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'visitante@example.com']);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }
}
