<?php
class QueuedTask extends DataObject {

	private static $db = array(
		"FirstExecution"        =>  "SS_Datetime",
		"ExecuteInterval"       =>  "Int",
		"ExecuteEvery"          =>  "Enum(',Minute,Hour,Day,Week,Fortnight,Month,Year')",
		"ExecuteFree"           =>  "Varchar",
		"BuildTaskClass"        =>  "Varchar(100)",
		"RequestVariables"      =>  "MultiValueField",
		"RequestType"           =>  "enum('GET, POST')",
		"JobType"               =>  "Varchar(16)"
	);

	private static $defaults = array(
		"ExecuteInterval"       => 1
	);

	private static $has_one = array(
		"ScheduledJob"          => "QueuedJobDescriptor"
	);

	private static $summary_fields = array(
		"Title"                 => "Title",
		"Created"               => "Added",
		"NextRunDate"           => "Next Run Date"
	);

	public function onBeforeWrite(){
		parent::onBeforeWrite();

		if ($this->FirstExecution && !$this->JustWritten) {
			$changed = $this->getChangedFields();
			$changed = (
				isset($changed['FirstExecution']) ||
				isset($changed['ExecuteInterval']) ||
				isset($changed['ExecuteEvery']) ||
				isset($changed['ExecuteFree'])
			);

			if ($changed && $this->ScheduledJobID) {
				if ($this->ScheduledJob()->exists()) {
					$this->ScheduledJob()->delete();
				}

				$this->ScheduledJobID = 0;
			}
		}
	}

	public function onAfterWrite(){
		parent::onAfterWrite();
		if ($this->FirstExecution && !$this->JustWritten) {
			if (!$this->ScheduledJobID) {
				$job = new ScheduledExecutionJob($this);
				$time = date('Y-m-d H:i:s');
				if ($this->FirstExecution) {
					$time = date('Y-m-d H:i:s', strtotime($this->FirstExecution));
				}

				$srv = singleton('QueuedJobService');
				$this->ScheduledJobID = $srv->queueJob($job, $time, Member::currentUserID(), $this->JobType);
				$this->JustWritten = true;
				$this->write();
			}
		}
	}

	public function onAfterDelete(){
		parent::onAfterDelete();
		$this->ScheduledJob()->delete();
	}

	public function getCMSFields(){
		$fields = parent::getCMSFields();

		$available_classes = ClassInfo::subclassesFor("BuildTask");
		array_shift($available_classes);
		ksort($available_classes);

		$available_jobtypes = array(QueuedJob::IMMEDIATE => "Small", QueuedJob::QUEUED => "Medium", QueuedJob::LARGE => "Large");

		$fields->removeByName(array("ExecuteInterval", "ExecuteEvery", "ExecuteFree", "ScheduledJobID"));

		$fields->addFieldsToTab('Root.Main', array(
			DropdownField::create("BuildTaskClass", "Build Task", $available_classes),
			KeyValueField::create("RequestVariables", "Request Variables")->setDescription("Optional"),
			$dt = new Datetimefield('FirstExecution', _t('ScheduledExecution.FIRST_EXECUTION', 'First Execution')),
			FieldGroup::create(
				new NumericField('ExecuteInterval', ''),
				new DropdownField(
					'ExecuteEvery',
					'',
					array(
						'' => '',
						'Minute' => _t('ScheduledExecution.ExecuteEveryMinute', 'Minute'),
						'Hour' => _t('ScheduledExecution.ExecuteEveryHour', 'Hour'),
						'Day' => _t('ScheduledExecution.ExecuteEveryDay', 'Day'),
						'Week' => _t('ScheduledExecution.ExecuteEveryWeek', 'Week'),
						'Fortnight' => _t('ScheduledExecution.ExecuteEveryFortnight', 'Fortnight'),
						'Month' => _t('ScheduledExecution.ExecuteEveryMonth', 'Month'),
						'Year' => _t('ScheduledExecution.ExecuteEveryYear', 'Year'),
					)
				)
			)->setTitle(_t('ScheduledExecution.EXECUTE_EVERY', 'Execute every')),
			//new TextField('ExecuteFree', _t('ScheduledExecution.EXECUTE_FREE','Scheduled (in strtotime format from first execution)')),
			DropdownField::create( "JobType", "Job Type", $available_jobtypes )
		));

		if ($this->ScheduledJobID) {
			$jobTime = $this->getNextRunDate();
			$fields->addFieldsToTab('Root.Schedule', array(
				new ReadonlyField('NextRunDate', _t('ScheduledExecution.NEXT_RUN_DATE', 'Next run date'), $jobTime)
			));
		}

		$dt->getDateField()->setConfig('showcalendar', true);
		$dt->getTimeField()->setConfig('showdropdown', true);

		return $fields;
	}

	public function getNextRunDate(){
		return $this->ScheduledJob()->StartAfter;
	}

	public function onScheduledExecution() {
		$getVars = ($this->RequestType == "GET")?$this->RequestVariables->values:array();
		$postVars = ($this->RequestType == "POST")?$this->RequestVariables->values:array();
		$request = new SS_HTTPRequest($this->RequestType, $this->getURL(), $getVars, $postVars);
		$job = $this->getBuildTaskObject();
		if($job->isEnabled()){
			return $job->run($request);
		}
	}

	protected function validate(){
		$result = parent::validate();

		if(!$this->getBuildTaskObject())
			$result->error("Please select a valid Build Task.");

		return $result;
	}

	public function getBuildTaskObject(){
		if($this->BuildTaskClass=="" || !class_exists($this->BuildTaskClass)) return false;
		return singleton($this->BuildTaskClass);
	}

	public function getTitle(){
		return "Queued Task for ".$this->BuildTaskClass;
	}

	public function getURL(){
		return ContentController::create()->Link("dev/tasks/".$this->BuildTaskClass);
	}

}