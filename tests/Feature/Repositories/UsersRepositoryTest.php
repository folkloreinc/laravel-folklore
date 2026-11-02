<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Contracts\Repositories\Users;
use Folklore\Models\User as UserModel;
use Folklore\Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class UsersRepositoryTest extends TestCase
{
    protected Users $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testbench']);

        $this->users = $this->app->make(Users::class);
    }

    public function test_find_by_email_matches_the_address()
    {
        $this->createUser('john@example.com');

        $this->assertSame('john@example.com', $this->users->findByEmail('john@example.com')?->email());
        $this->assertNull($this->users->findByEmail('jane@example.com'));
    }

    public function test_find_by_email_stays_case_insensitive()
    {
        $this->createUser('john@example.com');

        $this->assertSame('john@example.com', $this->users->findByEmail('JOHN@Example.com')?->email());
    }

    public function test_find_by_email_does_not_treat_like_wildcards_as_patterns()
    {
        $this->createUser('john@example.com');
        $this->createUser('janexdoe@example.com');

        $this->assertNull($this->users->findByEmail('%'));
        $this->assertNull($this->users->findByEmail('%@example.com'));
        $this->assertNull($this->users->findByEmail('jane_doe@example.com'));
    }

    public function test_find_by_email_matches_addresses_containing_special_characters()
    {
        $this->createUser('jane_doe@example.com');
        $this->createUser('100%real@example.com');
        $this->createUser('wow!@example.com');

        $this->assertSame('jane_doe@example.com', $this->users->findByEmail('jane_doe@example.com')?->email());
        $this->assertSame('100%real@example.com', $this->users->findByEmail('100%real@example.com')?->email());
        $this->assertSame('wow!@example.com', $this->users->findByEmail('wow!@example.com')?->email());
    }

    public function test_an_empty_password_keeps_the_current_one()
    {
        $this->createUser('john@example.com');
        $user = $this->users->findByEmail('john@example.com');

        $this->users->update($user->id(), ['name' => 'John', 'password' => null]);
        $this->users->update($user->id(), ['password' => '']);

        $this->assertTrue(Hash::check('secret', UserModel::find($user->id())->password));
    }

    public function test_a_new_password_is_hashed()
    {
        $this->createUser('john@example.com');
        $user = $this->users->findByEmail('john@example.com');

        $this->users->update($user->id(), ['password' => 'new-secret']);

        $password = UserModel::find($user->id())->password;
        $this->assertNotSame('new-secret', $password);
        $this->assertTrue(Hash::check('new-secret', $password));
    }

    public function test_a_hashed_password_is_not_hashed_again()
    {
        $this->users->create([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => Hash::make('secret'),
        ]);
        $user = $this->users->findByEmail('john@example.com');

        $this->assertTrue(Hash::check('secret', UserModel::find($user->id())->password));

        $hash = Hash::make('new-secret');
        $this->users->update($user->id(), ['password' => $hash]);

        $this->assertSame($hash, UserModel::find($user->id())->password);
        $this->assertTrue(Hash::check('new-secret', UserModel::find($user->id())->password));
    }

    public function test_a_hash_the_app_could_not_verify_is_hashed()
    {
        $this->createUser('john@example.com');
        $user = $this->users->findByEmail('john@example.com');
        $password = fn (): string => UserModel::find($user->id())->password;

        // Another algorithm than the app's bcrypt: stored as is, the login would throw
        $argon = password_hash('secret', PASSWORD_ARGON2ID);
        $this->users->update($user->id(), ['password' => $argon]);
        $this->assertNotSame($argon, $password());
        $this->assertTrue(Hash::check($argon, $password()));

        // A value that only looks like a hash, with a cost above the app's
        $lookalike = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 11]);
        Hash::driver('bcrypt')->setRounds(10);
        $this->users->update($user->id(), ['password' => $lookalike]);
        $this->assertNotSame($lookalike, $password());
        $this->assertTrue(Hash::check($lookalike, $password()));
    }

    public function test_a_number_is_hashed_as_a_password()
    {
        $this->createUser('john@example.com');
        $user = $this->users->findByEmail('john@example.com');

        $this->users->update($user->id(), ['password' => 12345678]);

        $this->assertTrue(Hash::check('12345678', UserModel::find($user->id())->password));
    }

    protected function createUser(string $email): void
    {
        $this->users->create([
            'name' => $email,
            'email' => $email,
            'password' => 'secret',
        ]);
    }
}
