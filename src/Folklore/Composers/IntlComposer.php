<?php

namespace Folklore\Composers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Folklore\Composers\Concerns\ComposesIntl;

class IntlComposer
{
    use ComposesIntl;

    protected $namespaces = ['*'];

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
