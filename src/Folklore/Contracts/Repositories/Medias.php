<?php

namespace Folklore\Contracts\Repositories;

use Symfony\Component\HttpFoundation\File\File;
use Folklore\Contracts\Resources\Media;

interface Medias extends Resources
{
    public function findById(string $id): ?Media;

    public function findByName(string $name): ?Media;

    public function findByPath(string $path): ?Media;

    public function create($data): Media;

    public function update(string $id, $data): ?Media;

    public function createFromFile(File $file, $data = []): Media;

    public function createFromPath(string $path, $data = []): ?Media;
}
