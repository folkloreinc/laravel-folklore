<?php

namespace Folklore\Composers;

use Folklore\Composers\Concerns\ComposesIntl;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntlComposer
{
    use ComposesIntl;

    protected $namespaces = ['*'];

    /**
     * The request
     *
     * @var Request
     */
    protected $request;

    /**
     * Create a new profile composer.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Bind data to the view.
     *
     * @return void
     */
    public function compose(View $view)
    {
        $locale = app()->getLocale();
        $view->intl = array_merge(
            [
                'locale' => $locale,
                'locales' => config('locale.locales'),
                'messages' => $this->composesTranslations($this->namespaces, $locale),
            ],
            $view->intl ?? []
        );
    }
}
