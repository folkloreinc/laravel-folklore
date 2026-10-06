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

    protected function makeRepository(array $responses): Medias
    {
        $stack = HandlerStack::create(new MockHandler($responses));
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
