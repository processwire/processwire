<?php namespace ProcessWire;

/**
 * Interface for Process modules that can edit pages (ProcessPageEdit being the most obvious)
 *
 */
interface WirePageEditor {
	/**
	 * @return Page The current page being edited
	 *
	 */
	public function getPage();
}

