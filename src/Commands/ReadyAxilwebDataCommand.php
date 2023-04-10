<?php

namespace Anwardote\AxilwebToCrewlix\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReadyAxilwebDataCommand extends Command
{
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
    public function handle()
    {
		$dbConnection = DB::connection('axilweb');

		// ulid added in users table
	    $dbConnection->table('users')->get()->each(function ($item) use($dbConnection){
		    $dbConnection->table('users')
			  ->where('id', $item->id)
			  ->update([
				'ulid' => strtolower(Str::ulid())
			]);
	    });
    }
}
