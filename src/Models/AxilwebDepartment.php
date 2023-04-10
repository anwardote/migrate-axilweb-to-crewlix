<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebDepartment extends Model {

	protected $connection = 'axilweb';

	protected $table = 'departments';


	public function employee() {
		return $this->hasMany( AxilwebEmployee::class, 'department_id' );
	}

}
