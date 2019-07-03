<?php namespace ProcessWire;

/**
 * Class SystemUpdate
 * 
 * Base class for individual system updates
 * 
 */
abstract class SystemUpdate extends Wire {

	/**
	 * @var SystemUpdater
	 * 
	 */
	protected $updater;

	/**
	 * Construct
	 *
	 * @param SystemUpdater $updater
	 * 
	 */
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

	/**
	 * Get update name that appears in notices
	 * 
	 * @return string
	 * 
	 */
	public function getName() {
		$name = "Update #" . $this->getVersion();
		return $name;
	}

	/**
	 * Get update version number
	 * 
	 * @return int
	 * 
	 */
	public function getVersion() {
		return (int) str_replace('SystemUpdate', '', $this->className());
	}

	public function message($text, $flags = 0) {
		$text = $this->getName() . ": $text";
		$this->updater->message($text, $flags);
		return $this;
	}
	
	public function warning($text, $flags = 0) {
		$text = $this->getName() . ": $text";
		$this->updater->warning($text, $flags);
		return $this;
	}

	public function error($text, $flags = 0) {
		$text = $this->getName() . ": $text";
		$this->updater->error($text, $flags);
		return $this;
	}

}