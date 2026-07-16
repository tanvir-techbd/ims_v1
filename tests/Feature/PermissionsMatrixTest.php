<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Systematic re-verification of the roles & permissions matrix in
 * PLAN.md §4 — "log into a test user of each role and confirm they see
 * exactly what they should, nothing more" (Phase 8 design doc), done here
 * as an automated HTTP-level check instead of manual click-through, so it
 * stays enforced on every future change rather than being a one-time audit.
 *
 * Runs the *real* PermissionSeeder (not a hand-copied duplicate of its role
 * grants) so this test fails loudly if someone changes the seeder's role
 * assignments without updating the expected-access table below — that's
 * the point: a matrix change should be a deliberate, visible decision.
 *
 * The Shield-generated resource permissions (view_any_product etc.) aren't
 * present in a fresh test database (shield:generate writes straight to
 * whichever DB was configured when it last ran, not via migration — see
 * CLAUDE.md), so this test creates them manually before running the real
 * seeder, mirroring what `shield:generate` would have produced.
 */
class PermissionsMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every permission PermissionSeeder assigns to a non-Admin role, as if
     * shield:generate had already run. Admin bypasses everything via its
     * Gate::before and needs none of these.
     */
    private const SHIELD_STYLE_PERMISSIONS = [
        'view_any_product',
        'view_product',
        'view_any_stock::request',
        'view_stock::request',
        'create_stock::request',
    ];

    /**
     * path => [roles that should get 200]. Any role not listed for a path
     * is expected to get 403. Derived directly from PLAN.md §4's table.
     */
    private const EXPECTED_ACCESS = [
        '/admin/categories' => ['Admin'],
        '/admin/units' => ['Admin'],
        '/admin/item-groups' => ['Admin'],
        '/admin/user-groups' => ['Admin'],
        '/admin/users' => ['Admin'],
        '/admin/settings' => ['Admin'],
        '/admin/activities' => ['Admin'],
        '/admin/products' => ['Admin', 'Approver', 'Storekeeper', 'Demander', 'Supplier'],
        '/admin/stock-requests' => ['Admin', 'Approver', 'Storekeeper', 'Demander'],
        '/admin/stock-alerts' => ['Admin', 'Approver', 'Storekeeper', 'Supplier'],
        '/admin/reports' => ['Admin', 'Approver', 'Storekeeper'],
    ];

    private const ROLES = ['Admin', 'Approver', 'Storekeeper', 'Demander', 'Supplier'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::SHIELD_STYLE_PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function roleProvider(): array
    {
        return collect(self::ROLES)->mapWithKeys(fn (string $role) => [$role => [$role]])->all();
    }

    #[DataProvider('roleProvider')]
    public function test_role_sees_exactly_what_the_matrix_grants_and_nothing_else(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        foreach (self::EXPECTED_ACCESS as $path => $allowedRoles) {
            $response = $this->actingAs($user)->get($path);

            if (in_array($role, $allowedRoles, true)) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }
}
