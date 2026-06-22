<?php namespace ProcessWire;

/**
 * WireTest is the base class for individual class tests
 * 
 * To implement: 
 * 
 *   - Extend this class with the name: `WireTest_ClassName` in a file named ./tests/ClassName.php
 *     (replacing 'ClassName' with the actual name of the class being tested)
 *
 */
class WireTest extends Wire {
	
	/**
	 * @var WireTests
	 * 
	 */
	protected $tests;
	
	/**
	 * Test page
	 * 
	 * @var Page
	 * 
	 */
	protected $page;
	
	/**
	 * Construct
	 * 
	 * @param WireTests $tests
	 * 
	 */
	public function __construct(WireTests $tests) {
		$tests->wire($this);
		$this->tests = $tests;
		$this->page = $tests->getTestPage();
		parent::__construct();
	}
	
	/**
	 * Get the page that are are using for tests
	 * 
	 * @return Page
	 * 
	 */
	public function getTestPage() {
		return $this->page;
	}
	
	/**
	 * Allow this test?
	 * 
	 * Return false if class not available, unmet version requirements, etc. 
	 * 
	 * @return bool
	 * 
	 */
	public function allow() {
		return true;
	}
	
	/**
	 * Setup test before execute
	 * 
	 */
	public function init() {
	}
	
	/**
	 * Execute/run test
	 * 
	 */
	public function execute() {
	}
	
	/**
	 * Optionally undo anything the init() or execute() changed
	 *
	 * Leave anything expensive to setup or teardown and do that in uninstall() instead.
	 *
	 */
	public function finish() {
	}
	
	/**
	 * Called when module uninstalled
	 * 
	 * This should undo anything that finish() doesn't undo
	 * 
	 */
	public function uninstall() {
	}
	
	/**
	 * Output a list item
	 *
	 * @param string $line
	 *
	 */
	public function li($line) {
		$this->tests->li($line);
	}
	
	/**
	 * Output an "OK" item
	 *
	 * @param string $line
	 *
	 */
	public function ok($line) {
		$this->tests->ok($line);
	}
	
	/**
	 * Indicate test fail
	 *
	 * @param string $note Optional note
	 * @throws WireTestException
	 *
	 */
	public function fail($note) {
		throw new WireTestException($note);
	}
	
	/**
	 * Assert that $expectValue and $actualValue satisfy $operator, output ok() on pass or throw on fail
	 *
	 * Supported operators: ===, !==, ==, !=, <, <=, >, >=
	 * String operators (actual vs. expected): *= (contains), ^= (starts with), $= (ends with)
	 *
	 * @param string $testName
	 * @param mixed $expectValue
	 * @param mixed $actualValue
	 * @param string $operator
	 * @throws WireTestException
	 *
	 */
	public function check($testName, $expectValue, $actualValue, $operator = '===') {
		$this->tests->check($testName, $expectValue, $actualValue, $operator); 
	}
}