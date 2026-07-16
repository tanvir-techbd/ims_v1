<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Custom, non-CRUD permissions that don't map to a Filament resource action
 * (Shield's shield:generate only covers resource CRUD) — the workflow verbs
 * from PLAN.md §4's roles matrix — plus the role assignments for both these
 * and the relevant Shield-generated resource permissions.
 *
 * Admin needs nothing here: it's Shield's super_admin and bypasses every
 * permission check regardless of what's assigned.
 */
class PermissionSeeder extends Seeder
{
    public const CUSTOM_PERMISSIONS = [
        'approve_request',
        'issue_request',
        'record_stock_in',
        'view_reports',
        'view_stock_alerts',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::CUSTOM_PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Note: Shield names permissions for compound-word models with a
        // literal "::" (e.g. "view_stock::request", not "view_stock_request")
        // — a cosmetic quirk, harmless, see .claude/memory/CONTEXT.md.
        $this->assignRolePermissions('Approver', [
            'view_any_product', 'view_product',
            'view_any_stock::request', 'view_stock::request',
            'approve_request', 'view_reports', 'view_stock_alerts',
        ]);

        $this->assignRolePermissions('Storekeeper', [
            'view_any_product', 'view_product',
            'view_any_stock::request', 'view_stock::request',
            'record_stock_in', 'issue_request', 'view_reports', 'view_stock_alerts',
        ]);

        $this->assignRolePermissions('Demander', [
            'view_any_product', 'view_product',
            'view_any_stock::request', 'view_stock::request', 'create_stock::request',
        ]);

        $this->assignRolePermissions('Supplier', [
            'view_any_product', 'view_product', 'view_stock_alerts',
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function assignRolePermissions(string $roleName, array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        $existing = Permission::query()
            ->whereIn('name', $permissions)
            ->where('guard_name', 'web')
            ->pluck('name');

        $role->syncPermissions($existing);
    }
}
