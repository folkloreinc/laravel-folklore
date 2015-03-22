<?php namespace Folklore\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Folklore extends Facade
{

	protected static function getFacadeAccessor()
	{
		return 'folklore';
	}

}
