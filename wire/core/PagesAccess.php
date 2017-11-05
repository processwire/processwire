<?php namespace ProcessWire;

/**
 * ProcessWire Pages Access
 *
 * Maintains the pages_access table which serves as a way to line up pages 
 * to the templates that maintain their access roles.
 * 
 * This class serves as a way for pageFinder() to determine if a user has access to a page
 * before actually loading it. 
 *
 * The pages_access template contains just two columns:
 * 
 * 	- pages_id:  Any given page 
 * 	- templates_id: The template that sets this pages access
 *
 * Pages using templates that already define their access (determined by $template->useRoles) 
 * are omitted from the pages_access table, as they aren't necessary. 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 *
 */

class PagesAccess extends Wire {

	/**	
	 * Cached templates that don't define access
	 *
	 */
	protected $_templates = array(); 

	/**
	 * Cached templates that DO define access
	 *
	 */
	protected $_accessTemplates = array();

	/**
	 * Array of page parent IDs that have already been completed
	 *
	 */
	protected $completedParentIDs = array(); 

	/**
	 * Construct a PagesAccess instance, optionally specifying a Page or Template
	 *
	 * If Page or Template specified, then the updateTemplate or updatePage method is assumed. 
	 *
	 * @param Page|Template
	 * 
	 */
	public function __construct($item = null) {
		if(!$item) return;
		if($item instanceof Page) {
			$this->updatePage($item);
		} else if($item instanceof Template) {
			$this->updateTemplate($item);
		}
	}

	/**
	 * Rebuild the entire pages_access table (or a part of it) starting from the given parent_id
	 * 
	 * @param int $parent_id
	 * @param int $accessTemplateID
	 * @param bool $doDeletions
	 *
	 */
	protected function rebuild($parent_id = 1, $accessTemplateID = 0, $doDeletions = true) {

		$insertions = array();
		$deletions = array();
		$accessTemplates = $this->getAccessTemplates();
		$parent_id = (int) $parent_id;
		$accessTemplateID = (int) $accessTemplateID;
		$database = $this->wire('database');

		if(!$accessTemplateID && $this->config->debug) $this->message("Rebuilding pages_access");

		if($parent_id == 1) {
			// if we're going to be rebuilding the entire tree, then just delete all of them now
			$database->exec("DELETE FROM pages_access"); // QA
			$doDeletions = false;
		}

		// no access template supplied (likely because of blank call to rebuild()
		// so we determine it automatically
		if(!$accessTemplateID) {
			$parent = $this->pages->get($parent_id);
			$accessTemplateID = $parent->getAccessTemplate()->id;
		}

		// if the accessTemplate has the guest role, it does not need to be in our pages_access table
		// since access to it is assumed for everyone. So we tell it not to perform insertions, but 
		// it should continue through the page tree
		$template = $this->templates->get($accessTemplateID);
		$doInsertions = !$template->hasRole('guest');

		$sql = 	"SELECT pages.id, pages.templates_id, count(children.id) AS numChildren " .
				"FROM pages " .
				"LEFT JOIN pages AS children ON children.parent_id=pages.id " .
				"WHERE pages.parent_id=:parent_id " .
				"GROUP BY pages.id ";

		$query = $database->prepare($sql); 
		$query->bindValue(":parent_id", $parent_id, \PDO::PARAM_INT);
		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_NUM)) {

			list($id, $templates_id, $numChildren) = $row;

			if(isset($accessTemplates[$templates_id])) {
				// this page is defining access with it's template
				// if there are children, rebuild those children with this template for access
				if($numChildren) $this->rebuild($id, $templates_id, $doDeletions);
			} else {
				// this template is not defining access... 
				if($doInsertions) {
					// ...so save an entry for the page and the template that IS defining access 
					$insertions[$id] = (int) $accessTemplateID;

				} else if($doDeletions) {
					// ...or delete existing entries if guest access is present
					$deletions[] = (int) $id;
				}
				// if there are children, rebuild any of them with this access template where applicable
				if($numChildren) $this->rebuild($id, $accessTemplateID, $doDeletions);
			}

		}
		
		$query->closeCursor();

		if(count($insertions)) {
			// add the entries to the pages_access table
			$sql = "INSERT INTO pages_access (pages_id, templates_id) VALUES ";
			foreach($insertions as $id => $templates_id) {
				$id = (int) $id;
				$templates_id = (int) $templates_id;
				$sql .= "($id, $templates_id),";
			}
			$sql = rtrim($sql, ",") . " " . "ON DUPLICATE KEY UPDATE templates_id=VALUES(templates_id) ";
			$query = $database->prepare($sql);
			$query->execute();

		} else if(count($deletions)) {
			$sql = "DELETE FROM pages_access WHERE pages_id IN(" . implode(',', $deletions) . ')';
			$query = $database->prepare($sql);
			$query->execute();
		}


	}

	/**
	 * Update the pages_access table for the given Template
	 *
	 * To be called when a template's 'useRoles' property has changed. 
	 * 
	 * @param Template $template
	 *
	 */
	public function updateTemplate(Template $template) {
		$this->rebuild();
	}

	/**
	 * Save to pages_access table to indicate what template each page is getting it's access from
	 *
	 * This should be called a page has been saved and it's parent or template has changed. 
 	 * Or, when a new page is added. 
	 *
	 * If there is no entry in this table, then the page is getting it's access from it's existing template. 
	 *
	 * This is used by PageFinder to determine what pages to include in a find() operation based on user access.
	 *
	 * @param Page $page
	 *
	 */
	public function updatePage(Page $page) {

		$page_id = (int) $page->id; 
		if(!$page_id) return;

		// this is the template where access is defined for this page
		$accessParent = $page->getAccessParent();
		$accessTemplate = $accessParent->template;
		$database = $this->wire('database');

		if(!$accessParent->id || $accessParent->id == $page->id) {
			// page is the same as the one that defines access, so it doesn't need to be here
			$query = $database->prepare("DELETE FROM pages_access WHERE pages_id=:page_id"); 	
			$query->bindValue(":page_id", $page_id, \PDO::PARAM_INT);
			$query->execute();

		} else {
			$template_id = (int) $accessParent->template->id; 

			$sql = 	"INSERT INTO pages_access (pages_id, templates_id) " .
					"VALUES(:page_id, :template_id) " .
					"ON DUPLICATE KEY UPDATE templates_id=VALUES(templates_id) ";
			
			$query = $database->prepare($sql);
			$query->bindValue(":page_id", $page_id, \PDO::PARAM_INT);
			$query->bindValue(":template_id", $template_id, \PDO::PARAM_INT);
			$query->execute();
		}

		if($page->numChildren > 0) { 

			if($page->parentPrevious && $accessParent->id != $page->id) {
				// the page has children, it's parent was changed, and access is coming from the parent
				// so the children entries need to be updated to reflect this change
				$this->rebuild($page->id, $accessTemplate->id); 

			} else if($page->templatePrevious) {
				// the page's template changed, so this may affect the children as well
				$this->rebuild($page->id, $accessTemplate->id); 
			}
		}

	}

	/**
	 * Delete a page from the pages_access table
	 * 
	 * @param Page $page
 	 *
	 */
	public function deletePage(Page $page) {
		$database = $this->wire('database');
		$query = $database->prepare("DELETE FROM pages_access WHERE pages_id=:page_id"); 
		$query->bindValue(":page_id", $page->id, \PDO::PARAM_INT); 
		$query->execute();
	}

	/**
	 * Returns an array of templates that DON'T define access
	 *
	 */
	protected function getTemplates() {
		if(count($this->_templates)) return $this->_templates; 
		foreach($this->templates as $template) {
			if($template->useRoles) {
				$this->_accessTemplates[$template->id] = $template;
			} else {
				$this->_templates[$template->id] = $template; 
			}
		}
		return $this->_templates; 
	}

	/**
	 * Returns an array of templates that DO define access
	 *
	 */
	protected function getAccessTemplates() {
		if(count($this->_accessTemplates)) return $this->_accessTemplates; 
		$this->getTemplates();
		return $this->_accessTemplates;
	}


}
