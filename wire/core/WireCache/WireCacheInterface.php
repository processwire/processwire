<?php namespace ProcessWire;

/**
 * Interface for WireCache handler classes
 *
 * For example implementations of this interface see
 * WireCacheDatabase (core) and WireCacheFilesystem (module)
 *
 * @since 3.0.218
 *
 */
interface WireCacheInterface {
	
	/**
	 * Find caches by names and/or expirations and return requested values
	 *
	 * ~~~~~
	 * // Default options
	 * $defaults = [
	 *  'names' => [],
	 *  'expires' => [],
	 *  'expiresMode' => 'OR',
	 *  'get' => [ 'name', 'expires', 'data' ],
	 * ];
	 *
	 * // Example options
	 * $options['names'] = [ 'my-cache', 'your-cache', 'hello-*' ];
	 * $options['expires'] => [
	 *  '<= ' . WireCache::expiresNever,
	 *  '>= ' . date('Y-m-d H:i:s')
	 * ];
	 * ~~~~~
	 *
	 * @param array $options
	 *  - `get` (array): Properties to get in return value, one or more of [ `name`, `expires`, `data`, `size` ] (default=all)
	 *  - `names` (array): Names of caches to find (OR condition), optionally appended with wildcard `*`.
	 *  - `expires` (array): Expirations of caches to match in ISO-8601 date format, prefixed with operator and space (see expiresMode mode below).
	 *  - `expiresMode` (string): Whether it should match any one condition 'OR', or all conditions 'AND' (default='OR')
	 * @return array Returns array of associative arrays, each containing requested properties
	 *
	 */
	public function find(array $options);
	
	/**
	 * Save a cache
	 *
	 * @param string $name
	 * @param string $data
	 * @param string $expire
	 * @return bool
	 *
	 */
	public function save($name, $data, $expire);
	
	/**
	 * Delete cache by name
	 *
	 * @param string $name
	 * @return bool
	 *
	 */
	public function delete($name);
	
	/**
	 * Delete all caches (except those reserved by the system)
	 *
	 * @return int
	 *
	 */
	public function deleteAll();
	
	/**
	 * Expire all caches (except those that should never expire)
	 *
	 * @return int
	 *
	 */
	public function expireAll();
	
	/**
	 * Optional method to perform maintenance
	 *
	 * When present, this method should return true if it handled maintenance or false if it did not.
	 * If it returns false, WireCache will attempt to perform maintenance instead by calling find and
	 * delete methods where appropriate.
	 *
	 * WireCache passes either null, a Page object or a Template object as the single argument.
	 * When null is passed, it means "general maintenance". When a Page or Template object is
	 * passed then it means that the given Page or Template was just saved, and to perform any
	 * necessary maintenance for that case. If the method handles general maintenance but not
	 * object maintenance, then it should return true when it receives null, and false when it
	 * receives a Page or Template.
	 *
	 * @param Page|Template|null $obj
	 * @return bool
	 * @since 3.0.219
	 *
	 * The method below is commented out because it optional and only used only if present:
	 *
	 */
	// public function maintenance($obj = null);
}
