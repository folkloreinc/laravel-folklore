<?php

namespace Folklore\Tests\Feature\Migrations;

use Folklore\Contracts\Repositories\Organisations;
use Folklore\Contracts\Repositories\Users;
use Folklore\Models\OrganisationInvitation;
use Folklore\Tests\TestCase;
use Illuminate\Support\Carbon;

class OrganisationsMigrationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testbench']);
        foreach ([
            '000000_create_organisations_table',
            '000001_create_organisations_members_table',
            '000002_create_roles_table',
            '000003_create_organisations_invitations_table',
        ] as $migration) {
            $this->artisan('migrate', [
                '--database' => 'testbench',
                '--path' => __DIR__.'/../../../src/migrations/2023_03_15_'.$migration.'.php',
                '--realpath' => true,
            ]);
        }
    }

    public function test_a_member_can_be_added_without_a_role()
    {
        $user = $this->app->make(Users::class)->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret',
        ]);
        $organisations = $this->app->make(Organisations::class);
        $organisation = $organisations->create(['name' => 'Acme', 'slug' => 'acme']);

        $member = $organisations->addMemberFromUser($organisation->id(), $user, []);

        $this->assertNotNull($member);
        $this->assertNull($member->role());
        $this->assertSame('jane@example.com', $member->user()->email());
    }

    public function test_an_invitation_can_be_saved_and_read_through_its_entity()
    {
        $organisation = $this->app->make(Organisations::class)->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $model = OrganisationInvitation::create([
            'organisation_id' => $organisation->id(),
            'email' => 'jane@example.com',
            'role' => 'editor',
            'token' => 'invitation-token',
            'expires_at' => Carbon::parse('2026-12-31 12:00:00'),
        ]);
        $invitation = OrganisationInvitation::findOrFail($model->id)->toEntity();

        $this->assertSame('jane@example.com', $invitation->email());
        $this->assertSame('editor', $invitation->role());
        $this->assertSame('invitation-token', $invitation->token());
        $this->assertSame('2026-12-31 12:00:00', $invitation->expiresAt()?->format('Y-m-d H:i:s'));
        $this->assertNotNull($invitation->invitedAt());
        $this->assertSame('acme', $invitation->organisation()->slug());
    }
}
