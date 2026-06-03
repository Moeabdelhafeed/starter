<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $super_admin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $fallback = Role::firstOrCreate(['name' => 'fallback', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

        $translationsPermission = Permission::firstOrCreate(['name' => 'translations', 'guard_name' => 'web']);
        $usersPermission = Permission::firstOrCreate(['name' => 'users', 'guard_name' => 'web']);
        $rolesPermission = Permission::firstOrCreate(['name' => 'roles', 'guard_name' => 'web']);
        $appUsersPermission = Permission::firstOrCreate(['name' => 'app_users', 'guard_name' => 'web']);
        $activityLogsPermission = Permission::firstOrCreate(['name' => 'activity_logs', 'guard_name' => 'web']);
        $pagesPermission = Permission::firstOrCreate(['name' => 'pages', 'guard_name' => 'web']);
        $notificationTemplatesPermission = Permission::firstOrCreate(['name' => 'notification_templates', 'guard_name' => 'web']);

        // Find existing super admin or create new one
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin')->where('guard_name', 'web'))->first();

        if ($admin) {
            $admin->update([
                'email' => env('ADMIN_EMAIL'),
                'password' => env('ADMIN_PASSWORD'),
            ]);
        } else {
            $admin = User::firstOrCreate(
                ['email' => env('ADMIN_EMAIL')],
                [
                    'name' => 'Super Admin',
                    'password' => env('ADMIN_PASSWORD'),
                ]
            );
        }

        $translationsPermission->assignRole($super_admin);
        $usersPermission->assignRole($super_admin);
        $rolesPermission->assignRole($super_admin);
        $appUsersPermission->assignRole($super_admin);
        $activityLogsPermission->assignRole($super_admin);
        $pagesPermission->assignRole($super_admin);
        $notificationTemplatesPermission->assignRole($super_admin);

        $admin->assignRole(Role::findByName('super_admin', 'web'));
    }
}
