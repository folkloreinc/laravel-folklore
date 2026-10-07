<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Mediatheque\Contracts\Type\Factory as TypeFactory;
use Folklore\Repositories\Medias;
use Folklore\Tests\TestCase;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\File;

class MediasDownloadTest extends TestCase
{
    protected string $directory;

    protected array $history = [];

    protected MockHandler $mock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/folklore-medias-download-'.uniqid();
        File::makeDirectory($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_it_downloads_the_file_and_keeps_its_extension()
    {
        $medias = $this->makeRepository([new Response(200, [], 'image-content')]);

        $path = $medias->download('https://example.com/images/photo.jpg?width=100');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertSame('image-content', file_get_contents($path));
        $this->assertSame([basename($path)], $this->filesInDirectory());
    }

    public function test_it_verifies_tls_certificates_and_sets_timeouts()
    {
        $medias = $this->makeRepository([new Response(200, [], 'image-content')]);

        $medias->download('https://example.com/images/photo.jpg');

        $options = $this->history[0]['options'];
        $this->assertTrue($options['verify']);
        $this->assertSame(10, $options['connect_timeout']);
        $this->assertSame(600, $options['timeout']);
    }

    public function test_a_failed_download_returns_null_and_leaves_no_file()
    {
        $medias = $this->makeRepository([
            new Response(500),
            new ConnectException('Connection refused', new Request('GET', 'https://example.com')),
        ]);

        $this->assertNull($medias->download('https://example.com/images/photo.jpg'));
        $this->assertNull($medias->download('https://example.com/images/photo.jpg'));
        $this->assertSame([], $this->filesInDirectory());
    }

    public function test_a_file_without_extension_uses_a_single_temp_file()
    {
        $medias = $this->makeRepository([new Response(200, [], 'content')]);

        $path = $medias->download('https://example.com/download');

        $this->assertSame('content', file_get_contents($path));
        $this->assertSame([basename($path)], $this->filesInDirectory());
    }

    public function test_downloads_are_limited_to_one_gigabyte_from_any_host_by_default()
    {
        $medias = $this->makeRepository([]);

        $this->assertNull($medias->allowedHosts());
        $this->assertSame(1024 * 1024 * 1024, $medias->maxSize());
    }

    public function test_the_site_config_sets_the_limits()
    {
        config(['site.medias.download' => ['allowed_hosts' => ['cdn.example.com'], 'max_size' => 2048]]);
        $medias = $this->makeRepository([]);

        $this->assertSame(['cdn.example.com'], $medias->allowedHosts());
        $this->assertSame(2048, $medias->maxSize());
    }

    public function test_a_file_announced_as_too_large_is_not_downloaded()
    {
        config(['site.medias.download.max_size' => 10]);
        $medias = $this->makeRepository([new Response(200, ['Content-Length' => '11'], str_repeat('a', 11))]);

        $this->assertNull($medias->download('https://example.com/videos/video.mp4'));
        $this->assertSame([], $this->filesInDirectory());
    }

    public function test_a_file_growing_past_the_maximum_size_is_not_kept()
    {
        config(['site.medias.download.max_size' => 10]);
        $medias = $this->makeRepository([new Response(200, [], str_repeat('a', 11))]);

        $this->assertNull($medias->download('https://example.com/videos/video.mp4'));
        $this->assertSame([], $this->filesInDirectory());
    }

    public function test_a_file_of_the_maximum_size_is_downloaded()
    {
        config(['site.medias.download.max_size' => 10]);
        $medias = $this->makeRepository([new Response(200, ['Content-Length' => '10'], str_repeat('a', 10))]);

        $path = $medias->download('https://example.com/videos/video.mp4');

        $this->assertSame(str_repeat('a', 10), file_get_contents($path));
    }

    public function test_the_size_limit_can_be_removed()
    {
        config(['site.medias.download.max_size' => null]);
        $medias = $this->makeRepository([new Response(200, [], str_repeat('a', 2048))]);

        $path = $medias->download('https://example.com/videos/video.mp4');

        $this->assertSame(2048, filesize($path));
    }

    public function test_allowed_hosts_restrict_downloads()
    {
        config(['site.medias.download.allowed_hosts' => ['cdn.example.com', '*.example.org']]);
        $medias = $this->makeRepository(array_fill(0, 6, new Response(200, [], 'content')));

        $this->assertNotNull($medias->download('https://cdn.example.com/a.jpg'));
        $this->assertNotNull($medias->download('https://CDN.Example.com/b.jpg'));
        $this->assertNotNull($medias->download('https://images.example.org/c.jpg'));
        $this->assertNull($medias->download('https://example.org/d.jpg'));
        $this->assertNull($medias->download('https://cdn.example.com.attacker.test/e.jpg'));
        $this->assertNull($medias->download('http://169.254.169.254/latest/meta-data'));
        $this->assertCount(3, $this->history);
    }

    public function test_allowed_hosts_can_be_a_comma_separated_string()
    {
        config(['site.medias.download.allowed_hosts' => 'cdn.example.com, *.example.org']);
        $medias = $this->makeRepository(array_fill(0, 3, new Response(200, [], 'content')));

        $this->assertNotNull($medias->download('https://cdn.example.com/a.jpg'));
        $this->assertNotNull($medias->download('https://images.example.org/b.jpg'));
        $this->assertNull($medias->download('https://example.com/c.jpg'));
        $this->assertCount(2, $this->history);
    }

    public function test_a_redirect_to_a_host_that_is_not_allowed_is_not_followed()
    {
        config(['site.medias.download.allowed_hosts' => ['cdn.example.com']]);
        $medias = $this->makeRepository([
            new Response(302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
            new Response(200, [], 'secret'),
        ]);

        $this->assertNull($medias->download('https://cdn.example.com/a.jpg'));
        $this->assertCount(1, $this->mock);
        $this->assertSame([], $this->filesInDirectory());
    }

    public function test_allowed_hosts_refuse_hosts_that_are_not_plain_names()
    {
        config(['site.medias.download.allowed_hosts' => ['cdn.example.com', '*.example.org']]);
        $medias = $this->makeRepository(array_fill(0, 9, new Response(200, [], 'content')));

        foreach ([
            'https://x%2E.example.org/a.jpg',
            'https://127.0.0.%31.example.org/a.jpg',
            'https://images..example.org/a.jpg',
            'https://user@cdn.example.com/a.jpg',
            'https://user:secret@cdn.example.com/a.jpg',
            'https://127.0.0.1\\@cdn.example.com/a.jpg',
            'https://cdn.example.com./a.jpg',
            'https://[::1]/a.jpg',
        ] as $url) {
            $this->assertNull($medias->download($url), $url);
        }
        $this->assertCount(0, $this->history);

        $this->assertNotNull($medias->download('https://CDN.example.com/a.jpg'));
    }

    public function test_a_redirect_to_a_host_that_is_not_a_plain_name_is_not_followed()
    {
        config(['site.medias.download.allowed_hosts' => ['*.example.org']]);
        $medias = $this->makeRepository([
            new Response(302, ['Location' => 'https://x%2E.example.org/b.jpg']),
            new Response(200, [], 'content'),
        ]);

        $this->assertNull($medias->download('https://cdn.example.org/a.jpg'));
        $this->assertCount(1, $this->mock);
        $this->assertSame([], $this->filesInDirectory());
    }

    public function test_a_redirect_to_an_allowed_host_is_followed()
    {
        config(['site.medias.download.allowed_hosts' => ['cdn.example.com']]);
        $medias = $this->makeRepository([
            new Response(302, ['Location' => 'https://cdn.example.com/b.jpg']),
            new Response(200, [], 'content'),
        ]);

        $path = $medias->download('https://cdn.example.com/a.jpg');

        $this->assertSame('content', file_get_contents($path));
    }

    protected function makeRepository(array $responses): Medias
    {
        $this->mock = new MockHandler($responses);
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new class($this->app->make(TypeFactory::class), new HttpClient(['handler' => $stack]), $this->directory) extends Medias
        {
            public function __construct(
                TypeFactory $typeFactory,
                protected HttpClient $testClient,
                protected string $testDirectory,
            ) {
                parent::__construct($typeFactory);
            }

            public function download(string $url): ?string
            {
                return $this->downloadFile($url);
            }

            public function allowedHosts(): ?array
            {
                return $this->getDownloadAllowedHosts();
            }

            public function maxSize(): ?int
            {
                return $this->getDownloadMaxSize();
            }

            protected function newHttpClient(): HttpClient
            {
                return $this->testClient;
            }

            protected function getDownloadDirectory(): string
            {
                return $this->testDirectory;
            }
        };
    }

    protected function filesInDirectory(): array
    {
        return array_values(array_diff(scandir($this->directory), ['.', '..']));
    }
}
