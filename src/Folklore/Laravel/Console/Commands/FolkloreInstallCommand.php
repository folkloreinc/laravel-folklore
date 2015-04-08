<?php namespace Folklore\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FolkloreInstallCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'folklore:install';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Install folklore package';

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$this->info('Publishing main files...');
		$this->call('vendor:publish', [
			'--provider' => 'Folklore\Laravel\FolkloreServiceProvider'
		]);
		
		$this->info('Publishing locale files...');
		$this->call('vendor:publish', [
			'--provider' => 'Folklore\Laravel\LocaleServiceProvider'
		]);
		
		$this->info('Publishing debug bar files...');
		$this->call('vendor:publish', [
			'--provider' => 'Barryvdh\Debugbar\ServiceProvider'
		]);
	}

}
