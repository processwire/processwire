<?php namespace ProcessWire;

/**
 * Tests for Session
 *
 */
class WireTest_Session extends WireTest {

	public function execute() {
		$this->testAmbiguousNameLogin();
	}

	/**
	 * Test that login authenticates the intended account when multiple users share a name
	 *
	 * With alternate user parents/templates registered, user pages may share the same name.
	 * Login must identify the account that the given password belongs to rather than
	 * attempting login on an arbitrary same-named account, which fails (or logs in the
	 * wrong account) even though the credentials are valid for another.
	 * See processwire/processwire-issues#1478
	 *
	 */
	protected function testAmbiguousNameLogin() {
		$pages = $this->wire()->pages;
		$users = $this->wire()->users;
		$prevUser = $this->wire()->user;

		// unique per run so SessionLoginThrottle windows and leftovers cannot interfere
		$suffix = strtolower((new WireRandom())->alphanumeric(6, array('alpha' => true, 'upper' => false)));
		$dupName = "wtest-dup-$suffix";
		$uniqueName = "wtest-uniq-$suffix";
		$passB = "Wt3st-PassB-$suffix";
		$passC = "Wt3st-PassC-$suffix";

		$parents = array();
		$testUsers = array();

		try {
			// alternate user parents, as registered via $config->usersPageIDs
			foreach(array(2, 3) as $n) {
				$parent = $pages->new(array(
					'template' => WireTests::templateName,
					'parent' => $this->getTestPage(),
					'title' => "Session test users parent $n",
				));
				$users->addParents($parent);
				$parents[$n] = $parent;
			}

			// user A: default users parent, no password (i.e. frontend view-only account),
			// created first so an arbitrary name lookup favors it
			$userA = $this->wire(new User());
			$userA->name = $dupName;
			$users->save($userA);
			$testUsers[] = $userA;

			// users B and C: same name under alternate parents, each with its own password
			$userB = $this->newTestUser($dupName, $passB, $parents[2]);
			$userC = $this->newTestUser($dupName, $passC, $parents[3]);
			$testUsers[] = $userB;
			$testUsers[] = $userC;

			$this->check('All same-named users exist', 3, $users->find("name=$dupName")->count());
			$this->check('Passwordless account created first (lowest id)', true,
				$userA->id < $userB->id && $userA->id < $userC->id);

			// each password logs into its own account: an arbitrary name lookup can
			// satisfy at most one of these two directions
			$loggedIn = $this->login($dupName, $passB);
			$this->check('Ambiguous name with first password authenticates its account',
				$userB->id, $loggedIn ? $loggedIn->id : null);
			$users->setCurrentUser($prevUser);

			$loggedIn = $this->login($dupName, $passC);
			$this->check('Ambiguous name with second password authenticates its account',
				$userC->id, $loggedIn ? $loggedIn->id : null);
			$users->setCurrentUser($prevUser);

			$loggedIn = $this->login($userB, $passB);
			$this->check('Login by User object is unaffected',
				$userB->id, $loggedIn ? $loggedIn->id : null);
			$users->setCurrentUser($prevUser);

			// regression: unique-named user login
			$userD = $this->newTestUser($uniqueName, $passB);
			$testUsers[] = $userD;
			$loggedIn = $this->login($uniqueName, $passB);
			$this->check('Login with unique name is unaffected',
				$userD->id, $loggedIn ? $loggedIn->id : null);
			$users->setCurrentUser($prevUser);

			// wrong password matches no same-named account (last: records a throttle failure)
			$loggedIn = $this->login($dupName, "wrong-$passB");
			$this->check('Ambiguous name with wrong password fails', null, $loggedIn);

		} finally {
			$users->setCurrentUser($prevUser);
			foreach($testUsers as $u) {
				if($u && $u->id) $users->delete($u);
			}
			foreach($parents as $parent) {
				if($parent && $parent->id) $pages->delete($parent, true);
			}
		}
	}

	/**
	 * Create and save a test user
	 *
	 * @param string $name
	 * @param string $pass
	 * @param Page|null $parent Alternate parent or omit for default users parent
	 * @return User
	 *
	 */
	protected function newTestUser($name, $pass, $parent = null) {
		$users = $this->wire()->users;
		$user = $this->wire(new User());
		if($parent) {
			$user->template = $users->getTemplate();
			$user->parent = $parent;
		}
		$user->name = $name;
		$user->pass = $pass;
		$users->save($user);
		return $user;
	}

	/**
	 * Login, suppressing warnings from repeat session regeneration in CLI
	 *
	 * @param string|User $name
	 * @param string $pass
	 * @return User|null
	 *
	 */
	protected function login($name, $pass) {
		$level = error_reporting();
		error_reporting($level & ~E_WARNING);
		try {
			$user = $this->wire()->session->login($name, $pass);
		} finally {
			error_reporting($level);
		}
		return $user;
	}
}
