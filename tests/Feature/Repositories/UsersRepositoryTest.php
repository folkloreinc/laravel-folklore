<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Contracts\Repositories\Users;
use Folklore\Tests\TestCase;

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

    protected function createUser(string $email): void
    {
        $this->users->create([
            'name' => $email,
            'email' => $email,
            'password' => 'secret',
        ]);
    }
}
