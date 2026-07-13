<?php

namespace Folklore\Panneau\Resources;

use Folklore\Http\Resources\MediaResource;
use Folklore\Http\Resources\MediasCollection;
use Panneau\Fields\Text;
use Panneau\Support\Resource;

class Medias extends Resource
{
    public static $repository = \Folklore\Contracts\Repositories\Medias::class;

    public static $jsonResource = MediaResource::class;

    public static $jsonCollection = MediasCollection::class;

    public static $settings = [
        'hideInNavbar' => true,
        'indexIsPaginated' => true,
        'canCreate' => false,
    ];

    public function name(): string
    {
        return trans('panneau.medias.name');
    }

    public function index(): ?array
    {
        return [
            'columns' => [
                'name',
                [
                    'id' => 'actions',
                    'actions' => ['edit', 'delete'],
                ],
            ],
        ];
    }

    public function fields(): array
    {
        return [Text::make('name')->withTransLabel('panneau.fields.name')];
    }
}
