<?php

namespace Folklore\Console;

use Folklore\Upgrade\ConfigChange;
use Folklore\Upgrade\Finding;
use Folklore\Upgrade\IsolatedLoader;
use Folklore\Upgrade\JsonSchemaConversion;
use Folklore\Upgrade\JsonSchemaConverter;
use Folklore\Upgrade\MediaPivotRows;
use Folklore\Upgrade\Severity;
use Folklore\Upgrade\Site;
use Folklore\Upgrade\Upgrade;
use Folklore\Upgrade\UpgradeCheck;
use Folklore\Upgrade\UpgradePlan;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Upgrades a site from 1.1 (docs/upgrade.md). `--check` reports what the site uses that
 * changes in 2.0, without writing anything. Without it, the command sets the site's
 * modules and media reference key in `config/site.php`, and replaces the rows of
 * `medias_pivot` that 1.x wrote without a handle for JSON data with rows under the
 * `json` handle; `--dry-run` says what it would do. `--schemas` converts the 1.x JSON
 * declarations of the site's models to `jsonDataSchema()`, instead.
 */
class UpgradeCommand extends Command
{
    use ConfirmableTrait;

    /**
     * How many locations of a finding the report lists; `--json` lists them all.
     */
    public const LOCATIONS = 10;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'folklore:upgrade
        {--check : Report what changes in 2.0, without writing anything}
        {--dry-run : Say what the upgrade would change, without writing anything}
        {--schemas : Convert the 1.x JSON declarations of the models to jsonDataSchema(), instead of the upgrade}
        {--force : Upgrade in production without asking}
        {--path=* : A directory or file of the site to read, from its base path; by default app, config, routes, resources/views and bootstrap}
        {--model=* : A model whose JSON data to read, besides those of the paths}
        {--limit=1000 : How many rows of each model to read; 0 reads them all}
        {--json : Print the report as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upgrade a site from laravel-folklore 1.1: its config/site.php and medias_pivot; --check reports what changes';

    public function handle(UpgradeCheck $upgradeCheck, Upgrade $upgrade, Repository $config, ConnectionResolverInterface $db): int
    {
        $site = $this->site($config, $db);

        if ($this->option('schemas') && ! $this->option('check')) {
            return $this->schemas(new JsonSchemaConverter, $site);
        }

        if (! $this->option('check')) {
            return $this->upgrade($upgrade, $site);
        }

        $report = $upgradeCheck->run($site);
        $findings = array_merge(...array_values($report));

        if ($this->option('json')) {
            $this->line((string) json_encode(
                array_map(fn (array $findings) => array_map(fn (Finding $finding) => $finding->toArray(), $findings), $report),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } else {
            $this->print($report);
        }

        return count(array_filter($findings, fn (Finding $finding) => $finding->severity->blocks())) > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function upgrade(Upgrade $upgrade, Site $site): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $plan = $upgrade->plan($site);
        $this->printPlan($plan, $dryRun);

        if ($dryRun || $plan->isEmpty()) {
            $this->newLine();
            $this->line($plan->isEmpty() ? '<options=bold>Nothing to change.</>' : '<options=bold>Dry run: nothing was written.</> Run `folklore:upgrade` to apply it.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->newLine();
        if (count($plan->config) > 0) {
            try {
                $path = $upgrade->writeConfig($site, $plan->config);
            } catch (RuntimeException $e) {
                $this->components->error('config/site.php was not written: '.$e->getMessage());

                return self::FAILURE;
            }
            $this->components->info('Wrote '.count($plan->config).' values in '.$site->relative($path).'.');
        }

        $removed = 0;
        foreach ($plan->mediaPivotRows as $rows) {
            if ($rows->count > 0) {
                $removed += $upgrade->removeMediaPivotRows($site, $rows);
            }
        }
        if ($removed > 0) {
            $this->components->info("Removed {$removed} rows of `medias_pivot` without a handle, whose media are synced under the `json` handle.");
        }
        if (! is_null($plan->blocks) && ! $plan->blocks->isEmpty()) {
            $converted = $upgrade->convertBlocks($site, true);
            if (! is_null($converted)) {
                $this->components->info("Converted the data of {$converted->pages} pages: {$converted->blocks} blocks put into their page, {$converted->parents} parents as `page://`.");
            }
        }
        if ($this->laravel->configurationIsCached()) {
            $this->components->warn('The configuration is cached: run `php artisan config:cache` again for config/site.php to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * Converts the 1.x JSON declarations of the site's models to `jsonDataSchema()`, and
     * comments those it leaves with why.
     */
    protected function schemas(JsonSchemaConverter $converter, Site $site): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $conversions = array_values(array_filter($converter->conversions($site), fn (JsonSchemaConversion $conversion) => $conversion->changes()));

        $this->newLine();
        $this->line('<options=bold>JSON declarations</>');
        if (count($conversions) === 0) {
            $this->line('  Nothing to convert.');

            return self::SUCCESS;
        }
        foreach ($conversions as $conversion) {
            if ($conversion->empty) {
                $this->line(sprintf('  %s the empty declarations of <fg=green>%s</> (%s)', $dryRun ? 'Would remove' : 'Removes', $conversion->model, $conversion->location()));
            } elseif ($conversion->converts()) {
                $this->line(sprintf('  %s <fg=green>%s</> to `jsonDataSchema()` (%s)', $dryRun ? 'Would convert' : 'Converts', $conversion->model, $conversion->location()));
                foreach ($conversion->todos as $todo) {
                    $this->line('    <fg=yellow>TODO(folklore-3)</> '.$todo);
                }
            } else {
                $this->line(sprintf('  %s <fg=yellow>%s</> (%s): %s.', $dryRun ? 'Would leave' : 'Leaves', $conversion->model, $conversion->location(), implode('; ', $conversion->blockers)));
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('<options=bold>Dry run: nothing was written.</> Run `folklore:upgrade --schemas` to apply it.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->newLine();
        foreach ($conversions as $conversion) {
            try {
                $converter->write($conversion);
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }
        $converted = count(array_filter($conversions, fn (JsonSchemaConversion $conversion) => $conversion->converts()));
        $this->components->info("Converted {$converted} models; wrote why in the ".(count($conversions) - $converted).' others. Read the schemas, then run the site\'s tests.');

        return self::SUCCESS;
    }

    protected function printPlan(UpgradePlan $plan, bool $dryRun): void
    {
        $this->newLine();
        $this->line('<options=bold>config/site.php</>');
        if (count($plan->config) === 0) {
            $this->line('  Nothing to set.');
        }
        foreach ($plan->config as $change) {
            $this->line(sprintf('  %s <fg=green>%s</> → %s', $dryRun ? 'Would set' : 'Sets', $change->key, $this->export($change)));
            $this->line('    '.$change->reason);
            $this->locations($change->locations);
        }

        $this->newLine();
        $this->line('<options=bold>medias_pivot</>');
        $rows = array_filter($plan->mediaPivotRows, fn (MediaPivotRows $rows) => $rows->count > 0);
        if (count($rows) === 0) {
            $this->line('  No rows without a handle to remove.');
        }
        foreach ($rows as $found) {
            $this->line(sprintf(
                '  %s %d rows without a handle of %d rows of `%s` (%s), which 1.x wrote for the media of their JSON data, after syncing these media under the `json` handle.',
                $dryRun ? 'Would remove' : 'Removes',
                $found->count,
                $found->models,
                $found->model,
                $found->morphClass,
            ));
        }

        if (! is_null($plan->blocks)) {
            $this->newLine();
            $this->line('<options=bold>Pages</>');
            $blocks = $plan->blocks;
            if ($blocks->isEmpty()) {
                $this->line('  No blocks or parents to convert.');
            } else {
                $this->line(sprintf(
                    '  %s the data of %d pages of `%s`: %d blocks of `blocks` put into their page, at every level, and %d `parent://` references as `page://`.',
                    $dryRun ? 'Would convert' : 'Converts',
                    $blocks->pages,
                    $blocks->model,
                    $blocks->blocks,
                    $blocks->parents,
                ));
            }
            if ($blocks->missing > 0) {
                $this->line("  {$blocks->missing} `blocks://` references to blocks that don't exist are left out.");
            }
        }

        if (count($plan->notes) > 0) {
            $this->newLine();
            $this->line('<options=bold>Left to do</>');
            foreach ($plan->notes as $finding) {
                $this->line('  '.$this->tag($finding->severity).' '.$finding->message);
                if (! is_null($finding->fix)) {
                    $this->line('    → '.$finding->fix);
                }
                $this->locations($finding->locations);
            }
        }
    }

    protected function export(ConfigChange $change): string
    {
        return is_bool($change->value) ? ($change->value ? 'true' : 'false') : "'{$change->value}'";
    }

    /**
     * @param  list<string>  $locations
     */
    protected function locations(array $locations): void
    {
        foreach (array_slice($locations, 0, self::LOCATIONS) as $location) {
            $this->line("    <fg=gray>{$location}</>");
        }
        if (count($locations) > self::LOCATIONS) {
            $this->line('    <fg=gray>and '.(count($locations) - self::LOCATIONS).' more (--check --json lists them all)</>');
        }
    }

    protected function site(Repository $config, ConnectionResolverInterface $db): Site
    {
        $paths = $this->stringList('path');
        $loader = new IsolatedLoader;
        $models = [];
        foreach ($this->stringList('model') as $model) {
            $error = $loader->error($model);
            if (! is_null($error)) {
                $this->components->warn("[{$model}] doesn't load, and is skipped: {$error}");
            } elseif (! is_subclass_of($model, Model::class)) {
                $this->components->warn("[{$model}] is not an Eloquent model, and is skipped.");
            } else {
                $models[] = $model;
            }
        }

        return new Site(
            $this->laravel->basePath(),
            $config,
            $db,
            count($paths) > 0 ? $paths : Site::PATHS,
            $models,
            max(0, (int) $this->option('limit')),
            $loader,
        );
    }

    /**
     * @param  array<string, list<Finding>>  $report
     */
    protected function print(array $report): void
    {
        $counts = array_fill_keys(array_map(fn (Severity $severity) => $severity->value, Severity::cases()), 0);
        foreach ($report as $title => $findings) {
            $this->newLine();
            $this->line("<options=bold>{$title}</>");
            if (count($findings) === 0) {
                $this->line('  Nothing found.');
            }
            foreach ($findings as $finding) {
                $counts[$finding->severity->value]++;
                $this->line('  '.$this->tag($finding->severity).' '.$finding->message);
                if (! is_null($finding->fix)) {
                    $this->line('    → '.$finding->fix);
                }
                $this->locations($finding->locations);
            }
        }

        $this->newLine();
        $this->line(sprintf(
            '<options=bold>%d breaking, %d to do, %d deprecated.</> See docs/upgrade.md of folklore/laravel-folklore.',
            $counts[Severity::Breaking->value],
            $counts[Severity::Todo->value],
            $counts[Severity::Deprecated->value],
        ));
    }

    protected function tag(Severity $severity): string
    {
        return match ($severity) {
            Severity::Breaking => '<fg=red;options=bold>BREAKING</>',
            Severity::Todo => '<fg=yellow;options=bold>TO DO</>',
            Severity::Deprecated => '<fg=cyan>DEPRECATED</>',
            Severity::Info => '<fg=gray>INFO</>',
        };
    }

    /**
     * @return list<string>
     */
    protected function stringList(string $option): array
    {
        $value = $this->option($option);

        return array_values(array_filter(is_array($value) ? $value : [], fn (mixed $item) => is_string($item) && $item !== ''));
    }
}
