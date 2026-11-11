<?php

namespace Tests\Unit;

use App\Services\AccessMatrix;
use PHPUnit\Framework\TestCase;

/**
 * Integridad de database/data/access_matrix.php y casos puntuales del Excel.
 */
class AccessMatrixDataTest extends TestCase
{
    private AccessMatrix $matrix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matrix = new AccessMatrix(dirname(__DIR__, 2).'/database/data/access_matrix.php');
    }

    public function test_every_cell_uses_known_profiles_actions_and_scopes(): void
    {
        $data = $this->matrix->data();

        foreach ($this->matrix->views() as $view => $definition) {
            $this->assertContains($definition['status'], ['existing', 'new'], $view);

            foreach ($definition['access'] as $profile => $cell) {
                $this->assertArrayHasKey($profile, $data['roles'], "{$view}.{$profile}");
                $this->assertArrayHasKey('raw', $cell, "{$view}.{$profile}");

                foreach ([...$cell['actions'], ...$cell['pending'] ?? []] as $action) {
                    $this->assertArrayHasKey($action, $data['actions'], "{$view}.{$profile}");
                }

                if (isset($cell['scope'])) {
                    $this->assertArrayHasKey($cell['scope'], $data['scopes'], "{$view}.{$profile}");
                }
            }
        }
    }

    public function test_legacy_roles_point_to_known_profiles_and_are_not_target_roles(): void
    {
        foreach ($this->matrix->legacyRoles() as $role => $rule) {
            $this->assertNull($this->matrix->profileForRole($role), "{$role} no puede ser a la vez rol objetivo");

            foreach (['becomes', 'if_manager', 'if_work_responsible', 'if_unassigned'] as $key) {
                foreach ($rule[$key] ?? [] as $profile) {
                    $this->assertArrayHasKey($profile, $this->matrix->roles(), "{$role}.{$key}");
                }
            }

            foreach ($rule['skip_if_has'] ?? [] as $other) {
                $this->assertArrayHasKey($other, $this->matrix->legacyRoles(), "{$role}.skip_if_has");
            }
        }
    }

    public function test_role_names_are_unique(): void
    {
        $names = array_column($this->matrix->roles(), 'name');

        $this->assertSame($names, array_unique($names));
    }

    public function test_permissions_follow_the_excel(): void
    {
        $this->assertContains('payments_paid.register', $this->matrix->permissionsFor('PAG'));
        $this->assertContains('payment_authorizations.authorize', $this->matrix->permissionsFor('DIR'));
        $this->assertNotContains('users.view', $this->matrix->permissionsFor('DIR'));
        $this->assertContains('stocks.view', $this->matrix->permissionsFor('GER'));
        $this->assertNotContains('stocks.view', $this->matrix->permissionsFor('SUP'));
        $this->assertContains('material_voucher_authorizations.authorize', $this->matrix->permissionsFor('ALM-A'));
        $this->assertNotContains('material_voucher_authorizations.authorize', $this->matrix->permissionsFor('ALM'));
        $this->assertSame([], $this->matrix->permissionsFor('REC'));
    }

    public function test_actions_the_erp_does_not_support_yet_are_pending_not_granted(): void
    {
        $cell = $this->matrix->views()['purchase_orders']['access']['DIR'];

        $this->assertSame(['view'], $cell['actions']);
        $this->assertSame(['authorize'], $cell['pending']);
        $this->assertNotContains('purchase_orders.authorize', $this->matrix->permissionsFor('DIR'));
    }

    public function test_all_permissions_are_unique_and_sorted(): void
    {
        $permissions = $this->matrix->allPermissions();
        $sorted = $permissions;
        sort($sorted);

        $this->assertSame($sorted, $permissions);
        $this->assertSame(array_values(array_unique($permissions)), $permissions);
        $this->assertCount(323, $permissions);
    }
}
