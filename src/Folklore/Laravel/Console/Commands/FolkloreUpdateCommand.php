<?php namespace Folklore\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FolkloreUpdateCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'folklore:update';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Update folklore package resources';

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$arguments = [
			'--provider' => 'Folklore\Laravel\FolkloreServiceProvider',
			'--force' => true
		];
		
		$tag = $this->argument('tag');
		$all = $tag === 'all';
		
		if($tag !== 'locale')
		{
			if(!$all)
			{
				$arguments['--tag'] = 'folklore.'.$tag;
			}
			$this->info('Publishing main files...');
			$this->call('vendor:publish', $arguments);
		}
		
		if($tag === 'locale' || $all)
		{
			$this->info('Publishing locale files...');
			$this->call('vendor:publish', [
				'--provider' => 'Folklore\Laravel\LocaleServiceProvider',
				'--force' => true
			]);	
		}
		
	}
	
	public function getArguments()
	{
		return [
			['tag', InputArgument::REQUIRED, 'The publish files group tag', null]
		];
	}

}
