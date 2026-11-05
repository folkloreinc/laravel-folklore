<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Entities\User as UserContract;
use Folklore\Contracts\Repositories\Users;
use Folklore\Mediatheque\Support\Models;
use Folklore\Panneau\Policies\MediaPolicy;
use Folklore\Panneau\Policies\PagePolicy;
use Folklore\Panneau\Policies\UserPolicy;
use Folklore\Panneau\Roles\DefaultRoles;
use Folklore\Panneau\Roles\UserRoleStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Panneau\Roles\RoleManager;

/**
 * The default roles on the users' `role` column, with the policies of the standard resources,
 * on the roles module of laravel-panneau (docs/decisions/0025).
 */
class StandardRolesTest extends StandardResourcesTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('panneau.roles', [
            'enabled' => true,
            'store' => UserRoleStore::class,
            'roles' => DefaultRoles::ROLES,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(PageContract::class, PagePolicy::class);
        Gate::policy(UserContract::class, UserPolicy::class);
        Gate::policy(Models::media(), MediaPolicy::class);
    }

    private function user(string $email, string $role): UserContract
    {
        return $this->app->make(Users::class)->create(['name' => $email, 'email' => $email, 'password' => 'secret', 'role' => $role]);
    }

    public function test_the_store_reads_and_writes_the_role_column()
    {
        $store = $this->app->make(UserRoleStore::class);
        $user = $this->user('ada@example.com', 'editor');

        $this->assertSame(['editor'], $store->roles($user));
        $this->assertSame([], $store->grants($user));
        $store->syncRoles($user, ['admin', 'editor']);
        $this->assertSame('admin', DB::table('users')->where('email', 'ada@example.com')->value('role'));
        $store->syncRoles($user, []);
        $this->assertSame('guest', DB::table('users')->where('email', 'ada@example.com')->value('role'));
        $this->assertSame([], $store->roles($user));
    }

    public function test_the_store_keeps_no_grants()
    {
        $store = $this->app->make(UserRoleStore::class);
        $user = $this->user('ada@example.com', 'editor');
        $store->syncGrants($user, []);

        $this->expectException(LogicException::class);
        $store->syncGrants($user, ['pages.delete']);
    }

    public function test_an_admin_can_do_everything()
    {
        $roles = $this->app->make(RoleManager::class);
        $admin = $this->user('admin@example.com', 'admin');

        $this->assertTrue($roles->allows($admin, 'users.create'));
        $this->assertTrue($roles->allows($admin, RoleManager::ASSIGN));

        $this->actingAs($admin, 'conformance');
        $this->getJson('/panneau/api/resources/users')->assertOk();
        $this->getJson('/panneau/api/resources/pages')->assertOk();
    }

    public function test_an_editor_manages_the_pages_and_the_media_but_not_the_users()
    {
        $editor = $this->user('editor@example.com', 'editor');
        $this->actingAs($editor, 'conformance');

        $this->getJson('/panneau/api/resources/pages')->assertOk();
        $this->postJson('/panneau/api/resources/pages', ['title' => ['fr' => 'Page', 'en' => 'Page']])->assertCreated();
        $this->getJson('/panneau/api/resources/medias')->assertOk();
        $this->getJson('/panneau/api/resources/users')->assertForbidden();
        $this->assertFalse($this->resourceDefinition('users')['abilities']['viewAny']);
    }

    public function test_a_user_without_a_role_has_no_permission()
    {
        $this->actingAs($this->user('nobody@example.com', 'guest'), 'conformance');

        $this->getJson('/panneau/api/resources/pages')->assertForbidden();
    }

    public function test_only_the_admins_change_the_role_of_a_user()
    {
        $fields = array_column($this->resourceDefinition('users')['fields'], null, 'name');

        $this->assertSame(['admin', 'editor'], array_column($fields['role']['options'], 'value'));

        $this->actingAs($this->user('admin@example.com', 'admin'), 'conformance');
        $created = $this->postJson('/panneau/api/resources/users', ['name' => 'Ed', 'email' => 'ed@example.com', 'password' => 'a long secret', 'role' => 'editor']);
        $created->assertCreated()->assertJsonPath('data.role', 'editor');
    }
}
