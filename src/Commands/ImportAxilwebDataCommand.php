<?php

namespace Anwardote\AxilwebToCrewlix\Commands;

use Illuminate\Console\Command;

class ImportAxilwebDataCommand extends Command {

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'axilweb:import {--users}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description';


	/**
	 * Execute the console command.
	 */
	public function handle() {
		if ( $this->option( 'users' ) ) {
			$this->importUsers();
		}
		\App\Console\Commands\dd( 'I am there' );
	}


	public function importUsers() {
//		$users = $this->dbconnection->table('users')
		\App\Console\Commands\dd();
	}

}
