<?php

namespace Folklore\Repositories;

use Symfony\Component\HttpFoundation\File\File;
use Folklore\Mediatheque\Contracts\Models\Media as MediaModelContract;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Folklore\Contracts\Repositories\Medias as MediasRepositoryContract;
use Folklore\Contracts\Resources\Media as MediaContract;
use Folklore\Mediatheque\Contracts\Type\Factory as TypeFactory;
use Folklore\Contracts\Resources\Resourcable;
use GuzzleHttp\Client as HttpClient;
use Exception;
use Illuminate\Support\Facades\Log;

class Medias extends Resources implements MediasRepositoryContract
{
    protected $typeFactory;

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
        return $model instanceof Resourcable ? $model->toResource() : $model;
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
        $this->fillModel($model, $data);
        $model->save(); // @TODO
        $model->load('files'); // @TODO
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function updateFromFile(string $id, File $file, $data = []): MediaContract
    {
        $model = $this->findModelById($id);
        $model->files()->detach();

        $model->setOriginalFile($file);
        $model->save();

        $type = $model->getType();
        if (!is_null($type)) {
            $pipeline = $type->pipeline();
            if (!is_null($pipeline) && !$model->typePipelineDisabled()) {
                $model->runPipeline($pipeline);
            }
        }

        $model->load('files');
        return $model instanceof Resourcable ? $model->toResource() : $model;
    }

    public function createFromPath(string $path, $data = []): ?MediaContract
    {
        $name = $this->getNameFromPath($path);
        $isUrl = filter_var($path, FILTER_VALIDATE_URL);
        if ($isUrl) {
            $path = $this->downloadFile($path);
        }
        $media = !empty($path)
            ? $this->createFromFile(
                new File($path),
                array_merge(
                    !empty($name)
                        ? [
                            'name' => $name,
                        ]
                        : [],
                    $data
                )
            )
            : null;

        if ($isUrl && file_exists($path)) {
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
        $ext = pathinfo($url, PATHINFO_EXTENSION);
        $tempPath = tempnam(sys_get_temp_dir(), 'media') . '.' . $ext;
        $client = new HttpClient();
        try {
            $client->request('GET', $url, ['sink' => $tempPath, 'verify' => false]);
            return $tempPath;
        } catch (Exception $e) {
            Log::error($e);
            return null;
        }
    }

    protected function getNameFromPath(string $path): ?string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $name = filter_var($path, FILTER_VALIDATE_URL)
            ? parse_url($path, PHP_URL_PATH)
            : basename($path);
        return Str::slug(
            !empty($ext) ? preg_replace('/\.' . preg_quote($ext, '/') . '$/', '', $name) : $name
        );
    }

    protected function buildQueryFromParams($query, $params)
    {
        $query = parent::buildQueryFromParams($query, $params);

        if (isset($params['search']) && !empty($params['search'])) {
            if (is_numeric($params['search'])) {
                $query->where('id', $params['search']);
            } else {
                $search = explode(' ', $params['search']);
                foreach ($search as $term) {
                    $word = Str::slug($term);
                    $query->where(function ($q) use ($word) {
                        $q->where('name', 'LIKE', '%' . $word . '%');
                    });
                }
            }
        }

        if (isset($params['type']) && !empty($params['type'])) {
            $query->whereIn('type', (array) $params['type']);
        }

        if (isset($params['types']) && !empty($params['types'])) {
            $query->whereIn('type', (array) $params['types']);
        }

        if (isset($params['exclude_type']) && !empty($params['exclude_type'])) {
            $query->whereNotIn('type', (array) $params['exclude_type']);
        }

        // If empty order defaults to page order column
        if (!isset($params['order']) || empty($params['order'])) {
            $query->orderBy('created_at', 'DESC');
        }

        return $query;
    }
}
