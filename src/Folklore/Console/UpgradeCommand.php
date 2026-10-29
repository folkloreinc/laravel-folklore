<?php

namespace Folklore\Console;

use Folklore\Upgrade\Finding;
use Folklore\Upgrade\IsolatedLoader;
use Folklore\Upgrade\Severity;
use Folklore\Upgrade\Site;
use Folklore\Upgrade\UpgradeCheck;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Upgrades a site from 1.1 (docs/upgrade.md). `--check` reports what the site uses that
 * changes in 2.0, without writing anything.
 */
class UpgradeCommand extends Command
{
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
        {--path=* : A directory or file of the site to read, from its base path; by default app, config, routes, resources/views and bootstrap}
        {--model=* : A model whose JSON data to read, besides those of the paths}
        {--limit=1000 : How many rows of each model to read; 0 reads them all}
        {--json : Print the report as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upgrade a site from laravel-folklore 1.1; --check reports what changes';

    public function handle(UpgradeCheck $upgradeCheck, Repository $config, ConnectionResolverInterface $db): int
    {
        if (! $this->option('check')) {
            $this->components->error('Only `folklore:upgrade --check` is available yet: the upgrade itself comes in a next pre-release. Follow docs/upgrade.md until then.');

            return self::FAILURE;
        }

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
        $site = new Site(
            $this->laravel->basePath(),
            $config,
            $db,
            count($paths) > 0 ? $paths : Site::PATHS,
            $models,
            max(0, (int) $this->option('limit')),
            $loader,
        );
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
                foreach (array_slice($finding->locations, 0, self::LOCATIONS) as $location) {
                    $this->line("    <fg=gray>{$location}</>");
                }
                if (count($finding->locations) > self::LOCATIONS) {
                    $this->line('    <fg=gray>and '.(count($finding->locations) - self::LOCATIONS).' more (--json lists them all)</>');
                }
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
