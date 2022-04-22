<?php namespace ProcessWire;

/**
 * Correct created_users_id on some pages from admin page ID to be default superuser ID
 *
 */
class SystemUpdate20 extends SystemUpdate {
	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}
	public function executeAtReady() {
		$database = $this->wire()->database;
		$config = $this->wire()->config;
		/** @var User $u */
		$u = $this->wire()->users->get($config->superUserPageID); 
		$superuserId = $u->id && $u->isSuperuser() ? $u->id : 0;
		$sql = 'UPDATE pages SET created_users_id=:superuser_id WHERE created_users_id=:admin_page_id';
		$query = $database->prepare($sql);
		$query->bindValue(':superuser_id', $superuserId, \PDO::PARAM_INT);
		$query->bindValue(':admin_page_id', $config->adminRootPageID, \PDO::PARAM_INT);
		$query->execute();
		$rowCount = $query->rowCount();
		if($rowCount) $this->message("Updated $rowCount page(s) for correct created_users_id");
		$this->updater->saveSystemVersion(20);
	}
}


