<?php

namespace Folklore\Tests\Feature\Auth;

use Folklore\Contracts\Entities\User as UserContract;
use Folklore\Contracts\Repositories\Users;
use Folklore\Models\User as UserModel;
use Folklore\Tests\TestCase;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\FortifyServiceProvider;
use PragmaRX\Google2FA\Google2FA;
use ReflectionClass;

class TwoFactorAuthenticationTest extends TestCase
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
        $app['config']->set('hashing.bcrypt.rounds', 4);
        $app['config']->set('fortify.views', false);
        $app['config']->set('fortify.features', [
            Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => false]),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testbench']);
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--path' => dirname((new ReflectionClass(Fortify::class))->getFileName(), 2)
                .'/database/migrations/2014_10_12_200000_add_two_factor_columns_to_users_table.php',
            '--realpath' => true,
        ]);

        $this->users = $this->app->make(Users::class);
    }

    public function test_a_user_without_two_factor_authentication_logs_in_directly()
    {
        $user = $this->createUser();

        $this->postJson('/login', ['email' => 'jane@example.com', 'password' => 'secret'])
            ->assertOk()
            ->assertJson(['two_factor' => false]);

        $this->assertAuthenticated();
        $this->assertSame($user->id(), (string) auth()->id());
    }

    public function test_a_user_can_enable_and_confirm_two_factor_authentication()
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson('/user/two-factor-authentication')->assertOk();

        $model = UserModel::findOrFail($user->id());
        $this->assertNotNull($model->two_factor_secret);
        $this->assertNull($model->two_factor_confirmed_at);
        $this->assertFalse($this->users->findById($user->id())->hasEnabledTwoFactorAuthentication());

        $this->actingAs($user)->getJson('/user/two-factor-qr-code')
            ->assertOk()
            ->assertJsonStructure(['svg', 'url']);
        $this->actingAs($user)->getJson('/user/two-factor-recovery-codes')
            ->assertOk()
            ->assertJsonCount(8);

        $this->actingAs($user)->postJson('/user/confirmed-two-factor-authentication', [
            'code' => $this->currentCode($user),
        ])->assertOk();

        $this->assertNotNull(UserModel::findOrFail($user->id())->two_factor_confirmed_at);
        $this->assertTrue($this->users->findById($user->id())->hasEnabledTwoFactorAuthentication());
    }

    public function test_a_user_with_two_factor_authentication_is_challenged_at_login()
    {
        $user = $this->createUserWithTwoFactorAuthentication();

        $this->postJson('/login', ['email' => 'jane@example.com', 'password' => 'secret'])
            ->assertOk()
            ->assertJson(['two_factor' => true])
            ->assertSessionHas('login.id', $user->id());

        $this->assertGuest();
    }

    public function test_the_challenge_is_not_skipped_for_a_user_with_two_factor_columns()
    {
        $user = $this->createUser();
        UserModel::findOrFail($user->id())->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('SECRETKEY234567A'),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->postJson('/login', ['email' => 'jane@example.com', 'password' => 'secret'])
            ->assertOk()
            ->assertJson(['two_factor' => true]);

        $this->assertGuest();
    }

    public function test_the_challenge_logs_the_user_in_with_a_valid_code()
    {
        $user = $this->createUserWithTwoFactorAuthentication();
        $this->postJson('/login', ['email' => 'jane@example.com', 'password' => 'secret']);
        // The confirmation used the current code, and Fortify refuses a code twice.
        $this->app['cache']->store()->flush();

        $this->withSession(['login.id' => $user->id()])
            ->postJson('/two-factor-challenge', ['code' => '000000'])
            ->assertStatus(422);
        $this->assertGuest();

        $this->withSession(['login.id' => $user->id()])
            ->postJson('/two-factor-challenge', ['code' => $this->currentCode($user)])
            ->assertNoContent();

        $this->assertAuthenticated();
        $this->assertSame($user->id(), (string) auth()->id());
    }

    public function test_the_challenge_logs_the_user_in_with_a_recovery_code_and_replaces_it()
    {
        $user = $this->createUserWithTwoFactorAuthentication();
        $recoveryCode = $this->users->findById($user->id())->recoveryCodes()[0];
        $this->postJson('/login', ['email' => 'jane@example.com', 'password' => 'secret']);

        $this->withSession(['login.id' => $user->id()])
            ->postJson('/two-factor-challenge', ['recovery_code' => $recoveryCode])
            ->assertNoContent();

        $this->assertAuthenticated();
        $recoveryCodes = $this->users->findById($user->id())->recoveryCodes();
        $this->assertCount(8, $recoveryCodes);
        $this->assertNotContains($recoveryCode, $recoveryCodes);
    }

    public function test_a_user_can_disable_two_factor_authentication()
    {
        $user = $this->createUserWithTwoFactorAuthentication();

        $this->actingAs($user)->deleteJson('/user/two-factor-authentication')->assertOk();

        $model = UserModel::findOrFail($user->id());
        $this->assertNull($model->two_factor_secret);
        $this->assertNull($model->two_factor_recovery_codes);
        $this->assertNull($model->two_factor_confirmed_at);

        $this->app['auth']->guard()->logout();
        $this->postJson('/login', ['email' => 'jane@example.com', 'password' => 'secret'])
            ->assertJson(['two_factor' => false]);
        $this->assertAuthenticated();
    }

    public function test_a_user_can_confirm_their_password()
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'wrong'])
            ->assertStatus(422);
        $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'secret'])
            ->assertCreated();
    }

    public function test_two_factor_secrets_are_hidden_from_the_model_serialization()
    {
        $user = $this->createUserWithTwoFactorAuthentication();

        $attributes = UserModel::findOrFail($user->id())->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $attributes);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $attributes);
    }

    protected function createUser(): UserContract
    {
        return $this->users->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret',
        ]);
    }

    protected function createUserWithTwoFactorAuthentication(): UserContract
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson('/user/two-factor-authentication')->assertOk();
        $this->actingAs($user)->postJson('/user/confirmed-two-factor-authentication', [
            'code' => $this->currentCode($user),
        ])->assertOk();
        $this->app['auth']->guard()->logout();
        $this->flushSession();

        return $user;
    }

    protected function currentCode(UserContract $user): string
    {
        return $this->app->make(Google2FA::class)->getCurrentOtp(
            Fortify::currentEncrypter()->decrypt(UserModel::findOrFail($user->id())->two_factor_secret)
        );
    }
}
