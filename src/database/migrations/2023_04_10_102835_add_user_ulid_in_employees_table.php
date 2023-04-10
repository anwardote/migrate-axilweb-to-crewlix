<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::connection('axilweb')->table('employees', function (Blueprint $table) {
			if(!Schema::connection('axilweb')->hasColumn('employees','user_ulid')){
				$table->ulid('user_ulid')->nullable()->unique()->after('user_id');
			}
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::connection('axilweb')->table('employees', function (Blueprint $table) {
			if(Schema::connection('axilweb')->hasColumn('employees','user_ulid')){
				$table->dropColumn('user_ulid');
			}
		});
	}
};
