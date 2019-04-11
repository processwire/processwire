<?php namespace ProcessWire;

/**
 * ProcessWire WireCache
 *
 * Simple cache for storing strings (encoded or otherwise) and serves as $cache API var
 * 
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-summary Provides easy, persistent caching of markup, strings, arrays or PageArray objects. 
 * #pw-summary-constants These constants are used for the `$expire` argument of get() and save() cache methods. 
 * #pw-use-constants
 * #pw-body = 
 * ~~~~~
 * // Get a cache named 'foo' that lasts for 1 hour (aka 3600 seconds)
 * $value = $cache->get('foo', 3600, function() {
 *   // this is called if cache expired or does not exist, 
 *   // so generate a new cache value here and return it
 *   return "This is the cached value";
 * });
 * ~~~~~
 * #pw-body
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
	 * Cache should never expire and should not be deleted during deleteAll() calls (for PW internal system use)
	 * Can only be deleted by delete() calls that specify it directly or match it specifically with a wildcard. 
	 *
	 */
	const expireReserved = '2010-04-08 03:10:01';

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
	 * String names of expire constants
	 * 
	 * @var array
	 * 
	 */
	protected $expireNames = array(
		'now' => self::expireNow,
		'hour' => self::expireHourly,
		'hourly' => self::expireHourly,
		'day' => self::expireDaily,
		'daily' => self::expireDaily,
		'week' => self::expireWeekly,
		'weekly' => self::expireWeekly,
		'month' => self::expireMonthly,
		'monthly' => self::expireMonthly,
	);
	
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
	 * #pw-group-advanced
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
	 * #pw-group-advanced
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
	 * Cached value can be a string, an array of non-object values, or a PageArray. 
	 * 
	 * ~~~~~
	 * // get single cache value 
	 * $str = $cache->get('foo');
	 * 
	 * // get 3 cached values, returns associative array with foo, bar, baz indexes
	 * $array = $cache->get([ 'foo', 'bar', 'baz' ]);
	 * 
	 * // get all cache values with names starting with “hello”
	 * $array = $cache->get('hello*');
	 * 
	 * // get cache only if it’s less than or equal to 1 hour old (3600 seconds)
	 * $str = $cache->get('foo', 3600);
	 * 
	 * // same as above, but also generates the cache value with function when expired
	 * $str = $cache->get('foo', 3600, function() {
	 *   return "This is the cached value";
	 * });
	 * ~~~~~
	 * 
	 * @param string|array $name Provide a single cache name, an array of cache names, or an asterisk cache name.
	 * - If given a single cache name (string) just the contents of that cache will be returned.
	 * - If given an array of names, multiple caches will be returned, indexed by cache name. 
	 * - If given a cache name with an asterisk in it, it will return an array of all matching caches. 
	 * @param int|string|null $expire Optionally specify max age (in seconds) OR oldest date string.
	 * - If cache exists and is older, then blank returned. You may omit this to divert to whatever expiration
	 *   was specified at save() time. Note: The $expire and $func arguments may optionally be reversed. 
	 * - If using a $func, the behavior of $expire becomes the same as that of save(). 
	 * @param callable $func Optionally provide a function/closure that generates the cache value and it 
	 * 	will be used when needed. This option requires that only one cache is being retrieved (not an array of caches). 
	 * 	Note: The $expire and $func arguments may optionally be reversed. 
	 * @return string|array|PageArray|mixed|null Returns null if cache doesn’t exist and no generation function provided. 
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
				if($this->looksLikeJSON($value)) $value = $this->decodeJSON($value); 
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
	 * Namespace is useful to avoid cache name collisions. The ProcessWire core commonly uses cache 
	 * namespace to bind cache values to the object class, which often make a good namespace. 
	 * 
	 * Please see the `$cache->get()` method for usage of all arguments. 
	 * 
	 * ~~~~~
	 * // specify namespace as a string
	 * $value = $cache->getFor('my-namespace', 'my-cache-name');
	 * 
	 * // or specify namespace is an object instance
	 * $value = $cache->get($this, 'my-cache-name'); 
	 * ~~~~~
	 * 
	 * @param string|object $ns Namespace
	 * @param string $name Cache name
	 * @param null|int|string $expire Optional expiration
	 * @param callable|null $func Optional cache generation function
	 * @return string|array|PageArray|mixed|null Returns null if cache doesn’t exist and no generation function provided. 
	 * @see WireCache::get()
	 * 
	 */
	public function getFor($ns, $name, $expire = null, $func = null) {
		if(is_object($ns)) $ns = wireClassName($ns, false); 
		return $this->get($ns . "__$name", $expire, $func); 
	}

	/**
	 * Save data to cache with given name
	 * 
	 * ~~~~~
	 * $value = "This is the value that will be cached.";
	 * 
	 * // cache the value, using default expiration (daily)
	 * $cache->save("my-cache-name", $value); 
	 * 
	 * // cache the value, and expire after 1 hour (3600 seconds)
	 * $cache->save("my-cache-name", $value, 3600); 
	 * ~~~~~
	 * 
	 * @param string $name Name of cache, can be any string up to 255 chars
	 * @param string|array|PageArray $data Data that you want to cache. May be string, array of non-object values, or PageArray.
	 * @param int|string|Page $expire Lifetime of this cache, in seconds, OR one of the following:
	 *  - Specify one of the `WireCache::expire*` constants. 
	 *  - Specify the future date you want it to expire (as unix timestamp or any `strtotime()` compatible date format)  
	 *  - Provide a `Page` object to expire when any page using that template is saved.  
	 *  - Specify `WireCache::expireNever` to prevent expiration.  
	 *  - Specify `WireCache::expireSave` to expire when any page or template is saved.   
	 *  - Specify selector string matching pages that–when saved–expire the cache.   
	 * @return bool Returns true if cache was successful, false if not
	 * @throws WireException if given data that cannot be cached
	 * 
	 */
	public function save($name, $data, $expire = self::expireDaily) {

		if(is_array($data)) {
			if(array_key_exists('WireCache', $data)) {
				throw new WireException("Cannot cache array that has 'WireCache' array key (reserved for internal use)"); 
			} else if(array_key_exists('PageArray', $data) && array_key_exists('template', $data)) {
				throw new WireException("Cannot cache array that has 'PageArray' combined with 'template' keys (reserved for internal use)"); 
			}
		} else if(is_object($data)) {
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
			// expire based on selector string
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
		} else if(is_string($data) && $this->looksLikeJSON($data)) {
			// ensure potentailly already encoded JSON text remains as text when cache is awakened
			$data = array('WireCache' => $data); 
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
	 * Namespace is useful to avoid cache name collisions. The ProcessWire core commonly uses cache
	 * namespace to bind cache values to the object class, which often make a good namespace.
	 * 
	 * ~~~~~
	 * // save cache using manually specified namespace
	 * $cache->save("my-namespace", "my-cache-name", $value);
	 * 
	 * // save cache using namespace of current object
	 * $cache->save($this, "my-cache-name", $value); 
	 * ~~~~~
	 *
	 * @param string|object $ns Namespace for cache
	 * @param string $name Name of cache, can be any string up to 255 chars
	 * @param string|array|PageArray $data Data that you want to cache
	 * @param int|Page $expire Lifetime of this cache, in seconds, OR one of the following:
	 *  - Specify one of the `WireCache::expire*` constants.
	 *  - Specify the future date you want it to expire (as unix timestamp or any strtotime compatible date format)
	 *  - Provide a `Page` object to expire when any page using that template is saved.
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
	 * Given an expiration seconds, date, page, or template, convert it to an ISO-8601 date
	 * 
	 * Returns an array if expires info requires multiple parts, like with self::expireSelector.
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

		} else if(is_string($expire) && isset($this->expireNames[$expire])) {
			// named expiration constant like "hourly", "daily", etc. 
			$expire = time() + $this->expireNames[$expire];

		} else if(in_array($expire, array(self::expireNever, self::expireReserved, self::expireSave))) {
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
	 * Delete/clear the cache(s) identified by given name or wildcard
	 * 
	 * ~~~~~
	 * // Delete cache named "my-cache-name"
	 * $cache->delete("my-cache-name");
	 * 
	 * // Delete all caches starting with "my-"
	 * $cache->delete("my-*");
	 * ~~~~~
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
				if($name === '%') return $this->deleteAll() ? true : false;
				$sql = 'DELETE FROM caches WHERE name LIKE :name';
			} else {
				$sql = 'DELETE FROM caches WHERE name=:name';
			}
			$query = $this->wire('database')->prepare($sql, "cache.delete($name)"); 
			$query->bindValue(':name', $name); 
			$query->execute();
			$query->closeCursor();
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
	 * Delete all caches (where allowed)
	 * 
	 * This method deletes all caches other than those with `WireCache::expireReserved` status. 
	 * 
	 * @return int Quantity of caches deleted
	 * @since 3.0.130
	 * 
	 */
	public function deleteAll() {
		try {
			$sql = "DELETE FROM caches WHERE expires!=:reserved";
			$query = $this->wire('database')->prepare($sql, "cache.deleteAll()");
			$query->bindValue(':reserved', self::expireReserved);
			$query->execute();
			$qty = $query->rowCount();
			$query->closeCursor();
		} catch(\Exception $e) {
			$this->trackException($e, true);
			$this->error($e->getMessage());
			$qty = 0;
		}
		return $qty;
	}

	/**
	 * Deletes all caches that have expiration dates (only)
	 * 
	 * This method does not delete caches that are expired by saving of resources or matching selectors.
	 * 
	 * @return int
	 * @since 3.0.130
	 * 
	 */
	public function expireAll() {
		try {
			$sql = "DELETE FROM caches WHERE expires>:never";
			$query = $this->wire('database')->prepare($sql, "cache.expireAll()");
			$query->bindValue(':never', self::expireNever);
			$query->execute();
			$qty = $query->rowCount();
			$query->closeCursor();
		} catch(\Exception $e) {
			$this->trackException($e, true);
			$this->error($e->getMessage());
			$qty = 0;
		}
		return $qty;
	}

	/**
	 * Delete one or more caches in a given namespace
	 * 
	 * ~~~~~
	 * // Delete all in namespace
	 * $cache->deleteFor("my-namespace"); 
	 * 
	 * // Delete one cache in namespace
	 * $cache->deleteFor("my-namespace", "my-cache-name"); 
	 * ~~~~~
	 *
	 * @param string $ns Namespace of cache.
	 * @param string $name Name of cache. If none specified, all for namespace are deleted.
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
	 * @return bool Returns true if any caches were deleted, false if not
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
		
		return $qty > 0;
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
	 * #pw-group-advanced
	 * 
	 * @param bool $verbose Whether to be more verbose for human readability
	 * @param array|string $names Optionally specify name(s) of cache to get info. If omitted, all caches are included.
	 * @param array|string $exclude Exclude any caches that begin with any of these namespaces (default=[])
	 * @return array of arrays of cache info
	 * 
	 */
	public function getInfo($verbose = true, $names = array(), $exclude = array()) {
		
		if(is_string($names)) $names = empty($names) ? array() : array($names);
		if(is_string($exclude)) $exclude = empty($exclude) ? array() : array($exclude);
		
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
		
		$query = $this->wire('database')->prepare($sql);
		
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
			
			if($this->looksLikeJSON($row['data'])) {
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
				if($row['expires'] == self::expireNever) {
					$info['expires'] = $verbose ? 'never' : '';
				} else if($row['expires'] == self::expireReserved) {
					$info['expires'] = $verbose ? 'reserved' : '';
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
	 * Render a file as a ProcessWire template file and cache the output
	 *
	 * This method is similar to the `$files->render()` method and actually delegates the file
	 * rendering to that method (when creating the cache). The important difference is that this
	 * method caches the output according to WireCache rules for the `$expire` argument, rather
	 * than re-rendering the file on every call. 
	 *
	 * If there are any changes to the source file `$filename` the cache will be automatically
	 * re-created, regardless of what is specified for the `$expire` argument.
	 * 
	 * ~~~~~~
	 * // render primary nav from site/templates/partials/primary-nav.php 
	 * // and cache for 3600 seconds (1 hour)
	 * echo $cache->renderFile('partials/primary-nav.php', 3600); 
	 * ~~~~~~
	 *
	 * @param string $filename Filename to render (typically PHP file). 
	 *   Can be full path/file, or dir/file relative to current work directory (which is typically /site/templates/).
	 *   If providing a file relative to current dir, it should not start with "/". 
	 *   File must be somewhere within site/templates/, site/modules/ or wire/modules/, or provide your own `allowedPaths` option. 
	 *   Please note that $filename receives API variables already (you don’t have to provide them).
	 * @param int|Page|string|null $expire Lifetime of this cache, in seconds, OR one of the following:
	 *  - Specify one of the `WireCache::expire*` constants.
	 *  - Specify the future date you want it to expire (as unix timestamp or any `strtotime()` compatible date format)
	 *  - Provide a `Page` object to expire when any page using that template is saved.
	 *  - Specify `WireCache::expireNever` to prevent expiration.
	 *  - Specify `WireCache::expireSave` to expire when any page or template is saved.
	 *  - Specify selector string matching pages that–when saved–expire the cache.
	 *  - Omit for default value, which is `WireCache::expireDaily`. 
	 * @param array $options Accepts all options for the `WireFileTools::render()` method, plus these additional ones:
	 *  - `name` (string): Optionally specify a unique name for this cache, otherwise $filename will be used as the unique name. (default='')
	 *  - `vars` (array): Optional associative array of extra variables to send to template file. (default=[])
	 *  - `allowedPaths` (array): Array of paths that are allowed (default is anywhere within templates, core modules and site modules)
	 *  - `throwExceptions` (bool): Throw exceptions when fatal error occurs? (default=true)
	 * @return string|bool Rendered template file or boolean false on fatal error (and throwExceptions disabled)
	 * @throws WireException if given file doesn’t exist
	 * @see WireFileTools::render()
	 * @since 3.0.130
	 *
	 */
	public function renderFile($filename, $expire = null, array $options = array()) {

		$defaults = array(
			'name' => '',
			'vars' => array(),
			'throwExceptions' => true,
		);

		$out = null;
		$paths = $this->wire('config')->paths;
		$files = $this->wire('files');
		$filename = $files->unixFileName($filename);
		
		if(strpos($filename, '/') !== 0 && strpos($filename, ':') === false && strpos($filename, '//') === false) {
			// make relative to current path
			$currentPath = $files->currentPath();
			if($files->fileInPath($filename, $currentPath)) {
				$f = $currentPath . $filename;
				if(file_exists($f)) $filename = $f;
			}
		}
		
		$options = array_merge($defaults, $options);
		$mtime = filemtime($filename);
		$name = str_replace($paths->root, '', $filename);
		$ns = 'cache.' . ($options['name'] ? $options['name'] : 'renderFile');
		$cacheName = $this->cacheName($name, $ns);

		if($mtime === false) {
			if($options['throwExceptions']) throw new WireException("File not found: $filename");
			return false;
		}

		$data = $this->get($cacheName, $expire);

		// cache value is array where [ 0=created, 1='value' ]
		if(!is_array($data) || $data[0] < $mtime) {
			// cache does not exist or is older source file mtime
			$out = $this->wire('files')->render($filename, $options['vars'], $options);
			if($out === false) return false;
			$data = array(time(), $out);
			if($expire === null) $expire = self::expireDaily;
			$this->save($cacheName, $data, $expire);
		} else {
			$out = $data[1];
		}

		return $out;
	}

	/**
	 * Make sure a cache name is of the right length and format for a cache name
	 *
	 * @param string $name Name including namespace (if applicable)
	 * @param bool|string $ns True to allow namespace present, false to prevent, or specify namespace to add to name if not already present.
	 * @return string
	 * @since 3.0.130
	 * @todo update other methods in this class to use this method
	 *
	 *
	 */
	protected function cacheName($name, $ns = true) {

		$maxLength = 190;
		$name = trim($name);

		if($ns === false) {
			// namespace not allowed (cache name is NAME only)
			while(strpos($name, '__') !== false) $name = str_replace('__', '_', $name);
			if(strlen($name) > $maxLength) $name = md5($name);
			return $name;
		}

		if(is_string($ns) && strlen($ns)) {
			// a namespace has been supplied
			while(strpos($name, '__') !== false) $name = str_replace('__', '_', $name);
			while(strpos($ns, '__') !== false) $ns = str_replace('__', '_', $ns);
			$ns = rtrim($ns, '_') . '__';
			if(strpos($name, $ns) === 0) {
				// name already has this namespace
			} else {
				// prepend namespace to name
				$name = $ns . $name;
			}
		}

		if(strlen($name) <= $maxLength) {
			// name already in bounds
			return $name;
		}

		// at this point we have a cache name that is too long
		if(strpos($name, '__') !== false) {
			// has namespace
			list($ns, $name) = explode('__', $name, 2);
			while(strpos($name, '__') !== false) $name = str_replace('__', '_', $name);
			if(strlen($name) > 32) $name = md5($name);
			if(strlen($ns . '__' . $name) > $maxLength) $ns = md5($ns); // not likely
			$name = $ns . '__' . $name;
		} else {
			// no namespace
			$name = md5($name);
		}

		return $name;
	}

	
	/**
	 * Does the given string look like it might be JSON?
	 *
	 * @param string $str
	 * @return bool
	 *
	 */
	protected function looksLikeJSON(&$str) {
		if(empty($str)) return false;
		$c = substr($str, 0, 1);
		if($c === '{' && substr(trim($str), -1) === '}') return true;
		if($c === '[' && substr(trim($str), -1) === ']') return true;
		return false;
	}

	/**
	 * Decode a JSON string (typically to array)
	 *
	 * Returns the given $value if it cannot be decoded.
	 *
	 * @param string $value JSON encoded text value
	 * @param bool $toArray Decode to associative array? Specify false to decode to object. (default=true)
	 * @return array|mixed|PageArray
	 *
	 */
	protected function decodeJSON($value, $toArray = true) {

		$a = json_decode($value, $toArray);

		if(is_array($a)) {
			// if there is a 'WireCache' key in the array, value becomes whatever is present in its value
			if(array_key_exists('WireCache', $a)) $a = $a['WireCache'];

			if(is_array($a) && isset($a['PageArray']) && is_array($a['PageArray']) && array_key_exists('template', $a)) {
				// convert to PageArray if keys for 'PageArray' and 'template' are both present and 'PageArray' value is an array
				$value = $this->arrayToPageArray($a);
			} else {
				// some other array
				$value = $a;
			}

		} else if($a !== null) {
			// it was JSON and now it’s some other non-array type
			$value = $a;

		} else {
			// we will return the $value we were given
		}

		return $value;
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
		//parent::___log($str, array('name' => 'modules'));
		return null;
	}
	
}

