<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('axilweb')->table('users', function (Blueprint $table) {
			if(!Schema::connection('axilweb')->hasColumn('users','ulid')){
				$table->ulid('ulid')->nullable()->unique()->after('id');
			}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
	    Schema::connection('axilweb')->table('users', function (Blueprint $table) {
	        if(Schema::connection('axilweb')->hasColumn('users','ulid')){
		        $table->dropColumn('ulid');
	        }
        });
    }
};
