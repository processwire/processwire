<?php namespace ProcessWire;

/**
 * Interface indicates item stores in a WireArray or type descending from it
 *
 * @since 3.0.205
 *
 */
interface WireArrayItem {
	/**
	 * @return WireArray
	 *
	 */
	public function getWireArray();
}

/**
 * Interface that indicates the class supports its items being paginated
 * 
 */
interface WirePaginatable {
	
	/**
	 * Set the total number of items, if more than are in the WireArray.
	 *
	 * @param int $total
	 * @return $this
	 */
	public function setTotal($total);
	
	/**
	 * Get the total number of items in all paginations of the WireArray.
	 * If no limit used, this returns total number of items currently in the WireArray.
	 *
	 * @return int
	 */
	public function getTotal();
	
	/**
	 * Set the limit that was used in pagination.
	 *
	 * @param int $numLimit
	 * @return $this
	 */
	public function setLimit($numLimit);
	
	/**
	 * Get the limit that was used in pagination.
	 * If no limit set, then return number of items currently in this WireArray.
	 *
	 * @return int
	 */
	public function getLimit();
	
	/**
	 * Set the starting offset that was used for pagination.
	 *
	 * @param int $numStart ;
	 * @return $this
	 */
	public function setStart($numStart);
	
	/**
	 * Get the starting offset that was used for pagination.
	 *
	 * @return int
	 */
	public function getStart();
	
}

