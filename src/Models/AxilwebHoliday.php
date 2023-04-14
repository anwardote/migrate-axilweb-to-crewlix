<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebHoliday extends Model
{
	protected $connection = 'axilweb';

	protected $table = 'holidays';

	public function exception() {
		return $this->belongsTo(AxilwebHolidayException::class,'holiday_date','exp_date')->whereNull('deleted_at');

	}
}
