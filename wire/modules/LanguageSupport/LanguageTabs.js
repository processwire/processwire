/**
 * True when other tabs are being automatically clicked
 * 
 * @type {boolean}
 * 
 */
var clickLanguageTabActive = false;

/**
 * Event called when language tab is long-clicked
 * 
 * @param e
 * 
 */
function dblclickLanguageTab(e) {
	if(clickLanguageTabActive) return;
	clickLanguageTabActive = true;
	var $tab = $(this);
	var langID = $tab.attr('data-lang');
	var $tabs = $tab.closest('form').find('a.langTab' + langID).not($tab);
	$tab.click();
	$tabs.click();
	$tabs.effect('highlight', 250);
	setTimeout(function() {
		clickLanguageTabActive = false;
	}, 250);
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
			var $span = $("<span></span>")
				.attr('title', cfg.labelOpen)
				.attr('class', 'langTabsToggle')
				.append("<i class='fa fa-folder-o'></i>");
			$parent.prev('.InputfieldHeader').append($span);
		}
		
		var $links = $this.find('a');
		var timeout = null;
		var $note = $parent.find('.langTabsNote');
		
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
			if($closeItem.attr('id') == $openItem.attr('id')) {
				$a.trigger('dblclicklangtab');
			} else {
				$closeItem.removeClass('LanguageSupportCurrent');
				$openItem.addClass('LanguageSupportCurrent');
			}
			// uikit tab (beta 34+) also requires a click on the <li> element
			if($a.closest('ul.uk-tab').length) $a.closest('li').click();
		}); 
		
		if(!cfg.jQueryUI) {
			$links.eq(cfg.activeTab).click();
		}
	});
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
		$ul.find('a').click(); // activate all (i.e. for CKEditor)
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
		$ul.find('.langTabLastActive').removeClass('langTabLastActive').children('a').click();
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
	var $tab = $(".langTabs").find("li:eq(0)");
	var cfg = ProcessWire.config.LanguageTabs;
	if(cfg.liActiveClass && !$tab.hasClass(cfg.liActiveClass)) $tab.find('a').click();

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
jQuery(document).ready(function() { 
	$(document).on('click', '.langTabsToggle', toggleLanguageTabs);
	$(document).on('dblclicklangtab', '.langTabs a', dblclickLanguageTab);
	$(document).on('reloaded', '.Inputfield', function() {
		setupLanguageTabs($(this));
	});
	$(document).on('AjaxUploadDone', '.InputfieldHasFileList .InputfieldFileList', function() {
		setupLanguageTabs($(this));
	});
}); 

