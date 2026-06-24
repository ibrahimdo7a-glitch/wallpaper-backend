<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the "مبدع" (creative) role: full control over wallpapers, sub-sections,
 * models, designers and watermarks — but no access to brands or any admin area
 * (enforced by the HiddenFromCreatives trait on the other resources).
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'can_login_admin_panel',
            'can_upload_wallpapers',
            'can_edit_all_wallpapers',
            'can_delete_all_wallpapers',
            'can_publish_wallpapers',
            'can_hide_wallpapers',
            'can_apply_watermarks',
            'can_manage_watermarks',
            'can_upload_without_watermark',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'مبدع', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
    }

    public function down(): void
    {
        Role::where('name', 'مبدع')->where('guard_name', 'web')->delete();
    }
};
