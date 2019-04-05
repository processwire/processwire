/**
 * jQuery Tabs for ProcessWire
 *
 * ProcessWire 3.x (development), Copyright 2015 by Ryan Cramer
 * https://processwire.com
 * 
 */
(function($) {

	$.fn.WireTabs = function(customOptions) {

		var options = {
			rememberTabs: 0, // -1 = no, 0 = only after submit, 1 = always
			requestID: '', 
			cookieName: 'WireTabs',
			items: null,
			skipRememberTabIDs: [],
			itemsParent: null,
			ulClass: 'WireTabs nav',
			ulAttrs: '',
			liActiveClass: '',
			aActiveClass: 'on',
			id: '' // id for tabList. if already exists, existing tabList will be used
		};

		var totalTabs = 0; 
		var cfg = ProcessWire.config.JqueryWireTabs;
		var keys = [ 'rememberTabs', 'requestID', 'cookieName', 'liActiveClass', 'aActiveClass', 'ulClass', 'ulAttrs' ];
		
		for(var n = 0; n < keys.length; n++) {
			var key = keys[n];
			if(typeof cfg[key] != "undefined") options[key] = cfg[key];
		}
		
		$.extend(options, customOptions);

		return this.each(function(index) {

			var $tabList = null;
			var $target = $(this); 
			var lastTabID = ''; // ID attribute of last tab that was clicked
			var generate = true; // generate markup/manipulate DOM?
			var queueTabClick = []; // queued wiretabclick event, becomes false after document.ready

			function init() {

				if(!options.items) return;
				if(options.items.length < 1) return;
				
				if(options.id.length) {
					$tabList = $("#" + options.id);
					if($tabList.length) generate = false;
						else $tabList = null;
				}
				if(!$tabList) {
					$tabList = $('<ul' + (options.ulAttrs ? ' ' + options.ulAttrs : '') + '></ul>');
					$tabList.addClass(options.ulClass);
					if(options.id.length) $tabList.attr('id', options.id); 
				}
				
				options.items.each(addTab); 
				if(generate) $target.prepend($tabList); // DOM manipulation
		
				var $form = $target; 	
				var $rememberTab = null;
				var cookieTab = getTabCookie(); 

				if(options.rememberTabs == 0) {
					$form.submit(function() { 
						setTabCookie(lastTabID); 
						return true; 
					}); 
				}

				var href = window.location.href;
				var hrefMatch = '';
				if(href.indexOf('WireTab')) {
					var regex = new RegExp('[&;?]WireTab=([-_a-z0-9]+)', 'i');
					hrefMatch = href.match(regex);
					hrefMatch = hrefMatch ? hrefMatch[1] : '';
					if(hrefMatch.length) {
						$rememberTab = $tabList.find("a#_" + hrefMatch);
					}
				}
				
				if($rememberTab == null) {
					var hash = document.location.hash.replace("#", ""); // thanks to @da-fecto
					if(hash.length) {
						$rememberTab = $tabList.find("a#_" + hash);
						if($rememberTab.length == 0) {
							$rememberTab = null;
						} else {
							document.location.hash = '';
						}
					}
				}
				if($rememberTab == null && cookieTab.length > 0 && options.rememberTabs > -1) {
					$rememberTab = $tabList.find("a#" + cookieTab);
				}
				if($rememberTab && $rememberTab.length > 0) {
					$rememberTab.click();
					if (options.rememberTabs == 0) setTabCookie(''); // don't clear cookie when rememberTabs=1, so it continues
					setTimeout(function() { $rememberTab.click(); }, 200); // extra backup, necessary for some event monitoring
				} else {
					$tabList.children("li:first").children("a").click();
				}
				
				$(document).ready(function() {
					// if a wiretabclick event queued before document.ready, trigger it now
					if(queueTabClick.length) $(document).trigger('wiretabclick', [ queueTabClick[0], queueTabClick[1] ]);
					queueTabClick = false;
				});
			}

			function addTab() {
				totalTabs++;
				var $t = $(this);
				if(!$t.attr('id')) $t.attr('id', "WireTab" + totalTabs); 
				var title = $t.attr('title') || $t.attr('id'); 
				$t.removeAttr('title');
				var href = $t.attr('id'); 
				var $a = $('a#_' + href); // does it already exist?
				if($a.length > 0) {
					$a.click(tabClick); 
				} else {
					var $a = $("<a></a>")
						.attr('href', '#' + href)
						.attr('id', '_' + href) // ID equal to tab content ID, but preceded with underscore
						.html(title)
						.click(tabClick); 
					$tabList.append($("<li></li>").append($a)); 
				}
				var tip = $t.attr('data-tooltip'); 
				if($t.hasClass('WireTabTip') || tip) {
					// if the tab being added has the class 'WireTabTip' or has a data-tooltip attribute
					// then display a tooltip with the tab
					if(!tip) tip = title;
					for(var key in cfg.tooltipAttr) {
						var val = cfg.tooltipAttr[key];
						if(val.indexOf('{tip}') > -1) val = val.replace('{tip}', tip); 
						if(key === 'class') {
							$a.addClass(val);
						} else {
							$a.attr(key, val); 
						}
					}
					// $a.addClass('tooltip');
					// $a.attr('title', tip ? tip : title); 
				}
				$t.hide();
				// the following removed to prevent DOM manipulation if the tab content:
				// if(options.itemsParent === null) options.itemsParent = $t.parent(); 
				//if($t.parent() != options.itemsParent) options.itemsParent.prepend($t);
				//$target.prepend($t.hide()); 
			}

			function tabClick() {
				
				var aActiveClass = options.aActiveClass;
				var liActiveClass = options.liActiveClass;
				
				var $oldTab = $tabList.find("a." + aActiveClass);
				var $newTab = $(this);
				
				if(!$oldTab.length) $oldTab = $tabList.find("a:eq(0)");
				
				
				var oldTabHref = $oldTab.attr('href');
				var newTabHref = $newTab.attr('href');
			
				var $oldTabContent = oldTabHref && oldTabHref.indexOf('#') === 0 ? $(oldTabHref) : null;
				var $newTabContent = newTabHref && newTabHref.indexOf('#') === 0 ? $(newTabHref) : null;
				
				var newTabID = $newTab.attr('id'); 
				var oldTabID = $oldTab.attr('id');

				$oldTab.removeClass(aActiveClass);
				$newTab.addClass(aActiveClass);
			
				if(liActiveClass.length) {
					$tabList.find('li.' + liActiveClass).removeClass(liActiveClass);
					$newTab.closest('li').addClass(liActiveClass);
				}
				
				if($oldTabContent) $oldTabContent.hide();
				if($newTabContent) {
					$newTabContent.show();
				} else if(newTabHref && newTabHref.length) {
					window.location.href = newTabHref;
					return true;
				}
				
				// add a target classname equal to the ID of the selected tab
				// so there is opportunity for 3rd party CSS adjustments outside this plugin
				if(oldTabID) $target.removeClass($oldTabContent.attr('id')); 
				$target.addClass(newTabID); 
				if(options.rememberTabs > -1) {
					if(jQuery.inArray(newTabID, options.skipRememberTabIDs) != -1) newTabID = '';
					if(options.rememberTabs == 1) setTabCookie(newTabID); 
					lastTabID = newTabID; 
				}
				if(queueTabClick === false) {
					$(document).trigger('wiretabclick', [ $newTabContent, $oldTabContent ]);
				} else {
					queueTabClick = [ $newTabContent, $oldTabContent ];
				}
				return false; 
			}

			function setTabCookie(value) {
				document.cookie = options.cookieName + '=' + options.requestID + '-' + escape(value);
			}
	
			function getTabCookie() {
				var regex = new RegExp('(?:^|;)\\s?' + options.cookieName + '=' + options.requestID + '-(.*?)(?:;|$)','i');
				var match = document.cookie.match(regex);	
				match = match ? match[1] : '';
				return match;
			}

			init(); 
		})
	}
})(jQuery); 

