<?php

namespace Anwardote\AxilwebToCrewlix;

use Anwardote\AxilwebToCrewlix\Commands\ImportAxilwebDataCommand;
use Anwardote\AxilwebToCrewlix\Commands\ReadyAxilwebDataCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class LaravelServiceProvider extends ServiceProvider
{

	/**
	 * Register services.
	 *
	 * @return void
	 */
	public function register()
	{

	}

	/**
	 * Bootstrap services.
	 *
	 * @return void
	 */
	public function boot()
	{
		Schema::defaultStringLength(255);

		$this->axilweb_registerCommands();

		// load routes
		$this->axilweb_load_routes();

		// load migrations
		$this->axilweb_load_migrations();
	}


	// load routes
	protected function axilweb_load_routes()
	{
		$this->loadRoutesFrom(__DIR__.'/routes/index.php');
	}


	// load migrations
	protected function axilweb_load_migrations()
	{
		$this->loadMigrationsFrom(__DIR__.'/database/migrations');
	}

	protected function axilweb_registerCommands()
	{
		$this->commands([
			ImportAxilwebDataCommand::class,
			ReadyAxilwebDataCommand::class,
		]);
	}

}