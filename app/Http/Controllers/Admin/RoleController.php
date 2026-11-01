<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        // Built-in roles first (Super Admin, Admin, Member), then the ones people added, oldest first.
        $roles = Role::query()->with('permissions')->withCount('users')->oldest()->get()
            ->sortBy(fn (Role $role) => Access::rank($role->name))
            ->values()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users' => $role->users_count,
                'permissions' => $role->name === Access::SUPER_ADMIN ? Access::all() : $role->permissions->pluck('name'),
                'locked' => $role->name === Access::SUPER_ADMIN,
                'builtIn' => in_array($role->name, Access::BUILT_IN, true),
            ]);

        $modules = collect(Access::PERMISSIONS)->map(fn (array $permissions, string $module) => [
            'name' => $module,
            'permissions' => collect($permissions)->map(fn (string $label, string $name) => ['name' => $name, 'label' => $label])->values(),
        ])->values();

        return Inertia::render('Admin/Roles', [
            'roles' => $roles,
            'modules' => $modules,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Access::all())],
        ]);

        $role = Role::query()->create(['name' => trim($data['name']), 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('flash.banner', "Role \"{$role->name}\" created.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if (in_array($role->name, Access::BUILT_IN, true)) {
            return $this->refuse('Built-in roles can\'t be renamed.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('roles', 'name')->ignore($role->id)],
        ]);

        $role->update(['name' => trim($data['name'])]);

        return back()->with('flash.banner', 'Role renamed.');
    }

    /** Tick or untick boxes in the matrix: one permission, or a whole module at once. */
    public function permission(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(Access::all())],
            'granted' => ['required', 'boolean'],
        ]);

        if ($role->name === Access::SUPER_ADMIN) {
            return $this->refuse('Super Admin always has every permission.');
        }

        $data['granted'] ? $role->givePermissionTo($data['permissions']) : $role->revokePermissionTo($data['permissions']);

        return back();
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, Access::BUILT_IN, true)) {
            return $this->refuse('Built-in roles can\'t be deleted.');
        }

        // Its people fall back to Member, so no one is left without access.
        $role->users()->each(fn ($user) => $user->syncRoles([Access::MEMBER]));
        $role->delete();

        return back()->with('flash.banner', "Role \"{$role->name}\" deleted. Its users are now Members.");
    }

    private function refuse(string $message): RedirectResponse
    {
        return back()->with('flash.banner', $message)->with('flash.bannerStyle', 'danger');
    }
}
