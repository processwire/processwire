<?php namespace ProcessWire;

/**
 * Indicates that a given Fieldtype may be used for page titles
 *
 */
interface FieldtypePageTitleCompatible { }

/**
 * Indicates Fieldtype manages files
 *
 */
interface FieldtypeHasFiles {
	/**
	 * Whether or not given Page/Field has any files connected with it
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return bool
	 *
	 */
	public function hasFiles(Page $page, Field $field);
	
	/**
	 * Get array of full path/file for all files managed by given page and field
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return array
	 *
	 */
	public function getFiles(Page $page, Field $field);
	
	/**
	 * Get path where files are (or would be) stored
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return string
	 *
	 */
	public function getFilesPath(Page $page, Field $field);
}

/**
 * Indicates Fieldtype manages Pagefile/Pageimage objects
 *
 */
interface FieldtypeHasPagefiles {
	
	/**
	 * Get Pagefiles
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return Pagefiles|Pagefile[]
	 *
	 */
	public function getPagefiles(Page $page, Field $field);
}

/**
 * Indicates Fieldtype manages Pageimage objects
 *
 */
interface FieldtypeHasPageimages {
	
	/**
	 * Get Pageimages
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return Pageimages|Pageimage[]
	 *
	 */
	public function getPageimages(Page $page, Field $field);
}

/**
 * Indicates Fieldtype has version support and manages its own versions
 *
 */
interface FieldtypeDoesVersions {
	
	/**
	 * Get the value for given page, field and version
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return mixed
	 *
	 */
	public function getPageFieldVersion(Page $page, Field $field, $version);
	
	/**
	 * Save version of given page field
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return bool
	 *
	 */
	public function savePageFieldVersion(Page $page, Field $field, $version);
	
	/**
	 * Restore version of given page field to live page
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return bool
	 *
	 */
	public function restorePageFieldVersion(Page $page, Field $field, $version);
	
	/**
	 * Delete version
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return bool
	 *
	 */
	public function deletePageFieldVersion(Page $page, Field $field, $version);
}

/**
 * Interface used to indicate that the Fieldtype supports multiple languages
 *
 */
interface FieldtypeLanguageInterface {
	/*
	 * This interface is symbolic only and doesn't require any additional methods, 
	 * however you do need to add an 'implements FieldtypeLanguageInterface' when defining your class. 
	 * 
	 */
}

