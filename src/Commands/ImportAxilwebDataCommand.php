<?php

namespace Anwardote\AxilwebToCrewlix\Commands;

use Anwardote\AxilwebToCrewlix\Models\AxilwebAttachmentType;
use Anwardote\AxilwebToCrewlix\Models\AxilwebDepartment;
use Anwardote\AxilwebToCrewlix\Models\AxilwebDesignation;
use Anwardote\AxilwebToCrewlix\Models\AxilwebEmployee;
use Anwardote\AxilwebToCrewlix\Models\AxilwebJobType;
use Anwardote\AxilwebToCrewlix\Models\AxilwebUser;
use Anwardote\AxilwebToCrewlix\Models\AxilwebUserAttachment;
use Crewlix\Tenant\Employee\Http\Models\Settings\AttachmentType;
use Crewlix\Tenant\Employee\Http\Models\Settings\Department;
use Crewlix\Tenant\Employee\Http\Models\Settings\Designation;
use Crewlix\Tenant\Employee\Http\Models\Settings\EmploymentType;
use Crewlix\Tenant\Leave\Http\Models\Settings\LeavePolicy;
use Crewlix\Tenant\Leave\Http\Models\Settings\LeaveReviewProcess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportAxilwebDataCommand extends Command {

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'axilweb:import {--users}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description';


	/**
	 * Execute the console command.
	 */
	public function handle() {
		//		$this->importDepartments();
		//		$this->importDesignations();
		//		$this->importJobTypes();
//				$this->importAttachmentTypes();
		if ( $this->option( 'users' ) ) {
			$this->importUsers();
		}
	}


	public function importUsers() {
		$users = AxilwebUser::query()
		                    ->select( [
			                    'employees.*',
			                    'employees.id as employee_id',
			                    'users.ulid',
			                    'users.email',
			                    'users.phone',
			                    'users.staff_id as emp_card_no',
			                    'users.status',
		                    ] )
		                    ->join( 'employees', 'employees.user_id', '=',
			                    'users.id' )
		                    ->with( [
			                    'department',
			                    'designation',
			                    'job_type',
			                    'addresses',
			                    'bank',
			                    'attachments',
		                    ] )
		                    ->where( 'employees.id', 3 )
		                    ->limit( 2 )
		                    ->get();


		$userData           = [];
		$userProfileData    = [];
		$leavePolicyId      = LeavePolicy::query()->first()?->id ?? null;
		$leaveReviewProcess = LeaveReviewProcess::query()->first()?->id ?? null;
		foreach ( $users as $user ) {
			$userDataItem = [
				'id'                    => $user->ulid,
				'emp_card_no'           => $user->emp_card_no,
				'first_name'            => $user->first_name,
				'last_name'             => $user->last_name,
				'username'              => Str::slug(
					$user->first_name . ' ' . $user->last_name, '-'
				),
				'email'                 => $user->email,
				'secondary_email'       => $user->personal_email,
				'phone'                 => $user->phone,
				'gender'                => $user->gender,
				'status'                => $user->status,
				'private_at'            => $user->hide_info == 1 ? now()->format( 'Y-m-d' ) : null,
				'joining_date'          => $user->joining_date,
				'date_of_birth'         => $user->dob,
				'probation_period_date' => $user->leave_effective_date,
				'discharged_date'       => $user->discharge_date,
			];

			if ( $user->department->name ?? '' ) {
				$userDataItem['department_id'] = Department::query()->where( 'name', $user->department->name )->first()?->id ?? null;
			}

			if ( $user->designation->name ?? '' ) {
				$userDataItem['designation_id'] = Designation::query()->where( 'name', $user->designation->name )->first()?->id ?? null;
			}

			if ( $user->job_type->name ?? '' ) {
				$userDataItem['employment_type_id'] = Designation::query()->where( 'name', $user->job_type->name )->first()?->id ?? null;
			}

			$userDataItem['leave_policy_id']         = $leavePolicyId;
			$userDataItem['leave_review_process_id'] = $leaveReviewProcess;
			$userDataItem['is_overtime_allowed']     = false;
			$userDataItem['is_allow_remote_work']    = $user->allow_remote == 1;
			$userDataItem['remote_work_date_start']  = $user->remote_start_date;
			$userDataItem['remote_work_date_end']    = $user->remote_end_date;


			dd( $userDataItem );
		}
	}


	public function importDepartments() {
		$data = AxilwebDepartment::query()
		                         ->select( 'name', 'details', 'status' )
		                         ->whereHas( 'employee' )
		                         ->get()->toArray();

		Department::query()->upsert( $data,
			[ 'name' ],
			[ 'details', 'status' ]
		);
	}

	public function importDesignations() {
		$data = AxilwebDesignation::query()
		                          ->select( 'name', 'details', 'status' )
		                          ->whereHas( 'employee' )
		                          ->get()->toArray();

		Designation::query()->upsert( $data,
			[ 'name' ],
			[ 'details', 'status' ]
		);
	}

	public function importJobTypes() {
		$data = AxilwebJobType::query()
		                      ->select( 'name', 'details', 'status' )
		                      ->whereHas( 'employee' )
		                      ->get()->toArray();

		EmploymentType::query()->upsert( $data,
			[ 'name' ],
			[ 'details', 'status' ]
		);
	}

	public function importAttachmentTypes() {
		$data = AxilwebAttachmentType::query()
		                      ->select( 'name', 'details', 'status' )
		                      ->whereHas( 'attachment' )
		                      ->get()->toArray();

		AttachmentType::query()->upsert( $data,
			[ 'name' ],
			[ 'details', 'status' ]
		);
	}

}
