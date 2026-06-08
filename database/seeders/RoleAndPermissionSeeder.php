<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'manage site settings',
            'view audit logs',
            'manage posts',
            'manage events',
            'manage podcasts',
            'manage comments',
            'manage talent',
            'manage outreach',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Create roles and assign created permissions
        $superAdminRole = Role::findOrCreate('Super Admin', 'web');
        // Super admin gets all permissions
        $superAdminRole->givePermissionTo(Permission::all());

        $editorRole = Role::findOrCreate('Editor/Locutor', 'web');
        $editorRole->givePermissionTo([
            'manage posts',
            'manage events',
            'manage podcasts',
            'manage comments',
        ]);

        // Assign roles to existing users based on is_admin / role columns
        $users = User::all();
        foreach ($users as $user) {
            if ($user->hasAdminAccess()) {
                $user->assignRole($superAdminRole);
            } else {
                $user->assignRole($editorRole);
            }
        }
    }
}
