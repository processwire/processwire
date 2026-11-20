<?php namespace ProcessWire;

/**
 * Tests for SessionLoginThrottle
 *
 */
class WireTest_SessionLoginThrottle extends WireTest {

	/**
	 * @var SessionLoginThrottle|null
	 *
	 */
	protected $module = null;

	public function allow() {
		return $this->wire()->modules->isInstalled('SessionLoginThrottle') && !$this->wire()->config->demo;
	}

	public function execute() {
		$this->testForcedLoginAndLoginSuccess();
	}

	/**
	 * Set the throttle row for a name so that the next attempt must wait, and clear the per-request results
	 *
	 * @param string $name
	 * @param int $attempts
	 *
	 */
	protected function setThrottle($name, $attempts) {
		$database = $this->wire()->database;
		$query = $database->prepare('DELETE FROM session_login_throttle WHERE name=:name');
		$query->execute([ ':name' => $name ]);
		$query = $database->prepare('INSERT INTO session_login_throttle (name, attempts, last_attempt) VALUES(:name, :attempts, :time)');
		$query->execute([ ':name' => $name, ':attempts' => $attempts, ':time' => time() ]);
		$this->clearResults();
	}

	/**
	 * Does the throttle table have a row for the given name?
	 *
	 * @param string $name
	 * @return bool
	 *
	 */
	protected function hasThrottle($name) {
		$query = $this->wire()->database->prepare('SELECT COUNT(*) FROM session_login_throttle WHERE name=:name');
		$query->execute([ ':name' => $name ]);
		return (int) $query->fetchColumn() > 0;
	}

	/**
	 * Clear the module's per-request allowLogin() results
	 *
	 */
	protected function clearResults() {
		$clear = function() { $this->allowLoginResults = []; };
		\Closure::bind($clear, $this->module, SessionLoginThrottle::class)();
	}

	/**
	 * A forced login is not throttled and a successful login clears the throttle for that name only
	 *
	 * processwire/processwire-issues#2379
	 *
	 */
	protected function testForcedLoginAndLoginSuccess() {
		$wire = $this->wire();
		$session = $wire->session;
		$database = $wire->database;
		$previousUser = $wire->user;
		$this->module = $wire->modules->get('SessionLoginThrottle');
		$settings = [ 'seconds' => $this->module->seconds, 'maxSeconds' => $this->module->maxSeconds, 'checkIP' => $this->module->checkIP ];
		$name = uniqid('wiretest_throttle_');
		$pass = 'Wt-' . sha1($name);
		$otherName = $name . '-other';
		$user = null;
		$errorHandler = false;

		try {
			$this->module->seconds = 5;
			$this->module->maxSeconds = 60;
			$this->module->checkIP = 0;
			$wire->users->setCurrentUser($wire->users->get($wire->config->superUserPageID));
			$user = $wire->users->add($name);
			$user->pass = $pass;
			$user->save();

			// 3 attempts means the next must wait 10 seconds
			$this->setThrottle($name, 3);
			$blocked = false;
			try {
				$session->login($name, $pass);
			} catch(SessionLoginThrottleException $e) {
				$blocked = true;
			}
			$this->check('login with password is throttled', true, $blocked);
			$this->check('current user unchanged after throttled login', $wire->config->superUserPageID, $wire->user->id);

			$this->setThrottle($name, 3);
			$this->setThrottle($otherName, 3);
			// no PHP session is active and output has started in CLI, so ignore the resulting session and cookie warnings
			set_error_handler(function($errno, $errstr) {
				return strpos($errstr, 'session_regenerate_id') !== false || strpos($errstr, 'headers already sent') !== false;
			}, E_WARNING);
			$errorHandler = true;
			$loggedIn = $session->forceLogin($user);
			restore_error_handler();
			$errorHandler = false;
			$this->check('forced login is not throttled', $user->id, $loggedIn ? $loggedIn->id : 0);
			$this->check('successful login clears throttle for its name', false, $this->hasThrottle($name));
			$this->check('successful login does not clear throttle for other names', true, $this->hasThrottle($otherName));

		} finally {
			if($errorHandler) restore_error_handler();
			$wire->users->setCurrentUser($wire->users->get($wire->config->superUserPageID));
			$query = $database->prepare('DELETE FROM session_login_throttle WHERE name=:name1 OR name=:name2');
			$query->execute([ ':name1' => $name, ':name2' => $otherName ]);
			if($user && $user->id) $wire->users->delete($user);
			foreach($settings as $key => $value) $this->module->set($key, $value);
			$this->clearResults();
			$wire->users->setCurrentUser($previousUser);
		}
	}
}
