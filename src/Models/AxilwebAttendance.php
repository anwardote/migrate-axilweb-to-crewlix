<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebAttendance extends Model
{
	protected $connection = 'axilweb';

	protected $table = 'attendances';


	public function histories() {
		return $this->hasMany( AxilwebAttendanceHistory::class,'attendance_id' );
	}

}
