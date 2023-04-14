<?php

namespace Anwardote\AxilwebToCrewlix\Commands;

use Anwardote\AxilwebToCrewlix\Models\AxilwebEmployee;
use Anwardote\AxilwebToCrewlix\Models\AxilwebUser;
use Crewlix\Tenant\Application\Helpers\TenantCarbon;
use Crewlix\Tenant\Application\Http\Models\CalendarCycle;
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


		$endYear   = TenantCarbon::now()->year;
		$startYear = 2018;

		$startDate = TenantCarbon::now()->startOfYear()->setYear($startYear);
		$endDate   = TenantCarbon::now()->endOfYear()->setYear($endYear);

		$date = $startDate;
		$insertData = [];

		while ($date <= $endDate) {
			$insertData[] = [
				'name'        => 'Calendar Year - '.$date->format('Y'),
				'start_date'  => $date->startOfYear()->format('Y-m-d'),
				'end_date'    => $date->endOfYear()->format('Y-m-d'),
				'is_repeated' => $date->endOfYear()->format('Y') == TenantCarbon::now()->format('Y'),
			];

			$date->addYear();
		}

		CalendarCycle::query()->insertOrIgnore($insertData);
	}

}
