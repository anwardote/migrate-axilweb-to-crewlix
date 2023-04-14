<?php

namespace Anwardote\AxilwebToCrewlix\Commands;

use Anwardote\AxilwebToCrewlix\Models\AxilwebAttachmentType;
use Anwardote\AxilwebToCrewlix\Models\AxilwebAttendance;
use Anwardote\AxilwebToCrewlix\Models\AxilwebDepartment;
use Anwardote\AxilwebToCrewlix\Models\AxilwebDesignation;
use Anwardote\AxilwebToCrewlix\Models\AxilwebEmployee;
use Anwardote\AxilwebToCrewlix\Models\AxilwebHoliday;
use Anwardote\AxilwebToCrewlix\Models\AxilwebHolidayType;
use Anwardote\AxilwebToCrewlix\Models\AxilwebJobType;
use Anwardote\AxilwebToCrewlix\Models\AxilwebLeaveApplication;
use Anwardote\AxilwebToCrewlix\Models\AxilwebLeaveType;
use Anwardote\AxilwebToCrewlix\Models\AxilwebPost;
use Anwardote\AxilwebToCrewlix\Models\AxilwebUser;
use Anwardote\AxilwebToCrewlix\Models\AxilwebUserAttachment;
use Carbon\CarbonPeriod;
use Crewlix\Tenant\Application\Helpers\TenantCarbon;
use Crewlix\Tenant\Application\Http\Models\Enums\StatusEnum;
use Crewlix\Tenant\Application\Http\Models\Media;
use Crewlix\Tenant\Application\Http\Services\CalendarCycleService;
use Crewlix\Tenant\Application\Http\Services\Tenant\FileOptimizerService;
use Crewlix\Tenant\Application\Http\Services\UploadManagerService;
use Crewlix\Tenant\Attendance\Http\Models\Attendance;
use Crewlix\Tenant\Attendance\Http\Models\Settings\ShiftTemplate;
use Crewlix\Tenant\Attendance\Http\Models\Settings\ShiftUser;
use Crewlix\Tenant\Employee\Http\Models\Settings\AttachmentType;
use Crewlix\Tenant\Employee\Http\Models\Settings\Department;
use Crewlix\Tenant\Employee\Http\Models\Settings\Designation;
use Crewlix\Tenant\Employee\Http\Models\Settings\EmploymentType;
use Crewlix\Tenant\Engagement\Http\Models\Feed;
use Crewlix\Tenant\Engagement\Http\Models\FeedAcknowledge;
use Crewlix\Tenant\General\Http\Models\User;
use Crewlix\Tenant\General\Http\Services\RoleService;
use Crewlix\Tenant\Leave\Http\Models\Leaves\LeaveApplication;
use Crewlix\Tenant\Leave\Http\Models\Settings\Holiday\Holiday;
use Crewlix\Tenant\Leave\Http\Models\Settings\Holiday\HolidayUser;
use Crewlix\Tenant\Leave\Http\Models\Settings\LeavePolicy;
use Crewlix\Tenant\Leave\Http\Models\Settings\LeaveReviewProcess;
use Crewlix\Tenant\Leave\Http\Models\Settings\LeaveType;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportAxilwebDataCommand extends Command {

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'axilweb:import {--avatars} {--attachments} {--users} {--attendances} {--leaves} {--holidays} {--feeds}';

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
		$this->call('optimize:clear');

		if ( $this->option( 'avatars' ) ) {
			$this->importAvatars();
		}

		if ( $this->option( 'attachments' ) ) {
			$this->importAttachments();
		}

		if ( $this->option( 'users' ) ) {
			$this->importDepartments();
			$this->importDesignations();
			$this->importJobTypes();
			$this->importAttachmentTypes();
			$this->importUsers();
		}

		if ( $this->option( 'attendances' ) ) {
			$this->importAttendances();
		}

		if ( $this->option( 'leaves' ) ) {
			$this->importLeaves();
		}

		if ( $this->option( 'holidays' ) ) {
			$this->importHolidays();
			$this->assignHolidays();
		}

		if ( $this->option( 'feeds' ) ) {
			$this->importFeeds();
		}
	}

	public function importFeeds() {
		Feed::query()->forceDelete();
		$this->importFeedAttachments();
		$posts = AxilwebPost::query()
			->addSelect(['user_ulid' => AxilwebEmployee::query()->select('user_ulid')->whereColumn('posts.employee_id','=','employees.id')->limit(1)])
			->whereNotNull('details')
			->whereNull('deleted_at')->get();


		$userIds = User::query()->select('id')->skipDischarged()->pluck('id')->toArray();

		foreach($posts as $post){
			$type = 2;
			if($title = $post->subject){
				$type = 1;
			}

			$feedData = [
				'user_id' => $post->user_ulid,
				'type' => $type,
				'title' => $title,
				'body' => $post->details,
				'enabled_react' => 1,
				'enabled_comment' => 1,
				'created_at' => $post->created_at,
				'updated_at' => $post->updated_at,
			];

			$this->info('completed: '. $post->id);

			$feed = Feed::query()->create($feedData);
			$assignsData = [];
			foreach($userIds as $user_id){
				$assignsData[] = [
					'feed_id' => $feed->id,
					'user_id' => $user_id,
					'seen_at'=> now()
				];
			}
			$feed->feedAcknowledges()->createMany($assignsData);

			if($file_paths = $post->file_paths){
				$filePaths = unserialize( $file_paths );
				if ( is_array( $filePaths ) ) {
					if ( $files = $filePaths['files'] ?? false ) {
						foreach ( $files as $file ) {
							$filePath ="feeds/files/{$post->user_ulid}/". $file;
							$filePath = Storage::disk('temporary')->path($filePath);
							if(file_exists($filePath)){
								$feed->addMedia($filePath)->toMediaCollection('feed_attachments');
							}
						}
					}

					if ( $image = $filePaths['image'] ?? false ) {
						$filePath ="feeds/image/{$post->user_ulid}/". $image;
						$filePath = Storage::disk('temporary')->path($filePath);
						if(file_exists($filePath)){
							$feed->addMedia($filePath)->toMediaCollection('feed_photos');
						}
					}
				}
			}
		}
	}

	public function assignHolidays()
	{
		$data = [];
		$holidays = Holiday::query()->get();

		foreach($holidays as $holiday){
			$users = User::query()->select('id')->where('joining_date', '<=', $holiday->start_date)
				->where(function($query) use ($holiday) {
					$query->where('discharged_date', '>', $holiday->start_date)
					      ->orWhereNull('discharged_date');
				})
				->get();

			foreach ($users as $user) {
				$period = CarbonPeriod::create($holiday->start_date, $holiday->end_date);

				foreach ($period as $date) {
					$data[] = [
						'holiday_id'=> $holiday->id,
						'user_id'   => $user->id,
						'date'      => $date->format('Y-m-d'),
					];
				}
			}
			$this->info('completed assigned: '.$holiday->id);

		}
		if($data){
			HolidayUser::insertOrIgnore($data);
		}

	}

	public function importHolidays() {

		Holiday::query()->forceDelete();

		$axilwebHolidays = AxilwebHoliday::query()
			->addSelect(['type_name' => AxilwebHolidayType::query()->select('name')->whereColumn('holidays.type_id','=','holiday_types.id')->limit(1)])
		                                 ->whereDoesntHave('exception')
		                                 ->orderBy('holiday_date')
		                                 ->whereNull('deleted_at')
		                                 ->get();

		try {

			$holidayData = [];
			foreach($axilwebHolidays as $axilwebHoliday){
				$holidayDate = Carbon::parse($axilwebHoliday->holiday_date);
				$name = $axilwebHoliday->name." - ".$holidayDate->format('Y');

				$endDate = $axilwebHoliday->holiday_date;
				$addedStatus = false;

				if($holidayData){
					$preHoliday = Arr::last($holidayData);
					if(Carbon::parse($preHoliday['end_date'])->addDay() == $holidayDate){
						array_pop($holidayData);
						$preHoliday['end_date'] = $holidayDate->format('Y-m-d');
						$holidayData[] = $preHoliday;
						$addedStatus = true;
					}
				}

				if(!$holidayData || !$addedStatus) {
					$holidayData[] = [
						'id'         => strtolower(Str::ulid()),
						'name'       => $name,
						'start_date' => $axilwebHoliday->holiday_date,
						'end_date'   => $endDate,
						'created_at' => $axilwebHoliday->created_at,
						'updated_at' => $axilwebHoliday->updated_at,
					];
				}
				$this->info('completed: '.$axilwebHoliday->id);
			}

			Holiday::query()->insert($holidayData);

		} catch (\Exception $exception){
			dd($exception->getMessage());

		}
	}

	public function importLeaves() {
		LeaveApplication::query()->forceDelete();

		$this->createLeavePolicy();
		$this->assignedLeaveReviewers();
		$this->importLeaveAttachments();

		$policyId = LeavePolicy::query()->first()->id;

		$axilwebLeaves = AxilwebLeaveApplication::query()
			->addSelect(['user_ulid' => AxilwebEmployee::query()->select('user_ulid')->whereColumn('leaves.employee_id','=','employees.id')->limit(1)])
			->addSelect(['reviewer_ulid' => AxilwebEmployee::query()->select('user_ulid')->whereColumn('leaves.approved_by','=','employees.user_id')->limit(1)])
			->whereNotNull('approved_start_date')
			->get();

		foreach($axilwebLeaves as $leave){

			$spentType = intval($leave->half_day);
			$startDate = $leave->from_date;
			$startEnd = $leave->to_date;
			if($leave->status == 1){
				$startDate = $leave->approved_start_date;
				$startEnd = $leave->approved_end_date;
			}


			$leaveData = [
				'user_id' => $leave->user_ulid,
				'leave_type_id' => $leave->type_id,
				'leave_policy_id' => $policyId,
				'leave_spent_type_id' => $spentType,
				'start_date' => $startDate,
				'end_date' => $startEnd,
				'body' => $this->strip_tags($leave->details),
				'total_days' => $leave->total_days,
				'paid_status' => 1,
				'status' => $leave->status,
				'created_at' => $leave->created_at,
				'updated_at' => $leave->updated_at,
			];

			$model = LeaveApplication::query()->create($leaveData);
			$this->makeLeaveEntry($model);
			$this->makeReviewer($model, $leave);

			if($attachment = $leave->attachment_path){
					$filePath ="leaves/{$leave->user_ulid}/". $attachment;
					$filePath = Storage::disk('temporary')->path($filePath);
					if(file_exists($filePath)){
						$model->addMedia($filePath)->toMediaCollection('leave-application-attachments');
					}
			}

			$this->info('completed:'. $leave->id);
		}
	}


	public function importAttendances() {
		Attendance::query()->forceDelete();
		$axilwebAttendances = AxilwebAttendance::query()
			->select('*')
			->addSelect(['user_ulid' => AxilwebEmployee::query()->select('user_ulid')->whereColumn('attendances.employee_id','=','employees.id')->limit(1)])
			->with(['histories'])
			->whereNull('deleted_at')
			->whereDate('check_date','>=', '2019-07-11')
			->groupBy('employee_id', 'check_date')
			->get();

		foreach($axilwebAttendances as $axilwebAttend){
			$this->info("Importing: ". $axilwebAttend->id);

			$attendData = [
				'user_id' => $axilwebAttend->user_ulid,
				'working_date' => $axilwebAttend->check_date,
				'late_status' => intval($axilwebAttend->is_late),
				'quickly_left_status' => 0,
				'completed_status' => $axilwebAttend->status == 2 ? 0:1,
				'status' => 1,
				'working_place' => $axilwebAttend->is_remote == 1 ? 2 : 1,
				'paid_status' => 1,
				'timezone_id' => 232,
				'created_at' => $axilwebAttend->created_at,
				'updated_at' => $axilwebAttend->updated_at,
			];

			$attendanceModel = Attendance::query()->create($attendData);

			$this->info("Importing: ". $attendanceModel->id);
			$axilwebHistories = $axilwebAttend->histories;

			$historyData = [];
			foreach($axilwebHistories as $axilweb_history){
				$totalSpents = 0;
				$inTime = $axilweb_history->check_in;
				$outTime = $axilweb_history->check_out;
				if($inTime && $outTime){
					$totalSpents = Carbon::parse($inTime)->diffInSeconds($outTime);
				}

				$historyData[] = [
					'attendance_id' => $attendanceModel->id,
					'start_time' => $axilweb_history->check_in,
					'end_time' => $axilweb_history->check_out,
					'total_spent' => $totalSpents,
					'status' => 0,
					'remote_ip' => $axilweb_history->remote_ip,
					'user_agent' => $axilweb_history->user_agent,
					'latitude' => $axilweb_history->latitude,
					'longitude' => $axilweb_history->longitude,
					'created_at' => $axilweb_history->created_at,
					'updated_at' => $axilweb_history->updated_at,
				];
			}
			$attendanceModel->histories()->createMany($historyData);


			$startTime = $axilwebAttend->start_time;

			$shiftUser = ShiftUser::query()
				->create([
					'id' => strtolower(Str::ulid()),
					'user_id' => $attendanceModel->user_id,
					'date' => $attendanceModel->working_date
				]);

			$shiftUser->times()->create([
				'start_time' => $startTime,
				'end_time' => Carbon::parse($startTime)->startOfHour()->addHours(8),
				'status' => 1
			]);


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
			                    'presentAddress',
			                    'permanentAddress',
			                    'bank',
			                    'attachments',
		                    ] )
//		                    ->where( 'employees.id', 3 )
//		                    ->limit( 2 )
		                    ->get();

		$leavePolicyId      = LeavePolicy::query()->first()?->id ?? null;
		$leaveReviewProcess = LeaveReviewProcess::query()->first()?->id ?? null;
		$default_role = app(RoleService::class)->getDefaultRole(true);

		User::query()->whereNot('email','admin@crewlix.test')->forceDelete();
		Media::query()->delete();

		foreach ( $users as $user ) {
			$userDataItem = [
				'id'                    => $user->ulid,
				'emp_card_no'           => $user->emp_card_no,
				'first_name'            => $user->first_name,
				'last_name'             => $user->last_name,
				'email'                 => $user->email,
				'secondary_email'       => $user->personal_email,
				'password'              => bcrypt('test'),
				'phone'                 => $user->phone,
				'status'                => $user->status,
				'gender'                => $user->gender,
				'private_at'            => $user->hide_info == 1 ? now()->format( 'Y-m-d' ) : null,
				'joining_date'          => $user->joining_date,
				'date_of_birth'         => $user->dob,
				'probation_period_date' => $user->leave_effective_date,
				'discharged_date'       => $user->discharge_date,
			];

//
//			dd($userDataItem);


			if ( $user->department->name ?? '' ) {
				$userDataItem['department_id'] = Department::query()->where( 'name', $user->department->name )->first()?->id ?? null;
			}

			if ( $user->designation->name ?? '' ) {
				$userDataItem['designation_id'] = Designation::query()->where( 'name', $user->designation->name )->first()?->id ?? null;
			}

			$userDataItem['leave_policy_id']         = $leavePolicyId;
			$userDataItem['leave_review_process_id'] = $leaveReviewProcess;

			if ( $user->job_type->name ?? '' ) {
				$userDataItem['employment_type_id'] = AxilwebJobType::query()->where( 'name', $user->job_type->name )->first()?->id ?? null;
			}

			$userDataItem['is_overtime_allowed']     = 0;
			$userDataItem['is_allow_remote_work']    = $user->allow_remote;
			$userDataItem['remote_work_date_start']  = $user->remote_start_date;
			$userDataItem['remote_work_date_end']    = $user->remote_end_date;
			$userDataItem['remote_work_date_end']    = $user->remote_end_date;

			$userProfileItem = [
				'discharged_reason'   => $user->discharge_note,
				'government_issued_no'   => $user->nid,
				'religion_id'       => $user->religion_id,
				'marital_status_id'       => $user->marital_status,
				'emergency_contact'       => [
					'name' => $user->guardian_name,
					'phone' => $user->emergency_contact_dhaka.','.$user->emergency_contact_home,
				],
				'family_contact' => [
					'father_name' => $user->father_name,
					'mother_name' => $user->mother_name,
					'spouse_name' => $user->spouse_name,
					'number_of_children' => $user->number_of_children,
				],
				'blood_group'       => $user->blood_group,
			];


			$presentAddress = $user->presentAddress;
			$permanentAddress = $user->permanentAddress;

			if($presentAddress){
				$userProfileItem['present_address'] = [
					'address'   => $presentAddress->address_one.','.$presentAddress->address_two,
					'city'      => $presentAddress->district,
					'zip_code'  => $presentAddress->postal_code,
					'state'     => $presentAddress->state,
					'country'   => $presentAddress->country_id,
				];
			}

			if($permanentAddress){
				$userProfileItem['permanent_address'] = [
					'address'   => $permanentAddress->address_one.','.$permanentAddress->address_two,
					'city'      => $permanentAddress->district,
					'zip_code'  => $permanentAddress->postal_code,
					'state'     => $permanentAddress->state,
					'country'   => $permanentAddress->country_id,
				];
			}

			$userModel = User::query()
			    ->create($userDataItem);

			$userModel->assignRole($default_role);

			$userProfileModel = $userModel->userProfile()->create([]);
			$userProfileModel->update($userProfileItem);

			$oldAvatar ="avatars/$user->ulid/". $user->profile_image;
			$avatarPath = Storage::disk('temporary')->path($oldAvatar);
			if(file_exists($avatarPath)){
				$avatarContent = Storage::disk('temporary')->get($oldAvatar);
				$uploadPath = 'avatars';
				$fileName   = $uploadPath."/".Str::uuid() . '-avatar.' . pathinfo($avatarPath, PATHINFO_EXTENSION);;
				$disk = UploadManagerService::getGeneralFileDisk();
				Storage::disk($disk)->put($fileName,$avatarContent);
				$userModel->avatar = $fileName;
				$userModel->save();
			}

			if($user->attachments->count()){
				$attachments = $user->attachments;
				foreach($attachments as $attachment){
					$filePath ="attachments/{$user->ulid}/". $attachment->file_path;
					$filePath = Storage::disk('temporary')->path($filePath);
					if(file_exists($filePath)){
						$media = $userModel->addMedia($filePath)
						                   ->toMediaCollection('employee-attachments');
						$media->attachment_type_id = $attachment->type;
						$media->save();
					}
				}
			}

			$this->info("completed: ". $userModel->first_name.' '. $userModel->last_name);
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

	public function importAvatars() {
		$users = AxilwebEmployee::query()->select( 'user_ulid', 'profile_image')->get();

		foreach($users as $user){
			if($avatar = $user->profile_image){
				$url = "https://hrm.axilweb.com/storage/axilweb-hrm/{$avatar}";

				$filePath ="avatars/{$user->user_ulid}/". $avatar;


				$this->info("Downloading...".$filePath);

				if (@getimagesize($url)) {
					Storage::disk('temporary')->put($filePath, file_get_contents($url));
					$image = Storage::disk('temporary')->path($filePath);
					FileOptimizerService::optimizeImage($image, 130,130)->save();
				}else{
					$this->info("failed...".$filePath);

				}
			}
		}
	}

	public function importAttachments() {
		$users = AxilwebUserAttachment::query()
		                              ->select([
										  'employee_attachments.id',
										  'employee_attachments.file_path'
										  ,'employees.user_ulid'
		                              ])->join('employees','employees.id','=','employee_attachments.employee_id')
		                                ->get();

		foreach($users as $user){
			if($file = $user->file_path){
				$url = "https://hrm.axilweb.com/storage/axilweb-hrm/{$file}";
				$filePath ="attachments/{$user->user_ulid}/". $file;

				$this->info("Downloading...".$filePath);

				$headers = @get_headers($url);

				if ($headers && strpos($headers[0], '200')) {
					Storage::disk('temporary')->put($filePath, file_get_contents($url));
				}else{
					$this->warn("failed...".$url);
				}
			}
		}
	}

	protected function makeLeaveEntry($event){
		$application = $event;
		$application_id = $application->id;

		$startDate = TenantCarbon::createFromFormat('Y-m-d', $application->start_date->format('Y-m-d'));
		$endDate = TenantCarbon::createFromFormat('Y-m-d', $application->end_date->format('Y-m-d'));
		$dateRange = CarbonPeriod::create($startDate, $endDate);

		$data = [];

		foreach ($dateRange->toArray() as $date) {
			$data[] = ['date' => $date->format('Y-m-d'), 'leave_application_id' => $application_id,'calendar_cycle_id' =>  app(CalendarCycleService::class)->getCycleIdBy($date)];
		}

		DB::table('leave_application_entries')->where('leave_application_id', $application_id)->delete();
		DB::table('leave_application_entries')->insertOrIgnore($data);
	}

	protected function makeReviewer($application, $leave){

		if(!$leave->status){
			return;
		}

		if($leave->reviewer_ulid){
			$leaveStatus = 1;
		}else{
			$leaveStatus = 2;
		}


		DB::table('leave_application_reviewers')->insertOrIgnore([
			'user_id' => $leave->reviewer_ulid,
			'application_id' => $application->id,
			'leave_status' => $leaveStatus
		]);
	}

	protected function assignedLeaveReviewers(){
		LeaveReviewProcess::query()->forceDelete();

		$process = LeaveReviewProcess::query()->firstOrCreate([
			'name'               => 'Leave Approval Process (Default)',
			'review_condition'   => 1,
			'status'             => StatusEnum::Active,
			'updated_at'         => TenantCarbon::now(),
			'created_at'         => TenantCarbon::now(),
		]);

		User::query()
		    ->select('id','leave_review_process_id')
		    ->update(['leave_review_process_id'=> $process->id]);

		$usersIds = User::whereNotNull('locked_at')
		                ->get()
		                ->pluck('id')
		                ->toArray();

		$process->reviewers()->sync($usersIds);
	}

	protected function createLeavePolicy(){
		LeaveType::query()->forceDelete();
		LeavePolicy::query()->forceDelete();

		$axilwebLeaveTypes = AxilwebLeaveType::query()->get();

		$leaveTypeData = [];
		$i = 1;
		foreach($axilwebLeaveTypes as $type){
			$leaveTypeData[] = [
				'id' => $type->id,
				'name' => $type->name,
				'color_class' => "leave-type-color-{$i}",
				'details' => $type->details,
				'is_auto_calculated' => false,
				'total_days' => $type->total_days,
				'is_carry_forward' => $type->is_carry_forward,
				'max_carry_forward_days' => $type->carry_forward_total_days,
				'status' => $type->status,
			];

			$i++;
		}

		LeaveType::query()->insertOrIgnore( $leaveTypeData);

		$policy = LeavePolicy::query()->firstOrCreate([
			'name' => 'Leave Package (Default)',
			'is_default' => true,
			'details' => 'This is system generated.',
			'status' => StatusEnum::Active,
			'updated_at' => TenantCarbon::now(),
			'created_at' => TenantCarbon::now(),
		]);

		$leaveTypes = LeaveType::query()->select(['id'])->get();
		$leaveTypeIds = $leaveTypes->pluck('id')->toArray();

		$policy->leaveTypes()->sync($leaveTypeIds);

		User::query()->select('leave_policy_id', 'id')
		    ->update(['leave_policy_id' => $policy->id]);

	}

	protected function importLeaveAttachments() {
		$users = AxilwebLeaveApplication::query()
		                              ->select([
			                              'leaves.id', 'leaves.attachment_path','employees.user_ulid'
		                              ])->join('employees','employees.id','=','leaves.employee_id')
		                              ->get();
		foreach($users as $user){
			if($file = $user->attachment_path){
				$url = "https://hrm.axilweb.com/storage/axilweb-hrm/{$file}";
				$filePath ="leaves/{$user->user_ulid}/". $file;

				$this->info("Downloading...".$filePath);

				$headers = @get_headers($url);

				if ($headers && strpos($headers[0], '200')) {
					Storage::disk('temporary')->put($filePath, file_get_contents($url));
				}else{
					$this->warn("failed...".$url);
				}
			}
		}
	}

	protected function importFeedAttachments() {
		$posts = AxilwebPost::query()
		                    ->addSelect( [
			                    'user_ulid' => AxilwebEmployee::query()
			                                                  ->select( 'user_ulid' )
			                                                  ->whereColumn( 'posts.employee_id',
				                                                  '=',
				                                                  'employees.id' )
			                                                  ->limit( 1 )
		                    ] )
		                    ->whereNotNull( 'details' )
		                    ->whereNot( function ( $query ) {
			                    $query->whereNull( 'file_paths' )
			                          ->orWhere( 'file_paths', 'a:0:{}' );
		                    } )
		                    ->whereNull( 'deleted_at' )->get();


		foreach ( $posts as $post ) {
			if ( $filePaths = $post->file_paths ) {
				$this->info('completed:'. $filePaths);
				$filePaths = unserialize( $filePaths );
				if ( is_array( $filePaths ) ) {
					if ( $files = $filePaths['files'] ?? false ) {
						foreach ( $files as $file ) {
							$url = "https://hrm.axilweb.com/storage/axilweb-hrm/{$file}";
							$filePath = "feeds/files/{$post->user_ulid}/" . $file;

							$headers = @get_headers( $url );
							if ( $headers && strpos( $headers[0], '200' ) ) {
								Storage::disk( 'temporary' )->put( $filePath,
									file_get_contents( $url ) );
							} else {
								$this->warn( "failed..." . $url );
							}
						}
					}

					if ( $image = $filePaths['image'] ?? false ) {
						$url
							      = "https://hrm.axilweb.com/storage/axilweb-hrm/{$image}";
						$filePath = "feeds/image/{$post->user_ulid}/" . $image;
						$headers  = @get_headers( $url );
						if ( $headers && strpos( $headers[0], '200' ) ) {
							Storage::disk( 'temporary' )->put( $filePath, file_get_contents( $url ) );
						} else {
							$this->warn( "failed..." . $url );
						}
					}
				}
			}
		}
	}
	protected function strip_tags( $body  ) {
		$bodyNew = str_replace(['<br>', '<br >', '<br />'], "<br />\n", $body);
		$bodyNew = str_replace([ '< /p>', '</ p>'], "</p>\n", $bodyNew);
		$bodyNew = str_replace([ '&nbsp;'], " ", $bodyNew);

		$bodyNew = strip_tags($bodyNew);
		return $bodyNew;
	}

}
