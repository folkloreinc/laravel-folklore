<?php

namespace App\Http\Resources;

use Folklore\Http\Resources\MediaResource;

class ArticleMediaResource extends MediaResource
{
    public function toArray($request)
    {
        return array_merge(parent::toArray($request), [
            'credits' => $this->credits(),
            'thumbnail' => $this->thumbnailUrl(),
            'large' => $this->when($this->resource->isImage(), fn () => $this->files()->first()),
            'legacy' => $this->withoutFiles()->isLegacy(),
        ]);
    }

    public function forArticle($article)
    {
        $this->resource = $article->cover();

        return $this;
    }

    protected function credits(): ?string
    {
        return $this->metadata()->get('credits');
    }
}
-----
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Folklore\Http\Resources\MediaResource;

class ArticleMediaResource extends MediaResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'credits' => $this->credits(),
            'thumbnail' => $this->entity()->thumbnailUrl(),
            'large' => $this->when($this->entity()->isImage(), fn () => $this->entity()->files()->first()),
            'legacy' => $this->withoutFiles()->isLegacy(),
        ]);
    }

    public function forArticle($article)
    {
        $this->resource = $article->cover();

        return $this;
    }

    protected function credits(): ?string
    {
        return $this->entity()->metadata()->get('credits');
    }
}
