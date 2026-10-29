<?php

namespace Folklore\Upgrade;

use Folklore\JsonData\JsonDataCast;
use Folklore\ServiceProvider;
use Folklore\Upgrade\Checks\BlocksCheck;
use Folklore\Upgrade\Checks\RemovedModulesCheck;
use Folklore\Upgrade\Checks\StoredReferencesCheck;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;
use Throwable;

/**
 * What `folklore:upgrade` changes in a 1.1 site (docs/upgrade.md, step 3): the modules
 * it uses, the media reference key it stores and the shape of `MediaResource` it gives,
 * in `config/site.php`, and the rows of `medias_pivot` that 1.x wrote without a handle
 * for JSON references.
 */
class Upgrade
{
    /**
     * The path of the site's config file, from its base path.
     */
    public const CONFIG_FILE = 'config/site.php';

    /**
     * The classes whose use means the site gives media in the JSON shape of 1.1.
     *
     * @var list<string>
     */
    public const RESOURCE_CLASSES = [
        'Folklore\\Http\\Resources\\MediaResource',
        'Folklore\\Http\\Resources\\MediasCollection',
    ];

    /**
     * The traits of laravel-mediatheque 2, whose media `MediaUsagesSync` syncs, and 1.x.
     */
    public const HAS_MEDIAS = 'Folklore\\Mediatheque\\Concerns\\HasMedias';

    public const LEGACY_HAS_MEDIAS = 'Folklore\\Mediatheque\\Support\\Traits\\HasMedias';

    /**
     * The shape of `MediaResource` that 1.1 gave (`MediaResource::LEGACY`, which doesn't
     * load without the medias module's packages).
     */
    public const LEGACY_RESOURCE = 'legacy';

    public function plan(Site $site): UpgradePlan
    {
        $notes = [];
        $config = [...$this->modules($site, $notes), ...$this->medias($site)];
        $rows = $this->mediaPivotRows($site, $notes);
        foreach ((new BlocksCheck)->check($site) as $finding) {
            $notes[] = $finding;
        }

        return new UpgradePlan($config, $rows, $notes);
    }

    /**
     * Writes the values in the site's `config/site.php`, from the package's when the
     * site has none, and returns its path.
     *
     * @param  list<ConfigChange>  $changes
     */
    public function writeConfig(Site $site, array $changes): string
    {
        $path = $site->absolute(self::CONFIG_FILE);
        $code = is_file($path) ? file_get_contents($path) : file_get_contents(__DIR__.'/../../../config/site.php');
        $file = new ConfigFile(is_string($code) ? $code : '');
        foreach ($changes as $change) {
            $file->set($change->key, $change->value);
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $file->code());
        foreach ($changes as $change) {
            $site->config->set('site.'.$change->key, $change->value);
        }

        return $path;
    }

    /**
     * Removes the rows of `medias_pivot` without a handle, and returns how many.
     */
    public function removeMediaPivotRows(Site $site, MediaPivotRows $rows): int
    {
        return $this->mediaPivotQuery($site, $rows)->delete();
    }

    /**
     * The modules the site uses and that are off. A module whose packages are missing
     * stays off, since the site wouldn't boot: it is noted instead.
     *
     * @param  list<Finding>  $notes
     * @return list<ConfigChange>
     */
    protected function modules(Site $site, array &$notes): array
    {
        $changes = [];
        foreach (ModuleUsage::of($site) as $module => $locations) {
            if (in_array($module, RemovedModulesCheck::MODULES, true) || ! array_key_exists($module, ServiceProvider::MODULES) || $site->moduleEnabled($module)) {
                continue;
            }

            $missing = [];
            foreach (ServiceProvider::MODULES[$module] as $class => $package) {
                if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class)) {
                    $missing[] = $package;
                }
            }
            if (count($missing) > 0) {
                $missing = array_values(array_unique($missing));
                $notes[] = Finding::todo(
                    "The site uses the `{$module}` module, which needs ".implode(', ', $missing).': it stays off, since the site would not boot without them.',
                    'composer require '.implode(' ', array_map(fn (string $package) => str_replace(' ', ':', $package), $missing)).', then run `folklore:upgrade` again.',
                    $locations,
                );

                continue;
            }

            $changes[] = new ConfigChange("modules.{$module}", true, "The site uses the `{$module}` module.", $locations);
        }

        return $changes;
    }

    /**
     * The media reference key and the shape of `MediaResource` that the site's front ends
     * read.
     *
     * @return list<ConfigChange>
     */
    protected function medias(Site $site): array
    {
        $changes = [];

        $stored = (new StoredReferencesCheck)->keys($site)['medias'] ?? [];
        if (count($stored) > 0 && $site->config('site.medias.reference_key', 'media') !== 'medias') {
            $changes[] = new ConfigChange(
                'medias.reference_key',
                'medias',
                'The site stores `medias://` references (by id), which its front ends read: it keeps writing them.',
                $stored,
            );
        }

        $resources = [];
        foreach (self::RESOURCE_CLASSES as $class) {
            foreach ($site->references($class) as $occurrence) {
                $resources[] = $occurrence->location();
            }
        }
        if (count($resources) > 0 && $site->config('site.medias.resource') !== self::LEGACY_RESOURCE) {
            $changes[] = new ConfigChange(
                'medias.resource',
                self::LEGACY_RESOURCE,
                'The site gives media with Folklore\'s `MediaResource`, in the JSON shape of 1.1 that its front ends read.',
                array_values(array_unique($resources)),
            );
        }

        return $changes;
    }

    /**
     * The rows without a handle of the models whose JSON data 2.0 syncs to `medias_pivot`:
     * those cast with `JsonDataCast` that use laravel-mediatheque 2's `HasMedias`. A
     * model that replaces the cast with a plain `json` cast syncs its references with
     * its own code, which still owns its rows.
     *
     * @param  list<Finding>  $notes
     * @return list<MediaPivotRows>
     */
    protected function mediaPivotRows(Site $site, array &$notes): array
    {
        $references = new StoredReferencesCheck;
        $rows = [];
        foreach ($site->models() as $class) {
            $model = new $class;
            if (! in_array(self::HAS_MEDIAS, class_uses_recursive($model), true)
                || ! in_array(JsonDataCast::class, $references->jsonAttributes($model), true)
                || ! method_exists($model, 'medias')) {
                continue;
            }

            try {
                $relation = $model->medias();
                if (! $relation instanceof MorphToMany) {
                    continue;
                }
                $found = new MediaPivotRows($class, $relation->getTable(), $relation->getMorphType(), $relation->getMorphClass(), 0);
                $key = $found->table.'|'.$found->morphClass;
                if (isset($rows[$key])) {
                    continue;
                }
                $rows[$key] = new MediaPivotRows($class, $found->table, $found->morphType, $found->morphClass, $this->mediaPivotQuery($site, $found)->count());
            } catch (Throwable $e) {
                $notes[] = Finding::info("The rows of `medias_pivot` of `{$class}` could not be read: ".$e->getMessage());
            }
        }

        foreach ($site->files() as $file) {
            $class = $file->declaredClass();
            if (! is_null($class) && ! Site::loads($class) && ($site->fileReferences($file, self::HAS_MEDIAS) || $site->fileReferences($file, self::LEGACY_HAS_MEDIAS))) {
                $notes[] = Finding::todo(
                    "`{$class}` uses `HasMedias` and doesn't load: its rows of `medias_pivot` without a handle were not looked at.",
                    'Fix it (the Rector rules move it to `Folklore\\Mediatheque\\Concerns\\HasMedias`), then run `folklore:upgrade` again.',
                    [$file->path],
                );
            }
        }

        return array_values($rows);
    }

    protected function mediaPivotQuery(Site $site, MediaPivotRows $rows): Builder
    {
        $model = new $rows->model;

        return $site->db->connection($model->getConnectionName())
            ->table($rows->table)
            ->where($rows->morphType, $rows->morphClass)
            ->whereNull('handle');
    }
}
