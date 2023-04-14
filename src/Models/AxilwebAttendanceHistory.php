<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebAttendanceHistory extends Model
{
	protected $connection = 'axilweb';

	protected $table = 'attendance_histories';
}
