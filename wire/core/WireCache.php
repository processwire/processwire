<?php namespace ProcessWire;

/**
 * ProcessWire WireCache
 *
 * Simple cache for storing strings (encoded or otherwise) and serves as $cache API var
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-summary Provides easy, persistent caching of markup, strings, arrays or PageArray objects. 
 * #pw-summary-constants These constants are used for the `$expire` argument of get() and save() cache methods. 
 * #pw-use-constants
 * 
 * @todo add support for a deleteAll() option that can delete non-system caches
 *
 */

class WireCache extends Wire {

	/**
	 * Expiration constants that may be supplied to WireCache::save $seconds argument. 
	 * 
	 */

	/**
	 * Cache should never expire (unless manually cleared). 
	 * 
	 */
	const expireNever = '2010-04-08 03:10:10';

	/**
	 * Cache should expire when a given resource (Page or Template) is saved. 
	 * 
	 */
	const expireSave = '2010-01-01 01:01:01';

	/**
	 * Used internally when a selector is specified. 
	 * #pw-internal
	 * 
	 */
	const expireSelector = '2010-01-02 02:02:02';

	/**
	 * Cache should expire now
	 * 
	 */
	const expireNow = 0;

	/**
	 * Cache should expire once per hour
	 * 
	 */
	const expireHourly = 3600;

	/**
	 * Cache should expire once per day
	 */
	const expireDaily = 86400;

	/**
	 * Cache should expire once per week
	 * 
	 */
	const expireWeekly = 604800;

	/**
	 * Cache should expire once per month
	 * 
	 */
	const expireMonthly = 2419200;

	/**
	 * Date format used by our database queries
	 * #pw-internal
	 * 
	 */
	const dateFormat = 'Y-m-d H:i:s';
	
	/**
	 * Preloaded cache values, indexed by cache name
	 * 
	 * @var array
	 * 
	 */
	protected $preloads = array();
	
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
	 * Preload the given caches, so that they will be returned without query on the next get() call
	 * 
	 * After a preloaded cache is returned from a get() call, it is removed from local storage. 
	 * 
	 * @param string|array $names
	 * @param int|string|null $expire
	 * 
	 */
	public function preload(array $names, $expire = null) {
		if(!is_array($names)) $names = array($names);
		$this->preloads = array_merge($this->preloads, $this->get($names, $expire));
	}

	/**
	 * Preload all caches for the given object or namespace
	 * 
	 * @param object|string $ns
	 * @param int|string|null $expire
	 * 
	 */
	public function preloadFor($ns, $expire = null) {
		if(is_object($ns)) $ns = wireClassName($ns, false);
		$ns .= '__*';
		$this->preloads = array_merge($this->preloads, $this->get($ns, $expire));
	}
	
	/**
	 * Get data from cache with given name
	 * 
	 * Optionally specify expiration time and/or a cache generation function to use when cache needs to be created.
	 * 
	 * @param string|array $name Provide a single cache name, an array of cache names, or an asterisk cache name.
	 * 	If given a single cache name (string) just the contents of that cache will be returned.
	 * 	If given an array of names, multiple caches will be returned, indexed by cache name. 
	 * 	If given a cache name with an asterisk in it, it will return an array of all matching caches. 
	 * @param int|string|null $expire Optionally specify max age (in seconds) OR oldest date string.
	 * 	If cache exists and is older, then blank returned. You may omit this to divert to whatever expiration
	 * 	was specified at save() time. Note: The $expire and $func arguments may optionally be reversed. 
	 * 	If using a $func, the behavior of $expire becomes the same as that of save(). 
	 * @param callable $func Optionally provide a function/closure that generates the cache value and it 
	 * 	will be used when needed.This option requires that only one cache is being retrieved (not an array of caches). 
	 * 	Note: The $expire and $func arguments may optionally be reversed. 
	 * @return string|array|PageArray|mixed|null Returns null if cache doesn't exist and no generation function provided. 
	 * @throws WireException if given invalid arguments
	 * 
	 * 
	 */
	public function get($name, $expire = null, $func = null) {
	
		$_expire = $expire;
		if(!is_null($expire)) {
			if(!is_int($expire) && !is_string($expire) && !$expire instanceof Wire && is_callable($expire)) {
				$_func = $func;
				$func = $expire; 
				$expire = is_null($_func) ? null : $this->getExpires($_func);
				unset($_func);
			} else {
				$expire = $this->getExpires($expire);
			}
		}

		$multi = is_array($name); // retrieving multiple caches at once?
		if($multi) {
			$names = $name;
		} else {
			if(isset($this->preloads[$name])) {
				$value = $this->preloads[$name];
				unset($this->preloads[$name]); 
				return $value; 
			}
			$names = array($name); 	
		}
		
		$where = array();
		$binds = array();
		$wildcards = array();
		$n = 0;
		
		foreach($names as $name) {
			$n++;
			if(strpos($name, '*') !== false || strpos($name, '%') !== false) {
				// retrieve all caches matching wildcard
				$wildcards[$name] = $name; 
				$name = str_replace('*', '%', $name); 
				$multi = true; 
				$where[$n] = "name LIKE :name$n";
			} else {
				$where[$n] = "name=:name$n";
			}
			$binds[":name$n"] = $name; 
		}
		
		if($multi && !is_null($func)) {
			throw new WireException("Function (\$func) may not be specified to \$cache->get() when requesting multiple caches.");
		}
		
		$sql = "SELECT name, data FROM caches WHERE (" . implode(' OR ', $where) . ") ";
		
		if(is_null($expire)) { // || $func) {
			$sql .= "AND (expires>=:now OR expires<=:never) ";
			$binds[':now'] = date(self::dateFormat, time());
			$binds[':never'] = self::expireNever;
		} else if(is_array($expire)) {
			// expire is specified by a page selector, so we just let it through
			// since anything present is assumed to be valid	
		} else {
			$sql .= "AND expires<=:expire ";
			$binds[':expire'] = $expire;
			// $sql .= "AND (expires>=:expire OR expires<=:never) ";
			//$binds[':never'] = self::expireNever;
		}
	
		$query = $this->wire('database')->prepare($sql, "cache.get(" . 
			implode('|', $names) . ", " . ($expire ? print_r($expire, true) : "null") . ")"); 
		
		foreach($binds as $key => $value) $query->bindValue($key, $value);
		
		$value = ''; // return value for non-multi mode
		$values = array(); // return value for multi-mode
		
		if($_expire !== self::expireNow) try {
			$query->execute(); 
			if($query->rowCount() == 0) {
				$value = null; // cache does not exist
			} else while($row = $query->fetch(\PDO::FETCH_NUM)) {
				list($name, $value) = $row;
				$c = substr($value, 0, 1);
				if($c == '{' || $c == '[') {
					$_value = json_decode($value, true);
					if(is_array($_value)) {
						if(array_key_exists('WireCache', $_value)) {
							$_value = $_value['WireCache'];
							// there is also $_value['selector'], which we don't need here
						}
						if(is_array($_value) && array_key_exists('PageArray', $_value)) {
							$value = $this->arrayToPageArray($_value);
						} else {
							$value = $_value;
						}
					}
					unset($_value);
				}
				if($multi) $values[$name] = $value; 
			}
			$query->closeCursor();
				
		} catch(\Exception $e) {
			$this->trackException($e, false);
			$value = null;
		}
		
		if($multi) {
			foreach($names as $name) {
				// ensure there is at least a placeholder for all requested caches
				if(!isset($values[$name]) && !isset($wildcards[$name])) $values[$name] = '';
			}
		} else if(empty($value) && !is_null($func) && is_callable($func)) {
			// generate the cache now from the given callable function
			$value = $this->renderCacheValue($name, $expire, $func); 
		}
		
		return $multi ? $values : $value; 
	}

	/**
	 * Render and save a cache value, when given a function to do so
	 * 
	 * Provided $func may specify any arguments that correspond with the names of API vars
	 * and it will be sent those arguments. 
	 * 
	 * Provided $func may either echo or return it's output. If any value is returned by
	 * the function it will be used as the cache value. If no value is returned, then 
	 * the output buffer will be used as the cache value. 
	 * 
	 * @param string $name
	 * @param int|string|null $expire
	 * @param callable $func
	 * @return bool|string
	 * @since Version 2.5.28
	 * 
	 */
	protected function renderCacheValue($name, $expire, $func) {
		
		$ref = new \ReflectionFunction($func);
		$params = $ref->getParameters(); // requested arguments
		$args = array(); // arguments we provide
		
		foreach($params as $param) {
			$arg = null;
			// if requested param is an API variable we will provide it
			if(preg_match('/\$([_a-zA-Z0-9]+)\b/', $param, $matches)) $arg = $this->wire($matches[1]);
			$args[] = $arg;
		}

		ob_start();
		
		if(count($args)) {
			$value = call_user_func_array($func, $args);
		} else {
			$value = $func();
		}
		
		$out = ob_get_contents();
		ob_end_clean();
		
		if(empty($value) && !empty($out)) $value = $out; 

		if($value !== false) {
			$this->save($name, $value, $expire);
		}
		
		return $value; 
	}

	/**
	 * Same as get() but with namespace
	 * 
	 * @param string|object $ns Namespace
	 * @param string $name
	 * @param null|int|string $expire
	 * @param callable|null $func
	 * @return string|array
	 * 
	 */
	public function getFor($ns, $name, $expire = null, $func = null) {
		if(is_object($ns)) $ns = wireClassName($ns, false); 
		return $this->get($ns . "__$name", $expire, $func); 
	}

	/**
	 * Save data to cache with given name
	 * 
	 * @param string $name Name of cache, can be any string up to 255 chars
	 * @param string|array|PageArray $data Data that you want to cache 
	 * @param int|Page $expire Lifetime of this cache, in seconds, OR one of the following:
	 *  - Specify one of the `WireCache::expire*` constants. 
	 *  - Specify the future date you want it to expire (as unix timestamp or any strtotime compatible date format)  
	 *  - Provide a Page object to expire when any page using that template is saved.  
	 *  - Specify `WireCache::expireNever` to prevent expiration.  
	 *  - Specify `WireCache::expireSave` to expire when any page or template is saved.   
	 *  - Specify selector string matching pages that, when saved, expire the cache.   
	 * @return bool Returns true if cache was successful, false if not
	 * @throws WireException if given uncachable data
	 * 
	 */
	public function save($name, $data, $expire = self::expireDaily) {

		if(is_object($data)) {
			if($data instanceof PageArray) {
				$data = $this->pageArrayToArray($data); 
			} else if(method_exists($data, '__toString')) {
				$data = (string) $data; 
			} else {
				throw new WireException("WireCache::save does not know how to cache values of type " . get_class($data));
			}
		}
		
		$expire = $this->getExpires($expire); 
		
		if(is_array($expire)) {
			$data = array(
				'selector' => $expire['selector'], 
				'WireCache' => $data
			);
			$expire = self::expireSelector;
			$this->cacheNameSelectors = null; // clear memory cache for maintenancePage method
		}
	
		if(is_array($data)) {
			$data = json_encode($data);
			if($data === false) throw new WireException("Unable to encode array data for cache: $name"); 
		}
		
		if(is_null($data)) $data = '';

		$sql = 
			'INSERT INTO caches (`name`, `data`, `expires`) VALUES(:name, :data, :expires) ' . 
			'ON DUPLICATE KEY UPDATE `data`=VALUES(`data`), `expires`=VALUES(`expires`)';
					
		$query = $this->wire('database')->prepare($sql, "cache.save($name)"); 
		$query->bindValue(':name', $name); 
		$query->bindValue(':data', $data); 
		$query->bindValue(':expires', $expire); 
		
		try {
			$result = $query->execute();
			$this->log($this->_('Saved cache ') . ' - ' . $name);
		} catch(\Exception $e) {
			$this->trackException($e, false);
			$result = false; 
		}

		$this->maintenance();
		
		return $result;
	}

	/**
	 * Same as save() except with namespace
	 * 
	 * @param string|object $ns Namespace for cache
	 * @param string $name Name of cache, can be any string up to 255 chars
	 * @param string|array|PageArray $data Data that you want to cache
	 * @param int|Page $expire Lifetime of this cache, in seconds, OR one of the following:
	 *  - Specify one of the `WireCache::expire*` constants.
	 *  - Specify the future date you want it to expire (as unix timestamp or any strtotime compatible date format)
	 *  - Provide a Page object to expire when any page using that template is saved.
	 *  - Specify `WireCache::expireNever` to prevent expiration.
	 *  - Specify `WireCache::expireSave` to expire when any page or template is saved.
	 *  - Specify selector string matching pages that, when saved, expire the cache.
	 * @return bool Returns true if cache was successful, false if not
	 * 
	 */
	public function saveFor($ns, $name, $data, $expire = self::expireDaily) {
		if(is_object($ns)) $ns = wireClassName($ns, false); 
		return $this->save($ns . "__$name", $data, $expire); 
	}

	/**
	 * Given a $expire seconds, date, page, or template convert it to an ISO-8601 date
	 * 
	 * Returns an array of expires info requires multiple parts, like with self::expireSelector.
	 * In this case it returns array with array('expires' => date, 'selector' => selector);
	 * 
	 * @param $expire
	 * @return string|array
	 * 
	 */
	protected function getExpires($expire) {
		
		if(is_object($expire) && $expire->id) {

			if($expire instanceof Page) {
				// page object
				$expire = $expire->template->id;

			} else if($expire instanceof Template) {
				// template object
				$expire = $expire->id;

			} else {
				// unknown object, substitute default
				$expire = time() + self::expireDaily;
			}
			
		} else if(is_array($expire)) { 
			// expire value already prepared by a previous call, just return it
			if(isset($expire['selector']) && isset($expire['expire'])) {
				return $expire;
			}

		} else if(in_array($expire, array(self::expireNever, self::expireSave))) {
			// good, we'll take it as-is
			return $expire;
			
		} else if(is_string($expire) && Selectors::stringHasSelector($expire)) {
			// expire when page matches selector
			return array(
				'expire' => self::expireSelector, 
				'selector' => $expire
			);

		} else {

			// account for date format as string
			if(is_string($expire) && !ctype_digit("$expire")) {
				$expire = strtotime($expire);
				$isDate = true; 
			} else {
				$isDate = false;
			}

			if($expire === 0 || $expire === "0") {
				// zero is allowed if that's what was specified
				$expire = (int) $expire; 
			} else {
				// zero is not allowed because it indicates failed type conversion
				$expire = (int) $expire;
				if(!$expire) $expire = self::expireDaily;
			}

			if($expire > time()) {
				// a future date has been specified, so we'll keep it
			} else if(!$isDate) {
				// a quantity of seconds has been specified, add it to current time
				$expire = time() + $expire;
			}
		}
		
		$expire = date(self::dateFormat, $expire);
		
		return $expire; 
	}

	/**
	 * Delete/clear the cache(s) identified by $name
	 * 
	 * @param string $name Name of cache, or partial name with wildcard (i.e. "MyCache*") to clear multiple caches. 
	 * @return bool True on success, false on failure
	 * 
	 */
	public function delete($name) {
		try {
			if(strpos($name, '*') !== false || strpos($name, '%') !== false) {
				// delete all caches matching wildcard
				$name = str_replace('*', '%', $name);
				$sql = 'DELETE FROM caches WHERE name LIKE :name';
			} else {
				$sql = 'DELETE FROM caches WHERE name=:name';
			}
			$query = $this->wire('database')->prepare($sql, "cache.delete($name)"); 
			$query->bindValue(':name', $name); 
			$query->execute();
			$success = true; 
			$this->log($this->_('Cleared cache') . ' - ' . $name);
		} catch(\Exception $e) {
			$this->trackException($e, true);
			$this->error($e->getMessage()); 
			$success = false;
		}
		return $success;
	}

	/**
	 * Delete the cache identified by $name within given namespace ($ns)
	 *
	 * @param string $ns Namespace of cache
	 * @param string $name If none specified, all for $ns are deleted
	 * @return bool True on success, false on failure
	 *
	 */
	public function deleteFor($ns, $name = '') {
		if(is_object($ns)) $ns = wireClassName($ns, false);
		if(!strlen($name)) $name = "*";
		return $this->delete($ns . "__$name");
	}

	/**
	 * Cache maintenance removes expired caches
	 * 
	 * Should be called as part of a regular maintenance routine and after page/template save/deletion.
	 * 
	 * @param Template|Page|null|bool Item to run maintenance for or, if not specified, general maintenance is performed.
	 * 	General maintenance only runs once per request. Specify boolean true to force general maintenance to run.
	 * @return bool
	 * 
	 */
	public function maintenance($obj = null) {
		
		static $done = false;
		$forceRun = false;
		
		if(is_object($obj)) {
		
			// check to see if it is worthwhile to perform this kind of maintenance at all
			if(is_null($this->usePageTemplateMaintenance)) {
				$minID = 999999;
				$maxID = 0;
				foreach($this->wire('templates') as $template) {
					if($template->id > $maxID) $maxID = $template->id;
					if($template->id < $minID) $minID = $template->id;
				}
				$sql = 
					"SELECT COUNT(*) FROM caches " . 
					"WHERE (expires=:expireSave OR expires=:expireSelector) " . 
					"OR (expires>=:minID AND expires<=:maxID)";
				
				$query = $this->wire('database')->prepare($sql);
				$query->bindValue(':expireSave', self::expireSave);
				$query->bindValue(':expireSelector', self::expireSelector);
				$query->bindValue(':minID', date(self::dateFormat, $minID));
				$query->bindValue(':maxID', date(self::dateFormat, $maxID));
				$query->execute();
				$this->usePageTemplateMaintenance = (int) $query->fetchColumn();
				$query->closeCursor();
			}
		
			if($this->usePageTemplateMaintenance) {
				if($obj instanceof Page) return $this->maintenancePage($obj);
				if($obj instanceof Template) return $this->maintenanceTemplate($obj);
				return true;
			} else {
				// skip it: no possible caches to maintain
				return true; 
			}
			
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
		if($this->wire('config')->ajax && !$forceRun) return false;

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
		
		$sql = 'DELETE FROM caches WHERE (expires<=:now AND expires>:never) ';

		$query = $this->wire('database')->prepare($sql, "cache.maintenance()");
		$query->bindValue(':now', date(self::dateFormat, time()));
		$query->bindValue(':never', self::expireNever);

		try {
			$result = $query->execute();
			$qty = $result ? $query->rowCount() : 0;
			if($qty) $this->log(sprintf($this->_('General maintenance expired %d cache(s)'), $qty));
			$query->closeCursor();

		} catch(\Exception $e) {
			$this->trackException($e, false);
			$this->error($e->getMessage(), Notice::debug | Notice::log);
			$result = false;
		}
		
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
		
		if(is_null($this->cacheNameSelectors)) {
			// locate all caches that specify selector strings and cache them so that 
			// we don't have to re-load them on every page save
			try {
				$query = $this->wire('database')->prepare("SELECT * FROM caches WHERE expires=:expire");
				$query->bindValue(':expire', self::expireSelector);
				$query->execute();
				$this->cacheNameSelectors = array();
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
	
		$query = $this->wire('database')->prepare("DELETE FROM caches WHERE $sql");
	
		// bind values
		$query->bindValue(':expireSave', self::expireSave); 
		$query->bindValue(':expireTemplateID', date(self::dateFormat, $page->template->id));
		
		foreach($clearNames as $key => $name) {
			$query->bindValue(":$key", $name);
		}
		
		$result = $query->execute();
		$qty = $result ? $query->rowCount() : 0;
		if($qty) $this->log(sprintf($this->_('Maintenance expired %d cache(s) for saved page'), $qty));
		
		return $result;
	}

	/**
	 * Run maintenance for a template that was just saved or deleted
	 *
	 * @param Template $template
	 * @return bool
	 *
	 */
	protected function maintenanceTemplate(Template $template) {
		
		$sql = 'DELETE FROM caches WHERE expires=:expireTemplateID OR expires=:expireSave';
		$query = $this->wire('database')->prepare($sql);

		$query->bindValue(':expireSave', self::expireSave);
		$query->bindValue(':expireTemplateID', date(self::dateFormat, $template->id));
		
		$result = $query->execute();
		$qty = $result ? $query->rowCount() : 0;
		if($qty) $this->log(sprintf($this->_('Maintenance expired %d cache(s) for saved template'), $qty));
	}
	
	/**
	 * Convert a cacheable array to a PageArray
	 *
	 * @param array $data
	 * @return PageArray
	 * @since Version 2.5.28
	 *
	 */
	protected function arrayToPageArray(array $data) {

		$pageArrayClass = isset($data['pageArrayClass']) ? $data['pageArrayClass'] : 'PageArray';

		if(!isset($data['PageArray']) || !is_array($data['PageArray'])) {
			$class = wireClassName($pageArrayClass, true);
			return $this->wire(new $class());
		}

		$options = array();
		$template = empty($data['template']) ? null : $this->wire('templates')->get((int) $data['template']);
		if($template) $options['template'] = $template;
		if($pageArrayClass != 'PageArray') $options['pageArrayClass'] = $pageArrayClass;
		if(!empty($data['pageClass']) && $data['pageClass'] != 'Page') $options['pageClass'] = $data['pageClass'];

		return $this->wire('pages')->getById($data['PageArray'], $options);
	}

	/**
	 * Given a PageArray, convert it to a cachable array
	 *
	 * @param PageArray $items
	 * @return array
	 * @throws WireException
	 * @since Version 2.5.28
	 *
	 */
	protected function pageArrayToArray(PageArray $items) {

		$templates = array();
		$ids = array();
		$pageClasses = array();

		foreach($items as $item) {
			$templates[$item->template->id] = $item->template->id;
			$ids[] = $item->id;
			$pageClass = $item->className();
			$pageClasses[$pageClass] = $pageClass;
		}

		if(count($pageClasses) > 1) {
			throw new WireException("Can't cache multiple page types together: " . implode(', ', $pageClasses));
		}

		$data = array(
			'PageArray' => $ids,
			'template'  => count($templates) == 1 ? reset($templates) : 0,
		);

		$pageClass = reset($pageClasses);
		if($pageClass && $pageClass != 'Page') $data['pageClass'] = $pageClass;

		$pageArrayClass = $items->className();
		if($pageArrayClass != 'PageArray') $data['pageArrayClass'] = $pageArrayClass;

		return $data;
	}

	/**
	 * Get information about all the caches in this WireCache
	 * 
	 * @param bool $verbose Whether to be more verbose for human readability
	 * @param string $name Optionally specify name of cache to get info. If omitted, all caches are included.
	 * @return array of arrays of cache info
	 * 
	 */
	public function getInfo($verbose = true, $name = '') {
		
		$all = array();
		$sql = "SELECT name, data, expires FROM caches ";
		if($name) $sql .= "WHERE name=:name";
		$query = $this->wire('database')->prepare($sql);
		if($name) $query->bindValue(":name", $name);
		$query->execute();
		
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {

			$info = array(
				'name' => $row['name'], 	
				'type' => 'string',
				'expires' => '',
			);
			
			$c = substr($row['data'], 0, 1);
			if($c == '{' || $c == '[') {
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
					if(is_array($data) && array_key_exists('PageArray', $data)) {
						$info['type'] = 'PageArray'; 
						if($verbose) $info['type'] .= ' (' . count($data['PageArray']) . ' pages)';
					} else if(is_array($data)) {
						$info['type'] = 'array'; 
						if($verbose) $info['type'] .= ' (' . count($data) . ' items)';
					}
				}
			}
			
			if(empty($info['expires'])) {
				if($row['expires'] == self::expireNever) {
					$info['expires'] = $verbose ? 'never' : '';
				} else if($row['expires'] == self::expireSave) {
					$info['expires'] = $verbose ? 'when any page or template is modified' : 'save';
				} else if($row['expires'] < time()) {
					$t = strtotime($row['expires']); 
					foreach($this->wire('templates') as $template) {
						if($template->id == $t) {
							$info['expires'] = $verbose ? "when '$template->name' page or template is modified" : 'save';
							$info['template'] = $template->id;
							break;
						}
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
	 * @param string $str Message to log
	 * @param array $options
	 * @return WireLog
	 *
	 */
	public function ___log($str = '', array $options = array()) {
		//parent::___log($str, array('name' => 'modules'));
		return null;
	}
	
}

