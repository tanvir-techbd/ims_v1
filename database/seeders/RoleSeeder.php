<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * The baseline user groups for the inventory management system.
     * Filament Shield will attach resource/page permissions to these
     * roles once the domain resources exist; this seeder only
     * guarantees the roles themselves are present.
     */
    public const ROLES = [
        'Admin',
        'Approver',
        'Storekeeper',
        'Demander',
        'Supplier',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
