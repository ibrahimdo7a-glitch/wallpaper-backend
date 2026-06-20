<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Admin panel access
            'can_login_admin_panel',

            // Wallpaper management
            'can_upload_wallpapers',
            'can_edit_own_wallpapers',
            'can_edit_all_wallpapers',
            'can_delete_own_wallpapers',
            'can_delete_all_wallpapers',
            'can_publish_wallpapers',
            'can_submit_for_review',
            'can_review_wallpapers',
            'can_reject_wallpapers',
            'can_restore_deleted_wallpapers',
            'can_force_delete_wallpapers',
            'can_hide_wallpapers',

            // Category management
            'can_create_categories',
            'can_edit_categories',
            'can_delete_categories',

            // User management
            'can_manage_users',
            'can_manage_roles',
            'can_manage_permissions',

            // Statistics
            'can_view_global_statistics',
            'can_view_own_statistics',

            // Content management
            'can_manage_translations',
            'can_manage_seo',
            'can_manage_ads',
            'can_manage_settings',

            // Logs
            'can_view_activity_logs',

            // Reports
            'can_manage_reports',

            // Watermarks
            'can_manage_watermarks',
            'can_apply_watermarks',
            'can_upload_without_watermark',

            // Tags
            'can_manage_tags',

            // Future: Paid features
            'can_manage_paid_wallpapers_future',
            'can_view_sales_future',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Create roles
        $roles = [
            'super_admin' => $permissions, // All permissions

            'admin' => [
                'can_login_admin_panel',
                'can_upload_wallpapers',
                'can_edit_own_wallpapers',
                'can_edit_all_wallpapers',
                'can_delete_own_wallpapers',
                'can_delete_all_wallpapers',
                'can_publish_wallpapers',
                'can_review_wallpapers',
                'can_reject_wallpapers',
                'can_restore_deleted_wallpapers',
                'can_create_categories',
                'can_edit_categories',
                'can_delete_categories',
                'can_manage_users',
                'can_view_global_statistics',
                'can_view_own_statistics',
                'can_manage_translations',
                'can_manage_seo',
                'can_manage_ads',
                'can_manage_settings',
                'can_view_activity_logs',
                'can_manage_reports',
                'can_manage_watermarks',
                'can_apply_watermarks',
                'can_upload_without_watermark',
                'can_manage_tags',
                'can_hide_wallpapers',
            ],

            'senior_moderator' => [
                'can_login_admin_panel',
                'can_upload_wallpapers',
                'can_edit_own_wallpapers',
                'can_edit_all_wallpapers',
                'can_delete_own_wallpapers',
                'can_publish_wallpapers',
                'can_review_wallpapers',
                'can_reject_wallpapers',
                'can_view_global_statistics',
                'can_view_own_statistics',
                'can_apply_watermarks',
                'can_manage_tags',
            ],

            'moderator' => [
                'can_login_admin_panel',
                'can_upload_wallpapers',
                'can_edit_own_wallpapers',
                'can_delete_own_wallpapers',
                'can_submit_for_review',
                'can_view_own_statistics',
                'can_apply_watermarks',
            ],

            'uploader' => [
                'can_login_admin_panel',
                'can_upload_wallpapers',
                'can_edit_own_wallpapers',
                'can_submit_for_review',
                'can_view_own_statistics',
            ],

            'reviewer' => [
                'can_login_admin_panel',
                'can_review_wallpapers',
                'can_reject_wallpapers',
                'can_publish_wallpapers',
                'can_view_global_statistics',
            ],

            'translator' => [
                'can_login_admin_panel',
                'can_manage_translations',
                'can_view_own_statistics',
            ],

            'seo_manager' => [
                'can_login_admin_panel',
                'can_manage_seo',
                'can_edit_all_wallpapers',
                'can_view_global_statistics',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissions);
        }

        $this->command->info('Permissions and roles seeded successfully.');
    }
}
