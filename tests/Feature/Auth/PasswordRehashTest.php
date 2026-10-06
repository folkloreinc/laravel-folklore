<?php

namespace Folklore\Tests\Feature\Auth;

use Folklore\Contracts\Repositories\Users;
use Folklore\Models\User as UserModel;
use Folklore\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;

class PasswordRehashTest extends TestCase
{
    protected Users $users;

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [FortifyServiceProvider::class]);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('auth.providers.users', ['driver' => 'repository']);
        $app['config']->set('hashing.bcrypt.rounds', 10);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testbench']);

        $this->users = $this->app->make(Users::class);
    }

    public function test_it_rehashes_an_outdated_password()
    {
        $model = $this->createUserWithOutdatedHash('secret');
        $outdatedHash = $model->password;

        $this->users->rehashPasswordIfRequired($this->users->findById((string) $model->id), [
            'password' => 'secret',
        ]);

        $hash = $model->fresh()->password;
        $this->assertNotSame($outdatedHash, $hash);
        $this->assertTrue(Hash::check('secret', $hash));
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function test_it_keeps_an_up_to_date_password()
    {
        $user = $this->users->create([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret',
        ]);
        $hash = $user->getModel()->password;

        $this->users->rehashPasswordIfRequired($user, ['password' => 'secret']);

        $this->assertSame($hash, $user->getModel()->fresh()->password);
    }

    public function test_it_rehashes_when_forced()
    {
        $user = $this->users->create([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret',
        ]);
        $hash = $user->getModel()->password;

        $this->users->rehashPasswordIfRequired($user, ['password' => 'secret'], true);

        $newHash = $user->getModel()->fresh()->password;
        $this->assertNotSame($hash, $newHash);
        $this->assertTrue(Hash::check('secret', $newHash));
    }

    public function test_login_through_the_repository_provider_rehashes_the_password()
    {
        $model = $this->createUserWithOutdatedHash('secret');
        $outdatedHash = $model->password;

        $this->assertTrue(Auth::guard('web')->attempt([
            'email' => 'john@example.com',
            'password' => 'secret',
        ]));

        $hash = $model->fresh()->password;
        $this->assertNotSame($outdatedHash, $hash);
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function test_fortify_login_rehashes_the_password()
    {
        $model = $this->createUserWithOutdatedHash('secret');
        $outdatedHash = $model->password;

        $user = call_user_func(Fortify::$authenticateUsingCallback, Request::create('/login', 'POST', [
            'email' => 'john@example.com',
            'password' => 'secret',
        ]));

        $this->assertSame('john@example.com', $user?->email());
        $hash = $model->fresh()->password;
        $this->assertNotSame($outdatedHash, $hash);
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function test_fortify_login_keeps_the_hash_when_rehash_on_login_is_disabled()
    {
        $this->app['config']->set('hashing.rehash_on_login', false);
        $model = $this->createUserWithOutdatedHash('secret');
        $outdatedHash = $model->password;

        call_user_func(Fortify::$authenticateUsingCallback, Request::create('/login', 'POST', [
            'email' => 'john@example.com',
            'password' => 'secret',
        ]));

        $this->assertSame($outdatedHash, $model->fresh()->password);
    }

    public function test_fortify_login_rejects_a_wrong_password()
    {
        $this->createUserWithOutdatedHash('secret');

        $this->assertNull(call_user_func(Fortify::$authenticateUsingCallback, Request::create('/login', 'POST', [
            'email' => 'john@example.com',
            'password' => 'wrong',
        ])));
    }

    protected function createUserWithOutdatedHash(string $password): UserModel
    {
        $model = $this->users
            ->create([
                'name' => 'John',
                'email' => 'john@example.com',
                'password' => $password,
            ])
            ->getModel();

        $model->forceFill(['password' => Hash::make($password, ['rounds' => 4])])->save();

        return $model;
    }
}
