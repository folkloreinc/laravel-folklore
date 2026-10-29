<?php

namespace Folklore\Tests\Feature\Repositories\Fixtures;

use Carbon\CarbonInterface;
use Folklore\Entities\ModelEntity;

/**
 * @extends ModelEntity<Article>
 */
class ArticleEntity extends ModelEntity
{
    public function title(): ?string
    {
        $title = $this->model->getAttribute('title');

        return is_string($title) ? $title : null;
    }

    public function slug(string $locale): ?string
    {
        $slug = $this->model->getAttribute('slug_'.$locale);

        return is_string($slug) ? $slug : null;
    }

    public function status(): string
    {
        $status = $this->model->getAttribute('status');

        return is_string($status) ? $status : 'draft';
    }

    public function workspace(): ?string
    {
        $workspace = $this->model->getAttribute('workspace');

        return is_string($workspace) ? $workspace : null;
    }

    public function publishedAt(): ?CarbonInterface
    {
        $date = $this->model->getAttribute('published_at');

        return $date instanceof CarbonInterface ? $date : null;
    }
}
