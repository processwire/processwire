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
function longclickLanguageTab(e) {
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
	if($form.hasClass('langTabs')) $langTabs = $form; 
		else $langTabs = $form.find('.langTabs');
	$langTabs.each(function() {
		var $this = $(this);
		if($this.hasClass('ui-tabs')) return;
		var $inputfield = $this.closest('.Inputfield');
		var $content = $inputfield.children('.InputfieldContent'); 
		if(!$content.hasClass('langTabsContainer')) {
			if($inputfield.find('.langTabsContainer').length == 0) $content.addClass('langTabsContainer');
		}
		$this.tabs({ active: ProcessWire.config.LanguageTabs.activeTab });
		if($inputfield.length) $inputfield.addClass('hasLangTabs');
		var $parent = $this.parent('.InputfieldContent'); 
		if($parent.length) {
			var $span = $("<span></span>")
				.attr('title', ProcessWire.config.LanguageTabs.title)
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
		});
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

	if($content.hasClass('langTabsContainer')) {
		$content.find('.ui-tabs-nav').find('a').click(); // activate all (i.e. for CKEditor)
		$content.removeClass('langTabsContainer');
		$inputfield.removeClass('hasLangTabs');
		$this.addClass('langTabsOff');
		$langTabs.tabs('destroy');
		$this.attr("title", ProcessWire.config.LanguageTabs.labelClose)
			.find('i').removeClass("fa-folder-o").addClass("fa-folder-open-o");
	} else {
		$content.addClass('langTabsContainer');
		$inputfield.addClass('hasLangTabs');
		$this.removeClass('langTabsOff');
		$langTabs.tabs();
		$(this).attr("title", ProcessWire.config.LanguageTabs.labelOpen)
			.find('i').addClass("fa-folder-o").removeClass("fa-folder-open-o");
	}
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
	if(!$tab.hasClass('ui-state-active')) $tab.find('a').click();

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
	$(document).on('longclick', '.langTabs a', longclickLanguageTab);
	$(document).on('reloaded', '.Inputfield', function() {
		setupLanguageTabs($(this));
	});
	$(document).on('AjaxUploadDone', '.InputfieldHasFileList .InputfieldFileList', function() {
		setupLanguageTabs($(this));
	});
}); 

