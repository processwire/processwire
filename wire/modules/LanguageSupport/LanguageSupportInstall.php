<?php namespace ProcessWire;

/**
 * Installer and uninstaller for LanguageSupport module
 *
 * Split off into a seprate class/file because it's only needed once and 
 * didn't want to keep all this code in the main module that's loaded every request.
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @method void install()
 * @method void uninstall()
 *
 *
 */

class LanguageSupportInstall extends Wire { 

	/**
	 * Install the module and related modules
	 *
	 */
	public function ___install() {

		$configData = array();

		if($this->templates->get(LanguageSupport::languageTemplateName)) 
			throw new WireException("There is already a template installed called 'language'"); 

		if($this->fields->get(LanguageSupport::languageFieldName)) 
			throw new WireException("There is already a field installed called 'language'"); 

		$adminPage = $this->pages->get($this->config->adminRootPageID); 
		$setupPage = $adminPage->child("name=setup"); 
		if(!$setupPage->id) throw new WireException("Unable to locate {$adminPage->path}setup/"); 

		// create the languages parent page
		$languagesPage = $this->wire(new Page()); 
		$languagesPage->parent = $setupPage; 
		$languagesPage->template = $this->templates->get('admin'); 
		$languagesPage->process = $this->modules->get('ProcessLanguage'); // INSTALL ProcessLanguage module
		$this->message("Installed ProcessLanguage"); 
		$languagesPage->name = 'languages';
		$languagesPage->title = 'Languages';
		$languagesPage->status = Page::statusSystem; 
		$languagesPage->sort = $setupPage->numChildren; 
		$languagesPage->save();
		$configData['languagesPageID'] = $languagesPage->id; 
		

		// create the fieldgroup to be used by the language template
		$fieldgroup = $this->wire(new Fieldgroup()); 
		$fieldgroup->name = LanguageSupport::languageTemplateName;
		$fieldgroup->add($this->fields->get('title'));
		$fieldgroup->save();
		$this->message("Created fieldgroup: " . LanguageSupport::languageTemplateName . " ($fieldgroup->id)");

		$this->addFilesFields($fieldgroup);

		// create the template used by Language pages
		$template = $this->wire(new Template());	
		$template->name = LanguageSupport::languageTemplateName;
		$template->fieldgroup = $fieldgroup; 
		$template->parentTemplates = array($adminPage->template->id); 
		$template->slashUrls = 1; 
		$template->pageClass = 'Language';
		$template->pageLabelField = 'name';
		$template->noGlobal = 1; 
		$template->noMove = 1; 
		$template->noTrash = 1; 
		$template->noUnpublish = 1; 
		$template->noChangeTemplate = 1; 
		$template->nameContentTab = 1; 
		$template->flags = Template::flagSystem; 
		$template->save();
		$this->message("Created Template: " . LanguageSupport::languageTemplateName); 

		// create the default language page
		$default = $this->wire(new Language());
		$default->template = $template; 
		$default->parent = $languagesPage; 
		$default->name = 'default';
		$default->title = 'Default'; 
		$default->status = Page::statusSystem; 
		$default->save();
		$configData['defaultLanguagePageID'] = $default->id; 
		$configData['otherLanguagePageIDs'] = array(); // non-default language IDs placeholder
		$this->message("Created Default Language Page: {$default->path}"); 

		// create the translator page and process
		$translatorPage = $this->wire(new Page()); 
		$translatorPage->parent = $setupPage; 
		$translatorPage->template = $this->templates->get('admin'); 
		$translatorPage->status = Page::statusHidden | Page::statusSystem; 
		$translatorPage->process = $this->modules->get('ProcessLanguageTranslator'); // INSTALL ProcessLanguageTranslator
		$this->message("Installed ProcessLanguageTranslator"); 
		$translatorPage->name = 'language-translator';
		$translatorPage->title = 'Language Translator';
		$translatorPage->save();
		$configData['languageTranslatorPageID'] = $translatorPage->id; 
		$this->message("Created Language Translator Page: {$translatorPage->path}"); 

		// save the module config data
		$this->modules->saveModuleConfigData('LanguageSupport', $configData); 
		
		// install 'language' field that will be added to the user fieldgroup
		$field = $this->wire(new Field()); 
		$field->type = $this->modules->get("FieldtypePage"); 
		$field->name = LanguageSupport::languageFieldName; 
		$field->label = 'Language';
		$field->derefAsPage = 1; 	
		$field->parent_id = $languagesPage->id; 
		$field->labelFieldName = 'title';
		$field->inputfield = 'InputfieldRadios';
		$field->required = 1; 
		$field->flags = Field::flagSystem | Field::flagPermanent; 
		$field->save();
		$this->message("Created Langage Field: " . LanguageSupport::languageFieldName); 

		// make the 'language' field part of the profile fields the user may edit
		$profileConfig = $this->modules->getModuleConfigData('ProcessProfile'); 	
		$profileConfig['profileFields'][] = 'language';
		$this->modules->saveModuleConfigData('ProcessProfile', $profileConfig); 

		// add to 'user' fieldgroup
		$userFieldgroup = $this->templates->get('user')->fieldgroup; 
		$userFieldgroup->add($field); 
		$userFieldgroup->save();
		$this->message("Added field 'language' to user profile"); 

		// update all users to have the default value set for this field
		$n = 0; 
		foreach($this->users as $user) {
			$user->set('language', $default);
			$user->save();
			$n++;
		}

		$this->message("Added default language to $n user profiles"); 

		$this->message("Language Support Installed! Click to the 'Setup' menu to begin defining languages."); 

	}
	
	public function addFilesFields($fieldgroup) {
		
		// create the 'language_files_site' field used by the 'language' fieldgroup
		$field = $this->wire('fields')->get('language_files_site');
		if(!$field) {
			$field = $this->wire(new Field());
			$field->type = $this->modules->get("FieldtypeFile");
			$field->name = 'language_files_site';
			$field->label = 'Site Translation Files';
			$field->extensions = 'json';
			$field->maxFiles = 0;
			$field->inputfieldClass = 'InputfieldFile';
			$field->unzip = 1;
			$field->flags = Field::flagSystem | Field::flagPermanent;
			$field->save();
			$this->message("Created field: language_files_site");
		}
		// update
		$field->label = 'Site Translation Files';
		$field->description = 'Use this field for translations specific to your site (like files in /site/templates/ for example).';
		$field->descriptionRows = 0;
		$field->save();
		$fieldgroup->add($field);
		
		// create the 'language_files' field used by the 'language' fieldgroup
		$field = $this->wire('fields')->get('language_files');
		if(!$field) {
			$field = $this->wire(new Field());
			$field->type = $this->modules->get("FieldtypeFile");
			$field->name = 'language_files';
			$field->label = 'Core Translation Files';
			$field->extensions = 'json';
			$field->maxFiles = 0;
			$field->inputfieldClass = 'InputfieldFile';
			$field->unzip = 1;
			$field->flags = Field::flagSystem | Field::flagPermanent;
			$field->save();
			$this->message("Created field: language_files");
		}
		// update
		$field->label = 'Core Translation Files';
		$field->description = 'Use this field for [language packs](http://modules.processwire.com/categories/language-pack/). To delete all files, double-click the trash can for any file, then save.';
		$field->descriptionRows = 0;
		$field->save();
		$fieldgroup->add($field); 
		
		$fieldgroup->save();
	}

	/**
	 * Uninstall the module and related modules
	 *
	 */
	public function ___uninstall() {

		$language = $this->wire('user')->language; 
		if($language && $language->id && !$language->isDefault) throw new WireException("Please switch your language back to the default language before uninstalling"); 

		// uninstall the components 1 by 1
		$configData = $this->wire('modules')->getModuleConfigData('LanguageSupport'); 

		$field = $this->fields->get(LanguageSupport::languageFieldName); 
		if($field) { 
			$field->flags = Field::flagSystemOverride; 
			$field->flags = 0; 
			$userFieldgroup = $this->templates->get('user')->fieldgroup; 
			if($userFieldgroup) { 
				$userFieldgroup->remove($field); 
				$userFieldgroup->save();
				$this->message("Removed language field from user profiles"); 
			}
			$this->fields->delete($field); 	
			$this->message("Removing field: $field"); 
		}

		$deletePageIDs = array(
			$configData['defaultLanguagePageID'],
			$configData['languageTranslatorPageID'],
			$configData['languagesPageID']
			);

		// remove any language pages that are in the trash		
		$trashLanguages = $this->wire('pages')->get($this->wire('config')->trashPageID)->find("include=all, template=" . LanguageSupport::languageTemplateName); 
		foreach($trashLanguages as $p) $deletePageIDs[] = $p->id; 

		foreach($deletePageIDs as $id) {
			$page = $this->pages->get($id); 
			if(!$page->id) continue; 
			$page->status = Page::statusSystemOverride; 
			$page->status = 0;
			$this->message("Removing page: {$page->path}"); 
			$this->pages->delete($page, true); 
		}

		$template = $this->templates->get(LanguageSupport::languageTemplateName); 	
		if($template) { 
			$template->flags = Template::flagSystemOverride; 
			$template->flags = 0;

			$this->message("Removing template: {$template->name}"); 
			$this->templates->delete($template); 
		}

		$fieldgroup = $this->fieldgroups->get(LanguageSupport::languageTemplateName); 
		if($fieldgroup) { 
			$this->message("Removing fieldgroup: $fieldgroup"); 
			$this->fieldgroups->delete($fieldgroup); 
		}

		$field = $this->fields->get("language_files"); 
		if($field) { 
			$field->flags = Field::flagSystemOverride; 
			$field->flags = 0;
			$this->message("Removing field: {$field->name}"); 
			$this->fields->delete($field); 
		}
		
		$field = $this->fields->get("language_files_site");
		if($field) {
			$field->flags = Field::flagSystemOverride;
			$field->flags = 0;
			$this->message("Removing field: {$field->name}");
			$this->fields->delete($field);
		}

		$this->wire('languages', false);
		$uninstallModules = array('ProcessLanguage', 'ProcessLanguageTranslator'); 
		foreach($uninstallModules as $name) {
			$this->modules->uninstall($name); 
			$this->message("Uninstalled Module: $name"); 
		}

	}


	/**
	 * @return InputfieldWrapper
	 * 
	 */
	public function getModuleConfigInputfields() {
		$install = $this->_('Click to install:') . ' ';

		$form = new InputfieldWrapper();
		$names = array(
			'LanguageSupportFields',
			'LanguageSupportPageNames',
			'LanguageTabs',
		);
		$installed = array();
		$list = array();

		foreach($names as $name) {
			if($this->wire('modules')->isInstalled($name)) continue;
			$list["./installConfirm?name=$name"] = "$install $name";
			$installed[$name] = true;
		}
		
		$list["../setup/languages/"] =
			$this->_('Add and configure new languages');

		$title = $this->wire('fields')->get('title');
		if($title && strpos($title->type->className(), 'Language') === false) {
			$list["../setup/field/edit?id=$title->id"] =
				$this->_('Change the type of your "title" field from "Page Title" to "Page Title (Multi-language)"') . ' *';
		}

		$list["../setup/field/?fieldtype=FieldtypeText"] =
			$this->_('Change the type of any other desired "Text" fields to "Text (Multi-language)"') . ' *';
		$list["../setup/field/?fieldtype=FieldtypeTextarea"] =
			$this->_('Change the type of any other desired "Textarea" fields to "Textarea (Multi-language)"') . ' *';

		if(count($list)) {
			$this->wire('modules')->get('JqueryUI')->use('modal');
			$f = $this->wire('modules')->get('InputfieldMarkup');
			$f->attr('name', '_next_steps');
			$f->label = $this->_('Next steps');
			$f->value = "<ul>";
			foreach($list as $url => $text) {
				$f->value .= "<li><a target='_blank' href='$url'>$text</a></li>";
			}
			$f->value .= "</ul>";
			$f->description = 
				$this->_('To continue setting up multi-language support, we recommend the following next steps.') . ' ' . 
				$this->_('The links below will open in a new window/tab. Close each after finishing to return here.'); 
			$f->notes = '*' . $this->_('Install the LanguageSupportFields module before attempting to change text field types.');
			$form->add($f);
		}

		return $form; 
	}
}
