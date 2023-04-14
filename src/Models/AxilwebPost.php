<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebPost extends Model
{
	protected $connection = 'axilweb';

	protected $table = 'posts';
}
