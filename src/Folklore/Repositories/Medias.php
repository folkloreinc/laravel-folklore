<?php

namespace Folklore\Repositories;

use Exception;
use Folklore\Contracts\Entities\Media as MediaContract;
use Folklore\Contracts\Repositories\Medias as MediasRepositoryContract;
use Folklore\Mediatheque\Contracts\Models\Media as MediathequeMedia;
use Folklore\Mediatheque\Facades\Mediatheque;
use Folklore\Mediatheque\Support\Models;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;

class Medias extends Entities implements MediasRepositoryContract
{
    /**
     * Seconds allowed to connect to the host, and to download the whole file,
     * when a media is created from a URL.
     */
    protected int $downloadConnectTimeout = 10;

    protected int $downloadTimeout = 600;

    /**
     * Maximum size in bytes of a file downloaded when a media is created from
     * a URL, unless `site.medias.download.max_size` sets another one (null
     * removes the limit).
     */
    protected int $downloadMaxSize = 1024 * 1024 * 1024;

    /**
     * The media model of `config('mediatheque.models.media')`.
     */
    protected function newModel(): Model
    {
        $class = Models::media();

        return new $class;
    }

    protected function newQuery()
    {
        return parent::newQuery()->with('mediaFiles');
    }

    public function findById(string $id): ?MediaContract
    {
        return parent::findById($id);
    }

    public function findByName(string $name): ?MediaContract
    {
        $model = $this->newQueryWithParams()
            ->where('name', $name)
            ->first();

        return to_entity($model);
    }

    public function findByPath(string $path): ?MediaContract
    {
        $name = $this->getNameFromPath($path);

        return $this->findByName($name);
    }

    public function create($data): MediaContract
    {
        return parent::create($data);
    }

    /**
     * Ingests the file with the mediatheque (`Mediatheque::ingest()`), which validates it,
     * stores it, probes it and runs its type's pipeline, then fills the model with the data.
     */
    public function createFromFile(File $file, $data = []): MediaContract
    {
        $data = (array) $data;
        $name = $data['name'] ?? null;
        $model = Mediatheque::ingest($file->getRealPath(), name: is_string($name) ? $name : null);
        $data = Arr::except($data, ['name']);
        if (count($data) > 0) {
            $this->saveData($model, $data);
        }
        if ($model instanceof Model) {
            $model->load('mediaFiles');
        }

        return to_entity($model);
    }

    /**
     * Replaces the original of a media (`Mediatheque::replaceOriginal()`): it keeps its id,
     * uid and usages, and its renditions are produced again.
     */
    public function updateFromFile(string $id, File $file, $data = []): MediaContract
    {
        $model = $this->findModelById($id);
        if (! $model instanceof MediathequeMedia) {
            throw (new ModelNotFoundException)->setModel(Models::media(), [$id]);
        }
        $model = Mediatheque::replaceOriginal($model, $file->getRealPath());
        $data = (array) $data;
        if (count($data) > 0) {
            $this->saveData($model, $data);
        }
        if ($model instanceof Model) {
            $model->load('mediaFiles');
        }

        return to_entity($model);
    }

    public function createFromPath(string $path, $data = []): ?MediaContract
    {
        $name = $this->getNameFromPath($path);
        $isUrl = filter_var($path, FILTER_VALIDATE_URL);
        if ($isUrl) {
            $path = $this->downloadFile($path);
        }
        $media = ! empty($path)
            ? $this->createFromFile(
                new File($path),
                array_merge(
                    ! empty($name)
                        ? [
                            'name' => $name,
                        ]
                        : [],
                    $data
                )
            )
            : null;

        if ($isUrl && ! empty($path) && file_exists($path)) {
            unlink($path);
        }

        return $media;
    }

    public function update(string $id, $data): ?MediaContract
    {
        return parent::update($id, $data);
    }

    /**
     * Downloads a URL to a temporary file, with the limits of `site.medias.download`, for
     * the caller to ingest and delete. Null when the download is refused or fails.
     */
    public function downloadForIngest(string $url): ?string
    {
        return $this->downloadFile($url);
    }

    protected function downloadFile(string $url): ?string
    {
        if (! $this->isAllowedDownloadUrl($url)) {
            Log::warning('Media download refused: the host of '.$url.' is not allowed.');

            return null;
        }

        $cleanPath = parse_url($url, PHP_URL_PATH) ?: $url;
        $ext = pathinfo($cleanPath, PATHINFO_EXTENSION);

        $tempFile = tempnam($this->getDownloadDirectory(), 'media');
        if ($tempFile === false) {
            return null;
        }
        $tempPath = $ext !== '' ? $tempFile.'.'.$ext : $tempFile;
        if ($tempPath !== $tempFile && ! rename($tempFile, $tempPath)) {
            unlink($tempFile);

            return null;
        }

        $maxSize = $this->getDownloadMaxSize();
        $stream = null;
        $size = 0;
        $tooLarge = false;

        try {
            $stream = Utils::streamFor(Utils::tryFopen($tempPath, 'w+'));
            // Writing nothing once the file is too large makes the HTTP client
            // abort the transfer.
            $sink = FnStream::decorate($stream, [
                'write' => function (string $data) use ($stream, $maxSize, &$size, &$tooLarge): int {
                    $size += strlen($data);
                    if (! is_null($maxSize) && $size > $maxSize) {
                        $tooLarge = true;

                        return 0;
                    }

                    return $stream->write($data);
                },
            ]);

            $this->newHttpClient()->request('GET', $url, [
                'sink' => $sink,
                'verify' => true,
                'connect_timeout' => $this->downloadConnectTimeout,
                'timeout' => $this->downloadTimeout,
                'allow_redirects' => [
                    'on_redirect' => function ($request, $response, UriInterface $uri) {
                        if (! $this->isAllowedDownloadUrl((string) $uri)) {
                            throw new RuntimeException('Redirected to a host that is not allowed: '.$uri->getHost().'.');
                        }
                    },
                ],
                'on_headers' => function ($response) use ($maxSize) {
                    $this->checkDownloadContentLength($response, $maxSize);
                },
            ]);

            if ($tooLarge) {
                throw new RuntimeException('The file is larger than the maximum download size of '.$maxSize.' bytes.');
            }

            $failed = false;
        } catch (Exception $e) {
            Log::error($e);
            $failed = true;
        }

        $stream?->close();

        if ($failed) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return null;
        }

        return $tempPath;
    }

    /**
     * Refuses a download whose Content-Length is larger than the maximum size.
     * Guzzle 7's MockHandler also passes the exception of a failed request as
     * the response.
     */
    protected function checkDownloadContentLength(mixed $response, ?int $maxSize): void
    {
        $length = $response instanceof ResponseInterface
            ? $response->getHeaderLine('Content-Length')
            : '';
        if (! is_null($maxSize) && ctype_digit($length) && (int) $length > $maxSize) {
            throw new RuntimeException('The file is larger than the maximum download size of '.$maxSize.' bytes.');
        }
    }

    /**
     * Whether the URL's host is allowed by `site.medias.download.allowed_hosts`.
     */
    protected function isAllowedDownloadUrl(string $url): bool
    {
        $allowedHosts = $this->getDownloadAllowedHosts();
        if (is_null($allowedHosts)) {
            return true;
        }

        $host = $this->getDownloadHost($url);
        if (is_null($host)) {
            return false;
        }

        foreach ($allowedHosts as $allowedHost) {
            if (Str::is(strtolower(trim($allowedHost)), $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The host of a download URL, lowercased, or null unless it is a plain
     * host name. Percent escapes, user info, backslashes, brackets or a
     * trailing dot can make the HTTP client connect to another host than the
     * one checked (GHSA-v5mv-p594-2x33), so such URLs are refused when the
     * allowed hosts are set.
     */
    protected function getDownloadHost(string $url): ?string
    {
        if (str_contains($url, '\\')) {
            return null;
        }

        try {
            $uri = new Uri($url);
        } catch (InvalidArgumentException $e) {
            return null;
        }

        $host = strtolower($uri->getHost());
        if (
            $uri->getUserInfo() !== '' ||
            $host !== strtolower((string) parse_url($url, PHP_URL_HOST)) ||
            preg_match('/\A[a-z0-9-]+(\.[a-z0-9-]+)*\z/', $host) !== 1
        ) {
            return null;
        }

        return $host;
    }

    /**
     * Hosts that medias can be downloaded from (`*` matches any characters),
     * from `site.medias.download.allowed_hosts`, or null to allow every host.
     * Redirects must stay on these hosts too.
     */
    protected function getDownloadAllowedHosts(): ?array
    {
        $hosts = config('site.medias.download.allowed_hosts');
        if (is_null($hosts)) {
            return null;
        }

        return is_string($hosts) ? explode(',', $hosts) : (array) $hosts;
    }

    /**
     * Maximum size of a downloaded file in bytes, or null for no limit.
     */
    protected function getDownloadMaxSize(): ?int
    {
        $maxSize = config('site.medias.download.max_size', $this->downloadMaxSize);

        return is_null($maxSize) ? null : (int) $maxSize;
    }

    protected function newHttpClient(): HttpClient
    {
        return new HttpClient;
    }

    protected function getDownloadDirectory(): string
    {
        return sys_get_temp_dir();
    }

    protected function getNameFromPath(string $path): ?string
    {
        // $ext = pathinfo($path, PATHINFO_EXTENSION);
        // $name = filter_var($path, FILTER_VALIDATE_URL)
        //     ? parse_url($path, PHP_URL_PATH)
        //     : basename($path);

        $isUrl = (bool) filter_var($path, FILTER_VALIDATE_URL);
        $cleanPath = $isUrl ? (parse_url($path, PHP_URL_PATH) ?: $path) : $path;
        $ext = pathinfo($cleanPath, PATHINFO_EXTENSION);
        $name = $isUrl ? $cleanPath : basename($path);

        return Str::slug(
            ! empty($ext) ? preg_replace('/\.'.preg_quote($ext, '/').'$/', '', $name) : $name
        );
    }

    protected function buildQueryFromParams($query, $params)
    {
        $query = parent::buildQueryFromParams($query, $params);

        if (isset($params['search']) && ! empty($params['search'])) {
            if (is_numeric($params['search'])) {
                $query->where('id', $params['search']);
            } else {
                $search = explode(' ', $params['search']);
                foreach ($search as $term) {
                    $word = Str::slug($term);
                    $query->where(function ($q) use ($word) {
                        $q->where('name', 'LIKE', '%'.$word.'%');
                    });
                }
            }
        }

        if (isset($params['type']) && ! empty($params['type'])) {
            $query->whereIn('type', (array) $params['type']);
        }

        if (isset($params['types']) && ! empty($params['types'])) {
            $query->whereIn('type', (array) $params['types']);
        }

        if (isset($params['exclude_type']) && ! empty($params['exclude_type'])) {
            $query->whereNotIn('type', (array) $params['exclude_type']);
        }

        // If empty order defaults to page order column
        if (! isset($params['order']) || empty($params['order'])) {
            $query->orderBy('created_at', 'DESC');
        }

        return $query;
    }
}
