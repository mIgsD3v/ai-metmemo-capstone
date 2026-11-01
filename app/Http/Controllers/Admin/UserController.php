<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Jetstream\DeleteUser;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public const PER_PAGE = [20, 50, 100];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $role = (string) $request->query('role');

        $users = User::query()
            ->with('roles')
            ->withCount(['cards', 'contacts'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%'])
                ->orWhereRaw('lower(email) like ?', ['%'.mb_strtolower($search).'%'])))
            ->when($role !== '', fn ($query) => $query->whereHas('roles', fn ($query) => $query->whereKey($role)))
            ->latest()
            ->paginate(in_array($request->integer('per_page'), self::PER_PAGE, true) ? $request->integer('per_page') : self::PER_PAGE[0])
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'photo_url' => $user->profile_photo_url,
                'google' => $user->google_id !== null,
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'role_id' => $user->roles->first()?->id,
                'role' => $user->roles->first()?->name,
                'cards_count' => $user->cards_count,
                'contacts_count' => $user->contacts_count,
                'created_at' => $user->created_at->toDateString(),
                'is_self' => $user->is($request->user()),
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'roles' => $this->roleOptions(),
            'filters' => ['search' => $search, 'role' => $role, 'per_page' => $users->perPage()],
            'perPageOptions' => self::PER_PAGE,
            'canGrantSuperAdmin' => $request->user()->hasRole(Access::SUPER_ADMIN),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role_id' => ['required', 'string', Rule::exists('roles', 'id')]]);
        $role = Role::query()->findOrFail($data['role_id']);
        $actor = $request->user();

        if ($user->is($actor)) {
            return $this->refuse('You can\'t change your own role.');
        }

        // Only a Super Admin can make, or unmake, another Super Admin.
        if (($role->name === Access::SUPER_ADMIN || $user->hasRole(Access::SUPER_ADMIN)) && ! $actor->hasRole(Access::SUPER_ADMIN)) {
            return $this->refuse('Only a Super Admin can change Super Admin access.');
        }

        $user->syncRoles([$role]);

        return back()->with('flash.banner', "{$user->name} is now {$role->name}.");
    }

    public function destroy(Request $request, User $user, DeleteUser $deleter): RedirectResponse
    {
        if ($user->is($request->user())) {
            return $this->refuse('Delete your own account from your Profile instead.');
        }

        if ($user->hasRole(Access::SUPER_ADMIN)) {
            return $this->refuse('A Super Admin can\'t be deleted. Change their role first.');
        }

        $name = $user->name;
        $deleter->delete($user);

        return back()->with('flash.banner', "{$name}'s account was deleted.");
    }

    private function roleOptions(): array
    {
        return Role::query()->oldest()->get(['id', 'name'])->sortBy(fn (Role $role) => Access::rank($role->name))->values()->all();
    }

    private function refuse(string $message): RedirectResponse
    {
        return back()->with('flash.banner', $message)->with('flash.bannerStyle', 'danger');
    }
}
