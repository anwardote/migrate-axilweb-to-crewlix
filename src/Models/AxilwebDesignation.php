<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebDesignation extends Model
{
	protected $connection = 'axilweb';

	protected $table = 'designations';

	public function employee() {
		return $this->hasMany( AxilwebEmployee::class, 'designation_id' );
	}
}
