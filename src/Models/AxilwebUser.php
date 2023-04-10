<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebUser extends Model {

	protected $connection = 'axilweb';

	protected $table = 'users';

	public function employee() {
		return $this->hasOne( AxilwebEmployee::class, 'user_ulid' );
	}


	public function department() {
		return $this->belongsTo( AxilwebDepartment::class, 'department_id' );
	}

	public function designation() {
		return $this->belongsTo( AxilwebDesignation::class, 'designation_id' );
	}

	public function job_type() {
		return $this->belongsTo( AxilwebJobType::class, 'job_type_id' );
	}

	public function addresses() {
		return $this->hasManyThrough(AxilwebUserAddress::class, AxilwebEmployee::class,'id' ,'employee_id');
	}

	public function bank() {
		return $this->hasOneThrough(AxilwebUserBank::class, AxilwebEmployee::class,'id' ,'employee_id');
	}

	public function attachments() {
		return $this->hasManyThrough(AxilwebUserAttachment::class, AxilwebEmployee::class,'id' ,'employee_id');
	}

}
