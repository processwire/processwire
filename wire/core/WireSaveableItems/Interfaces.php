<?php namespace ProcessWire;

/**
 * For classes that are saved to a database or disk.
 *
 * Item must have a gettable/settable 'id' property for this interface as well
 *
 * @property int $id
 * @property string $name
 *
 */
interface Saveable {
	
	/**
	 * Save the object's current state to database.
	 *
	 */
	public function save();
	
	/**
	 * Get an array of this item's saveable data, should match exact with the table it saves in
	 *
	 * @return array
	 *
	 */
	public function getTableData();
	
}

/**
 * For classes that may have their data exported to an array
 *
 * Classes implementing this interface are also assumed to be able to accept the same
 *
 *
 */
interface Exportable {
	
	/**
	 * Return export data (may be the same as getTableData from Saveable interface)
	 *
	 * @return array
	 *
	 */
	public function getExportData();
	
	/**
	 * Given an export data array, import it back to the class and return what happened
	 *
	 * @param array $data
	 * @return array Returns array(
	 * 	[property_name] => array(
	 * 		'old' => 'old value',	// old value, always a string
	 * 		'new' => 'new value',	// new value, always a string
	 * 		'error' => 'error message or blank if no error'
	 * 	)
	 *
	 */
	public function setImportData(array $data);
	
}



/**
 * For classes that contain lookup items, as used by WireSaveableItemsLookup
 *
 */
interface HasLookupItems {
	
	/**
	 * Get all lookup items, usually in a WireArray derived type, but specified by class
	 *
	 */
	public function getLookupItems();
	
	/**
	 * Add a lookup item to this instance
	 *
	 * @param int $item The ID of the item to add
	 * @param array $row The row from which it was retrieved (in case you want to retrieve or modify other details)
	 *
	 */
	public function addLookupItem($item, array &$row);
	
}

