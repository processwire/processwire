<?php namespace ProcessWire;

/**
 * Database cache handler for WireCache
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 2.0.218
 *
 */
class WireCacheDatabase extends Wire implements WireCacheInterface {
	
	const useLog = false;
	
	/**
	 * Memory cache used by the maintenancePage method
	 *
	 * @var array|null Once determined becomes array of cache names => Selectors objects
	 *
	 */
	protected $cacheNameSelectors = null;

	/**
	 * Whether or not it's worthwhile to attempt Page or Template maintenance after saves
	 *
	 * @var null|bool
	 *
	 */
	protected $usePageTemplateMaintenance = null;

	/**
	 * Get cache by name
	 * 
	 * @param string $name Cache name to get
	 * @param string|array|null $expire Datetime in 'YYYY-MM-DD HH:MM:SS' format or array of them, or null for any
	 * @return string|false
	 * 
	 */
	public function get($name, $expire) {
		$values = $this->getMultiple(array($name), $expire);
		return count($values) ? reset($values) : false;
	}

	/**
	 * Find multiple caches by name and return them 
	 * 
	 * @param array $names Cache names to get
	 * @param string|array|null|false $expire Datetime in 'YYYY-MM-DD HH:MM:SS' format or array of them, or null for any, false to ignore
	 * @return array
	 * 
	 */
	public function getMultiple(array $names, $expire) {
		
		$where = array();
		$binds = array();
		$n = 0;
		
		foreach($names as $s) {
			$n++;
			if(strpos($s, '*') !== false) {
				// retrieve all caches matching wildcard
				$s = str_replace('*', '%', $s);
				$where[$n] = "name LIKE :name$n";
			} else {
				$where[$n] = "name=:name$n";
			}
			$binds[":name$n"] = $s;
		}

		$sql = "SELECT name, data FROM caches WHERE (" . implode(' OR ', $where) . ") ";

		if($expire === null) {
			$sql .= "AND (expires>=:now OR expires<=:never) ";
			$binds[':now'] = date(WireCache::dateFormat, time());
			$binds[':never'] = WireCache::expireNever;
		} else if($expire === WireCache::expireIgnore) {
			// ignore expiration
		} else if(is_array($expire)) {
			// expire is specified by a page selector, so we just let it through
			// since anything present is assumed to be valid	
		} else {
			$sql .= "AND expires<=:expire ";
			$binds[':expire'] = $expire;
		}

		$query = $this->wire()->database->prepare($sql, "cache.get(" .
			implode('|', $names) . ", " . ($expire ? print_r($expire, true) : "null") . ")");

		foreach($binds as $key => $value) {
			$query->bindValue($key, $value);
		}

		$values = array(); // return value for multi-mode

		$query->execute();
		
		if(!$query->rowCount()) return $values;
		
		while($row = $query->fetch(\PDO::FETCH_NUM)) {
			list($name, $value) = $row;
			$values[$name] = $value;
		}
		
		$query->closeCursor();

		return $values;
	}

	/**
	 * Save a cache
	 * 
	 * @param string $name
	 * @param string $data
	 * @param string $expire
	 * @return bool
	 * 
	 */
	public function save($name, $data, $expire) {
	
		if($expire === WireCache::expireSelector) {
			$this->cacheNameSelectors = null;
		}

		$sql =
			'INSERT INTO caches (`name`, `data`, `expires`) VALUES(:name, :data, :expires) ' .
			'ON DUPLICATE KEY UPDATE `data`=VALUES(`data`), `expires`=VALUES(`expires`)';

		$query = $this->wire()->database->prepare($sql, "cache.save($name)");
		$query->bindValue(':name', $name);
		$query->bindValue(':data', $data);
		$query->bindValue(':expires', $expire);

		$result = $query->execute();
		
		return $result;
	}

	/**
	 * Delete a cache by name
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	public function delete($name) {
		if(strpos($name, '*') !== false) {
			// delete all caches matching wildcard
			$name = str_replace('*', '%', $name);
			if($name === '%') return $this->deleteAll() ? true : false;
			$sql = 'DELETE FROM caches WHERE name LIKE :name';
		} else {
			$sql = 'DELETE FROM caches WHERE name=:name';
		}
		$query = $this->wire()->database->prepare($sql, "cache.delete($name)");
		$query->bindValue(':name', $name);
		$result = $query->execute();
		$query->closeCursor();
		return $result;
	}

	/**
	 * Delete all caches
	 * 
	 * @return int
	 * 
	 */
	public function deleteAll() {
		$sql = "DELETE FROM caches WHERE expires!=:reserved";
		$query = $this->wire()->database->prepare($sql, "cache.deleteAll()");
		$query->bindValue(':reserved', WireCache::expireReserved);
		$query->execute();
		$qty = $query->rowCount();
		$query->closeCursor();
		return $qty;
	}

	/**
	 * Expire all caches
	 * 
	 * @return int
	 * 
	 */
	public function expireAll() {
		$sql = "DELETE FROM caches WHERE expires>:never";
		$query = $this->wire()->database->prepare($sql, "cache.expireAll()");
		$query->bindValue(':never', WireCache::expireNever);
		$query->execute();
		$qty = $query->rowCount();
		$query->closeCursor();
		return $qty;
	}

	/**
	 * Cache maintenance removes expired caches
	 *
	 * Should be called as part of a regular maintenance routine and after page/template save/deletion.
	 * ProcessWire already calls this automatically, so you don’t typically need to call this method on your own.
	 *
	 * #pw-group-advanced
	 *
	 * @param Template|Page|null|bool Item to run maintenance for or, if not specified, general maintenance is performed.
	 * 	General maintenance only runs once per request. Specify boolean true to force general maintenance to run.
	 * @return bool
	 *
	 */
	public function maintenance($obj = null) {

		static $done = false;

		$forceRun = false;
		$database = $this->wire()->database;
		$config = $this->wire()->config;

		if(!$database || !$config) return false;

		if(is_object($obj)) {

			// check to see if it is worthwhile to perform this kind of maintenance at all
			if($this->usePageTemplateMaintenance === null) {
				$templates = $this->wire()->templates;
				if(!$templates) $templates = array();
				$minID = 999999;
				$maxID = 0;
				foreach($templates as $template) {
					if($template->id > $maxID) $maxID = $template->id;
					if($template->id < $minID) $minID = $template->id;
				}
				$sql =
					"SELECT COUNT(*) FROM caches " .
					"WHERE (expires=:expireSave OR expires=:expireSelector) " .
					"OR (expires>=:minID AND expires<=:maxID)";

				$query = $database->prepare($sql);
				$query->bindValue(':expireSave', WireCache::expireSave);
				$query->bindValue(':expireSelector', WireCache::expireSelector);
				$query->bindValue(':minID', date(WireCache::dateFormat, $minID));
				$query->bindValue(':maxID', date(WireCache::dateFormat, $maxID));
				$query->execute();
				$this->usePageTemplateMaintenance = (int) $query->fetchColumn();
				$query->closeCursor();
			}

			if($this->usePageTemplateMaintenance) {
				if($obj instanceof Page) return $this->maintenancePage($obj);
				if($obj instanceof Template) return $this->maintenanceTemplate($obj);
			} else {
				// skip it: no possible caches to maintain
			}
			return true;

		} else if($obj === true) {
			// force run general maintenance, even if run earlier
			$forceRun = true;
			$done = true;

		} else {
			// general maintenance: only perform maintenance once per request
			if($done) return true;
			$done = true;
		}

		// don't perform general maintenance during ajax requests
		if($config->ajax && !$forceRun) return false;

		// perform general maintenance now	
		return $this->maintenanceGeneral();
	}
	
	/**
	 * General maintenance removes expired caches
	 *
	 * @return bool
	 *
	 */
	protected function maintenanceGeneral() {

		$database = $this->wire()->database;

		$sql = 'DELETE FROM caches WHERE (expires<=:now AND expires>:never) ';
		$query = $database->prepare($sql, "cache.maintenance()");
		$query->bindValue(':now', date(WireCache::dateFormat, time()));
		$query->bindValue(':never', WireCache::expireNever);

		$result = $query->execute();
		$qty = $result ? $query->rowCount() : 0;
		if(self::useLog && $qty) $this->wire()->cache->log(sprintf('General maintenance expired %d cache(s)', $qty));
		$query->closeCursor();

		return $result;
	}

	/**
	 * Run maintenance for a page that was just saved or deleted
	 *
	 * @param Page $page
	 * @return bool
	 *
	 */
	protected function maintenancePage(Page $page) {

		$database = $this->wire()->database;

		if($this->cacheNameSelectors === null) {
			// locate all caches that specify selector strings and cache them so that 
			// we don't have to re-load them on every page save
			$this->cacheNameSelectors = array();
			try {
				$query = $database->prepare("SELECT * FROM caches WHERE expires=:expire");
				$query->bindValue(':expire', WireCache::expireSelector);
				$query->execute();
			} catch(\Exception $e) {
				$this->trackException($e, false);
				$this->error($e->getMessage(), Notice::log);
				return false;
			}
			if($query->rowCount()) {
				while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
					$data = json_decode($row['data'], true);
					if($data !== false && isset($data['selector'])) {
						$name = $row['name'];
						$selectors = $this->wire(new Selectors($data['selector']));
						$this->cacheNameSelectors[$name] = $selectors;
					}
				}
			}
		} else {
			// cacheNameSelectors already loaded once and is in cache
		}

		// determine which selectors match the page: the $clearNames array
		// will hold the selectors that match this $page
		$n = 0;
		$clearNames = array();
		foreach($this->cacheNameSelectors as $name => $selectors) {
			if($page->matches($selectors)) {
				$clearNames["name" . (++$n)] = $name;
			}
		}

		// clear any caches that expire on expireSave or specific page template
		$sql = "expires=:expireSave OR expires=:expireTemplateID ";

		// expire any caches that match names found in cacheNameSelectors
		foreach($clearNames as $key => $name) {
			$sql .= "OR name=:$key ";
		}

		$query = $database->prepare("DELETE FROM caches WHERE $sql");

		// bind values
		$query->bindValue(':expireSave', WireCache::expireSave);
		$query->bindValue(':expireTemplateID', date(WireCache::dateFormat, $page->template->id));

		foreach($clearNames as $key => $name) {
			$query->bindValue(":$key", $name);
		}

		$result = $query->execute();
		$qty = $result ? $query->rowCount() : 0;
		if(self::useLog && $qty) {
			$this->wire()->cache->log(sprintf('Maintenance expired %d cache(s) for saved page', $qty));
		}

		return $result;
	}

	/**
	 * Run maintenance for a template that was just saved or deleted
	 *
	 * @param Template $template
	 * @return bool Returns true if any caches were deleted, false if not
	 *
	 */
	protected function maintenanceTemplate(Template $template) {

		$sql = 'DELETE FROM caches WHERE expires=:expireTemplateID OR expires=:expireSave';
		$query = $this->wire()->database->prepare($sql);

		$query->bindValue(':expireSave', WireCache::expireSave);
		$query->bindValue(':expireTemplateID', date(WireCache::dateFormat, $template->id));

		$result = $query->execute();
		$qty = $result ? $query->rowCount() : 0;
		if(self::useLog && $qty) $this->wire()->cache->log(sprintf('Maintenance expired %d cache(s) for saved template', $qty));

		return $qty > 0;
	}


	/**
	 * Get info about caches
	 * 
	 * @param array $options
	 *  - `verbose` (bool): Return verbose details? (default=true)
	 *  - `names` (array): Names of caches to return info for, or omit for all (default=[])
	 *  - `exclude` (array): Name prefixes of caches to exclude from return value (default=[])
	 * @return array
	 * 
	 */
	public function getInfo(array $options = array()) {
		
		$templates = $this->wire()->templates;
		$database = $this->wire()->database;

		$defaults = array(
			'verbose' => true, 
			'names' => array(), 
			'exclude' => array()
		);
		
		$options = array_merge($defaults, $options);
		$verbose = (bool) $options['verbose'];
		$names = $options['names'];
		$exclude = $options['exclude'];
		
		$all = array();
		$binds = array();
		$wheres = array();
		$sql = "SELECT name, data, expires FROM caches ";

		if(count($names)) {
			$a = array();
			foreach($names as $n => $s) {
				$a[] = "name=:name$n";
				$binds[":name$n"] = $s;
			}
			$wheres[] = '(' . implode(' OR ', $a) . ')';
		}

		if(count($exclude)) {
			foreach($exclude as $n => $s) {
				$wheres[] = "name NOT LIKE :ex$n";
				$binds[":ex$n"] = $s . '%';
			}
		}

		if(count($wheres)) {
			$sql .= "WHERE " . implode(' AND ', $wheres);
		}

		$query = $database->prepare($sql);

		foreach($binds as $key => $val) {
			$query->bindValue($key, $val);
		}

		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {

			$info = array(
				'name' => $row['name'],
				'type' => 'string',
				'expires' => '',
			);

			if($this->wire()->cache->looksLikeJSON($row['data'])) {
				// json encoded
				$data = json_decode($row['data'], true);
				if(is_array($data)) {
					if(array_key_exists('WireCache', $data)) {
						if(isset($data['selector'])) {
							$selector = $data['selector'];
							$info['expires'] = $verbose ? 'when selector matches modified page' : 'selector';
							$info['selector'] = $selector;
						}
						$data = $data['WireCache'];
					}
					if(is_array($data) && array_key_exists('PageArray', $data) && array_key_exists('template', $data)) {
						$info['type'] = 'PageArray';
						if($verbose) $info['type'] .= ' (' . count($data['PageArray']) . ' pages)';
					} else if(is_array($data)) {
						$info['type'] = 'array';
						if($verbose) $info['type'] .= ' (' . count($data) . ' items)';
					}
				}
			}

			if(empty($info['expires'])) {
				if($row['expires'] === WireCache::expireNever) {
					$info['expires'] = $verbose ? 'never' : '';
				} else if($row['expires'] === WireCache::expireReserved) {
					$info['expires'] = $verbose ? 'reserved' : '';
				} else if($row['expires'] === WireCache::expireSave) {
					$info['expires'] = $verbose ? 'when any page or template is modified' : 'save';
				} else if($row['expires'] < WireCache::expireSave) {
					// potential template ID encoded as date string
					$templateId = strtotime($row['expires']);
					$template = $templates->get($templateId);
					if($template) {
						$info['expires'] = $verbose ? "when '$template->name' page or template is modified" : 'save';
						$info['template'] = $template->id;
						break;
					}
				}
				if(empty($info['expires'])) {
					$info['expires'] = $row['expires'];
					if($verbose) $info['expires'] .= " (" . wireRelativeTimeStr($row['expires']) . ")";
				}
			}

			if($verbose) $info['size'] = strlen($row['data']);

			$all[] = $info;
		}

		$query->closeCursor();

		return $all;
	}
	
	/**
	 * Save to the cache log
	 *
	 * #pw-internal
	 *
	 * @param string $str Message to log
	 * @param array $options
	 * @return WireLog
	 *
	 */
	public function ___log($str = '', array $options = array()) {
		//parent::___log($str, array('name' => 'cache'));
		if(self::useLog) {
			return $this->wire()->cache->log($str, $options);
		} else {
			$str = ''; // disable log
		}
		return parent::___log($str, $options);
	}

}
