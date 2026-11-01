<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every permission MetMemo checks, grouped by module, and the built-in roles.
 * Member is every new account's role; Super Admin can do everything and can't be edited or deleted.
 */
final class Access
{
    public const SUPER_ADMIN = 'Super Admin';

    public const ADMIN = 'Admin';

    public const MEMBER = 'Member';

    /** module => [name => label], in the order the Roles page shows them. */
    public const PERMISSIONS = [
        'Workspace' => [
            'cards.view' => 'Card - view',
            'cards.create' => 'Card - create',
            'cards.update' => 'Card - update',
            'cards.delete' => 'Card - delete',
            'contacts.view' => 'Contact - view',
            'contacts.create' => 'Contact - create',
            'contacts.update' => 'Contact - update',
            'contacts.delete' => 'Contact - delete',
            'contacts.export' => 'Contact - export',
            'scan.use' => 'Scan card - use',
            'assistant.use' => 'Assistant - use',
            'insights.view' => 'Insights - view',
        ],
        'Administration' => [
            'overview.view' => 'Overview - view',
            'users.view' => 'User - view',
            'users.update' => 'User - change role',
            'users.delete' => 'User - delete',
            'roles.view' => 'Role permission - view',
            'roles.create' => 'Role permission - create',
            'roles.update' => 'Role permission - update',
            'roles.delete' => 'Role permission - delete',
        ],
    ];

    /** Roles that can't be renamed or deleted. Super Admin's permissions can't be changed either. */
    public const BUILT_IN = [self::SUPER_ADMIN, self::ADMIN, self::MEMBER];

    /** Sort key that lists the built-in roles first, in their order, then the rest. */
    public static function rank(string $role): int
    {
        $index = array_search($role, self::BUILT_IN, true);

        return $index === false ? count(self::BUILT_IN) : $index;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::PERMISSIONS)));
    }

    /** @return list<string> */
    public static function module(string $module): array
    {
        return array_keys(self::PERMISSIONS[$module]);
    }

    /** The permissions the given user has (a Super Admin has all of them). */
    public static function for(User $user): array
    {
        return $user->hasRole(self::SUPER_ADMIN) ? self::all() : $user->getAllPermissions()->pluck('name')->all();
    }

    /**
     * Create the permissions and built-in roles (safe to run again). New permissions are added; the
     * built-in roles get their starting set only when first created, so later edits on the Roles page stay.
     */
    public static function install(): void
    {
        foreach (self::PERMISSIONS as $module => $permissions) {
            foreach (array_keys($permissions) as $name) {
                Permission::query()->updateOrCreate(['name' => $name, 'guard_name' => 'web'], ['module' => $module]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $defaults = [
            self::SUPER_ADMIN => self::all(),
            self::ADMIN => array_values(array_diff(self::all(), ['roles.create', 'roles.update', 'roles.delete'])),
            self::MEMBER => self::module('Workspace'),
        ];

        foreach ($defaults as $name => $permissions) {
            $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if ($role->wasRecentlyCreated || $name === self::SUPER_ADMIN) {
                $role->syncPermissions($permissions);
            }
        }
    }
}
