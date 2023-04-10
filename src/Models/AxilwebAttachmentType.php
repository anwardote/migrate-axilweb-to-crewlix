<?php

namespace Anwardote\AxilwebToCrewlix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AxilwebAttachmentType extends Model
{
	protected $connection = 'axilweb';

	protected $table = 'attachment_types';

	public function attachment() {
		return $this->hasMany( AxilwebUserAttachment::class, 'type' );
	}

}
