/**
 * True when other tabs are being automatically clicked
 * 
 * @type {boolean}
 * 
 */
var clickLanguageTabActive = false;

/**
 * True after document.ready language tab events have been added
 * 
 * @type {boolean}
 * 
 */
var languageTabsReady = false;

/**
 * Queue of selectors for language tabs to click on document ready
 * 
 * @type {*[]}
 * 
 */
var languageTabsClickOnReady = [];

/**
 * Event called when language tab is double-clicked
 * 
 * @param e
 * 
 */
function dblclickLanguageTab(e) {
	if(!languageTabsReady) return;
	if(clickLanguageTabActive) return;
	clickLanguageTabActive = true;
	var $tab = $(this);
	var langID = $tab.attr('data-lang');
	var $tabs = $tab.closest('form').find('a.langTab' + langID).not($tab);
	$tab.trigger('click');
	$tabs.trigger('click');
	$tabs.effect('highlight', 250);
	setTimeout(function() {
		clickLanguageTabActive = false;
	}, 250);
	var cfg = ProcessWire.config.LanguageTabs;
	jQuery.cookie('langTabsDC', langID + '-' + cfg.requestId);
}

/**
 * Establish tabs for all ".langTabs" elements in the given element
 * 
 * @param $form
 * 
 */
function setupLanguageTabs($form) {
	var $langTabs;
	var cfg = ProcessWire.config.LanguageTabs;
	if($form.hasClass('langTabs')) $langTabs = $form; 
		else $langTabs = $form.find('.langTabs');
	$langTabs.each(function() {
		var $this = $(this);
		if($this.hasClass('langTabsInit') || $this.hasClass('ui-tabs')) return;
		var $inputfield = $this.closest('.Inputfield');
		var $content = $inputfield.children('.InputfieldContent'); 
		if(!$content.hasClass('langTabsContainer')) {
			if($inputfield.find('.langTabsContainer').length == 0) $content.addClass('langTabsContainer');
		}
		if(cfg.jQueryUI) $this.tabs({active: cfg.activeTab});
		$this.addClass('langTabsInit');
		if($inputfield.length) $inputfield.addClass('hasLangTabs');
		var $parent = $this.parent('.InputfieldContent'); 
		if($parent.length) {
			var $header = $parent.prev('.InputfieldHeader');
			if($header.length && !$header.children('.langTabsToggle').length) {
				var $span = $("<span></span>")
					.attr('title', cfg.labelOpen)
					.attr('class', 'langTabsToggle')
					.append("<i class='fa fa-folder-o'></i>");
				$header.append($span);
			}
		}
		
		var $links = $this.find('a.langTabLink');
		var timeout = null;
		var $note = $parent.find('.langTabsNote');
		
		if(!$links.length) {
			$links = $this.find('a[data-lang]'); // fallback if missing langTabLink class
			if(!$links.length) $links = $this.find('a');
			$links.addClass('langTabLink');
		}
		
		$links.on('mouseover', function() {
			if(timeout) clearTimeout(timeout);
			if($parent.width() < 500) return;
			timeout = setTimeout(function() { $note.fadeIn('fast'); }, 250);
		}).on('mouseout', function() {
			if(timeout) clearTimeout(timeout);
			if($parent.width() < 500) return;
			timeout = setTimeout(function() { $note.fadeOut('fast'); }, 250);
		}).on('click', function() {
			var $a = $(this);
			var $items = $a.closest('ul').siblings('.LanguageSupport');
			var $closeItem = $items.filter('.LanguageSupportCurrent');
			var $openItem = $items.filter($a.attr('href'));
			if(languageTabsReady && $closeItem.attr('id') == $openItem.attr('id')) {
				$a.trigger('dblclicklangtab');
			} else {
				$closeItem.removeClass('LanguageSupportCurrent');
				$openItem.addClass('LanguageSupportCurrent');
				$a.trigger('clicklangtab', [ $openItem, $closeItem ]);
			}
			// uikit tab also requires the following
			var $ukTab = $a.closest('ul.uk-tab');
			if($ukTab.length) {
				$ukTab.find('.uk-active').removeClass('uk-active');
				$a.closest('li').addClass('uk-active');
			}
		}); 
		
		if(!cfg.jQueryUI) {
			$links.eq(cfg.activeTab).trigger('click');
		}
	});
	
	var value = jQuery.cookie('langTabsDC'); // DC=DoubleClick
	if(value && value.indexOf('-' + cfg.requestId) > 0) {
		value = value.split('-'); // i.e. 123-ProcessPageEdit456
		var languageId = value[0];
		$('a.langTab' + languageId, $form).trigger('click');
		if(!languageTabsReady) {
			languageTabsClickOnReady.push('a.langTab' + languageId);
		}
	}
}

/**
 * Click event that toggles language tabs on/off
 * 
 * @returns {boolean}
 * 
 */
function toggleLanguageTabs() {
	var $this = $(this);
	var $header = $this.closest('.InputfieldHeader');
	var $content = $header.next('.InputfieldContent');
	var $inputfield = $header.parent('.Inputfield');
	var $langTabs = $content.children('.langTabs');
	var $ul = $langTabs.children('ul');
	var cfg = ProcessWire.config.LanguageTabs;
	
	clickLanguageTabActive = true;

	if($content.hasClass('langTabsContainer')) {
		$ul.find('.langTabLastActive').removeClass('langTabLastActive');
		if(cfg.liActiveClass) $ul.find('.' + cfg.liActiveClass).addClass('langTabLastActive');
		$ul.find('a').trigger('click'); // activate all (i.e. for CKEditor)
		$content.removeClass('langTabsContainer');
		$inputfield.removeClass('hasLangTabs').addClass('langTabsOff');
		$this.addClass('langTabsOff');
		if(cfg.jQueryUI) $langTabs.tabs('destroy');
		$ul.hide();
		$this.attr("title", ProcessWire.config.LanguageTabs.labelClose)
			.find('i').removeClass("fa-folder-o").addClass("fa-folder-open-o");
	} else {
		$content.addClass('langTabsContainer');
		$inputfield.addClass('hasLangTabs').removeClass('langTabsOff');
		$this.removeClass('langTabsOff');
		if(cfg.jQueryUI) $langTabs.tabs();
		$ul.show();
		$(this).attr("title", cfg.labelOpen).find('i').addClass("fa-folder-o").removeClass("fa-folder-open-o");
		$ul.find('.langTabLastActive').removeClass('langTabLastActive').children('a').trigger('click');
	}
	
	clickLanguageTabActive = false;
	
	return false;
}

/**
 * Hide all language tab inputs except for default
 * 
 * For cases where all inputs are shown rather than tabs (like page name).
 * 
 */
function hideLanguageTabs() {
	
	$(".InputfieldContent").each(function() {
		var n = 0;
		$(this).children('.LanguageSupport').each(function() {
			if(++n == 1) {
				$(this).closest('.Inputfield').addClass('hadLanguageSupport');
				return;
			}
			$(this).addClass('langTabsHidden');
		});
	});

	// make sure first tab is clicked
	var $tab = $(".langTabs").find('li').eq(0);
	var cfg = ProcessWire.config.LanguageTabs;
	if(cfg.liActiveClass && !$tab.hasClass(cfg.liActiveClass)) $tab.find('a').trigger('click');

	// hide the tab toggler
	$(".langTabsToggle, .LanguageSupportLabel:visible, .langTabs > ul").addClass('langTabsHidden');
	$(".hasLangTabs").removeClass("hasLangTabs").addClass("hadLangTabs");
}

/**
 * The opposite of the hideLanguageTabs() function
 * 
 */
function unhideLanguageTabs() {
	// un-hide the previously hidden language tabs
	$('.langTabsHidden').removeClass('langTabsHidden');
	$('.hadLangTabs').removeClass('hadLangTabs').addClass('hasLangTabs');
	$('.hadLanguageSupport').removeClass('hadLanguageSupport'); // just .Inputfield with open inputs
}

/**
 * document.ready
 * 
 */
jQuery(document).ready(function($) { 
	$(document).on('click', '.langTabsToggle', toggleLanguageTabs);
	$(document).on('dblclicklangtab', 'a.langTabLink', dblclickLanguageTab);
	$(document).on('reloaded', '.Inputfield', function() {
		var $inputfield = $(this);
		setTimeout(function() {
			setupLanguageTabs($inputfield);
		}, 100);
	});
	$(document).on('AjaxUploadDone', '.InputfieldHasFileList .InputfieldFileList', function() {
		setupLanguageTabs($(this));
	});
	for(var n = 0; n < languageTabsClickOnReady.length; n++) {
		var selector = languageTabsClickOnReady[n];
		$(selector).trigger('click');
	}
	languageTabsClickOnReady = [];
	languageTabsReady = true;
}); 
