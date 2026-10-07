<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Contracts\Repositories\Users;
use Folklore\Models\User as UserModel;
use Folklore\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class UsersRoleTest extends TestCase
{
    protected Users $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testbench']);
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--path' => __DIR__.'/../../../src/migrations/2020_01_01_000000_add_role_to_users.php',
            '--realpath' => true,
        ]);

        $this->users = $this->app->make(Users::class);
    }

    public function test_the_role_is_not_mass_assignable_on_the_model()
    {
        $model = UserModel::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret',
            'role' => 'admin',
        ]);

        $this->assertSame('guest', $model->fresh()->role);

        $model->update(['role' => 'admin']);

        $this->assertSame('guest', $model->fresh()->role);
    }

    public function test_the_repository_sets_the_role()
    {
        $user = $this->users->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret',
            'role' => 'editor',
        ]);

        $this->assertSame('editor', $user->role());
        $this->assertSame('editor', UserModel::findOrFail($user->id())->role);

        $this->assertSame('admin', $this->users->update($user->id(), ['role' => 'admin'])?->role());
        $this->assertSame('admin', $this->users->update($user->id(), ['name' => 'Janet'])?->role());
    }

    public function test_the_repository_sets_the_role_when_discarded_attributes_throw()
    {
        Model::preventSilentlyDiscardingAttributes();

        try {
            $user = $this->users->create([
                'name' => 'Jane',
                'email' => 'jane@example.com',
                'password' => 'secret',
                'role' => 'editor',
            ]);
        } finally {
            Model::preventSilentlyDiscardingAttributes(false);
        }

        $this->assertSame('editor', $user->role());
    }
}
