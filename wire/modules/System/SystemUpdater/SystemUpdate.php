<?php namespace ProcessWire;

/**
 * Class SystemUpdate
 * 
 * Base class for individual system updates
 * 
 */
abstract class SystemUpdate extends Wire {

	protected $updater;

	public function __construct(SystemUpdater $updater) {
		$this->updater = $updater;
	}

	/**
	 * Execute a system update
	 *
	 * @return int|bool True if successful, false if not.
	 * 		Or return integer 0 if SystemUpdate[n].php will handle updating the system version when it is ready.
	 * 		When false or 0 is returned, updates will stop being applied for that request.
	 *
	 */
	abstract public function execute();

	public function getName() {
		$name = str_replace(__NAMESPACE__ . "\\SystemUpdate", "", get_class($this));
		$name = "Update #$name";
		return $name;
	}

	public function message($text, $flags = 0) {
		$text = $this->getName() . ": $text";
		$this->updater->message($text, $flags);
		return $this;
	}

	public function error($text, $flags = 0) {
		$text = $this->getName() . " ERROR: $text";
		$this->updater->error($text, $flags);
		return $this;
	}

}