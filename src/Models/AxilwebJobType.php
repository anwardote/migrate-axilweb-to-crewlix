<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebJobType extends Model
{
	protected $connection = 'axilweb';

	protected $table = 'job_types';

	public function employee() {
		return $this->hasMany( AxilwebEmployee::class, 'job_type_id' );
	}
}
