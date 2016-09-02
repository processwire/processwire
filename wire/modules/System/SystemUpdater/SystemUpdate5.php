<?php namespace ProcessWire;

/**
 * Add Lister and make children of admin /page/ hidden
 *
 */
class SystemUpdate5 extends SystemUpdate {
	
	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::init', $this, 'executeAtInit'); 
		return 0; // indicates we will update system version ourselves when ready
	}

	public function executeAtInit($event) {
		
		// remember user's language setting, if applicable

		$user = $this->wire('user');
		$language = null; 
		if($this->wire('languages') && $user->language && !$user->language->isDefault()) {
			$language = $user->language; 
			$user->language = $this->wire('languages')->get("default"); 
		}
	
		// first make all children of /admin/page/ hidden, since we intend to enable admin themes
		// to have the possibility of a drop-down menu there, but only for items they add and not
		// our general system pages

		$admin = $this->wire('pages')->get($this->wire('config')->adminRootPageID); 
		$page = $this->wire('pages')->get("parent=$admin, name=page, include=all"); 
		
		if(!$page->id) {
			
			$this->error("Unable to locate: {$admin->path}page/"); 
			
		} else foreach($page->children("include=all") as $child) {

			$skip = array('lister', 'recent-pages'); 
			if(in_array($child->name, $skip)) continue; // not likely, since these are installed by later updates

			$of = $child->of();
			$child->of(false);

			if($child->name == 'list') {
				// change title from "Page List" to "Tree" for better navigation
				if($child->title == 'Page List') {
					$child->title = 'Tree';
					try {
						$this->wire('pages')->___saveField($child, 'title', array('quiet' => true)); 
						$this->message("Updated title for: $child->path"); 
					} catch(\Exception $e) {
						$this->error("Error updating title for: $child->path"); 
					}
				}	
				continue; 
			}

			$child->addStatus(Page::statusHidden); 

			try {
				$this->wire('pages')->___save($child, array('quiet' => true)); 
				$this->message("Updated status for: $child->path", Notice::debug); 

			} catch(\Exception $e) {
				$this->error("Error updating status for: $child->path - " . $e->getMessage()); 
			}

			$child->of($of); 
		}

		$this->wire('modules')->resetCache();
	
		try {
			// make sure we've got the InputfieldSelector module ready to use
			if(!$this->wire('modules')->isInstalled('InputfieldSelector')) {
				$this->wire('modules')->install('InputfieldSelector');
				$this->message("Installed module: InputfieldSelector"); 
			}
	
			// now install ProcessPageLister, which will complete the rest of it's own installation
			if(!$this->wire('modules')->isInstalled('ProcessPageLister')) {
				$this->wire('modules')->install('ProcessPageLister');
				$this->message("Installed module: ProcessPageLister"); 
			}
		} catch(\Exception $e) {
			$this->error($e->getMessage()); 
		}
	
		// if Lister is already installed, convert existing Listers to PageListers and copy config data
		/*
		if($this->wire('modules')->isInstalled('ProcessLister')) {
			$data = $this->wire('modules')->getModuleConfigData('ProcessLister');
			if(!empty($data)) $this->wire('modules')->saveModuleConfigData('ProcessPageLister', $data); 
			$moduleID = $this->wire('modules')->getModuleID('ProcessLister');
			$items = $this->wire('pages')->find("template=admin, process=$moduleID, include=all");
			foreach($items as $item) {
				try {
					$item->of(false);
					$item->process = 'ProcessPageLister';
					$item->save();
					$this->message("Converted $item->path to use PageLister"); 
				} catch(\Exception $e) {
					$this->error($e->getMessage()); 
				}
			}
		}
		*/


		$lister = $page->child("name=lister, include=all"); 
		if(!$lister->id) {
			$lister = $this->wire(new Page());
			$lister->template = 'admin';
			$lister->parent = $page;
			$lister->name = 'lister';
			$lister->title = 'Find';
			$lister->process = 'ProcessPageLister';
			try {
				$this->wire('pages')->save($lister); 
				$this->message("Created page: $lister->path"); 
			} catch(\Exception $e) {
				$this->error("Error creating: /$page->path/$lister->name/ - " . $e->getMessage()); 
			}
		}
		
		// update ProcessPageSearch module settings to have a default of searching "title" rather than "title body"
	
		$data = $this->wire('modules')->getModuleConfigData('ProcessPageSearch'); 
		if(!isset($data['searchFields']) || $data['searchFields'] == 'title body') {
			$data['searchFields'] = 'title'; 
			$this->wire('modules')->saveModuleConfigData('ProcessPageSearch', $data); 
		}
	
		// restore user's language setting, if applicable	
		if($language) $user->language = $language; 
	
		// now update the system version
		$this->updater->saveSystemVersion(5); 
	}
}
