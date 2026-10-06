<?php

namespace Folklore\Repositories;

use Exception;
use Folklore\Contracts\Entities\Media as MediaContract;
use Folklore\Contracts\Repositories\Medias as MediasRepositoryContract;
use Folklore\Mediatheque\Contracts\Models\Media as MediaModelContract;
use Folklore\Mediatheque\Contracts\Type\Factory as TypeFactory;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

class Medias extends Entities implements MediasRepositoryContract
{
    protected $typeFactory;

    /**
     * Seconds allowed to connect to the host, and to download the whole file,
     * when a media is created from a URL.
     */
    protected int $downloadConnectTimeout = 10;

    protected int $downloadTimeout = 600;

    public function __construct(TypeFactory $typeFactory)
    {
        $this->typeFactory = $typeFactory;
    }

    protected function newModel(): Model
    {
        return resolve(MediaModelContract::class);
    }

    protected function newQuery()
    {
        return parent::newQuery()->with('files', 'metadatas');
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

    public function createFromFile(File $file, $data = []): MediaContract
    {
        $type = $this->typeFactory->typeFromPath($file->getRealPath());
        $model = $type->newModel();
        $model->setOriginalFile($file);
        $this->saveData($model, $data);
        $model->load('files'); // @TODO

        return to_entity($model);
    }

    public function updateFromFile(string $id, File $file, $data = []): MediaContract
    {
        $model = $this->findModelById($id);
        $model->files()->detach();

        $model->setOriginalFile($file);
        $this->saveData($model, $data);

        $type = $model->getType();
        if (! is_null($type)) {
            $pipeline = $type->pipeline();
            if (! is_null($pipeline) && ! $model->typePipelineDisabled()) {
                $model->runPipeline($pipeline);
            }
        }

        $model->load('files');

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

    protected function downloadFile(string $url): ?string
    {
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

        try {
            $this->newHttpClient()->request('GET', $url, [
                'sink' => $tempPath,
                'verify' => true,
                'connect_timeout' => $this->downloadConnectTimeout,
                'timeout' => $this->downloadTimeout,
            ]);

            return $tempPath;
        } catch (Exception $e) {
            Log::error($e);
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return null;
        }
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
