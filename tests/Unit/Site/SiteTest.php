<?php

namespace Folklore\Tests\Unit\Site;

use Folklore\Site\Site;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SiteTest extends TestCase
{
    public function test_it_matches_requests_on_its_hosts()
    {
        $site = new Site(['hosts' => ['example.ca', '*.example.ca']], 'fr');

        $this->assertTrue($site->matchRequest(Request::create('https://example.ca/about')));
        $this->assertTrue($site->matchRequest(Request::create('https://www.example.ca/')));
        $this->assertTrue($site->matchRequest(Request::create('https://WWW.Example.CA/')));
        $this->assertFalse($site->matchRequest(Request::create('https://example.com/')));
        $this->assertFalse($site->matchRequest(Request::create('https://example.ca.attacker.test/')));
    }

    public function test_it_accepts_a_single_host()
    {
        $site = new Site(['host' => 'example.com'], 'en');

        $this->assertSame(['example.com'], $site->hosts());
        $this->assertTrue($site->matchRequest(Request::create('https://example.com/')));
        $this->assertFalse($site->matchRequest(Request::create('https://www.example.com/')));
    }

    public function test_it_matches_requests_on_its_path()
    {
        $site = new Site(['host' => 'example.ca', 'path' => '/blog/'], 'blog');

        $this->assertSame('blog', $site->path());
        $this->assertTrue($site->matchRequest(Request::create('https://example.ca/blog')));
        $this->assertTrue($site->matchRequest(Request::create('https://example.ca/blog/a-post')));
        $this->assertFalse($site->matchRequest(Request::create('https://example.ca/blogging')));
        $this->assertFalse($site->matchRequest(Request::create('https://example.ca/')));
        $this->assertFalse($site->matchRequest(Request::create('https://example.com/blog')));
    }

    public function test_a_path_without_hosts_matches_every_host()
    {
        $site = new Site(['path' => 'en'], 'en');

        $this->assertTrue($site->matchRequest(Request::create('https://example.ca/en/about')));
        $this->assertTrue($site->matchRequest(Request::create('https://example.com/en')));
        $this->assertFalse($site->matchRequest(Request::create('https://example.ca/fr')));
    }

    public function test_a_site_without_hosts_nor_path_matches_no_request()
    {
        $site = new Site(['locale' => 'fr'], 'fr');

        $this->assertFalse($site->matchRequest(Request::create('https://example.ca/')));
    }

    public function test_it_exposes_its_config()
    {
        $site = new Site(['host' => 'example.ca', 'locale' => 'fr', 'social' => ['twitter' => '@example']], 'fr');

        $this->assertSame('fr', $site->id());
        $this->assertSame('fr', $site->get('locale'));
        $this->assertSame('@example', $site->get('social.twitter'));
        $this->assertSame('default', $site->get('missing', 'default'));
        $this->assertSame(
            ['host' => 'example.ca', 'locale' => 'fr', 'social' => ['twitter' => '@example'], 'id' => 'fr'],
            $site->toArray(),
        );
        $this->assertSame(json_encode($site->toArray()), json_encode($site));
    }

    public function test_its_id_can_be_set_in_its_config()
    {
        $this->assertSame('fr', (new Site(['id' => 'fr']))->id());
        $this->assertSame('fr', (new Site(['id' => 'fr'], 'other'))->id());
    }

    public function test_it_needs_an_id()
    {
        $this->expectException(InvalidArgumentException::class);

        new Site(['host' => 'example.ca']);
    }
}
