<?php namespace ProcessWire;

/** Tests account changes against independent authenticated session snapshots. */
class WireTest_Session extends WireTest {

	public function execute() {
		$wire = $this->wire();
		$previousUser = $wire->user;
		$user = null;
		try {
			$wire->users->setCurrentUser($wire->users->get($wire->config->superUserPageID));
			$user = $wire->users->add(uniqid('wiretest_session_'));
			$user->of(false);
			$user->setAndSave('pass', 'Synthetic-session-password-1!');
			$first = new WireTest_SessionSnapshot($wire, $user);
			$second = new WireTest_SessionSnapshot($wire, $user);
			$this->check('first session starts valid', true, $first->validate($user->id));
			$this->check('independent second session starts valid', true, $second->validate($user->id));
			$this->check('session stores no password hash', false, $first->get('_user', 'authHash') === $user->pass->hash);
			$this->check('snapshot matches the production login binding', $first->currentAuthHash($user), $first->get('_user', 'authHash'));
			$user->setAndSave('email', 'session-test@example.invalid');
			$this->check('unrelated account edit preserves session', true, $first->validate($user->id));
			$user->setAndSave('pass', 'Synthetic-session-password-1!');
			$wire->pages->uncache($user);
			$this->check('saving the unchanged password preserves session', true, $first->validate($user->id));
			$user->setAndSave('pass', 'Synthetic-session-password-2!');
			$this->check('test password change is stored', true, $user->pass->matches('Synthetic-session-password-2!'));
			$this->check('test password change affects the session binding', false, $first->get('_user', 'authHash') === $first->currentAuthHash($user));
			$wire->pages->uncache($user); // the next HTTP request reloads the account from storage
			$this->check('password change rejects first prior session', false, $first->validate($user->id));
			$this->check('password change rejects second prior session', false, $second->validate($user->id));
			$this->check('another account cannot refresh this session binding', false, $first->refreshUserAuthHash($user));
			$wire->users->setCurrentUser($user);
			$this->check('verified self-service workflow can preserve its session', true, $first->refreshUserAuthHash($user));
			$this->check('refreshed current session remains valid', true, $first->validate($user->id));
			$this->check('refreshing current session does not revive the other session', false, $second->validate($user->id));
			$wire->users->setCurrentUser($wire->users->get($wire->config->superUserPageID));
			$first = new WireTest_SessionSnapshot($wire, $user);
			$second = new WireTest_SessionSnapshot($wire, $user);
			$this->check('new credentials support a new session', true, $first->validate($user->id));
			$user->addStatus(Page::statusUnpublished);
			$user->save();
			$wire->pages->uncache($user);
			$this->check('unpublishing rejects first session', false, $first->validate($user->id));
			$this->check('unpublishing rejects second session', false, $second->validate($user->id));
			$user->removeStatus(Page::statusUnpublished);
			$user->save();
			$wire->pages->uncache($user);
			$first = new WireTest_SessionSnapshot($wire, $user);
			$this->check('published user can establish a new session', true, $first->validate($user->id));
			$first->remove('_user', 'authHash');
			$this->check('pre-update session without credential binding requires login', false, $first->validate($user->id));
			$first = new WireTest_SessionSnapshot($wire, $user);
			$first->set('_user', 'authHash', array('invalid'));
			$this->check('malformed credential binding is rejected', false, $first->validate($user->id));
			$first = new WireTest_SessionSnapshot($wire, $user);
			$first->set('_user', 'ts', 1);
			$this->check('existing inactivity timeout still applies', false, $first->validate($user->id));
			$first = new WireTest_SessionSnapshot($wire, $user);
			$id = $user->id;
			$wire->users->delete($user);
			$wire->pages->uncache($id);
			$user = null;
			$this->check('deleted account cannot restore a session', false, $first->validate($id));
		} finally {
			$wire->users->setCurrentUser($previousUser);
			if($user && $user->id) $wire->users->delete($user);
		}
	}
}

/** Isolates session data and cookies while exercising the real validation method. */
class WireTest_SessionSnapshot extends Session {

	public function __construct(ProcessWire $wire, User $user) {
		$wire->wire($this);
		$this->config = clone $wire->config;
		$this->config->sessionChallenge = false;
		$this->config->sessionFingerprint = false;
		$this->config->sessionExpireSeconds = 86400;
		$this->set('_user', 'id', $user->id);
		$this->set('_user', 'ts', time());
		$password = $user->getUnformatted('pass');
		$hash = $password instanceof Password ? $password->hash : '';
		$salt = strlen($hash) ? $password->salt : '';
		$this->set('_user', 'authHash', hash_hmac('sha256', "$user->id:$salt:$hash", $this->config->userAuthSalt));
	}

	public function validate($userID) {
		return $this->___isValidSession($userID);
	}

	public function currentAuthHash(User $user) {
		return $this->getUserAuthHash($user);
	}

	protected function setChallengeCookie($challenge = '') {
		return true;
	}

	public function log($str = '', $options = array()) {
		return $this;
	}

	public function error($text, $flags = 0) {
		return $this;
	}
}
