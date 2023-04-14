<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebHolidayType extends Model
{
	protected $connection = 'axilweb';

	protected $table = 'holiday_types';
}
