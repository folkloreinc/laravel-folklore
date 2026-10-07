<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Contracts\Repositories\Organisations;
use Folklore\Models\Organisation as OrganisationModel;
use Folklore\Tests\TestCase;

class OrganisationsRepositoryTest extends TestCase
{
    protected Organisations $organisations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--path' => __DIR__.'/../../../src/migrations/2023_03_15_000000_create_organisations_table.php',
            '--realpath' => true,
        ]);

        $this->organisations = $this->app->make(Organisations::class);
    }

    public function test_find_by_slug_matches_the_slug()
    {
        $this->organisations->create(['name' => 'Acme', 'slug' => 'acme']);

        $this->assertSame('acme', $this->organisations->findBySlug('acme')?->slug());
        $this->assertSame('acme', $this->organisations->findBySlug('ACME')?->slug());
        $this->assertNull($this->organisations->findBySlug('acme-inc'));
    }

    public function test_find_by_slug_does_not_treat_like_wildcards_as_patterns()
    {
        $this->organisations->create(['name' => 'Acme', 'slug' => 'acme']);
        $this->organisations->create(['name' => 'Acme Inc', 'slug' => 'acmexinc']);

        $this->assertNull($this->organisations->findBySlug('%'));
        $this->assertNull($this->organisations->findBySlug('ac%'));
        $this->assertNull($this->organisations->findBySlug('acme_inc'));
    }

    public function test_find_by_slug_matches_slugs_containing_special_characters()
    {
        $this->organisations->create(['name' => 'Acme Inc', 'slug' => 'acme_inc']);

        $this->assertSame('acme_inc', $this->organisations->findBySlug('acme_inc')?->slug());
    }

    public function test_destroying_an_organisation_soft_deletes_it()
    {
        $organisation = $this->organisations->create(['name' => 'Acme', 'slug' => 'acme']);

        $this->assertTrue($this->organisations->destroy($organisation->id()));

        $this->assertNull($this->organisations->findById($organisation->id()));
        $this->assertNull($this->organisations->findBySlug('acme'));
        $this->assertSame(0, $this->organisations->count());
        $this->assertTrue(OrganisationModel::withTrashed()->findOrFail($organisation->id())->trashed());
    }
}
