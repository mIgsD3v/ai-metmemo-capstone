<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Contact;
use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $user;
    }

    public function test_new_accounts_are_members_with_the_workspace_and_no_admin_access(): void
    {
        $member = User::factory()->create();

        $this->assertTrue($member->hasRole(Access::MEMBER));
        $this->actingAs($member)->get(route('contacts.index'))->assertOk();
        $this->actingAs($member)->get(route('admin.overview'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_overview_counts_users_and_activity_without_anyones_data(): void
    {
        $this->travelTo('2026-11-01 12:00');
        $admin = $this->userWithRole(Access::ADMIN);
        $other = User::factory()->create(['google_id' => 'g-1']);
        Contact::factory()->for($other)->create(['full_name' => 'Secret Person']);

        $this->actingAs($admin)->get(route('admin.overview'))
            ->assertOk()
            ->assertDontSee('Secret Person')
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Overview')
                ->where('stats.users.total', 2)
                ->where('stats.users.new7', 2)
                ->where('stats.users.google', 1)
                ->where('stats.users.active30', 1)
                ->where('stats.content.contacts', 1)
                ->has('stats.signups', 30));
    }

    public function test_the_matrix_can_grant_and_take_away_a_feature(): void
    {
        $superAdmin = $this->userWithRole(Access::SUPER_ADMIN);
        $member = User::factory()->create();
        $memberRole = Role::findByName(Access::MEMBER, 'web');

        $this->actingAs($superAdmin)->put(route('admin.roles.permission', $memberRole), ['permissions' => ['assistant.use'], 'granted' => false]);
        $this->actingAs($member->fresh())->get(route('assistant.show'))->assertForbidden();

        $this->actingAs($superAdmin)->put(route('admin.roles.permission', $memberRole), ['permissions' => ['assistant.use'], 'granted' => true]);
        $this->actingAs($member->fresh())->get(route('assistant.show'))->assertOk();
    }

    public function test_a_new_role_gets_only_the_permissions_ticked(): void
    {
        $superAdmin = $this->userWithRole(Access::SUPER_ADMIN);

        $this->actingAs($superAdmin)->post(route('admin.roles.store'), [
            'name' => 'Support',
            'permissions' => ['overview.view', 'users.view'],
        ])->assertRedirect();

        $support = $this->userWithRole('Support');
        $this->actingAs($support)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($support)->get(route('contacts.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_built_in_roles_are_protected(): void
    {
        $superAdmin = $this->userWithRole(Access::SUPER_ADMIN);
        $super = Role::findByName(Access::SUPER_ADMIN, 'web');

        $this->actingAs($superAdmin)->put(route('admin.roles.permission', $super), ['permissions' => ['users.view'], 'granted' => false]);
        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', Role::findByName(Access::MEMBER, 'web')));
        $this->actingAs($superAdmin)->patch(route('admin.roles.update', Role::findByName(Access::ADMIN, 'web')), ['name' => 'Boss']);

        $this->assertTrue($super->fresh()->hasPermissionTo('users.view'));
        $this->assertNotNull(Role::query()->where('name', Access::MEMBER)->first());
        $this->assertNotNull(Role::query()->where('name', Access::ADMIN)->first());
    }

    public function test_deleting_a_role_moves_its_people_back_to_member(): void
    {
        $superAdmin = $this->userWithRole(Access::SUPER_ADMIN);
        $role = Role::query()->create(['name' => 'Interns', 'guard_name' => 'web']);
        $intern = $this->userWithRole('Interns');

        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $role))->assertRedirect();

        $this->assertTrue($intern->fresh()->hasRole(Access::MEMBER));
    }

    public function test_admin_can_change_a_users_role_but_not_their_own_or_make_super_admins(): void
    {
        $admin = $this->userWithRole(Access::ADMIN);
        $member = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.role', $member), ['role_id' => Role::findByName(Access::ADMIN, 'web')->id]);
        $this->assertTrue($member->fresh()->hasRole(Access::ADMIN));

        $this->actingAs($admin)->patch(route('admin.users.role', $member), ['role_id' => Role::findByName(Access::SUPER_ADMIN, 'web')->id]);
        $this->assertFalse($member->fresh()->hasRole(Access::SUPER_ADMIN));

        $this->actingAs($admin)->patch(route('admin.users.role', $admin), ['role_id' => Role::findByName(Access::MEMBER, 'web')->id]);
        $this->assertTrue($admin->fresh()->hasRole(Access::ADMIN));
    }

    public function test_admin_can_delete_an_account_but_not_their_own_or_a_super_admin(): void
    {
        $admin = $this->userWithRole(Access::ADMIN);
        $member = User::factory()->create();
        $superAdmin = $this->userWithRole(Access::SUPER_ADMIN);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $member))->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));
        $this->actingAs($admin)->delete(route('admin.users.destroy', $superAdmin));

        $this->assertNull($member->fresh());
        $this->assertNotNull($admin->fresh());
        $this->assertNotNull($superAdmin->fresh());
    }

    public function test_super_admin_still_cannot_open_someone_elses_contacts_or_cards(): void
    {
        $superAdmin = $this->userWithRole(Access::SUPER_ADMIN);
        $contact = Contact::factory()->create();
        $card = Card::factory()->create();

        $this->actingAs($superAdmin)->get(route('contacts.show', $contact))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('cards.edit', $card))->assertForbidden();
    }

    public function test_make_admin_command_promotes_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('metmemo:make-admin', ['email' => 'owner@example.com'])->assertSuccessful();
        $this->artisan('metmemo:make-admin', ['email' => 'nobody@example.com'])->assertFailed();

        $this->assertTrue($user->fresh()->hasRole(Access::SUPER_ADMIN));
    }

    public function test_shared_permissions_hide_admin_links_from_members(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('permissions', fn ($permissions) => ! collect($permissions)->contains('overview.view')));

        $this->actingAs($this->userWithRole(Access::SUPER_ADMIN))->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('permissions', fn ($permissions) => collect($permissions)->contains('roles.delete')));
    }
}
