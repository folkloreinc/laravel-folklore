<?php

namespace Folklore\Panneau\Resources;

use Folklore\Contracts\Entities\HasModel;
use Folklore\Contracts\Entities\Page as PageContract;
use Folklore\Contracts\Repositories\Pages as PagesRepository;
use Folklore\JsonData\JsonData;
use Folklore\Panneau\Fields\Blocks;
use Folklore\Panneau\Fields\Page as PageField;
use Folklore\Panneau\Fields\PageSlug;
use Folklore\Panneau\Resources\Concerns\EditsFields;
use Folklore\Support\OtherLocales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Panneau\Fields\Boolean;
use Panneau\Fields\Field;
use Panneau\Fields\Text;
use Panneau\Fields\Textarea;
use Panneau\Layout\Field as FieldNode;
use Panneau\Layout\Node;
use Panneau\Layout\Publication;
use Panneau\Lists\ResourceList;
use Panneau\Resources\Resource;

/**
 * The pages of the site, on the pages module (docs/decisions/0025): their title, address,
 * description, parent and blocks, localized in `panneau.locales`, and their publication at the
 * bottom of the form. A site extends it, even empty, and registers its class:
 *
 * ```php
 * class Pages extends \Folklore\Panneau\Resources\Pages {}
 * ```
 *
 * - Each field comes from its own method (`titleField()`, `blocksField()`…), and `fields()`
 *   lists them: override one to change a field, and `fields()` to add some, with
 *   `insertAfter()` and the other helpers of `EditsFields`. The form follows the order of
 *   `fields()`, with the fields of `publicationFields()` at the bottom.
 * - The blocks are the types of `site.pages.blocks.types` and of the `BlockTypes` registry:
 *   `blocksField()` narrows them.
 * - The list sorts by title and publication, and searches the titles.
 * - Saving keeps the locales of the page and of its blocks that the admin doesn't edit
 *   (`updating()`).
 */
class Pages extends Resource
{
    use EditsFields;

    public function repository(): ?string
    {
        return PagesRepository::class;
    }

    public function itemClass(): ?string
    {
        return PageContract::class;
    }

    public function singularLabel(): string
    {
        return static::trans('resources.pages.singular');
    }

    public function pluralLabel(): string
    {
        return static::trans('resources.pages.plural');
    }

    public function icon(): ?string
    {
        return 'file-text';
    }

    /**
     * The form: a page has no details that the form doesn't show.
     */
    public function open(): string
    {
        return 'edit';
    }

    public function fields(): array
    {
        return [
            $this->titleField(),
            $this->slugField(),
            $this->descriptionField(),
            $this->parentField(),
            $this->blocksField(),
            $this->publishedField(),
        ];
    }

    /**
     * The fields in the order of `fields()`, then the publication of the page, which frames
     * the fields of `publicationFields()` under its state.
     *
     * @return list<Node>
     */
    public function layout(): array
    {
        $publication = $this->publicationFields();
        $nodes = [];
        foreach ($this->fields() as $field) {
            if (! in_array($field->name(), $publication, true)) {
                $nodes[] = FieldNode::make($field->name());
            }
        }
        if (count($publication) > 0) {
            $nodes[] = Publication::make(array_map(fn (string $name): Node => FieldNode::make($name), $publication));
        }

        return $nodes;
    }

    /**
     * The fields of the publication, at the bottom of the form.
     *
     * @return list<string>
     */
    protected function publicationFields(): array
    {
        return ['published'];
    }

    public function list(ResourceList $list): ResourceList
    {
        return $list->columns(['title', 'published'])->sorts(['title', 'published'])->search(['title']);
    }

    /**
     * Required, in each locale of the admin (`panneau.locales`), as Panneau requires a
     * localized field.
     */
    protected function titleField(): Field
    {
        return Text::make('title')
            ->localized()
            ->required()
            ->label(static::trans('fields.title'))
            ->resolveUsing(static::localized(fn (object $page, string $locale): ?string => $page instanceof PageContract ? $page->title($locale) : null));
    }

    /**
     * Editable, from the title until it is changed.
     */
    protected function slugField(): Field
    {
        return PageSlug::make('slug')->label(static::trans('fields.slug'));
    }

    /**
     * Optional: a site that requires it overrides this method.
     */
    protected function descriptionField(): Field
    {
        return Textarea::make('description')
            ->localized()
            ->label(static::trans('fields.description'))
            ->resolveUsing(static::localized(fn (object $page, string $locale): ?string => $page instanceof PageContract ? $page->description($locale) : null));
    }

    protected function parentField(): Field
    {
        return PageField::make('parent')
            ->label(static::trans('fields.parent'))
            ->resolveUsing(fn (object $page): ?PageContract => $page instanceof PageContract ? $page->parent() : null);
    }

    protected function blocksField(): Field
    {
        return Blocks::make('blocks')->label(static::trans('fields.blocks'));
    }

    /**
     * A switch: an on and off setting, in the publication of the page.
     */
    protected function publishedField(): Field
    {
        return Boolean::make('published')
            ->label(static::trans('fields.published'))
            ->settings(['widget' => 'switch'])
            ->resolveUsing(fn (object $page): bool => $page instanceof PageContract && $page->published());
    }

    /**
     * Keeps the locales that the admin doesn't edit (`panneau.locales`), in the page's data
     * and in its blocks, matched by their id: a site in French whose pages have English
     * values too keeps them.
     */
    public function updating(object $item, array $values, Request $request): array
    {
        return $this->withOtherLocales($item, $values);
    }

    /**
     * The values of a save, with the locales of their translatable values that only the stored
     * data of the page has, for the values of its data's schema.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function withOtherLocales(object $item, array $values): array
    {
        $model = $item instanceof HasModel ? $item->getModel() : null;
        if (! $model instanceof Model) {
            return $values;
        }
        $schema = JsonData::schema($model, 'data');
        $stored = $schema !== null ? JsonData::stored($model, 'data') : null;
        if ($schema === null || $stored === null) {
            return $values;
        }
        foreach ($schema->getProperties() as $name => $property) {
            if (array_key_exists($name, $values) && array_key_exists($name, $stored)) {
                $values[$name] = OtherLocales::keep($property, $values[$name], $stored[$name]);
            }
        }

        return $values;
    }
}
