<?php

namespace App\View\Composers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Contracts\Resources\Pageable;
use App\Contracts\Resources\Page;

class MetaComposer
{
    /**
     * The request
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Create a new profile composer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Bind data to the view.
     *
     * @param  View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $locale = $this->request->locale();
        $view->title = trans('meta.title');
        $view->description = trans('meta.description');
        $view->image = asset(trans('meta.image'));
        $view->url = $this->request->url();
        $view->canonical = $this->request->url();
        $view->siteName = trans('meta.siteName');
        $view->appId = config('services.facebook.client_id');

        $page = $view->page instanceof JsonResource ? $view->page->resource : $view->page;

        if ($page instanceof Pageable) {
            $metadata = $page->metadata();

            $title = $metadata->title($locale, true);
            $description = $metadata->description($locale, true);
            $image = $metadata->image($locale, true);
            $canonical = $metadata->canonical($locale, true);

            if (!empty($title)) {
                if (!$page instanceof Page || $page->handle() !== 'home') {
                    $view->title = trans('meta.title_prefix', ['title' => $title]);
                }
            }
            if (!empty($description)) {
                $view->description = $description;
            }
            if (isset($image)) {
                $view->image = $image->url();
                $facebook = $image->sizes()->first(function ($it) {
                    return $it->id() === 'facebook';
                });
                if (!is_null($facebook)) {
                    $view->image = $facebook->url();
                }
            }
            if (!empty($canonical)) {
                $view->canonical = $canonical;
            }
        }
    }
}
