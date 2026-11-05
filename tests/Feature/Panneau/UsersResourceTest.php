<?php

namespace Folklore\Tests\Feature\Panneau;

use Folklore\Contracts\Repositories\Users;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The standard users resource (docs/decisions/0025), extended by an empty class of the site.
 */
class UsersResourceTest extends StandardResourcesTestCase
{
    private const PATH = '/panneau/api/resources/users';

    public function test_its_definition_has_the_name_email_password_and_role()
    {
        $definition = $this->resourceDefinition('users');

        $this->assertSame('users', $definition['icon']);
        $this->assertSame(['name' => 'text', 'email' => 'email', 'password' => 'text', 'role' => 'select'], array_column($definition['fields'], 'type', 'name'));
        $fields = array_column($definition['fields'], null, 'name');
        $this->assertTrue($fields['password']['settings']['secret']);
        $this->assertSame(['admin', 'editor'], array_column($fields['role']['options'], 'value'));
        $this->assertSame(['name', 'email', 'role'], array_column($definition['list']['columns'], 'field'));
        $this->assertSame(['name', 'email'], $definition['list']['sorts']);
    }

    public function test_the_roles_are_translated()
    {
        $this->app->setLocale('fr');

        $role = $this->app->make(Fixtures\Site\Users::class)->field('role')?->toDefinition();

        $this->assertSame(['Administrateur', 'Éditeur'], array_column($role['options'] ?? [], 'label'));
    }

    public function test_a_user_is_created_with_a_hashed_password_that_is_never_sent()
    {
        $created = $this->postJson(self::PATH, [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'a long secret',
            'role' => 'editor',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Ada')
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonPath('data.password', null);
        $hash = (string) DB::table('users')->where('email', 'ada@example.com')->value('password');
        $this->assertTrue(Hash::check('a long secret', $hash));
        $this->assertSame('editor', DB::table('users')->where('email', 'ada@example.com')->value('role'));

        // An empty password keeps the current one
        $this->patchJson(self::PATH.'/'.$created->json('data.id'), ['name' => 'Ada L.', 'password' => ''])->assertOk();
        $this->assertSame($hash, DB::table('users')->where('email', 'ada@example.com')->value('password'));
    }

    public function test_the_password_is_required_to_create_a_user()
    {
        $this->postJson(self::PATH, ['name' => 'Ada', 'email' => 'ada@example.com'])
            ->assertUnprocessable();
    }

    public function test_the_email_is_unique()
    {
        $user = $this->app->make(Users::class)->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);

        $this->postJson(self::PATH, ['name' => 'Other', 'email' => 'ada@example.com', 'password' => 'a long secret'])
            ->assertUnprocessable();
        $this->patchJson(self::PATH.'/'.$user->id(), ['email' => 'ada@example.com'])->assertOk();
    }

    public function test_a_role_must_be_one_of_the_roles()
    {
        $this->postJson(self::PATH, ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'a long secret', 'role' => 'owner'])
            ->assertUnprocessable();
    }

    public function test_the_list_sorts_by_name_and_email()
    {
        $users = $this->app->make(Users::class);
        foreach ([['Bob', 'c@example.com'], ['Alice', 'b@example.com'], ['Carol', 'a@example.com']] as [$name, $email]) {
            $users->create(['name' => $name, 'email' => $email, 'password' => 'secret']);
        }

        $this->getJson(self::PATH.'?sort=name')->assertOk()->assertJsonPath('data.*.name', ['Alice', 'Bob', 'Carol']);
        $this->getJson(self::PATH.'?sort=-email')->assertOk()->assertJsonPath('data.*.name', ['Bob', 'Alice', 'Carol']);
    }
}
