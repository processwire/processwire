<?php namespace ProcessWire;

/**
 * Tools for working with numbers
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 3.0.213
 *
 */
class WireNumberTools extends Wire {

	/**
	 * Generate and return an installation unique number/ID (integer)
	 *
	 * - Numbers returned by this method are incrementing, starting from 1.
	 * - Unique number counter stored in the database so is unique aross all time/requests.
	 * - Returned number is guaranteed to be unique among other calls to this method.
	 * - When using the `namespace` option, it will generate a new DB table for that namespace.
	 * - Use the `reset` option to delete a namespace when no longer needed.
	 * - You cannot reset the default namespace, so any caller is always assured a unique number.
	 * - This method creates table names that begin with `unique_num`.
	 *
	 * @param array|string $options Array of options or string for the namespace option.
	 *  - `namespace` (string): Optional namespace for unique numbers, in table name format [_a-zA-Z0-9] (default='')
	 *  - `getLast` (bool): Get last unique number rather than generating new one? (default=false)
	 *  - `reset` (bool): Reset numbers in namespace by deleting its table? Namespace required (default=false)
	 * @return int Returns unique number,
	 *  or returns 0 if `reset` option is used,
	 *  or returns 0 if `getLast` option is used and no numbers exist.
	 * @throws WireException
	 * @since 3.0.213
	 *
	 */
	public function uniqueNumber($options = array()) {

		$defaults = array(
			'namespace' => (is_string($options) ? $options : ''),
			'getLast' => false,
			'reset' => false,
		);

		$database = $this->wire()->database;
		$config = $this->wire()->config;
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$table = 'unique_num';

		if($options['namespace']) {
			$table .= '_' . $this->wire()->sanitizer->fieldName($options['namespace']);
		}

		if($options['reset']) {
			if(!$options['namespace']) throw new WireException('Namespace required for reset');
			if($database->tableExists($table)) $database->exec("DROP TABLE $table");
			return 0;
		}

		if($options['getLast']) try {
			$query = $database->query("SELECT MAX(id) FROM $table");
			$uniqueNum = (int) $query->fetchColumn();
			$query->closeCursor();
			return $uniqueNum;
		} catch(\Exception $e) {
			return 0;
		}

		try {
			$database->query("INSERT INTO $table SET id=null");
			$uniqueNum = (int) $database->lastInsertId();
		} catch(\Exception $e) {
			$uniqueNum = 0;
		}

		if(!$uniqueNum && !$database->tableExists($table) && empty($options['recursive'])) {
			$idSchema = "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
			$database->exec("CREATE TABLE $table ($idSchema) ENGINE=$config->dbEngine");
			return $this->uniqueNumber(array_merge($options, array('recursive' => true)));
		}

		if(!$uniqueNum) throw new WireException('Unable to generate unique number');

		if(($uniqueNum % 10 === 0) && $uniqueNum >= 10) {
			// maintain only 10 unique IDs in the DB table at a time
			$query = $database->prepare("DELETE FROM $table WHERE id<:id");
			$query->bindValue(':id', $uniqueNum, \PDO::PARAM_INT);
			$query->execute();
		}

		return $uniqueNum;
	}

}
