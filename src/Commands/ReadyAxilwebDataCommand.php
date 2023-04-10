<?php

namespace Anwardote\AxilwebToCrewlix\Commands;

use Anwardote\AxilwebToCrewlix\Models\AxilwebEmployee;
use Anwardote\AxilwebToCrewlix\Models\AxilwebUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReadyAxilwebDataCommand extends Command {

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'axilweb:ready';

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
		// Update Users IDS with ulids
		AxilwebUser::all()->each( function ( $item ) {
			$userUlid = strtolower( Str::ulid() );
			$item->forceFill( [
				'ulid' => $userUlid,
			] )->save();


			if ( $employee = AxilwebEmployee::query()->where( 'user_id', $item->id )->first() ) {
				$employee->forceFill( [ 'user_ulid' => $userUlid ] )->save();
			}
		} );
	}

}
