<?php namespace ProcessWire;

/**
 * Configuration class for the SystemNotifications module
 * 
 * @property string $systemUserName
 * 
 */

class SystemNotificationsConfig extends ModuleConfig {
	
	const ghostPosLeft = 1;
	const ghostPosRight = 2; 
	
	const placementCombined = 0; // notifications and notices bundled together
	const placementSeparate = 1; // notifications are rendered separately from notices
	const placementNone = 2; // notifications are not rendered
	
	public function getDefaults() {
		
		if(empty($this->systemUserName)) $this->systemUserName = $this->users->get($this->config->superUserPageID)->name;
		
		return array(
			'disabled' => 0, 						// SystemNotifications disabled?
			'placement' => self::placementCombined, // placement of notifications relative to notices
			'reverse' => 0, 						// reverse default sort order?
			'systemUserID' => 41,					// user that will receive system notifications
			'systemUserName' => $this->systemUserName,	// user that will receive system notifications (name)
			'activeHooks' => array(0, 1, 2), 		// Indexes of $this->systemHooks that are active
			'trackEdits' => 1, 						// Track page edits?
			'updateDelay' => 5000, 					// delay between ajax updates (in ms) 5000+ recommended
			'iconMessage' => 'check-square-o',		// default icon for message notifications
			'iconWarning' => 'exclamation-circle',	// default icon for warning notifications
			'iconError' => 'exclamation-triangle',	// default icon for error notifications
			'iconProgress' => 'spinner fa-spin',	// icon for any item that has an active progress bar
			'iconDebug' => 'bug', 					// default icon for debug-mode notification
			'ghostDelay' => 1000, 					// how long a ghost appears on screen (in ms)
			'ghostDelayError' => 2000, 				// how long an error ghost appears on screen (in ms)
			'ghostFadeSpeed' => 'fast',				// speed at which ghosts fade in or out, or blank for no fade
			'ghostOpacity' => 0.85, 				// full opacity of ghost (when fully faded in) 
			'ghostPos' => 2, 						// ghost position: 1=left, 2=right
			'ghostLimit' => 20, 					// only show 1 summary ghost if there are more than this number
			'dateFormat' => 'rel', 					// date format to use in notifications (anything compatible with wireDate() function)
		); 
	}
	
	/**
	 * Configure notifications
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function getInputfields() {

		$form = parent::getInputfields();
		$modules = $this->wire('modules');
		$on = $this->_('On');
		$off = $this->_('Off');
		
		$f = $modules->get('InputfieldRadios'); 
		$f->attr('name', 'disabled');
		$f->label = __('Notifications status'); 
		$f->description = __('When turned off, notifications will not be rendered.'); 
		$f->addOption(0, $on);
		$f->addOption(1, $off);
		$f->optionColumns = 1; 
		$f->columnWidth = 33; 
		$form->add($f);
		
		$f = $modules->get('InputfieldRadios'); 
		$f->attr('name', 'trackEdits'); 
		$f->label = __('Track page edits?');
		$f->description = __('Notifies users when they try to edit a page that is already being edited.');
		$f->addOption(1, $on);
		$f->addOption(0, $off);
		$f->optionColumns = 1; 
		$f->columnWidth = 34;
		$form->add($f); 
		
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'reverse');
		$f->label = __('Notification order');
		$f->description = __('Select what order the notifications should display in.'); 
		$f->addOption(0, __('Newest to oldest'));
		$f->addOption(1, __('Oldest to newest'));
		$f->optionColumns = 1;
		$f->columnWidth = 33;
		$form->add($f);

		$f = $modules->get('InputfieldCheckboxes');
		$f->attr('name', 'activeHooks');
		$f->label = __('Active automatic notification hooks');
		$f->description = __('Whenever one of these events occurs, the system user above will be notified.');
		$f->addOption(0, __('404 page not found'));
		$f->addOption(1, __('User login success and failure'));
		$f->addOption(2, __('User logout'));
		$f->notes = __('These are primarily just examples of notifications for the purpose of demonstration.');
		$f->columnWidth = 50; 
		$form->add($f);
		
		$f = $modules->get('InputfieldName');
		$f->attr('name', 'systemUserName');
		$f->label = __('Name of user that receives system notifications');
		$f->columnWidth = 50;
		$form->add($f);

		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'updateDelay');
		$f->label = __('Time between updates');
		$f->description = __('How often to check for notification updates (in milliseconds). Example: 5000 means 5 seconds.');
		$f->columnWidth = 50;
		$form->add($f);

		$f = $modules->get('InputfieldText');
		$f->attr('name', 'dateFormat');
		$f->label = __('Date format');
		$f->description = __('Date format used for notifications. Use date() or strftime() format, or "relative" for relative date/time, "rel" for abbreviated date/time.');
		$f->columnWidth = 50;
		$form->add($f);

		$f = $modules->get('InputfieldIcon');
		$f->attr('name', 'iconMessage');
		$f->label = __('Message icon');
		$f->prefixValue = false;
		$form->add($f);

		$f = $modules->get('InputfieldIcon');
		$f->attr('name', 'iconWarning');
		$f->label = __('Warning icon');
		$f->prefixValue = false;
		$form->add($f);

		$f = $modules->get('InputfieldIcon');
		$f->attr('name', 'iconError');
		$f->label = __('Error icon');
		$f->prefixValue = false;
		$form->add($f);

		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'ghostDelay');
		$f->label = __('Ghost delay');
		$f->description = __('How long ghost messages appear for (in ms).');
		$f->columnWidth = 25;
		$form->add($f);

		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'ghostDelayError');
		$f->label = __('Ghost error delay');
		$f->description = __('How long ghost errors appear for (in ms).');
		$f->columnWidth = 25;
		$form->add($f);
		
		$f = $modules->get('InputfieldFloat');
		$f->attr('name', 'ghostOpacity');
		$f->label = __('Ghost full opacity');
		$f->description = __('Full opacity of ghosts (0.1-1.0)');
		$f->columnWidth = 25;
		$form->add($f);
		
		$f = $modules->get('InputfieldFloat');
		$f->attr('name', 'ghostLimit');
		$f->label = __('Ghost Limit');
		$f->description = __('Show summary ghost if more this.');
		$f->columnWidth = 25;
		$form->add($f);

		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'ghostFadeSpeed');
		$f->label = __('Ghost fade speed');
		$f->description = __('Speed at which ghosts fade in or out.');
		$f->addOption('', __('Immediate (no fade in/out)'));
		$f->addOption('fast', __('Fast'));
		$f->addOption('normal', __('Normal'));
		$f->addOption('slow', __('Slow'));
		$f->columnWidth = 50;
		$form->add($f);

		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'ghostPos');
		$f->label = __('Ghost position');
		$f->description = __('What side of the screen ghosts should float on.'); 
		$f->addOption(self::ghostPosLeft, $this->_('Left'));
		$f->addOption(self::ghostPosRight, $this->_('Right'));
		$f->columnWidth = 50;
		$form->add($f);
		
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'placement');
		$f->label = __('Runtime/single-request notices');
		$f->addOption(self::placementCombined, __('Display as notifications'));
		$f->addOption(self::placementSeparate, __('Leave them alone'));
		$f->columnWidth = 50;
		$form->add($f);
		
		include_once(dirname(__FILE__) . "/Notification.php"); 

		$this->message('Example runtime message notification');
		$this->message('Example debug message notification', Notification::flagDebug);
		$this->warning('Example runtime warning notification');
		$this->warning('Example debug warning notification', Notification::flagDebug);
		$this->error('Example runtime error notification');
		$this->error('Example debug error notification', Notification::flagDebug);
		
		return $form;
	}
}
