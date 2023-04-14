<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebLeaveType extends Model
{
	protected $connection = 'axilweb';

	protected $table = 'leave_types';
}
