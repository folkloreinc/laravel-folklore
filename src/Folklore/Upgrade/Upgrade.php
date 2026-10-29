<?php

namespace Folklore\Upgrade;

use Folklore\Eloquent\MediaUsagesSync;
use Folklore\JsonData\JsonDataCast;
use Folklore\ServiceProvider;
use Folklore\Upgrade\Checks\BlocksCheck;
use Folklore\Upgrade\Checks\RemovedModulesCheck;
use Folklore\Upgrade\Checks\StoredReferencesCheck;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use RuntimeException;
use Throwable;

/**
 * What `folklore:upgrade` changes in a 1.1 site (docs/upgrade.md, step 4): the modules
 * it uses and the media reference key it stores, in `config/site.php`, and the rows of
 * `medias_pivot` that 1.x wrote without a handle for JSON references.
 */
class Upgrade
{
    /**
     * The path of the site's config file, from its base path.
     */
    public const CONFIG_FILE = 'config/site.php';

    /**
     * The traits of laravel-mediatheque 2, whose media `MediaUsagesSync` syncs, and 1.x.
     */
    public const HAS_MEDIAS = 'Folklore\\Mediatheque\\Concerns\\HasMedias';

    public const LEGACY_HAS_MEDIAS = 'Folklore\\Mediatheque\\Support\\Traits\\HasMedias';

    /**
     * The models of the pages module, whose JSON data syncs media.
     *
     * @var list<class-string<Model>>
     */
    public const PAGES_MODELS = ['Folklore\\Models\\Page', 'Folklore\\Models\\Block'];

    /**
     * How many rows of a model are read at a time.
     */
    public const CHUNK = 500;

    public function plan(Site $site): UpgradePlan
    {
        $notes = [];
        $usage = ModuleUsage::of($site);
        $config = $this->writable($site, [...$this->modules($site, $usage, $notes), ...$this->medias($site)], $notes);
        $rows = $this->mediaPivotRows($site, $usage, $notes);
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
        $file = $this->configFile($site);
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
     * Syncs the media of the JSON data of a model's rows under the `json` handle, then
     * removes the rows without a handle that 1.x wrote for them, and returns how many.
     */
    public function removeMediaPivotRows(Site $site, MediaPivotRows $rows): int
    {
        return $this->walkMediaPivotRows($rows->model, true)['rows'];
    }

    /**
     * The site's config file, or the package's when the site has none.
     */
    protected function configFile(Site $site): ConfigFile
    {
        $path = $site->absolute(self::CONFIG_FILE);
        $code = is_file($path) ? file_get_contents($path) : file_get_contents(__DIR__.'/../../../config/site.php');

        return new ConfigFile(is_string($code) ? $code : '');
    }

    /**
     * The changes that the config file can take: a key whose value isn't a literal
     * (`env(...)`), or under one, is left to the site, and noted.
     *
     * @param  list<ConfigChange>  $changes
     * @param  list<Finding>  $notes
     * @return list<ConfigChange>
     */
    protected function writable(Site $site, array $changes, array &$notes): array
    {
        try {
            $file = $this->configFile($site);
        } catch (RuntimeException $e) {
            $file = null;
            $error = $e->getMessage();
        }

        $writable = [];
        foreach ($changes as $change) {
            try {
                if (is_null($file)) {
                    throw new RuntimeException($error ?? 'config/site.php can\'t be read.');
                }
                $file->set($change->key, $change->value);
                $writable[] = $change;
            } catch (RuntimeException $e) {
                $value = is_bool($change->value) ? ($change->value ? 'true' : 'false') : "'{$change->value}'";
                $notes[] = Finding::todo(
                    "`site.{$change->key}` can't be written in config/site.php: ".$e->getMessage(),
                    "Set `site.{$change->key}` to `{$value}` yourself. {$change->reason}",
                    $change->locations,
                );
            }
        }

        return $writable;
    }

    /**
     * The modules the site uses and that are off. A module whose packages are missing
     * stays off, since the site wouldn't boot: it is noted instead.
     *
     * @param  array<string, list<string>>  $usage  `ModuleUsage::of()`
     * @param  list<Finding>  $notes
     * @return list<ConfigChange>
     */
    protected function modules(Site $site, array $usage, array &$notes): array
    {
        $changes = [];
        foreach ($usage as $module => $locations) {
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
     * The media reference key that the site stores, which its front ends read.
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

        return $changes;
    }

    /**
     * The rows without a handle that 1.x wrote for the media of the JSON data of the
     * models that 2.0 syncs to `medias_pivot`: those cast with `JsonDataCast` that use
     * laravel-mediatheque 2's `HasMedias`, and declare a `medias` relation that the cast
     * syncs. Only the rows of the media that a row's JSON references are counted: a
     * media attached without a handle by other code stays.
     *
     * @param  array<string, list<string>>  $usage  `ModuleUsage::of()`
     * @param  list<Finding>  $notes
     * @return list<MediaPivotRows>
     */
    protected function mediaPivotRows(Site $site, array $usage, array &$notes): array
    {
        $models = $site->models();
        if (isset($usage['pages']) || $site->moduleEnabled('pages')) {
            foreach (self::PAGES_MODELS as $class) {
                if ($site->loads($class)) {
                    $models[] = $class;
                }
            }
        }

        $references = new StoredReferencesCheck;
        $rows = [];
        foreach (array_values(array_unique($models)) as $class) {
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
                $key = $relation->getTable().'|'.$relation->getMorphClass();
                if (isset($rows[$key])) {
                    continue;
                }
                $found = $this->walkMediaPivotRows($class, false);
                $rows[$key] = new MediaPivotRows($class, $relation->getTable(), $relation->getMorphType(), $relation->getMorphClass(), $found['rows'], $found['models']);
            } catch (Throwable $e) {
                $notes[] = Finding::info("The rows of `medias_pivot` of `{$class}` could not be read: ".$e->getMessage());
            }
        }

        foreach ($site->files() as $file) {
            if (! $site->fileReferences($file, self::HAS_MEDIAS) && ! $site->fileReferences($file, self::LEGACY_HAS_MEDIAS)) {
                continue;
            }
            $class = $file->declaredClass();
            if (! is_null($class) && ! $site->loads($class)) {
                $notes[] = Finding::todo(
                    "`{$class}` uses `HasMedias` and doesn't load: its rows of `medias_pivot` without a handle were not looked at.",
                    'Fix it (the Rector rules move it to `Folklore\\Mediatheque\\Concerns\\HasMedias`), then run `folklore:upgrade` again.',
                    [$file->path],
                );
            }
        }

        return array_values($rows);
    }

    /**
     * Reads the rows of a model, in chunks, for the media that their JSON data references
     * through a `medias` relation that the cast syncs, and the rows of `medias_pivot`
     * without a handle of those media. With `$write`, syncs the media under the `json`
     * handle first, then removes those rows, so that the media stay used.
     *
     * @param  class-string<Model>  $class
     * @return array{models: int, rows: int}
     */
    protected function walkMediaPivotRows(string $class, bool $write): array
    {
        $models = 0;
        $rows = 0;
        (new $class)->newQueryWithoutScopes()->chunkById(self::CHUNK, function (Collection $chunk) use ($write, &$models, &$rows) {
            foreach ($chunk as $model) {
                $plan = JsonDataCast::syncRelations($model, null, true)['medias'] ?? null;
                // A relation with its own `sync` callback isn't synced under the handle
                $sync = $plan['relation']['sync'] ?? true;
                if (is_null($plan) || $sync !== true || ! method_exists($model, 'medias')) {
                    continue;
                }
                $ids = [];
                foreach ($plan['ids'] as $id) {
                    if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                        $ids[] = (int) $id;
                    }
                }
                $relation = $model->medias();
                if (count($ids) === 0 || ! $relation instanceof MorphToMany) {
                    continue;
                }

                $query = fn () => $relation->newPivotStatement()
                    ->where($relation->getMorphType(), $relation->getMorphClass())
                    ->where($relation->getForeignPivotKeyName(), $model->getAttribute($relation->getParentKeyName()))
                    ->whereIn($relation->getRelatedPivotKeyName(), $ids)
                    ->whereNull('handle');
                $count = $query()->count();
                if ($count === 0) {
                    continue;
                }
                $models++;
                $rows += $count;
                if ($write) {
                    $model->getConnection()->transaction(function () use ($model, $ids, $query) {
                        // As `MediaUsagesSync` does when the model is saved
                        if (method_exists($model, 'syncMedias')) {
                            $model->syncMedias([MediaUsagesSync::HANDLE => $ids]);
                        }
                        $query()->delete();
                    });
                }
            }
        });

        return ['models' => $models, 'rows' => $rows];
    }
}
